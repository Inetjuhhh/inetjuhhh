# Going live: step by step

From the old WordPress site to the new blog on inetjuhhh.nl. Locally everything has been rehearsed; on the server you follow the same steps.

**The most important thing to know:** the import downloads the photos from the WordPress site at inetjuhhh.nl. So WordPress has to stay online at that address **until the import is finished and checked**. That's why the new site first runs on a temporary address (in these steps: `nieuw.inetjuhhh.nl`), and only at the very end does inetjuhhh.nl switch over.

| Part | What | Time |
|---|---|---|
| A | Server and site on the temporary address | ~1 hour |
| B | The import (blogs, photos, comments) | ~3 hours, mostly waiting |
| C | Checking | ~30 min |
| D | Switching inetjuhhh.nl over | ~30 min + waiting for DNS |
| E | Afterwards | a few minutes, spread over weeks |

---

## A. Server and site on the temporary address

### A1. What the server needs
- **PHP 8.2 or newer**, with the extensions Laravel needs, plus:
  - `gd` **with WebP and JPEG support** (shrinking photos); check with `php -r "print_r(gd_info());"`
  - `exif` (photos the right way up)
- **MySQL 8** (or MariaDB 10.6+)
- **Composer** and **Node.js** (to build the styles)
- **SSH access**: the photo import takes hours and has to run on the server itself
- **Disk space:** about **2 GB** free (0.85 GB of photos, plus room for the cache and backups)

### A2. The temporary address
At your domain host, add a DNS record for `nieuw.inetjuhhh.nl` pointing to the new server, and give it an SSL certificate (Let's Encrypt, usually one click in Forge/Ploi or your host's panel).

### A3. Put the code on the server
```bash
git clone <your repository> /var/www/inetjuhhh
cd /var/www/inetjuhhh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

### A4. Settings in `.env`
```env
APP_NAME="Inetjuhhh - Blogs"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nieuw.inetjuhhh.nl     # for now; becomes https://inetjuhhh.nl in step D
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=inetjuhhh
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_EMAILS=ine@inetjuhhh.nl
```
Plus the mail settings from **EMAILSTEPS.md** (step 3 there). Remember: a value with spaces needs "quotes".

### A5. Database and storage
```bash
php artisan migrate --force
php artisan storage:link
```
**Do not run `db:seed`**: that would put the example blogs back.

### A6. Your admin account
```bash
php artisan make:filament-user
```
Use **ine@inetjuhhh.nl** (the address in `ADMIN_EMAILS`). The blogs will be placed under this account.

### A7. The web server: let old photo links through to Laravel
Old photo links (`/wp-content/uploads/…jpg`) are sent on to the new photos by the app. Some nginx setups answer image requests themselves with a 404, so the app never sees them. In nginx, the site should **not** have a separate block like `location ~* \.(jpg|png)$ { ... }` that ends in `=404`; the usual Laravel block is enough:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
(With Apache, Laravel's own `.htaccess` already does this.)

### A8. Queue worker and scheduler
Set up as described in **EMAILSTEPS.md** (steps 4 and 5). Needed for the newsletter, the comment mails and the nightly cleanup.

### A9. Speed
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```
Run these again after every change to `.env` or every new version of the code.

**Check:** `https://nieuw.inetjuhhh.nl` shows an empty blog, and you can log in at `/admin`.

---

## B. The import

### B1. Put the export file on the server
Download a **fresh** export from WordPress (Tools → Export → All content), so posts and comments since the last export come along too. Upload it, **not** in a public folder:
```bash
# from your laptop
scp inetjuhhh.WordPress.*.xml user@server:/var/www/inetjuhhh/storage/app/wordpress/
```
(If the folder doesn't exist yet: `mkdir -p storage/app/wordpress` on the server.)

### B2. Work in a session that survives a dropped connection
The import runs for hours. If your SSH connection drops, an ordinary command stops. So start a `screen` session first:
```bash
screen -S import
cd /var/www/inetjuhhh
```
Connection lost? Log in again and type `screen -r import` to go back.

### B3. Test run
```bash
php -d memory_limit=1G artisan wordpress:import --dry-run --author=ine@inetjuhhh.nl
```
The first time takes a few minutes: about 150 places are looked up on the map (one per second). Check:
- **271 blogs** (254 published, 17 drafts); more if you wrote new ones in WordPress since 13 October
- **22 trips**, with India & Nepal 2016 and Thailand & Cambodja 2017 as one trip each
- **379 comments**
- "places to check": ideally none

### B4. The real import, with photos
```bash
php -d memory_limit=1G artisan wordpress:import --photos --author=ine@inetjuhhh.nl
```
- ~2.5 hours (locally it was 1.4 seconds per photo; a server is often faster)
- **Stopped halfway?** Run the same command again. Photos already done are skipped.
- At the end you get the totals table; locally that was: 4,022 photos, 2,405 MB downloaded, 808 MB stored, 66% smaller.

Leave the session with `Ctrl+A`, then `D`; the import keeps running.

### B5. What the import leaves behind, and must stay
In `storage/app/wordpress/`:
- **`redirects.json`**: makes the old links work. **Needed for years**: include it in your backups.
- `media-map.json`: which photo became which (needed if you ever run the import again)
- `places.csv`: every blog's place, to check in a spreadsheet

---

## C. Checking (on nieuw.inetjuhhh.nl)

- [ ] Open a few blogs from different years: text, photos, galleries, featured image, the place on the photo ("India – Varanasi")
- [ ] The travel map shows pins in the right countries
- [ ] Menu: **Reizen** and **Citytrips** contain the right trips
- [ ] Admin → **Reacties** → tab "Wacht op goedkeuring": the 39 old comments that were waiting. Approve or delete them
- [ ] Admin → **Blogs**: the 17 drafts are there (read them via the eye icon)
- [ ] Search for a word from an old blog
- [ ] Old links, by putting the temporary address in front:
  - `https://nieuw.inetjuhhh.nl/2019/01/12/jutten-of-jatten/` → the blog
  - `https://nieuw.inetjuhhh.nl/wp-content/uploads/2019/01/Foto-04-01-19-20-43-16-768x1024.jpg` → the photo (if not: step A7)
  - `https://nieuw.inetjuhhh.nl/category/thailand/` → the Thailand page

**Found a wrong place?** Correct it in the admin (place name and/or drag the pin), then save it so it's never lost:
```bash
php artisan wordpress:keep-places
```
That writes `database/wordpress/place-corrections.json`. Copy it to your laptop and commit it, so your corrections are in the project too. (Corrections made locally first work the same way: commit the file before deploying.)

**From here on, don't run the import again on production.** You're free to edit blogs in the admin now; a new import run would overwrite those edits.

---

## D. Switching inetjuhhh.nl over

### D1. A final backup of WordPress
Before anything changes: a **full backup** of the old site, **files and database** (your host's backup function, or a plugin like UpdraftPlus). Keep it somewhere safe, for example on an external drive *and* in the cloud.

### D2. Comments placed in the meantime
Comments placed on WordPress after your export didn't come along. Usually it's easiest to copy the few that matter by hand, or to let them go.

Running the import again with a fresh export also works, but **first** run `php artisan wordpress:keep-places` (otherwise corrected places are lost) and know that **other edits you made in the admin to imported blogs** (texts, titles) are overwritten. So only do this before you start editing blogs.

### D3. Point the domain to the new server
At your domain host, change the DNS records for `inetjuhhh.nl` and `www.inetjuhhh.nl` to the new server. It can take a few hours before everyone sees the new site (usually much faster).

### D4. The new address in the app
In `.env`:
```env
APP_URL=https://inetjuhhh.nl
```
then:
```bash
php artisan config:cache
php artisan queue:restart
```
And give `inetjuhhh.nl` an SSL certificate, like in step A2.

### D5. Check again
The same old links as in step C, now on `https://inetjuhhh.nl/…`. Also try `www.inetjuhhh.nl`: it should end up at the same site.

---

## E. Afterwards

- **Keep the old hosting for a few weeks**, unused, in case something turns out to be missing. Only then cancel it.
- **Google Search Console** (if you use it): the domain stays the same, so nothing has to move. Under "Pages" you can see whether Google follows the old links to the new pages.
- **After a few weeks**, the import code can be removed from the project (the command and `app/Support/WordPress`, except `WordPressUploads.php`). **Keep the redirects** (`WordPressRedirectController`, `WordPressUploads.php`, the routes, `redirects.json`): old links keep coming in for years.
- **Registration:** the site still has a public "register" page from the starter kit. Anyone can create an account, but only the addresses in `ADMIN_EMAILS` get into the admin. If you don't need accounts for readers, it can be switched off.

## When something goes wrong

| Problem | What to do |
|---|---|
| Import stops halfway | Run the same command again; it carries on |
| "Failed" photos in the totals table | `storage/logs/laravel.log` says which; run the command again to retry them |
| Old photo links give a 404 | Web server setting, step A7 |
| Old blog link gives a 404 | Does `storage/app/wordpress/redirects.json` exist? It's written at the end of a full import (not with `--only`) |
| Links in mails point to nieuw.inetjuhhh.nl | `APP_URL` in step D4, then `php artisan config:cache` |
| Out of memory during the import | The `-d memory_limit=1G` part of the command is missing |
