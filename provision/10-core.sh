# Core WordPress settings. Sourced by provision/run.sh; $WP_ENV is set.

wp rewrite structure '/%postname%/' --quiet
wp option update timezone_string 'America/New_York' --quiet
wp option update start_of_week 0 --quiet
wp option update date_format 'F j, Y' --quiet

wp theme activate cigarholic --quiet
