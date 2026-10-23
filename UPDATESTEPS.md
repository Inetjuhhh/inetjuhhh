# Bijwerken en beheren: je spiekbriefje

Voor elke keer dat je iets aan de code verandert, en voor als de server moeilijk doet. Server: **nieuw.inetjuhhh.nl** (na de overstap **inetjuhhh.nl**), gebruiker **`ine`**.

**De vaste regel:** content (blogs, foto's, reacties, Ik & wij, Privacy) maak je **in de admin op de server**. Code en opmaak verander je **op je laptop**.

---

## 1. Een code-aanpassing online zetten

| Waar | Wat |
|---|---|
| laptop | aanpassen, bekijken op `http://inetjuhhh.test` |
| laptop | `php artisan test`: alles groen? |
| laptop | commit + push naar GitHub |
| server | `./deploy.sh` |
| browser | even kijken op de site |

**Op de server:**
```bash
ssh ine@nieuw.inetjuhhh.nl
cd /var/www/inetjuhhh && ./deploy.sh
```
`deploy.sh` haalt de nieuwe code op, installeert pakketten, bouwt de styles, voert databasewijzigingen (migrations) door, ververst de caches en herstart de queue worker. Hij vraagt één keer het **sudo-wachtwoord van ine**. Het eindigt met **✓ Done**.

**Goed om te weten:**
- **Een nieuwe instelling in `.env`?** Die gaat niet mee met git. Zet hem op je laptop in `.env.example` (met lege waarde), en op de server met de hand: `nano .env`, daarna `php artisan optimize && php artisan queue:restart`.
- **Je echte blogs zijn beschermd:** op de server weigeren `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `db:seed`, `wordpress:import` en `blogs:subjects` (ook met `--force`). Je ziet dan *This command is prohibited from running in this environment*. Op je laptop werken ze gewoon.
- **Een wijziging aan de database** (tabel of kolom erbij) doe je alleen met een **migration** op je laptop (`php artisan make:migration …`). `deploy.sh` voert hem op de server uit. Nooit met de hand in de database op de server.
- **Styles aangepast?** Stop daarna lokaal `npm run dev` (Ctrl+C), anders blijft `public/hot` staan en wordt je lokale admin traag.

### Iets misgegaan na een update?
Draai de laatste commit terug op je laptop, en zet die "terugdraai" online:
```bash
git revert HEAD          # op je laptop: maakt een nieuwe commit die de vorige ongedaan maakt
git push
```
En op de server weer `./deploy.sh`. Ging er iets mis met de database zelf? Dan is er de dagelijkse **backup van Hetzner** (hoofdstuk 4).

---

## 2. De server doet moeilijk: van licht naar zwaar

**Een mail "Foutje op inetjuhhh.nl"?** Daarin staat wat er misging, op welke pagina, en waar het in het logboek staat. Dezelfde fout krijg je hooguit één keer per uur, en hooguit 10 mails per uur in totaal. Eén zo'n mail is meestal een eenmalig foutje van één bezoeker; krijg je er meerdere, begin dan hieronder bij stap 2.

Begin bovenaan, en ga pas een stap verder als het niet helpt.

### Stap 1: is het de site, of de hele server?
- Open **`https://nieuw.inetjuhhh.nl/up`**. Zie je een groene pagina? Dan draait Laravel; het probleem zit in één pagina of onderdeel.
- Kun je inloggen met `ssh ine@nieuw.inetjuhhh.nl`? Dan leeft de server: ga naar stap 2. Lukt dat niet (blijft hangen, *timed out*)? Ga naar stap 4.

### Stap 2: wat zegt de server? (via SSH)
```bash
cd /var/www/inetjuhhh
tail -30 storage/logs/laravel-$(date +%F).log      # de laatste fouten van de site
df -h /                                            # schijf vol? (Use% bijna 100%)
free -h                                            # geheugen op?
sudo supervisorctl status                          # queue worker RUNNING?
systemctl status nginx php8.4-fpm mysql --no-pager | grep -E "●|Active"
```

### Stap 3: één onderdeel herstarten (site is ~5 seconden weg)
```bash
sudo systemctl restart php8.4-fpm      # "502 Bad Gateway", of de site is heel traag
sudo systemctl restart nginx           # de site antwoordt helemaal niet
sudo systemctl restart mysql           # fouten over de database ("Connection refused")
sudo supervisorctl restart inetjuhhh-queue   # mails blijven hangen
php artisan optimize:clear && php artisan optimize   # vreemde fouten na het wijzigen van .env
```

### Stap 4: de hele server herstarten
**Via SSH** (als dat nog lukt), netjes:
```bash
sudo reboot
```
Na een minuut weer online. Alles start vanzelf: nginx, PHP, MySQL, de queue worker en de scheduler.

**Via de Hetzner Console** (als SSH niet meer lukt), ook vanaf je telefoon:
1. Ga naar **console.hetzner.com** → project **Inetjuhhh** → je server.
2. Tab **Power**:
   - **Reboot** / **Shut down**: netjes, de server krijgt de tijd om alles af te sluiten. Probeer dit eerst.
   - **Reset**: hard, alsof je de stekker eruit trekt. Alleen als netjes niet werkt (de server reageert nergens meer op). Je verliest hooguit wat er die seconde werd opgeslagen.
3. Wacht 1 à 2 minuten, en open de site.

**Kijken wat de server doet, zonder SSH:** rechtsboven op de pagina van je server staat een **>_**-knop (**Console**). Dat is een scherm van de server in je browser, alsof je er een toetsenbord en beeldscherm op aansluit. Log in als **`ine`** met het **sudo-wachtwoord**. Handig als SSH stuk is (bijvoorbeeld door een fout in de firewall).

### Stap 5: een backup terugzetten (noodgeval)
Console → je server → tab **Backups** → kies een dag → **Restore**. Dat zet de **hele server** terug, ook de foto's. Let op: alles wat daarna veranderd is (nieuwe blogs, reacties) is dan weg. Alleen als niets anders helpt. Eén blog of reactie kwijt? Dan is hoofdstuk 6 handiger.

---

## 3. Vanaf je telefoon of tablet (bijvoorbeeld op vakantie)

| Wat je wilt | Wat je nodig hebt |
|---|---|
| Server herstarten (Reboot/Reset) | Alleen een browser: **console.hetzner.com**. Geen app nodig |
| In de server kijken of iets herstarten | Een **SSH-app** + een **eigen sleutel voor dat apparaat** |
| Een blog schrijven of een reactie goedkeuren | Alleen een browser: `/admin` (werkt op de telefoon, met de tabbladen Verhaal/Details) |

**Een SSH-app instellen** (doe dit thuis, vóór je weggaat):
1. Installeer een SSH-app. Bijvoorbeeld:
   - **Termius** (iPhone, iPad en Android, gratis basisversie)
   - **Blink Shell** (iPhone/iPad)
   - **JuiceSSH** (Android)
2. Laat de app **een eigen sleutel maken** (in Termius: *Keychain → Generate key*, type **ED25519**). Kopieer **nooit** de privésleutel van je laptop naar je telefoon: elk apparaat zijn eigen sleutel. Raak je je telefoon kwijt, dan haal je alleen díe sleutel van de server.
3. Kopieer de **publieke** sleutel van de app (begint met `ssh-ed25519`) en zet hem op de server, vanaf je laptop:
   ```bash
   ssh ine@nieuw.inetjuhhh.nl
   nano ~/.ssh/authorized_keys
   ```
   Plak hem als **nieuwe regel** onderaan (de regel van je laptop laat je staan). Opslaan: Ctrl+O, Enter, Ctrl+X.
4. Maak in de app een verbinding: host `nieuw.inetjuhhh.nl` (later `inetjuhhh.nl`), gebruiker `ine`, en de sleutel van stap 2.
5. **Test het thuis**, met je wifi uit (via mobiel internet): `sudo supervisorctl status`.

**Telefoon kwijt?** Haal zijn regel weg uit `~/.ssh/authorized_keys` op de server (vanaf je laptop). Dan werkt die sleutel nergens meer.

---

## 4. Klaar voor vakantie: checklist

- [ ] **Tweestapsverificatie (2FA)** aan op je **Hetzner**-account (Console → je account → *Security*). Wie in je Hetzner-account komt, kan je server herstarten of verwijderen; met 2FA alleen jij.
- [ ] 2FA ook op **GitHub**, **Brevo** en **Cloudflare**.
- [ ] Je wachtwoordmanager op je telefoon, met: Hetzner, het **sudo-wachtwoord van ine**, de passphrase van je telefoonsleutel.
- [ ] SSH-app op je telefoon getest via mobiel internet (hoofdstuk 3).
- [ ] **Foutmeldingen per mail** komen aan: op de server `php artisan site:test-alert`. Binnen een minuut krijg je "Foutje op …" in je Gmail (kijk ook even in Spam, en markeer hem als "geen spam").
- [ ] Een gratis **uptime-monitor** die je mailt als de site plat ligt, bijvoorbeeld **UptimeRobot** (uptimerobot.com): monitor-type *HTTP(s)*, adres `https://inetjuhhh.nl/up`, controle elke 5 minuten. Dan weet je het vóór je lezers het merken.
- [ ] Controleer in de Hetzner Console dat er **Backups** van de afgelopen dagen staan, en in het R2-dashboard dat er een database-kopie van vannacht is (hoofdstuk 8).

---

## 5. Veelvoorkomende problemen

| Wat je ziet | Wat je doet |
|---|---|
| **502 Bad Gateway** | `sudo systemctl restart php8.4-fpm` |
| **500 Server Error** of witte pagina | `tail -30 storage/logs/laravel-$(date +%F).log`; vaak een typfout in `.env` → verbeteren → `php artisan optimize` |
| **Site helemaal onbereikbaar**, SSH ook | Hetzner Console → Power → Reboot (hoofdstuk 2, stap 4) |
| **Mails komen niet aan** | `sudo supervisorctl status` → niet RUNNING? `sudo supervisorctl restart inetjuhhh-queue`. Zie verder EMAILSTEPS.md |
| **`./deploy.sh` stopt met een fout** | Lees de laatste regels: bij `git pull` heb je op de server iets in de code veranderd → `git status` en `git checkout -- <bestand>`; bij `composer`/`npm` stuur de foutmelding naar mij |
| **Browser waarschuwt voor het certificaat** | `sudo certbot renew`, daarna `sudo systemctl reload nginx` |
| **Schijf vol** (`df -h` bijna 100%) | Meestal logbestanden: `sudo journalctl --vacuum-time=7d`, en kijk met `du -sh /var/www/inetjuhhh/storage/*` wat groot is |
| **Je komt de admin niet in** | "Wachtwoord vergeten?" op de inlogpagina; en `ADMIN_EMAILS` in `.env` moet je inlogadres bevatten |
| **Op je laptop reageert de admin niet** (zoeken, filters, menu doen niets) | Meestal passen de pakketten in `vendor/` niet meer bij `composer.lock`, bijvoorbeeld na een `composer update` die je niet hebt gecommit. Op je laptop: `composer install`. Dat zet alles terug naar `composer.lock` en kopieert Filament's JavaScript opnieuw |

### `composer install` of `composer update`?

| | Wat het doet | Wanneer |
|---|---|---|
| **`composer install`** | Installeert precies de versies uit `composer.lock`. Verandert niets aan je project. | Na een `git pull`, of als er iets raar doet. `deploy.sh` doet dit op de server ook. |
| **`composer update`** | Haalt van alle pakketten de nieuwste versie op, en schrijft die in `composer.lock`. | Alleen bewust, als je wilt bijwerken. |

Doe je een `composer update`, dan hoort daar meer bij:
1. `php artisan test`: alles groen?
2. Kijk in de admin of alles werkt.
3. `git status`: commit **`composer.lock`**, en ook **`public/js/filament`** en **`public/css/filament`** als die veranderd zijn (Filament kopieert zijn JavaScript daar vanzelf naartoe).
4. Push, en `./deploy.sh` op de server.

Commit je `composer.lock` niet mee, dan draait je laptop een andere versie dan de server, en dat geeft vreemde fouten zoals hierboven.

---

## 6. Een blog of reactie per ongeluk weggegooid

Elke nacht om 03:30 maakt de server een kopie van de **database** (zonder foto's), in `storage/app/backups/database-<datum>.sql.gz`. De kopieën van de laatste 14 dagen blijven bewaard. Zelf een kopie maken, bijvoorbeeld vóór een grote opruimactie, kan ook:
```bash
cd /var/www/inetjuhhh && php artisan site:backup
ls -lh storage/app/backups/        # welke kopieën er zijn
```

Foto's staan **niet** in deze kopie. Een weggegooide blog komt dus terug met zijn tekst, maar een foto die je óók uit de mediabibliotheek hebt verwijderd niet. Die zit wel in de dagelijkse backup van Hetzner.

### Eén blog terughalen

Het idee: je zet de kopie van vóór het weggooien in een **aparte, tijdelijke database**, en kopieert alleen die ene blog terug. Je echte database blijft verder onaangeraakt.

**1. De kopie in een tijdelijke database zetten** (kies de datum van vóór het weggooien):
```bash
cd /var/www/inetjuhhh
sudo mysql -e "CREATE DATABASE inetjuhhh_herstel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c storage/app/backups/database-2026-10-18.sql.gz | sudo mysql inetjuhhh_herstel
```

**2. De blog opzoeken** (een woord uit de titel):
```bash
sudo mysql inetjuhhh_herstel -e "SELECT id, title FROM blogs WHERE title LIKE '%Toscane%';"
```
Onthoud het **id**, het getal vooraan. Hieronder staat `123`: vervang dat overal door jouw id.

**3. De blog terugzetten, met zijn landen, tags, reacties en hartjes:**
```bash
sudo mysql <<'SQL'
INSERT INTO inetjuhhh.blogs            SELECT * FROM inetjuhhh_herstel.blogs            WHERE id = 123;
INSERT INTO inetjuhhh.blog_country     SELECT * FROM inetjuhhh_herstel.blog_country     WHERE blog_id = 123;
INSERT INTO inetjuhhh.blog_tag         SELECT * FROM inetjuhhh_herstel.blog_tag         WHERE blog_id = 123;
INSERT INTO inetjuhhh.blog_subcategory SELECT * FROM inetjuhhh_herstel.blog_subcategory WHERE blog_id = 123;
INSERT INTO inetjuhhh.responses        SELECT * FROM inetjuhhh_herstel.responses        WHERE blog_id = 123;
INSERT INTO inetjuhhh.blog_likes       SELECT * FROM inetjuhhh_herstel.blog_likes       WHERE blog_id = 123;
INSERT INTO inetjuhhh.blog_views       SELECT * FROM inetjuhhh_herstel.blog_views       WHERE blog_id = 123;
SQL
php artisan cache:clear
```
`INSERT … SELECT` kopieert rijen van de ene database naar de andere. Omdat een id nooit opnieuw wordt gebruikt, botst er niets. `cache:clear` zorgt dat de blog meteen weer op de kaart en in de menu's staat.

**4. De tijdelijke database weer weggooien:**
```bash
sudo mysql -e "DROP DATABASE inetjuhhh_herstel;"
```
Let goed op dat hier `inetjuhhh_herstel` staat, met `_herstel` erachter.

**Twijfel je?** Doe stap 1 en 2, en stuur me het id en de titel: dan lopen we stap 3 samen door.

### Eén reactie terughalen
Hetzelfde, maar in stap 2 zoek je de reactie (`SELECT id, name, response FROM responses WHERE name LIKE '%Femke%';`), en in stap 3 gebruik je alleen de regel van `responses`, met `WHERE id = …` in plaats van `WHERE blog_id = …`.

---

## 7. Beschrijvingen bij je foto's (alt-tekst)

Een beschrijving wordt voorgelezen aan mensen die de foto niet kunnen zien, en helpt Google. Je foto- en galerijblokken gebruiken hem meteen.

**Eén foto:** Mediabibliotheek → open de foto → **Beschrijf met AI**. Claude kijkt naar de foto en de blog waar hij in staat, en zet een voorstel in het veld *Alt*. Pas het gerust aan (bijvoorbeeld met een naam: Claude noemt bewust niemand bij naam), en klik op **Opslaan**.

**Alle foto's in één keer:** op de **server** (daar staan je echte foto's):
```bash
cd /var/www/inetjuhhh
php artisan media:describe --dry-run       # hoeveel foto's, en een schatting van de tokens
php artisan media:describe --limit=10      # eerst tien, één voor één: kijk of je de beschrijvingen goed vindt
php artisan media:describe --batch         # de rest, als batch: de helft van de prijs
```
- Een batch verwerkt Anthropic op de achtergrond, meestal binnen een uur, uiterlijk binnen een dag. De planner haalt de beschrijvingen elk kwartier zelf op; je hoeft niets te doen.
- Benieuwd? `php artisan media:describe --collect` laat zien hoeveel er al binnen zijn.
- Foto's die al een beschrijving hebben (ook zelfgeschreven) worden nooit aangeraakt. Lukt een foto niet, dan gaat hij mee met de volgende `--batch`.
- **De kosten** gaan van je **API-tegoed** op console.anthropic.com, niet van je Claude-abonnement. De fotobeschrijvingen gebruiken het kleinere, veel goedkopere model **Claude Haiku**; de tag-suggesties blijven op Opus. Reken de schatting van `--dry-run` om met de prijzen op anthropic.com/pricing (per miljoen tokens, met batch de helft), of meet het echt: draai `--limit=10` en kijk bij *Usage* in de console wat die tien kostten. Er gaat nooit iets vanzelf: alleen als jij op de knop klikt of het commando draait.
- Ander model nodig? Zet in `.env` op de server `ANTHROPIC_ALT_MODEL=…`, en daarna `php artisan optimize`.

---

## 8. De backup buiten Hetzner (Cloudflare R2)

Je andere backups staan allemaal bij Hetzner. Gaat er iets mis met je Hetzner-account, dan ben je die in één keer kwijt. Daarom staat er elke nacht om 04:00 ook een kopie bij **Cloudflare R2**:
- de **database** (de laatste **30 dagen**);
- al je **foto's**. Alleen nieuwe gaan erheen, dus na de eerste nacht duurt het seconden. Een foto die je van de site haalt, blijft in de backup staan.

### Eenmalig instellen (± 15 minuten)

1. **dash.cloudflare.com** → links **R2 Object Storage**. De eerste keer vraagt Cloudflare om een **betaalmethode** (creditcard of PayPal). Tot 10 GB is het gratis; jij gebruikt ongeveer 1 GB.
2. **Create bucket**:
   - **Naam:** `inetjuhhh-backup`
   - **Location:** kies **Europe (EU)**, zodat de gegevens van je lezers binnen de EU blijven (AVG)
   - Laat **Public access** uit: niemand anders mag hierbij.
3. **Manage R2 API Tokens** (rechts op de R2-pagina) → **Create API token**:
   - **Naam:** `inetjuhhh server`
   - **Permissions:** *Object Read & Write*
   - **Specify bucket:** alleen `inetjuhhh-backup`
4. Je krijgt drie dingen te zien. Kopieer ze meteen naar je wachtwoordmanager, want Cloudflare laat ze maar één keer zien:
   - **Access Key ID**
   - **Secret Access Key**
   - **Endpoint**: voor een EU-bucket met `.eu.` erin: `https://1a2b3c….eu.r2.cloudflarestorage.com`. Zonder `.eu.` krijg je *Access Denied*, ook met een goede token.
5. Op de server: `nano /var/www/inetjuhhh/.env` en voeg toe:
   ```env
   OFFSITE_ACCESS_KEY_ID=de-access-key-id
   OFFSITE_SECRET_ACCESS_KEY=de-secret-access-key
   OFFSITE_BUCKET=inetjuhhh-backup
   OFFSITE_ENDPOINT=https://1a2b3c….eu.r2.cloudflarestorage.com
   OFFSITE_REGION=auto
   ```
   Daarna `php artisan optimize`.
6. **De eerste keer zelf starten** (dan zie je meteen of het werkt; de foto's duren nu even, zo'n 850 MB):
   ```bash
   cd /var/www/inetjuhhh && php artisan site:offsite-backup
   ```
   Je ziet *Database: gekopieerd* en *Foto's: … nieuw gekopieerd*. In het R2-dashboard staan nu de mappen `database` en `photos` in je bucket.

Gaat het 's nachts ooit mis, dan krijg je een **foutmail** (hoofdstuk 2).

### Is de backup in orde? (± 5 minuten)

Niet nodig zolang er geen foutmail komt, maar handig na een grote wijziging of als je twijfelt:

1. **Staan alle taken gepland?**
   ```bash
   cd /var/www/inetjuhhh && php artisan schedule:list
   ```
   Je ziet onder andere `site:backup` (03:30) en `site:offsite-backup` (04:00).
2. **Loopt de klok die ze start?**
   ```bash
   crontab -l
   ```
   (Een kleine **L**, van *list*. Niet `-r`: dat wist alles.) Je ziet een regel met `* * * * *` en `schedule:run`.
3. **Ging de afgelopen nacht goed?**
   ```bash
   grep "production.ERROR" storage/logs/laravel-$(date +%F).log | cut -c1-200
   ```
   Niets te zien = niets mis. In het R2-dashboard staat in de map `database` een bestand van vandaag.

**Zie je toch een fout?** De regel *Scheduled command … failed with exit code [1]* zegt alleen dát het misging. Draai het commando dan zelf (`php artisan site:offsite-backup`): dan zie je in één zin waaróm.

### Terughalen

**Foto's die op de server ontbreken:**
```bash
cd /var/www/inetjuhhh && php artisan site:offsite-backup --restore-photos
```
Dit haalt alleen foto's terug die er **niet** meer zijn. Foto's die er nog wel zijn, worden nooit overschreven.

**De database van een bepaalde dag:**
1. In het R2-dashboard: je bucket → map `database` → klik op het bestand van de dag die je wilt → **Download**.
2. Zet het op de server (vanaf je laptop): `scp database-2026-10-18.sql.gz ine@<IP>:/var/www/inetjuhhh/storage/app/backups/`
3. Wil je **één blog** terug? Dan volg je hoofdstuk 6, met dit bestand. Wil je de **hele database** terug? Doe dat alleen in een echte noodsituatie, want alles van daarna is dan weg:
   ```bash
   cd /var/www/inetjuhhh
   php artisan down
   gunzip -c storage/app/backups/database-2026-10-18.sql.gz | mysql -u inetjuhhh -p inetjuhhh
   php artisan cache:clear && php artisan up
   ```
   (Het databasewachtwoord staat in je wachtwoordmanager. `down` laat bezoekers even een onderhoudspagina zien.)

---

## 9. Automatische updates, ook voor PHP (± 15 minuten, eenmalig)

**Hoe het nu zit:** Ubuntu installeert zijn eigen beveiligingsupdates al vanzelf (*unattended-upgrades*). Maar PHP komt niet van Ubuntu: het komt van **Ondřej Surý** (de "PPA" `ppa:ondrej/php`), want Ubuntu 24.04 heeft zelf geen PHP 8.4. Updates van Ondřej worden **niet** automatisch geïnstalleerd. Daardoor moest je bij het PHP-lek van 21 oktober 2026 zelf wachten en updaten.

**Wat we doen:** we zeggen tegen *unattended-upgrades* dat hij Ondřej's pakketten ook mag bijwerken. Zijn pakketten zijn al vertrouwd: bij het installeren (SERVERSTEPS stap 4) is zijn digitale handtekening toegevoegd, en `apt` controleert die bij elke download. Er mist alleen de toestemming om ze **zonder jou** te installeren.

**Is dat veilig?** Ja:
- Alleen **8.4.x** wordt bijgewerkt (bijvoorbeeld 8.4.26 → 8.4.27). Zulke updates repareren alleen fouten en lekken. PHP 8.5 is een apart pakket en komt nooit vanzelf.
- Ondřej's pakketten worden door een groot deel van alle Ubuntu-servers met PHP gebruikt.
- Gaat er toch iets mis, dan krijg je een mail van UptimeRobot (site plat) of een foutmail van de site.

### Stap 1. Kijk of automatische updates aan staan
```bash
systemctl status unattended-upgrades --no-pager | head -3
cat /etc/apt/apt.conf.d/20auto-upgrades
```
Je ziet **active (running)**, en twee regels die eindigen op `"1";`. Staat er `"0"` of mist het bestand: `sudo dpkg-reconfigure -plow unattended-upgrades` en kies **Yes**.

### Stap 2. Zoek de naam van Ondřej's bron op
```bash
apt-cache policy | grep -o "o=LP-PPA-ondrej-php,a=[a-z]*" | sort -u
```
Je ziet `o=LP-PPA-ondrej-php,a=noble`. Het deel na `o=` is de naam die we in stap 3 gebruiken. Zie je niets? Stuur Claude de uitvoer van `apt-cache policy | grep -i ondrej`.

### Stap 3. Geef toestemming (en kies wanneer de server mag herstarten)
```bash
sudo nano /etc/apt/apt.conf.d/52unattended-upgrades-ondrej
```
Plak dit erin:
```
// Also install updates from Ondřej Surý's PHP packages by themselves
Unattended-Upgrade::Origins-Pattern {
    "origin=LP-PPA-ondrej-php,codename=${distro_codename}";
};

// Restart the server by itself when an update needs it (a new Linux kernel), at 05:00:
// after the backups of 03:30 and 04:00. The site is offline for about a minute then.
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "05:00";
```
Opslaan: Ctrl+O, Enter, Ctrl+X.

We maken een **eigen bestand** in plaats van Ubuntu's `50unattended-upgrades` te veranderen. Zo blijft jouw aanpassing staan als Ubuntu dat bestand ooit vervangt.

### Stap 4. Proefdraaien (verandert niets)
```bash
sudo unattended-upgrade --dry-run --debug 2>&1 | grep -iE "allowed origins|ondrej|php8.4" | head -20
```
Bij *Allowed origins* staat nu ook `origin=LP-PPA-ondrej-php`. Staat er een PHP-update klaar, dan zie je ook regels met `php8.4-…`.

### Stap 5. Klaar, en wat er nu vanzelf gebeurt
- **Elke dag** kijkt de server of er updates zijn (Ubuntu én Ondřej), en installeert ze.
- **PHP herstart zichzelf** na een update, dus de website draait meteen op de nieuwe versie. De queue worker (mails) start uiterlijk binnen een uur opnieuw (hij herstart elk uur, zie SERVERSTEPS stap 9). Wil je dat meteen: `php artisan queue:restart`.
- **Herstarten** gebeurt alleen als het moet, om 05:00.

**Nakijken wat er geïnstalleerd is:**
```bash
tail -20 /var/log/unattended-upgrades/unattended-upgrades.log
grep -A3 "php8.4" /var/log/apt/history.log | tail -20
php -v
```

**Een update meteen draaien in plaats van wachten** (bijvoorbeeld bij een nieuw lek): `sudo unattended-upgrade -v`. Of met de hand, zoals altijd: `sudo apt update && sudo apt upgrade`.
