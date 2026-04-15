# WEB - Apache path hosting

Webová část projektu je postavena na vlastním Docker image založeném na oficiálním obrazu `php:8.2-apache`. Tento kontejner zajišťuje běh Apache serveru, PHP aplikace a zároveň podporuje obsluhu zákaznických webů.

---

## Cíl

Webový server v projektu zajišťuje několik funkcí současně:

- běh hlavní administrátorské aplikace na adrese `http://localhost:8080`
- obsluhu zákaznických webů uložených ve složce `data/www`
- podporu adresářového hostingu přes `http://localhost:8080/~uzivatel`
- podporu subdoménového hostingu přes `http://uzivatel.localhost:8080`
- podporu testovacích domén přes aliasy `*.cz`, `*.nip.io` a `*.sslip.io`


---

## Dockerfile pro Apache

Docker image webového serveru je definován v souboru `docker/apache/Dockerfile`.

Základ image tvoří:

```dockerfile
FROM php:8.2-apache
```
To znamená, že projekt používá **Apache server s podporou PHP 8.2**.

---

## Instalace PHP rozšíření

V Dockerfile se instalují rozšíření potřebná pro komunikaci s databází MySQL a MariaDB:

```bash
RUN docker-php-ext-install pdo pdo_mysql
```

Tím je umožněno připojení PHP aplikace k databázi pomocí PDO.

---

## Instalace nástroje pure-ftpd

Součástí image je také instalace balíčku `pure-ftpd`:

```bash
RUN apt-get update \
	&& apt-get install -y --no-install-recommends pure-ftpd \
	&& rm -rf /var/lib/apt/lists/*
```

Důvodem je dostupnost nástroje `pure-pw`, který se používá pro vytváření FTP uživatelů.

---

## Změna PHP limitů

Dockerfile nastavuje vyšší limity pro upload souborů:

```bash
RUN echo "upload_max_filesize = 100M\npost_max_size = 100M\nmax_file_uploads = 1000\noutput_buffering = 4096" > /usr/local/etc/php/conf.d/uploads.ini
```

Toto nastavení umožňuje nahrávání větších souborů a většího počtu souborů najednou.

---

## Povolení Apache modulů

V image se aktivují moduly:

```bash
RUN a2enmod rewrite vhost_alias
```

Použité moduly mají tento význam:

- **rewrite** umožňuje přepisování URL adres
- **vhost_alias** umožňuje dynamické mapování domén a subdomén na složky

---

## Kopírování konfigurace a startovacího skriptu

Do image se kopírují důležité soubory:

```bash
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/start-web.sh /usr/local/bin/start-web.sh
```

Soubor `000-default.conf` obsahuje konfiguraci virtuálních hostů a soubor `start-web.sh` připravuje prostředí před spuštěním Apache.

---

## Nastavení pracovního adresáře a spuštění

Na konci Dockerfile se nastaví pracovní adresář a start kontejneru:

```bash
WORKDIR /var/www/html
CMD ["/usr/local/bin/start-web.sh"]
```

---

## Startovací skript `start-web.sh`

Po spuštění kontejneru se nejprve provede skript `start-web.sh`.

Jeho hlavní úkoly jsou:

- **vytvořit složky `/srv/www`**, pokud ještě neexistuje
- nastavit práva pro zákaznické weby, aby do nich mohl zapisovat Apache i FTP
- upravit vlastnictví FTP databáze uživatelů
- vytvořit soubor `.hostings.json`, pokud při prvním spuštění ještě neexistuje
- nastavit `umask 0000`, aby nově vytvořené soubory byly snadno zapisovatelné
- následně spustit Apache pomocí `apache2-foreground`

Tento skript zajišťuje, že prostředí bude funkční i po čerstvém naklonování projektu.

---

## Implementace v Docker Compose

V `docker-compose.yml` jsou pro webovou službu použity tyto mounty:

- `./app:/var/www/html:rw`
- `./data/www:/srv/www:rw`
- `./docker/apache/000-default.conf:/etc/apache2/sites-enabled/000-default.conf:ro`

Tyto mounty mají následující význam:

- složka `app` je připojena do `/var/www/html` a slouží pro hlavní administrátorskou aplikaci
- složka `data/www` je připojena do `/srv/www` a obsahuje zákaznické weby
- soubor `000-default.conf` je připojen jako aktivní Apache konfigurace

Díky tomu zůstává administrační aplikace dostupná na hlavní adrese a zákaznické weby jsou obsluhovány odděleně.

---

## Směrování požadavků

Projekt podporuje několik způsobů směrování požadavků.

### Hlavní aplikace

Hlavní administrační rozhraní běží na adrese:

```md
http://localhost:8080
```

Apache zde používá:

```md
DocumentRoot /var/www/html
```

To znamená, že při přístupu na `localhost:8080` se obsluhuje obsah ze složky `app`.

---

## Adresářový hosting

Pro zpětnou kompatibilitu je podporován také adresářový hosting ve tvaru:

```md
http://localhost:8080/~uzivatel
```

Tento způsob je definován direktivou:

```md
AliasMatch ^/~([A-Za-z0-9._-]+)(/.*)?$ /srv/www/$1/public$2
```

Například adresa:

```md
http://localhost:8080/~teri
```

se přeloží na složku:

```md
/srv/www/teri/public
```

To znamená, že jméno uživatele je součástí cesty URL a Apache podle něj vybere správný adresář.

---

## Subdoménový hosting

Další možností je subdoménový hosting ve tvaru:

```md
http://uzivatel.localhost:8080
```

Tento způsob je definován pomocí:

```md
ServerAlias *.localhost
VirtualDocumentRoot /srv/www/%1/public
```

Proměnná `%1` znamená první část domény, tedy subdoménu.

Například:

```md
http://rohliky.localhost:8080
```

se namapuje na složku:

```md
/srv/www/rohliky/public
```

Tento přístup více připomíná skutečný webhosting, protože každý zákazník má vlastní subdoménu.

---

## Doménový hosting

Konfigurace zároveň podporuje i testovací domény typu:

```md
teri.cz
www.teri.cz
```

Tato část používá:

```md
ServerAlias *.cz
VirtualDocumentRoot /srv/www/%-2/public
```

Díky tomu:

- `teri.cz` -> `teri`
- `www.teri.cz` -> také `teri`

Obě varianty tedy povedou do stejné složky:

```md
/srv/www/teri/public
```

---

## Rozdíl mezi adresářovým hostingem a subdoménami

### Adresářový hosting

Příklad:

```md
http://localhost:8080/~uzivatel
```

Charakteristika:

- jméno uživatele je součástí cesty URL
- Apache používá `AliasMatch`
- řešení je jednoduché a funguje i bez speciální DNS konfigurace

**Výhoda:**

- snadné testování

**Nevýhoda:**

- méně připomíná reálný hosting

---

### Subdoménový hosting

Příklad:

```md
http://uzivatel.localhost:8080
```

Charakteristika:

- jméno uživatele je součástí domény
- Apache používá `VirtualDocumentRoot`
- adresa působí jako samostatný web

**Výhoda:**

- více odpovídá reálnému hostingu

**Nevýhoda:**

- vyžaduje podporu aliasů nebo vhodné testovací domény

---

## Popis souboru `000-default.conf`

Soubor `docker/apache/000-default.conf` obsahuje konfiguraci tří virtuálních hostů.

### První VirtualHost

```bash
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html
    ...
    AliasMatch ^/~([A-Za-z0-9._-]+)(/.*)?$ /srv/www/$1/public$2
</VirtualHost>
```

Tento blok zajišťuje:

- hlavní administrátorské rozhraní `localhost`
- načítání obsahu ze složky `/var/www/html`
- podporu adresářového hostingu přes `~uzivatel`

---

### Druhý VirtualHost

```bash
<VirtualHost *:80>
    ServerName customer.cz
    ServerAlias *.cz
    VirtualDocumentRoot /srv/www/%-2/public
    ...
</VirtualHost>
```

Tento blok zajišťuje:

- obsluhu testovacích domén s koncovkou `.cz`
- mapování domén na složky zákazníků
- vlastní logování do samostatných log souborů

---

### Třetí VirtualHost

```bash
<VirtualHost *:80>
    ServerName customer.localhost
    ServerAlias *.localhost
    ServerAlias *.nip.io
    ServerAlias *.sslip.io
    VirtualDocumentRoot /srv/www/%1/public
    ...
</VirtualHost>
```

Tento blok zajišťuje:

- obsluhu zákaznických webů přes subdomény
- mapování první části domény na odpovídající složku zákazníka
- podporu testovacích aliasů `localhost`, `nip.io` a `sslip.io`

---

## Mapování domén pomocí `/etc/hosts`

Aby bylo možné používat vlastní testovací domény (např. `teri.cz` nebo `www.teri.cz`), je nutné upravit soubor `/etc/hosts` na hostitelském systému.

Příklad:

```bash
127.0.0.1 teri.cz
127.0.0.1 www.teri.cz
```

Tímto se zajistí, že požadavky na tyto domény budou směrovány na lokální Apache server.

V projektu je soubor `/etc/hosts` zároveň připojen do kontejneru:

```yaml
- /etc/hosts:/host_etc_hosts:rw
```

Díky tomu může aplikace případně dynamicky upravovat doménové záznamy přímo na hostitelském systému.

!!! warning "Pozor"
    Úprava souboru `/etc/hosts` vyžaduje administrátorská práva.

---

## Struktura zákaznických webů

Zákaznické weby jsou ukládány ve složce:

`data/www/{uzivatel}/public`

Například:

`data/www/teri/public/index.php`

Tato struktura odpovídá běžnému webhostingu, kde každý zákazník má vlastní root adresář.

---

## Shrnutí

Webový server v tomto projektu umožňuje:

- běh hlavní administrační aplikace  
- adresářový hosting zákaznických webů  
- subdoménový hosting zákaznických webů  
- testování doménového směrování  
- spolupráci s databází a FTP vrstvou  

!!! info "Poznámka"
    Webový server je mapován jako `8080:80`, proto je hlavní aplikace dostupná na adrese `http://localhost:8080`.
