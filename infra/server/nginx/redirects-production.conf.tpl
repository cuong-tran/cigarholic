# Canonical-host redirects for production (ADR 0008): www.cigarholic.com, max2lab.com and
# www.max2lab.com answer 301 to https://cigarholic.com with path and query preserved, one hop,
# over HTTP and HTTPS. Rendered by site.sh to /etc/nginx/sites-available/cigarholic-production-redirects.

server {
    listen 80;
    listen [::]:80;
    server_name www.cigarholic.com max2lab.com www.max2lab.com;
    access_log /var/log/nginx/cigarholic-production.access.log cigarholic;

    location ^~ /.well-known/acme-challenge/ {
        root @@ROOT@@/letsencrypt;
        default_type text/plain;
        try_files $uri =404;
    }
    location / {
        return 301 https://cigarholic.com$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name www.cigarholic.com max2lab.com www.max2lab.com;
    access_log /var/log/nginx/cigarholic-production.access.log cigarholic;

    ssl_certificate     @@CERT_DIR@@/fullchain.pem;
    ssl_certificate_key @@CERT_DIR@@/privkey.pem;
    include snippets/cigarholic-ssl.conf;

    return 301 https://cigarholic.com$request_uri;
}
