# Dokumentace Docker Compose

Tento dokument popisuje architekturu a konfiguraci kontejnerů definovaných v souboru `docker-compose.yml`. Projekt se skládá z několika provázaných služeb, které dohromady tvoří kompletní prostředí pro webový hosting.

## Přehled služeb

### 1. `web` (Apache a PHP)
Tato služba běží na vlastním Dockerfile (`docker/apache/Dockerfile`) a slouží jako hlavní webový server. Obsluhuje hlavní administrátorskou aplikaci i weby jednotlivých zákazníků.

- **Kontejner:** `hosting_web`
- **Port:** `8080:80`
- **Závislosti:** Závisí na běhu služby `db`.
- **Zapojené volumes:**
  - `./app` -> `/var/www/html` - Hlavní administrátorská aplikace (dostupná na kořenové doméně).
  - `./data/www` -> `/srv/www` - Úložiště pro data zákazníků. Sdíleno s FTP serverem.
  - `./docker/apache/000-default.conf` -> Nastavení virtuálních hostů Apache.
  - `./data/ftp/passwd` -> Soubor hesel FTP pro jejich správu.
  - `/etc/hosts` -> Slouží pro dynamickou změnu DNS záznamů přímo z PHP kódu (vyžaduje oprávnění na hostitelském stroji).

### 2. `db` (MariaDB)
Relační databázový server, který ukládá jak data pro samotnou aplikaci hostingu, tak případné databáze zákazníků.

- **Kontejner:** `hosting_db`
- **Port:** `3306:3306`
- **Proměnné prostředí:** Nastavení hesla přes `MYSQL_ROOT_PASSWORD` (získáváno ze souboru `.env`).
- **Zapojené volumes:**
  - `./data/mariadb` -> `/var/lib/mysql` - Perzistentní úložiště pro databáze.

### 3. `ftp` (stilliard/pure-ftpd)
FTP server pro přístup zákazníků ke svým souborům.

- **Kontejner:** `hosting_ftp`
- **Porty:** `2121:21` (příkazový port), `30000-30009` (pasivní datové porty).
- **Konfigurace:** Je spuštěn s konkrétními příznaky omezujícími přístup k virtuálním uživatelům přes `/etc/pure-ftpd/passwd/pureftpd.pdb`.
- **Zapojené volumes:**
  - `./data/www` -> `/home` - Sdílené úložiště složek se zákaznickými účty.
  - `./data/ftp/passwd` -> Odkud se čtou (a spouští) přístupové údaje.

### 4. `pma` (phpMyAdmin)
Nástroj pro webovou vizualizaci a správu databáze, určený pro jednodušší ladění a pro zákazníky.

- **Kontejner:** `hosting_pma`
- **Port:** `8081:80`
- **Proměnné prostředí:** Nastaveno přihlašování k MariaDB kontejneru (`PMA_HOST`, `PMA_PORT`).
- **Závislosti:** Závisí na běhu služby `db`.

### 5. `docs` (MkDocs)
Samotná dokumentace, kterou právě čtete, je spouštěna jako nezávislý kontejner.

- **Kontejner:** `hosting_docs`
- **Port:** `8000:8000`
- **Zapojené volumes:** Skript monitoruje celý adresář projektu (`./`) a live-reloaduje dokumentaci.

## Datové složky a perzistence

Služby jako takové jsou bezstavové a veškerá důležitá data jsou ukládána do připojených složek (volumes) uvnitř složky `./data`:
- `./data/www/` - Konkrétní adresáře uživatelů.
- `./data/mariadb/` - Uložené databáze.
- `./data/ftp/passwd/` - Správa virtuálních FTP účtů.

## Sítě (Networks)

Všechny kontejnery jsou zaintegrovány do sítě typu do bridge s názvem `hosting_net` (v rámci compose souboru jako interní síť). To zajišťuje izolovanou síťovou vrstvu, kde se na sebe mohou kontejnery bez problému odkazovat svými názvy (např. připojení na databázový hostitel přes doménu `db`).

## Spuštění a správa

Pro start a ukončení aplikací v kořenovém adresáři sítě projektu (tam, kde leží soubor `.env`) používáme klasické Docker Compose příkazy:

```bash
# Sestavení a spuštění kontejnerů na popředí
docker compose up --build

# Spuštění kontejnerů na pozadí (detached mód)
docker compose up -d

# Zastavení aplikací
docker compose stop

# Zastavení a odebrání kontejnerů i související internej sítě
docker compose down

# Zastavení a smazání vytvořených volumes
docker compose down -v
```
