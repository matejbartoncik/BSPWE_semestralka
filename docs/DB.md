# Dokumentace k Databázi (DB)

## Ověření připojení
- Databáze běží v kontejneru `hosting_db` (MariaDB).
- Správa probíhá přes phpMyAdmin na adrese: `http://localhost:8081`.
- Přihlášení pro admina: `root` / heslo viz `.env` (proměnná `MYSQL_ROOT_PASSWORD`).
- Připojení z web kontejneru: Hostitel je `db`, port `3306`.

## Ruční vytvoření zákaznické databáze (Návod)
Tento postup zajišťuje, že zákazník má práva **pouze na svou vlastní databázi**:
1. Přihlaste se do phpMyAdmin jako `root`.
2. Přejděte do záložky **Uživatelské účty** -> **Přidat uživatelský účet**.
3. Nastavení jména: `cust_<name>` (např. `cust_pepa`).
4. Název hostitele (Host name): Vyberte `%` (Jakýkoliv hostitel).
5. Vyplňte heslo zákazníka.
6. V sekci **Databáze pro uživatele** zaškrtněte: *Vytvořit databázi se stejným názvem a přidělit všechna oprávnění*.
7. ⚠️ V sekci **Globální oprávnění** nechte vše prázdné!
8. Klikněte dole na **Proveď**.