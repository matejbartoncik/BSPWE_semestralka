
---

# 🛠 Windows + WSL2 + Ubuntu + Docker Desktop Setup

---

# 0️⃣ Požadavky

* Windows 10 (ideálně **22H2**) nebo Windows 11
* Zapnutá virtualizace v BIOS/UEFI

  * Intel VT-x
  * AMD-V

---

# 1️⃣ Zapnutí WSL2 + instalace Ubuntu

## 1.1 Otevři PowerShell jako Administrátor

Start → napiš `PowerShell` → pravé tlačítko → **Spustit jako správce**

---

## 1.2 Nainstaluj WSL + Ubuntu

```powershell
wsl --install
```

Pokud už WSL máš, pokračuj dál.

---

## 1.3 Restart počítače

Po instalaci Windows obvykle vyžaduje restart.

---

## 1.4 Dokonči nastavení Ubuntu

Po restartu se otevře Ubuntu okno.

Zadej:

* nové **username**
* nové **password**

⚠️ Při psaní hesla se nic nezobrazuje – to je v pořádku.

---

## 1.5 Ověř, že běží WSL2

V PowerShellu:

```powershell
wsl -l -v
```

U Ubuntu musí být:

```
VERSION 2
```

Pokud je 1, nastav:

```powershell
wsl --set-version Ubuntu 2
```

---

# 2️⃣ Aktualizace Ubuntu ve WSL

V Ubuntu terminálu:

```bash
sudo apt update && sudo apt upgrade -y
```

---

# 3️⃣ Instalace Git (ve WSL Ubuntu)

```bash
sudo apt install git -y
```

---

# 4️⃣ Instalace Docker Desktop (Windows)

## 4.1 Instalace

Stáhni a nainstaluj **Docker Desktop pro Windows**.
Po instalaci Docker Desktop spusť.

---

## 4.2 Přepnutí na WSL2 backend

Docker Desktop → **Settings → General**

✅ `Use the WSL 2 based engine`

---

## 4.3 Zapnutí WSL integrace (KRITICKY DŮLEŽITÉ)

Docker Desktop →

**Settings → Resources → WSL Integration**

✅ Enable integration with my default WSL distro
✅ U Ubuntu přepínač ON

Klikni **Apply & Restart**

---

# 5️⃣ Ověření Dockeru ve WSL

Zavři všechna Ubuntu okna a otevři Ubuntu znovu.

```bash
docker --version
docker compose version
docker ps
```

✅ Pokud `docker ps` nehodí chybu → vše funguje.

---

## ❗ Pokud se objeví:

```
permission denied ... docker.sock
```

Ve WSL spusť:

```bash
sudo groupadd docker 2>/dev/null
sudo usermod -aG docker $USER
```

Poté v PowerShellu:

```powershell
wsl --shutdown
```

Znovu otevři Ubuntu a zkus:

```bash
docker ps
```

---

# 6️⃣ Klon projektu

⚠️ **DŮLEŽITÉ: Klonovat uvnitř WSL, NE na C:**

Ve WSL Ubuntu:

```bash
mkdir -p ~/projects
cd ~/projects

git clone https://github.com/matejbartoncik/BSPWE_semestralka.git
cd BSPWE_semestralka
```

❌ Neklonovat do `C:\Users\...`
→ pomalé mounty
→ problémy s právy
→ file watcher problémy

---

# 7️⃣ První spuštění projektu (dev)

## 7.1 Připrav `.env`

V rootu repa:

```bash
cp .env.example .env
```

---

## 7.2 Připrav složky (pokud nejsou v repu)

```bash
mkdir -p app data/www data/mariadb
```

---

## 7.3 Spusť kontejnery

```bash
docker compose up -d
```

---

## 7.4 Ověření

| Služba          | URL                                            |
| --------------- | ---------------------------------------------- |
| Web (admin app) | [http://localhost:8080](http://localhost:8080) |
| phpMyAdmin      | [http://localhost:8081](http://localhost:8081) |

---

### Zastavení

```bash
docker compose down
```

---

# 8️⃣ Pravidla pro práci v týmu

* ✅ Všichni pracují ve WSL Ubuntu (`~/projects/...`)
* ✅ Docker se spouští přes `docker compose` ve WSL
* ❌ `.env` se **necommituje**
* ✅ Committuje se jen `.env.example`
* ✅ Porty držíme fixní (8080 / 8081 / 3306 / 2121 …)
* ✅ Změny přes PR (žádné přímé push do `main`)

---

# 🧯 Nejčastější problémy a rychlé opravy

---

## A) `permission denied ... docker.sock`

Viz krok 5 (docker group + `wsl --shutdown`)

---

## B) Port je obsazený (např. 3306)

* Najdi proces ve Windows
  nebo
* Dočasně změň port v `docker-compose.yml`
* Pak sjednotit s týmem

---

## C) „Nejde mi otevřít localhost:8080“

Zkontroluj:

```bash
docker compose ps
docker compose logs web --tail=50
```

---

---

Pokud chceš, můžu ti to ještě:

* 🔹 přepsat do více „produkční“ dokumentace
* 🔹 přidat sekci pro JetBrains + WSL
* 🔹 nebo upravit do README stylu pro GitHub (víc badge, struktura, atd.)
