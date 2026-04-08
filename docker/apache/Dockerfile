FROM php:8.2-apache

# Instalace ovladačů pro MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Nástroj pure-pw pro vytváření FTP uživatelů
RUN apt-get update \
	&& apt-get install -y --no-install-recommends pure-ftpd \
	&& rm -rf /var/lib/apt/lists/*

# Zvýšení limitů pro upload většího počtu a velikosti souborů
RUN echo "upload_max_filesize = 100M\npost_max_size = 100M\nmax_file_uploads = 1000\noutput_buffering = 4096" > /usr/local/etc/php/conf.d/uploads.ini

RUN a2enmod rewrite vhost_alias

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/start-web.sh /usr/local/bin/start-web.sh

RUN chmod +x /usr/local/bin/start-web.sh

WORKDIR /var/www/html

CMD ["/usr/local/bin/start-web.sh"]