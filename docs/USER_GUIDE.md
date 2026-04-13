# Klientský manuál

Tento průvodce slouží jako rychlý návod pro zákazníky našeho hostingu. Dozvíte se, jak nahrát svůj web a jak se připojit k databázi.

---

## 1. Přístupové údaje
Při založení hostingu se vám veškeré přístupové údaje zobrazí v informační zprávě přímo v klientském/administračním portálu. **Bezpečně si je uložte.**

*(Poznámka: Systém tyto údaje ukládá také do souboru `hosting_credentials.txt` v kořenovém adresáři vašeho hostingu. Z bezpečnostních důvodů do této kořenové složky nemáte přes FTP přístup – vidí ji pouze hlavní administrátor serveru.)*

---

## 2. Nahrávání webu (FTP)
K nahrání souborů použijte libovolného FTP klienta (doporučujeme **FileZilla**).

| Pole | Hodnota |
| :--- | :--- |
| **Hostitel** | `localhost` *(nebo IP adresa vašeho serveru)* |
| **Port** | `2121` |
| **Uživatel** | vaše jméno (např. `petr`) |
| **Protokol** | FTP - File Transfer Protocol |

!!! success "Kam nahrávat?"
    Své soubory (index.php, obrázky atd.) nahrávejte vždy do složky **`public`**. Jste do ní po přihlášení automaticky přesměrováni a z bezpečnostních důvodů ji nemůžete opustit. Pouze soubory v této složce budou dostupné z internetu.

---

## 3. Práce s databází
Pro správu databáze využijte webové rozhraní phpMyAdmin.

- **Adresa:** [http://localhost:8081](http://localhost:8081)
- **Server pro připojení z PHP:** `db`

**Příklad připojení z vašeho PHP kódu:**
~~~php
$host = 'db'; // V rámci sítě hostingu používáme interní název
$db   = 'cust_petrdb';
$user = 'cust_petr';
$pass = 'vaše_heslo';
~~~

!!! warning "Externí přístup k databázi"
    Ve výchozím nastavení tohoto projektu je port databáze (`3306`) mapován i na hostitelský server. Primárním a bezpečným způsobem správy je ovšem využití aplikace phpMyAdmin, případně přístup z PHP skriptů. Pro produkční nasazení doporučujeme přímý externí přístup na port 3306 omezit pomocí firewallu.