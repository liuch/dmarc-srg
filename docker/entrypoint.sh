#!/bin/sh
set -eu

rm -f /run/php/php-fpm.sock

php-fpm &
php_fpm_pid="$!"

nginx -g 'daemon off;' &
nginx_pid="$!"

trap 'kill "$php_fpm_pid" "$nginx_pid" 2>/dev/null || true' TERM INT

wait -n "$php_fpm_pid" "$nginx_pid"

kill "$php_fpm_pid" "$nginx_pid" 2>/dev/null || true
wait
