# WEB - Apache path + subdomain hosting

Webova cast projektu bezi na vlastnim Docker image nad `php:8.2-apache` a obsluhuje:

- hlavni administracni aplikaci na `http://localhost:8080`
- zakaznicke weby ze slozky `data/www`
- adresarovy hosting pres URL `http://localhost:8080/~uzivatel`
- subdomenovy hosting pres URL `http://uzivatel.localhost:8080`

---

## Cil

Apache server je nastaveny tak, aby nebylo potreba resit testovaci domeny ani upravy souboru `/etc/hosts`.
Kazdy zakaznik ma URL ve tvaru `/~uzivatel` i `uzivatel.localhost`, ktere se mapuji na jeho `public` slozku.

---

## Dockerfile pro Apache

Konfigurace image je v `docker/apache/Dockerfile`.

Zaklad:

```dockerfile
FROM php:8.2-apache
```

Instalace DB extension:

```bash
RUN docker-php-ext-install pdo pdo_mysql
```

Instalace `pure-ftpd` (kvuli nastroji `pure-pw` z PHP):

```bash
RUN apt-get update \
    && apt-get install -y --no-install-recommends pure-ftpd \
    && rm -rf /var/lib/apt/lists/*
```

Povoleny Apache moduly:

```bash
RUN a2enmod rewrite vhost_alias
```

---

## Startovaci skript

Skript `docker/apache/start-web.sh` pripravi runtime pred startem Apache:

- vytvori `/srv/www`, pokud chybi
- upravi opravneni na data zakazniku
- nastavi vlastnictvi FTP passwd databaze
- vytvori `/srv/www/.hostings.json`, pokud neexistuje
- nastavi `umask 0000`
- spusti `apache2-foreground`

---

## Docker Compose mounty

Sluzba `web` ma tyto klicove mounty:

- `./app:/var/www/html:rw`
- `./data/www:/srv/www:rw`
- `./docker/apache/000-default.conf:/etc/apache2/sites-enabled/000-default.conf:ro`
- `./data/ftp/passwd:/etc/pure-ftpd/passwd:rw`

To znamena:

- hlavni aplikace je oddelena od zakaznickych webu
- zakaznicka data jsou persistentni ve `data/www`
- Apache cte konfiguraci primo z repozitare

---

## Smerovani pozadavku

### Hlavni aplikace

`http://localhost:8080`

Apache pouziva:

```apache
DocumentRoot /var/www/html
```

### Zakaznicky web

`http://localhost:8080/~uzivatel`

Apache pouziva:

```apache
AliasMatch ^/~([A-Za-z0-9._-]+)(/.*)?$ /srv/www/$1/public$2
```

Priklad:

- `http://localhost:8080/~teri` -> `/srv/www/teri/public`
- `http://localhost:8080/~web1/index.php` -> `/srv/www/web1/public/index.php`

### Zakaznicky web pres subdomenu

`http://uzivatel.localhost:8080`

Apache pouziva:

```apache
ServerAlias *.localhost
VirtualDocumentRoot /srv/www/%1/public
```

Priklad:

- `http://teri.localhost:8080` -> `/srv/www/teri/public`
- `http://web1.localhost:8080` -> `/srv/www/web1/public`

---

## `000-default.conf`

Aktualni konfigurace obsahuje dva `VirtualHost` bloky:

- `localhost` pro hlavni aplikaci a path routing `~uzivatel`
- `*.localhost` pro subdomenovy routing

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    AliasMatch ^/~([A-Za-z0-9._-]+)(/.*)?$ /srv/www/$1/public$2
</VirtualHost>

<VirtualHost *:80>
    ServerName customer.localhost
    ServerAlias *.localhost
    VirtualDocumentRoot /srv/www/%1/public

    <Directory /srv/www>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>
</VirtualHost>
```

---

## Struktura zakaznickych webu

Zakaznicke weby jsou ve slozce:

`data/www/{uzivatel}/public`

Priklad:

`data/www/teri/public/index.php`

---

## Shrnuti

Webova vrstva je ted zamerne jednoducha:

- s podporou subdomen `*.localhost`
- bez mapovani nebo uprav `/etc/hosts`
- se stabilnim adresarovym routingem `localhost:8080/~uzivatel`
