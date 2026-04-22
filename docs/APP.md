# Dokumentace - Skupina ADMIN APP

Aplikace slouží jako centrální panel pro správu hostingu: admin založí nový hosting a klient pak spravuje svůj webový prostor.

## `create_hosting.php` (provisioning klienta)

Skript `app/create_hosting.php` spouští admin po odeslání formuláře v panelu.

- Ověří admin session (`$_SESSION['admin_logged_in']`) a přijímá pouze `POST`.
- Vyčistí název klienta (`sanitize_customer_name`) a zkontroluje kolize v `/srv/www/.hostings.json`.
- Vytvoří adresář klienta `/srv/www/{customer}/public` a startovní `index.php`.
- Vygeneruje přístupy pro portál, databázi a FTP.
- V MariaDB vytvoří DB `cust{customer}db` a DB uživatele s právy pouze na tuto DB.
- Přes `pure-pw` vytvoří FTP účet s home `/home/{customer}/public`.
- Zapíše metadata do registru `/srv/www/.hostings.json` a klientský přehled do `hosting_credentials.txt`.
- Při chybě provede rollback (FTP účet, DB/uživatel, vytvořené složky), aby zůstal systém konzistentní.

## `delete_hosting.php` (deprovisioning klienta)

Skript `app/delete_hosting.php` spouští admin z výpisu existujících hostingů.

- Ověří admin session a přijímá pouze `POST`.
- Načte záznam hostingu z registru `/srv/www/.hostings.json`.
- Pokusí se smazat FTP účet (`pure-pw userdel`) a přegenerovat FTP DB.
- Pokusí se odstranit zákaznickou DB a DB uživatele v MariaDB.
- Odstraní složku zákazníka `/srv/www/{customer}`.
- Vždy aktualizuje registr hostingů a vrátí flash zprávu (včetně varování, pokud některý krok vyžaduje ruční kontrolu).

## Struktura dat ve složce `/srv/www`

`/srv/www` je sdílený web root klientů (mapovaný z `./data/www`).

```text
/srv/www/
  .hostings.json
  {customer}/
    hosting_credentials.txt
    public/
      index.php
      ... soubory webu klienta ...
```

- `.hostings.json` drží registr hostingů pro přihlášení a výpis v panelu.
- `{customer}/public` je veřejná webová složka klienta.
- `hosting_credentials.txt` obsahuje vygenerované přístupy pro předání klientovi.

## `index.php` (hlavní aplikace hostingu)

Soubor `app/index.php` řeší přihlášení i uživatelské rozhraní.

- Admin režim: přihlášení, formulář pro vytvoření hostingu, seznam existujících hostingů a jejich smazání.
- Klientský režim: zobrazení vlastních údajů (web, DB, FTP) a správa vlastního `public` prostoru.
- Upload (`customer_upload`) ukládá soubory jen do složky přihlášeného klienta.
- Cesty uploadu se validují, aby nebylo možné nahrávat mimo povolený adresář.
- UI je v `app/style.css`, akce vrací uživateli flash zprávy.

## Poznámky

- Bezpečnost a izolace klientů jsou popsány v `docs/SECURITY.md`.
- Detail DB vrstvy je v `docs/DB.md`, FTP vrstva v `docs/FTP.md`.
