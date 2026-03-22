# Dokumentace - Skupina ADMIN APP

Tato aplikace slouží k administrátorské správě a automatickému zakládání (provisioningu) webových hostingů pro zákazníky.

## Funkcionalita

### 1. Správa uživatelů a UI
- **Autentizace**: Systém je chráněn heslem přes PHP Sessions.
- **Logout**: Plně funkční odhlašování, které bezpečně ukončí relaci.
- **Moderní UI**: Rozhraní využívá responzivní CSS design (Card layout) pro lepší přehlednost.

### 2. Provisioning webového prostoru (Iterace 1)
- **Automatizace**: Po zadání jména aplikace vytvoří strukturu adresářů v Linuxovém prostředí.
- **Cesta**: `/srv/www/{customer}/public`.
- **Index**: Každý web dostane startovní `index.php`.

### 3. Provisioning databáze a FTP (Iterace 2)
- **MariaDB**: Skript automaticky vytváří databázi `cust{customer}db` a dedikovaného uživatele.
- **FTP**: Generují se unikátní credentials pro přístup přes port 2121.
- **Credentials**: Veškeré údaje jsou bezpečně zapsány do souboru `db_credentials.txt` ve složce zákazníka.

## Technické požadavky
- PHP 8.2 s rozšířením `pdo_mysql` (řešeno přes vlastní Dockerfile).
- Nastavená práva zápisu pro uživatele `www-data` do složky `./data/www`.