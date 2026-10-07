#!/bin/sh
set -eu

cache_dir=/var/www/html/cache
cache_seed=/opt/oct-cache-seed
cert_dir=/etc/apache2/local-certs

# The separate read-only mount exposes the original cache skeleton even though
# the persistent cache volume masks cache/ in the application source mount.
if [ ! -f "$cache_dir/.docker-cache-initialized" ]; then
    mkdir -p "$cache_dir"
    if [ -d "$cache_seed" ]; then
        cp -an "$cache_seed/." "$cache_dir/"
    fi
    chown -R www-data:www-data "$cache_dir"
    touch "$cache_dir/.docker-cache-initialized"
fi

# The unmodified application writes .htaccess, .user.ini, and temporary domain
# checks in its root. Docker Desktop supplies a writable Windows bind mount.
if ! runuser -u www-data -- test -w /var/www/html; then
    printf '%s\n' 'Application source must be writable by www-data.' >&2
    exit 1
fi

mkdir -p "$cert_dir"
if [ ! -s "$cert_dir/localhost.crt" ] || [ ! -s "$cert_dir/localhost.key" ]; then
    openssl req -x509 -nodes -newkey rsa:2048 -sha256 -days 825 \
        -keyout "$cert_dir/localhost.key" \
        -out "$cert_dir/localhost.crt" \
        -subj '/CN=localhost' \
        -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' \
        -addext 'basicConstraints=critical,CA:FALSE' \
        -addext 'extendedKeyUsage=serverAuth'
    chmod 600 "$cert_dir/localhost.key"
fi

exec docker-php-entrypoint "$@"
