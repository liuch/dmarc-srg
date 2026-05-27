#!/bin/sh
set -eu

rm -f /run/php/php-fpm.sock

php-fpm &
php_fpm_pid="$!"

nginx -g 'daemon off;' &
nginx_pid="$!"

cron_pid=""
if [ "${ENABLE_FETCH_CRON:-true}" = "true" ]; then
    interval="${FETCH_INTERVAL_SECONDS:-3600}"
    (
        while true; do
            sleep "$interval"
            php -f /var/www/dmarc-srg/utils/fetch_reports.php || true
        done
    ) &
    cron_pid="$!"
fi

trap 'kill "$php_fpm_pid" "$nginx_pid" ${cron_pid:+"$cron_pid"} 2>/dev/null || true' TERM INT

wait -n "$php_fpm_pid" "$nginx_pid" ${cron_pid:+"$cron_pid"}

kill "$php_fpm_pid" "$nginx_pid" ${cron_pid:+"$cron_pid"} 2>/dev/null || true
wait
