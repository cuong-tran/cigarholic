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
    location ~ /\.(?!well-known/) { deny all; }
    location ~* ^/app/uploads/.*\.php$ { deny all; }
    location ~* ^/(wp-config\.php|composer\.(json|lock)|readme\.html|license\.txt)$ { deny all; }
    location ~* ^/wp/(readme\.html|license\.txt|wp-config-sample\.php)$ { deny all; }

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
