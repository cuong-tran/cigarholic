# Per-site http-level zones. Rendered by site.sh to /etc/nginx/conf.d/cigarholic-@@SITE@@.conf.
fastcgi_cache_path /var/cache/nginx/cigarholic-@@SITE@@ levels=1:2 keys_zone=cigarholic_@@SITE@@:32m
                   max_size=1g inactive=12h use_temp_path=off;
limit_req_zone $binary_remote_addr zone=cigarholic_@@SITE@@_login:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=cigarholic_@@SITE@@_checkout:10m rate=1r/s;
