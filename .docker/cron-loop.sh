#!/bin/sh
set -eu

# Optional: the official cron.php handles its own application scheduling.
# Keep the same localhost network namespace as the web service for self-fetches.
while :; do
    php /var/www/html/cron.php || printf '%s\n' 'cron.php failed; retrying in 20 minutes.' >&2
    sleep 1200
done
