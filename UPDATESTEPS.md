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
- [ ] Een gratis **uptime-monitor** die je mailt als de site plat ligt, bijvoorbeeld **UptimeRobot** (uptimerobot.com): monitor-type *HTTP(s)*, adres `https://inetjuhhh.nl/up`, controle elke 5 minuten. Dan weet je het vóór je lezers het merken.
- [ ] Controleer in de Hetzner Console dat er **Backups** van de afgelopen dagen staan.

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
