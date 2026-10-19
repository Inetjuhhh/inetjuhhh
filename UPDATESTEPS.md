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
Console → je server → tab **Backups** → kies een dag → **Restore**. Let op: alles wat daarna veranderd is (nieuwe blogs, reacties) is dan weg. Alleen als niets anders helpt.

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
