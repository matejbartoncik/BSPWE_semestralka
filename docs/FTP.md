# FTP Server (Pure-FTPd)

Pro přenos souborů na server využíváme Pure-FTPd server. Ten je konfigurován pro práci s virtuálními uživateli, což umožňuje vysokou míru zabezpečení bez nutnosti vytvářet systémové účty v Linuxu.

---

## Architektura a parametry kontejneru

| Parametr | Hodnota |
| :--- | :--- |
| **Image** | `stilliard/pure-ftpd` (komunitní obraz) |
| **Kontejner** | `hosting_ftp` |
| **Hlavní port** | `2121` (namapováno na interní `21`) |
| **Perzistence dat** | Klientské weby: `./data/www` -> `/home` <br> DB hesel: `./data/ftp/passwd` -> `/etc/pure-ftpd/passwd` |

!!! info "Bez vlastního buildu"
    Na rozdíl od Apache nepoužívá FTP služba vlastní `Dockerfile`. Veškerá potřebná konfigurace (včetně nasměrování na správnou databázi hesel) se řeší dynamicky pomocí direktivy `command:` přímo v `docker-compose.yml`.

---

## Pasivní režim a síťový provoz
Aby FTP spojení fungovalo korektně i přes NAT a vnitřní Docker bridge síť, je server spuštěn s explicitní konfigurací pro pasivní režim.

- **Datové porty:** V `docker-compose.yml` je vyhrazen rozsah `30000-30009`. Tyto porty slouží k samotnému přenosu souborů a výpisu adresářů.
- **Externí IP:** Proměnná `PUBLICHOST=localhost` zajišťuje, že FTP server odpoví klientovi (např. FileZille) správnou adresou pro navázání datového spojení, jinak by klientům hrozil timeout.

---

## Správa virtuálních uživatelů a datové úložiště

Uživatelé FTP (zákazníci) nejsou evidováni v systémovém `/etc/passwd`. Místo toho využíváme integrovaný nástroj `pure-pw`, který pracuje se dvěma oddělenými soubory:

1. **Textový zdroj (`pureftpd.passwd`)**
   Tento soubor obsahuje uživatele v čitelné podobě. Zde PHP aplikace (`create_hosting.php`) zakládá nové účty a definuje jim domovské adresáře (chroot do `/home/{customer}/public`).
2. **Binární databáze (`pureftpd.pdb`)**
   Sama služba Pure-FTPd z výkonnostních důvodů textový soubor nečte. Používá tento zkompilovaný binární soubor, ve kterém probíhá samotná autentizace.

---

## Mechanismus okamžité aktualizace (Bez restartu)

Běžným problémem při přidání FTP uživatele v Dockeru je nutnost restartovat celý kontejner. My tento problém řešíme plně dynamicky:

1. **Úprava ze strany PHP:**
   Když je založen nový hosting, PHP aplikace spustí příkaz `pure-pw useradd...` a provede zápis do textového souboru. Následně obratem zavolá `pure-pw mkdb`, čímž vygeneruje čerstvou binární `.pdb` databázi.
2. **Čtení ze strany Pure-FTPd:**
   Díky modifikovanému spouštěcímu příkazu v `docker-compose.yml` (`-l puredb:/etc/pure-ftpd/passwd/pureftpd.pdb`) FTP server natvrdo "kouká" přímo do nasdíleného svazku (`./data/ftp/passwd/`).

**Výsledek:** Jakmile PHP aplikace zkompiluje novou `.pdb` databázi, FTP server je schopen nového zákazníka okamžitě ověřit, a to zcela bez výpadku či restartu služby.