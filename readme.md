Setup (Windows + WSL2 Ubuntu + Docker Desktop)
0) Požadavky

Windows 10 (ideálně 22H2) nebo Windows 11

Zapnutá virtualizace v BIOS/UEFI (Intel VT-x / AMD-V)

1) Zapnutí WSL2 + instalace Ubuntu
1.1 Otevři PowerShell jako Administrátor

Start → napiš „PowerShell“ → pravé tlačítko → Spustit jako správce

1.2 Nainstaluj WSL + Ubuntu

Spusť:

wsl --install

Pokud už WSL máš, ale nejsi si jistý, pokračuj dál.

1.3 Restart počítače

Po instalaci WSL Windows obvykle vyžaduje restart.

1.4 Dokonči nastavení Ubuntu

Po restartu se otevře Ubuntu okno. Zadej:

nové username

nové password (při psaní není vidět, to je OK)

1.5 Ověř, že běží WSL2

V PowerShellu:

wsl -l -v

U Ubuntu musí být VERSION 2.
Pokud je 1, nastav:

wsl --set-version Ubuntu 2
2) Aktualizace Ubuntu ve WSL

V Ubuntu terminálu:

sudo apt update && sudo apt upgrade -y
3) Instalace Git (ve WSL Ubuntu)

Ve WSL Ubuntu:

sudo apt install git -y
4) Instalace Docker Desktop (na Windows)
4.1 Nainstaluj Docker Desktop

Stáhni a nainstaluj Docker Desktop (Windows instalátor)

Po instalaci Docker Desktop spusť

4.2 Přepni Docker na WSL2 backend

V Docker Desktop:

Settings → General
✅ Use the WSL 2 based engine

4.3 Zapni WSL integraci pro Ubuntu (kriticky důležité!)

Docker Desktop:

Settings → Resources → WSL Integration
✅ Enable integration with my default WSL distro
✅ U Ubuntu přepínač ON

Apply & Restart
5) Ověření Dockeru ve WSL Ubuntu

Zavři všechna Ubuntu okna, otevři Ubuntu znovu.

Ve WSL Ubuntu:

docker --version
docker compose version
docker ps

✅ Pokud docker ps nehodí chybu, jsi OK.

Pokud uvidíš permission denied ... docker.sock, dej:

sudo groupadd docker 2>/dev/null
sudo usermod -aG docker $USER

Pak ve Windows PowerShell:

wsl --shutdown

Znovu otevři Ubuntu a zkus docker ps.
6) Klon projektu (DŮLEŽITÉ: uvnitř WSL, ne na C:)

Ve WSL Ubuntu:

mkdir -p ~/projects
cd ~/projects
git clone https://github.com/matejbartoncik/BSPWE_semestralka.git
cd BSPWE_semestralka

Neklonovat do C:\Users\... – budete mít pomalé mounty a problémy.
7) První spuštění projektu (dev)
7.1 Připrav .env

V rootu repa:

cp .env.example .env
7.2 Připrav složky (pokud nejsou v repu)

Ve WSL Ubuntu v rootu repa:

mkdir -p app data/www data/mariadb
7.3 Spusť kontejnery
docker compose up -d
7.4 Ověření

Web (admin app): http://localhost:8080

phpMyAdmin (pokud je v compose): http://localhost:8081

Zastavení:

docker compose down
8) Pravidla pro práci v týmu (aby to všem fungovalo stejně)

Všichni pracují ve WSL Ubuntu (repo je v ~/projects/...)

Docker se spouští přes docker compose ... ve WSL

.env se necommituje (jen .env.example)

Porty držíme fixní (8080/8081/3306/2121…)

Změny přes PR (žádné přímé push do main)

9) Nejčastější problémy a rychlé opravy
A) permission denied ... docker.sock

Viz krok 5 (docker group + wsl --shutdown).

B) Port je obsazený (např. 3306)

Najdi proces ve Windows nebo změň port v compose (dočasně, pak to sjednotit).

C) „Nejde mi otevřít localhost:8080“

Zkus:

docker compose ps
docker compose logs web --tail=50