# Dokumentace k FTP

## Ověření připojení a testovací účet
- FTP server běží v kontejneru `hosting_ftp` (použitý obraz `stilliard/pure-ftpd`).
- Připojení: hostitel `localhost`, port `2121`.
- Pasivní porty pro přenos: `30000-30009`.
- V kontejneru je nasměrována lokální složka `./data/www` přímo do `/home`.
- Testovací FTP účet je definován v `.env` (aktuálně `test` / `testpass`).
- Upload otestován nahráním souboru do `data/www/test/public`. (Aby se web zobrazil na `http://localhost:8080/~test/`, je potřeba ještě donastavit `UserDir` nebo `Alias` v Apache - úkol pro Web tým).

## Návrh budoucího provisioningu (Automatizace)
Pro automatické zakládání FTP účtů z naší zákaznické administrace (PHP) navrhuji:
1. Obraz `stilliard/pure-ftpd` obsahuje utilitu `pure-pw`.
2. Ve chvíli, kdy zákazník klikne na "Vytvořit hosting", PHP aplikace spustí příkaz přes Docker a založí uživatele přímo v běžícím FTP kontejneru:
   `pure-pw useradd cust_<name> -u ftpuser -d /home/cust_<name> -m`
3. Tím dojde k okamžitému založení uživatele a jeho složky bez nutnosti restartovat celý FTP kontejner.