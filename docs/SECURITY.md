# Bezpečnost a izolace

Tato stránka popisuje, jak je v projektu oddělená správa jednotlivých zákazníků a kde jsou aktuální limity řešení.

## Izolace mezi zákazníky

### Souborový prostor
- Každý zákazník má vlastní složku: `/srv/www/{customer}/public`.
- Upload z portálu zapisuje pouze do složky aktuálně přihlášeného zákazníka.
- Při uploadu se čistí cesta souboru (`sanitize_relative_upload_path`) a zakazují se segmenty `.` a `..`.
- Apache směruje doménu zákazníka jen do jeho vlastního `public` rootu (VirtualDocumentRoot).

### Databáze
- Pro každého zákazníka se tvoří samostatná DB (`cust{customer}db`) a DB účet (`cust{customer}`).
- Práva jsou grantována jen na konkrétní DB: `GRANT ALL PRIVILEGES ON \`db\`.* TO ...`.
- Přihlášení přes tento DB účet v phpMyAdmin zobrazí pouze jeho databázi.

### Portálové přístupy
- Zákaznické heslo do portálu se neukládá v plaintextu, ale jako hash (`password_hash`).
- Session drží kontext přihlášeného zákazníka (`$_SESSION['customer_name']`).
- Přihlášený zákazník vidí jen svůj záznam v panelu a jen seznam svých souborů.

### FTP
- Každý zákazník má vlastní FTP účet a home: `/home/{customer}/public`.
- FTP kontejner mapuje `./data/www` jako `/home`, takže cesta odpovídá klientskému prostoru.

## Jak je zabráněno přístupu do cizích souborů

V aplikační vrstvě je izolace řešená kombinací:
- oddělených adresářů podle jména klienta,
- session vazby na konkrétního klienta,
- sanitizace názvu klienta i upload cest,
- cesty uploadu odvozené pouze z přihlášeného klienta, nikoliv z volného vstupu uživatele.

Prakticky: klient nemá ve formuláři možnost zadat cílovou složku cizího klienta a path traversal je při uploadu blokovaný.

## Shrnutí
Izolace zákazníků je primárně aplikační (oddělené cesty, session kontext, oddělené DB/FTP účty)
a funkčně splňuje požadavek, aby jeden zákazník neviděl data druhého v panelu ani databázi.