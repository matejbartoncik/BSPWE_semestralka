# Dokumentace k hostingu BSPWE

Tento projekt představuje jednoduché hostingové prostředí vytvořené pomocí Docker Compose. Cílem je simulovat základní principy webhostingu, kde administrátor vytváří hosting pro zákazníky a zákazníci následně spravují své weby, soubory a databáze.

Dokumentace je rozdělena do těchto částí:

- [Aplikace](APP.md) - popis aplikační vrstvy
- [Bezpečnost](SECURITY.md) - bezpečnostní pravidla a doporučení
- [Databáze](DB.md) - popis databázové vrstvy
- [FTP](FTP.md) - správa uživatelů a souborů
- [Web](WEB.md) - webový server a směrování požadavků
- [Jak na MkDocs](mkdocs_tutorial.md) - práce s dokumentací a její správa
- [Návod pro klienty](USER_GUIDE.md) - základní používání hostingu pro zákazníka

---

## Stažení projektu

Projekt je možné stáhnout z GitHubu pomocí:

```bash
git clone https://github.com/matejbartoncik/BSPWE_semestralka.git
cd BSPWE_semestralka
```

!!! TIP
    Doporučuje se projekt klonovat do prostředí WSL (např. `~/projects`),nebo na Linuxové distribuce.Nikoli do Windows disku `C:\`, kvůli výkonu a práci s právy.

---

## Konfigurace prostředí

Před prvním spuštěním je nutné vytvořit konfigurační soubor `.env`:

```bash
cp .env
```

Soubor obsahuje proměnné prostředí, například heslo k databázi.

---

## Spuštění projektu

Projekt se spouští v kořenové složce pomocí příkazu:

```bash
docker compose up -d
```

Tento příkaz spustí všechny kontejnery na pozadí.

Pro zastavení projektu:

```bash
docker compose down
```

---

## Požadavky na systém

Pro spuštění je potřeba:

- **Docker** 
- **Docker Compose** 
- **WSL2 na Windows** 

Volné porty:

- **8080** (web)
- **3306** (databáze)
- **2121** (FTP)  
- **30000–30009** (FTP pasivní režim)  
- **8081** (phpMyAdmin)  
- **8000** (dokumentace)  

---

## Dostupné služby

Po spuštění projektu:

- **Web** -> [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin** -> [http://localhost:8081](http://localhost:8081)
- **Dokumentace** -> [http://localhost:8000](http://localhost:8000) 

!!! info "Poznámka"
    Webový server je v tomto projektu mapován jako `8080:80`, proto je dostupný na `http://localhost:8080`.

---

## Kontejnery

Projekt obsahuje tyto kontejnery:

- **hosting_web** (Apache + PHP)  
- **hosting_db** (MariaDB)  
- **hosting_ftp** (FTP server)  
- **hosting_pma** (phpMyAdmin)  
- **hosting_docs** (MkDocs)  

---

## Popis služeb

- **web (hosting_web)**

    Apache + PHP server, který obsluhuje administrační aplikaci a zákaznické weby

- **db (hosting_db)**

    MariaDB databáze pro ukládání dat aplikace

- **ftp (hosting_ftp)**

    FTP server pro správu souborů zákazníků

- **pma (hosting_pma)**

    phpMyAdmin pro správu databáze přes webové rozhraní

- **docs (hosting_docs)**

    Dokumentace projektu běžící na MkDocs

---

## Struktura projektu

Základní struktura projektu:

```bash
app/                # administrační aplikace
data/www/           # zákaznické weby
data/mariadb/       # data databáze
docker/apache/      # konfigurace Apache
docker/ftp/         # konfigurace FTP
docs/               # dokumentace (MkDocs)
docker-compose.yml  # definice služeb
```

---

## Síťové propojení

Všechny služby jsou propojeny v jedné Docker síti:

- **hosting_net** 

Typ sítě:

- **bridge**

Díky tomu mohou kontejnery komunikovat mezi sebou pomocí názvů služeb:

- `web -> db` (připojení k databázi)
- `pma -> db` (phpMyAdmin)
- `ftp -> web` (sdílené soubory)

!!! info "Tip"
    Docker automaticky zajišťuje DNS překlad názvů služeb v rámci sítě.