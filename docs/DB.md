# Databáze (MariaDB)

Tato část projektu zajišťuje ukládání dat pro hlavní aplikaci i pro weby jednotlivých zákazníků. Systém je navržen tak, aby poskytoval plný přístup administrátorům a zároveň striktně izoloval data jednotlivých zákazníků.

---

## Architektura a parametry kontejneru

| Parametr | Hodnota |
| :--- | :--- |
| **Image** | `mariadb:10.11` |
| **Kontejner** | `hosting_db` |
| **Interní / Externí port** | `3306` / `3306` |
| **Persistence dat** | `./data/mariadb` -> `/var/lib/mysql` |

!!! tip "Perzistence a obnova"
    Díky mapování složky `./data/mariadb` přežijí veškerá databázová data restart, zastavení i úplné smazání kontejneru. Při havárii stačí zachovat tuto složku a po spuštění `docker-compose up` je databáze v původním stavu.

---

## Přístupové role a phpMyAdmin

Správa databází probíhá primárně přes nástroj phpMyAdmin běžící v kontejneru `hosting_pma` na adrese **[http://localhost:8081](http://localhost:8081)**. Chování se liší podle toho, kdo se přihlašuje:

### 1. Administrátor (Root přístup)
- **Login:** `root`
- **Heslo:** Definuje proměnná `MYSQL_ROOT_PASSWORD` v souboru `.env`.
- **Oprávnění:** Má absolutní kontrolu. Vidí systémové tabulky i databáze všech zákazníků. Kontejner `hosting_pma` má tento root přístup defaultně předkonfigurovaný pro komunikaci s kontejnerem `db`.

### 2. Zákazník (Izolovaný přístup)
- **Login / Heslo:** Unikátně generováno při založení hostingu (uživatel např. `cust_alfa`).

!!! warning "Pozor na záměnu údajů (Portal vs. DB)"
    Údaje pro přístup do databáze (`db_user` a `db_password`) jsou z bezpečnostních důvodů **zcela oddělené** od údajů pro přístup do klientského portálu (`portal_user` a `portal_password`). Pro přihlášení do phpMyAdminu musí zákazník použít výhradně své databázové údaje.

- **Oprávnění:** Striktně omezeno. Pokud se zákazník přihlásí do phpMyAdminu svými databázovými údaji, uvidí **pouze** svou vlastní databázi (např. `cust_alfadb`). Systémové a cizí databáze jsou mu zcela skryty na úrovni MariaDB oprávnění.

---

## Životní cyklus a Provisioning

Vytváření databází a přístupů je plně automatizováno skriptem `create_hosting.php`. Zde je technický tok (credential flow):

1. **Generování hesel:** Aplikace vygeneruje samostatné zapamatovatelné heslo pro portál, FTP i databázi pomocí funkce `generate_memorable_password()`.
2. **Připojení k DB:** PHP aplikace se připojí jako `root` pomocí PDO.
3. **Exekuce SQL:**
   - `CREATE DATABASE cust_{name}db CHARACTER SET utf8mb4...`
   - `CREATE USER 'cust_{name}'@'%' IDENTIFIED BY '{db_heslo}';`
   - `GRANT ALL PRIVILEGES ON cust_{name}db.* TO 'cust_{name}'@'%';`
   - `FLUSH PRIVILEGES;`
4. **Uložení credentials:** Vygenerované údaje se propíšou do registru `.hostings.json` a exportují se zákazníkovi do souboru `hosting_credentials.txt` v jeho složce.

---

## Zpracování chyb a Rollback

Aplikace počítá s tím, že proces zřízení hostingu může selhat (např. chyba při vytváření FTP uživatele nebo selhání zápisu na disk). 

V souboru `create_hosting.php` je implementován robustní **rollback mechanismus**:
- Pokud proces selže v jakékoliv fázi po vytvoření databáze, chytí se výjimka (`Throwable`).
- Aplikace se znovu připojí jako root a provede úklid:
  - `DROP DATABASE IF EXISTS cust_{name}db;`
  - `DROP USER IF EXISTS 'cust_{name}'@'%';`
- Tím je zaručeno, že v systému nezůstanou žádné "osiřelé" databáze ani uživatelské účty z nedokončených registrací.