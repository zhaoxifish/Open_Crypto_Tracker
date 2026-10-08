#!/bin/sh
set -eu
umask 077
data_dir=/var/lib/btc-monitor-account
key_dir=/var/lib/btc-monitor-account-key
mkdir -p "$data_dir" "$key_dir"
chown www-data:www-data "$data_dir" "$key_dir"
chmod 700 "$data_dir" "$key_dir"
if [ ! -e "$key_dir/master.key" ]; then
    if [ -n "$(find "$data_dir" -type f -print -quit)" ]; then
        printf '%s\n' 'Account data exists but its encryption key is missing; restore the original key volume.' >&2
        exit 1
    fi
    openssl rand -out "$key_dir/master.key.new" 32
    chown www-data:www-data "$key_dir/master.key.new"
    chmod 600 "$key_dir/master.key.new"
    mv "$key_dir/master.key.new" "$key_dir/master.key"
fi
if [ ! -f "$key_dir/master.key" ] || [ "$(wc -c < "$key_dir/master.key")" -ne 32 ]; then
    printf '%s\n' 'Account encryption key unavailable; existing data left untouched.' >&2
    exit 1
fi
chown www-data:www-data "$key_dir/master.key"
chmod 600 "$key_dir/master.key"
printf '%s\n' 'Private account storage is ready.'
