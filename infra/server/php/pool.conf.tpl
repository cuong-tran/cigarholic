; PHP-FPM pool for Cigarholic @@SITE@@. Rendered by site.sh to /etc/php/8.5/fpm/pool.d/cigarholic-@@SITE@@.conf.
; Each site runs as its own system user; Nginx (www-data) talks to the socket.
[cigarholic-@@SITE@@]
user = @@USER@@
group = @@USER@@
listen = /run/php/cigarholic-@@SITE@@.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; 16 GB host shared by two sites, MariaDB and Valkey; a WooCommerce worker uses 60-120 MB.
pm = dynamic
pm.max_children = @@PM_MAX@@
pm.start_servers = @@PM_START@@
pm.min_spare_servers = @@PM_MIN_SPARE@@
pm.max_spare_servers = @@PM_MAX_SPARE@@
pm.max_requests = 500
pm.process_idle_timeout = 10s

request_terminate_timeout = 120s
request_slowlog_timeout = 10s
slowlog = @@ROOT@@/shared/logs/php-slow.log
catch_workers_output = yes
decorate_workers_output = no
clear_env = yes
security.limit_extensions = .php

env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

php_admin_value[error_log] = @@ROOT@@/shared/logs/php-error.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 120
php_admin_value[session.save_path] = @@ROOT@@/shared/sessions
php_admin_value[sys_temp_dir] = /tmp
