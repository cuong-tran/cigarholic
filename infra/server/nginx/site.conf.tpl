# Cigarholic @@SITE@@ (@@HOST@@). Rendered by site.sh to /etc/nginx/sites-available/cigarholic-@@SITE@@.
# Bedrock layout: document root is <release>/web, WordPress core lives in /wp/.

server {
    listen 80;
    listen [::]:80;
    server_name @@HOST@@;
    access_log /var/log/nginx/cigarholic-@@SITE@@.access.log cigarholic;

    location ^~ /.well-known/acme-challenge/ {
        root @@ROOT@@/letsencrypt;
        default_type text/plain;
        try_files $uri =404;
    }
    location / {
        return 301 https://@@HOST@@$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name @@HOST@@;

    root @@ROOT@@/current/web;
    index index.php;

    ssl_certificate     @@CERT_DIR@@/fullchain.pem;
    ssl_certificate_key @@CERT_DIR@@/privkey.pem;
    include snippets/cigarholic-ssl.conf;

    include snippets/cigarholic-headers.conf;
    include /etc/nginx/cigarholic/@@SITE@@/headers.conf;
    include /etc/nginx/cigarholic/@@SITE@@/auth.conf;

    access_log /var/log/nginx/cigarholic-@@SITE@@.access.log cigarholic;
    error_log  /var/log/nginx/cigarholic-@@SITE@@.error.log;

    client_max_body_size 64m;
    limit_req_status 429;

    location ^~ /.well-known/acme-challenge/ {
        auth_basic off;
        root @@ROOT@@/letsencrypt;
        default_type text/plain;
        try_files $uri =404;
    }

    # --- hard denies ----------------------------------------------------------------------------
    location ~ ^/(wp/)?xmlrpc\.php$ { deny all; }
    # The web installer: WordPress is installed with WP-CLI (docs/runbooks/deploy.md), and until it is,
    # whoever opens this page first owns the site. Matched on the normalised URI, so ../ cannot dodge it.
    location ~ ^/(wp/)?wp-admin/(install|setup-config)\.php$ { deny all; }
    location ~ /\.(?!well-known/) { deny all; }
    location ~* ^/app/uploads/.*\.php$ { deny all; }
    location ~* ^/(wp-config\.php|composer\.(json|lock)|readme\.html|license\.txt)$ { deny all; }
    location ~* ^/wp/(readme\.html|license\.txt|wp-config-sample\.php)$ { deny all; }

    # --- short admin URLs: WordPress lives in /wp/ (Bedrock), so the familiar paths redirect there.
    # After the hard denies on purpose: /wp-admin/install.php stays 403 (the first matching regex wins).
    # The target always starts with /wp, so the redirect cannot leave this host.
    location = /wp-login.php { return 301 /wp$request_uri; }
    location = /wp-admin { return 301 /wp/wp-admin/$is_args$args; }
    location ~ ^/wp-admin/ { return 301 /wp$request_uri; }

    # --- login: rate limited, never cached ----------------------------------------------------
    location ~ ^/(wp/)?wp-login\.php$ {
        limit_req zone=cigarholic_@@SITE@@_login burst=5 nodelay;
        try_files $uri =404;
        include snippets/cigarholic-fastcgi.conf;
        fastcgi_pass unix:@@PHP_SOCKET@@;
    }

    # --- checkout page and the Store API checkout route: rate limited ----------------------------
    location ^~ /checkout/ {
        limit_req zone=cigarholic_@@SITE@@_checkout burst=20 nodelay;
        try_files $uri $uri/ /index.php$is_args$args;
    }
    location ~ ^/wp-json/wc/store/v1/checkout {
        limit_req zone=cigarholic_@@SITE@@_checkout burst=20 nodelay;
        try_files $uri /index.php$is_args$args;
    }

    # --- virtual files WordPress answers: robots.txt and the XML sitemaps (SEO-01) ---------------
    # Not on disk: index.php builds them (robots.txt from blog_public, so staging's disallows
    # everything, see cigarholic-core Seo\RobotsTxt; sitemaps from SEOPress, or core when SEOPress's
    # are off), so they end in the PHP location below with its FastCGI cache rules and headers. A
    # real file of the same name is still served as it is. They must stay ahead of the static-asset
    # regex, which answers 404 for any .txt or .xml without a file (the first matching regex wins);
    # the hard denies above still come first.
    #   WordPress 7.1  /sitemap.xml  /wp-sitemap.xml  /wp-sitemap-<provider>[-<subtype>]-<n>.xml
    #   SEOPress 10.4  /sitemaps.xml  /<type>-sitemap<n>.xml  /sitemap_index.xml (301 to sitemaps.xml)
    # Their .xsl stylesheets (wp-sitemap*.xsl, sitemaps_xsl.xsl) are no static extension: location /.
    # tests/infra/nginx-staging-auth.sh checks these paths against the shipped blocks.
    location = /robots.txt {
        try_files $uri /index.php$is_args$args;
    }
    location ~ ^/(?:sitemap|sitemap_index|sitemaps|wp-sitemap(?:-[a-z0-9_-]+)?|[a-z0-9_-]+-sitemap[0-9]*)\.xml$ {
        try_files $uri /index.php$is_args$args;
    }

    # --- static assets ---------------------------------------------------------------------------
    location ~* \.(?:css|js|mjs|jpe?g|png|gif|ico|svg|webp|avif|woff2?|ttf|otf|eot|mp4|webm|pdf|txt|xml|json|map)$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # --- PHP with FastCGI cache (bypassed for cart, checkout, account, logged-in, REST, wc-api) ----
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include snippets/cigarholic-fastcgi.conf;
        fastcgi_pass unix:@@PHP_SOCKET@@;

        fastcgi_cache cigarholic_@@SITE@@;
        fastcgi_cache_key "$scheme$request_method$host$request_uri";
        fastcgi_cache_valid 200 301 302 10m;
        fastcgi_cache_valid 404 1m;
        fastcgi_cache_use_stale error timeout updating http_500 http_503;
        fastcgi_cache_background_update on;
        fastcgi_cache_lock on;
        fastcgi_cache_bypass $cigarholic_skip_cache;
        fastcgi_no_cache $cigarholic_skip_cache;
        fastcgi_cache_min_uses 1;

        add_header X-Cache $upstream_cache_status;
        include snippets/cigarholic-headers.conf;
        include /etc/nginx/cigarholic/@@SITE@@/headers.conf;
    }
}
