#!/bin/sh
set -e

# Bind mount keeps host permissions; normalize them so Apache/PHP and FTP can write.
mkdir -p /srv/www
chmod -R a+rwX /srv/www || true

# FTP users db file needs to be writable by www-data (the PHP user running pure-pw)
# Using chown to avoid potential pureftpd strict permission checks rather than a+rwX
chown -R www-data:www-data /etc/pure-ftpd/passwd || true

if [ ! -f /srv/www/.hostings.json ]; then
    printf '{}\n' > /srv/www/.hostings.json || true
fi
chmod a+rw /srv/www/.hostings.json || true

umask 0000

exec apache2-foreground
