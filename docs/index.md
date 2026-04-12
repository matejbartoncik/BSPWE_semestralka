# Dokumentace k hostingu BSPWE

Tento projekt představuje jednoduché hostingové prostředí vytvořené pomocí Docker Compose. Cílem je simulovat základní principy webhostingu, kde administrátor vytváří hosting pro zákazníky a zákazníci následně spravují své weby, soubory a databáze.

Dokumentace je rozdělena do několika částí:

- Aplikace (App) - popis aplikační vrstvy  
- Databáze (DB) - popis databázové vrstvy  
- FTP (FTP) - správa uživatelů a souborů  
- Web (Web) - webový server a směrování požadavků  

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
- **(doporučeno) WSL2 na Windows** 

Volné porty:

- **80** (web)  
- **3306** (databáze)  
- **2121** (FTP)  
- **30000–30009** (FTP pasivní režim)  
- **8081** (phpMyAdmin)  
- **8000** (dokumentace)  

---

## Dostupné služby

Po spuštění projektu:

- **Web** -> [http://localhost](http://localhost)
- **phpMyAdmin** -> [http://localhost:8081](http://localhost:8081)
- **Dokumentace** -> [http://localhost:8000](http://localhost:8000) 

---

## Kontejnery

Projekt obsahuje tyto kontejnery:

- **hosting_web** (Apache + PHP)  
- **hosting_db** (MariaDB)  
- **hosting_ftp** (FTP server)  
- **hosting_pma** (phpMyAdmin)  
- **hosting_docs** (MkDocs)  

---

## Síťové propojení

Všechny služby jsou propojeny v jedné Docker síti:

- **hosting_net**

Typ sítě:

- **bridge**

!!! info "Tip"
    Kontejnery komunikují pomocí názvů služeb (např. **web -> db**).
