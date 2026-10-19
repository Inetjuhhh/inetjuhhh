# Going live: the whole route

The new blog moves from your laptop to your own server at Hetzner. First it runs on a temporary address, **nieuw.inetjuhhh.nl**, while WordPress stays on inetjuhhh.nl. Only when everything is right does inetjuhhh.nl switch over.

**How it works:** the code comes from GitHub. Everything else (blogs, photos, comments, trips, subjects, the Ik & wij page) is in your local database and in `storage/app/public`. You pack those into two files with `site:export` and put them on the server. No WordPress import happens on the server.

| Step | What | Where it's explained | Time |
|---|---|---|---|
| 1 | Clean up test content | here | 10 min |
| 2 | Accounts and keys: Brevo, Cloudflare Turnstile, Anthropic | here + EMAILSTEPS.md 1–3 | 1 hour (+ waiting for DNS) |
| 3 | The server, with your content on it | **SERVERSTEPS.md** | ~2 hours |
| 4 | Mail settings on the server, and testing mail | EMAILSTEPS.md 4–6 | 20 min |
| 5 | Check everything on nieuw.inetjuhhh.nl | here | 30 min |
| 6 | Switch inetjuhhh.nl over | here | 30 min + waiting for DNS |
| 7 | Afterwards | here | spread over weeks |

**The most important rule:** from the moment you make the export (SERVERSTEPS.md step 7), **edit only on the server**. Changes you make locally after that don't go along, and a new export later would overwrite what you did on the server.

**Secrets** (keys, passwords) only ever go in the `.env` on the server, never in a file in the project. GitHub is public: anything committed can be read by anyone.

---

## 1. Clean up test content

Things that exist only for testing should not go online. Already done on 17 October: the Costa Rica test trip with its three "Test:" blogs, and the `test@example.com` account. Your only account is **inetjuhhh@gmail.com**.

Before exporting, still:
- Glance through **Admin → Abonnees** and **Reacties** for anything you made yourself while testing.
- Made new test content since? `site:export` warns about trips with "TESTREIS" in the description, blogs whose title starts with "Test:" and `@example.` accounts, and stops until they're gone.

You don't have to clean up the drafts: they stay invisible to readers, and you can finish them online.

## 2. Accounts and keys

Get these ready before you start on the server; you fill them in at SERVERSTEPS.md step 6.

| Setting | Where you get it |
|---|---|
| `MAIL_…` | **Brevo**: EMAILSTEPS.md steps 1–3 (account, DNS records for inetjuhhh.nl, SMTP key). Start this one first: DNS and Brevo's account check can take a while. |
| `TURNSTILE_SITE_KEY` + `TURNSTILE_SECRET_KEY` | **Cloudflare**, see 2.1 below |
| `ANTHROPIC_API_KEY` | console.anthropic.com → API keys (the one in your local `.env`, or a new one). Optional: without it the "Stel voor" button explains it is off. |
| `ADMIN_EMAILS` | **inetjuhhh@gmail.com**: only this address gets into `/admin`, and the "new comment" mails go here. |

### 2.1 The Cloudflare Turnstile widget (spam protection)

Turnstile is the "I'm not a robot" check under the comment form and the newsletter form. Most readers see only a small green tick; only suspicious visitors get a puzzle. It's free, and inetjuhhh.nl does **not** have to move to Cloudflare: your domain and DNS stay where they are.

1. Go to **dash.cloudflare.com** and sign up (free plan) or log in. Cloudflare asks you to add a website: skip it, Turnstile doesn't need it.
2. In the menu on the left: **Turnstile** (in some versions under *Application security* or *Protect & connect*). Click **Add widget**.
3. Fill in:
   - **Widget name:** `Inetjuhhh blog`
   - **Hostnames:** `inetjuhhh.nl`. That also covers `www.inetjuhhh.nl` and the temporary `nieuw.inetjuhhh.nl`.
   - **Widget mode:** **Managed** (Cloudflare decides when a puzzle is needed)
   - **Pre-clearance:** **No**
4. Click **Create**. You see two keys:
   - **Site key**: public, it's visible in the page source. → `TURNSTILE_SITE_KEY`
   - **Secret key**: treat it like a password. → `TURNSTILE_SECRET_KEY`

What is different from your laptop:
- **Locally** you keep Cloudflare's **test keys** (in your local `.env`): they always pass and show "Alleen voor testen". Don't put `inetjuhhh.test` in the real widget, so a local test can never use up or leak the real keys.
- **On the server** the real keys check every comment and sign-up with Cloudflare. In the widget's page you can see how many checks were solved and how many bots were stopped.

## 3. The server

Follow **[SERVERSTEPS.md](SERVERSTEPS.md)**: creating the server at Hetzner, making it safe, installing the software, the code, `.env`, your content (export and import), the web server with https, the queue worker and scheduler for mails, and a first check.

## 4. Mail

**[EMAILSTEPS.md](EMAILSTEPS.md)** steps 4–6: the Brevo settings in the server's `.env`, and testing that mail arrives, isn't marked as spam, and replies reach your Gmail.

## 5. Check on nieuw.inetjuhhh.nl

- [ ] Log in at `/admin` with inetjuhhh@gmail.com and your local password (the database came along, so your password did too). Forgotten? **Wachtwoord vergeten?** on the login page sends a link (once mail works). Change your password under your name → **Profiel**. Changing your email address there too? Change `ADMIN_EMAILS` in `.env` first, otherwise you lock yourself out
- [ ] The robot check shows under the comment form and in the newsletter form (once opened), with a green tick and **without** "Alleen voor testen" (that text means the test keys ended up on the server)
- [ ] **Admin → Blogs**: the totals that `site:export` showed (filter on Gepubliceerd and Concept)
- [ ] Open a few blogs from different years: text, photos, galleries, featured image, the place on the photo
- [ ] **Reizen**: the timeline and trip cards; **Kaart**: pins in the right countries
- [ ] **Lifestyle**: the subjects; **Ik & wij**: the text with three photos
- [ ] Search for a word from an old blog
- [ ] Upload a photo in a blog (checks the server's upload limits), and save
- [ ] **Stel voor** next to Tags gives suggestions (if you set the Anthropic key)
- [ ] On your phone: a blog, the Kaart, and the admin with its Verhaal/Details tabs
- [ ] Old links, with the temporary address in front:
  - `/2019/01/12/jutten-of-jatten/` → the blog
  - `/wp-content/uploads/2019/01/Foto-04-01-19-20-43-16-768x1024.jpg` → the photo
  - `/category/thailand/` → the Thailand page
- [ ] Mail: EMAILSTEPS.md step 6 (test mail, sign-up, new blog, mail-tester.com, unsubscribe). Also post a comment in a private window: you get a "new comment" mail

**From here on, write and edit on the server.** Search engines don't know the temporary address, so readers won't find it yet.

## 6. Switch inetjuhhh.nl over

When you're happy with step 5.

### 6.1 A final backup of WordPress
A **full backup** of the old site, files and database (your old host's backup function, or a plugin like UpdraftPlus). Keep it somewhere safe, on an external drive *and* in the cloud.

### 6.2 Comments placed on WordPress since your import
Your local import was made from a WordPress export from 13 October. Comments placed on WordPress after that didn't come along. Copy the few that matter by hand (Admin → Reacties), or let them go.

### 6.3 Point the domain to the server
At your domain host, change these records to the Hetzner server (the same IPs as `nieuw` in SERVERSTEPS.md step 2):

| Type | Name | Value |
|---|---|---|
| A | `@` | the IPv4 address |
| AAAA | `@` | the IPv6 address |
| A | `www` | the IPv4 address |
| AAAA | `www` | the IPv6 address |

Leave the Brevo records (EMAILSTEPS.md step 2) and any MX records as they are. Wait until `ping inetjuhhh.nl` shows the new IP (minutes to a few hours).

### 6.4 The server answers on inetjuhhh.nl
```bash
ssh ine@<IP>
sudo sed -i 's/server_name nieuw.inetjuhhh.nl;/server_name inetjuhhh.nl www.inetjuhhh.nl nieuw.inetjuhhh.nl;/' /etc/nginx/sites-available/inetjuhhh
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx --expand -d inetjuhhh.nl -d www.inetjuhhh.nl -d nieuw.inetjuhhh.nl
```
- The `sed` line gives the site its three names. Check with `grep server_name /etc/nginx/sites-available/inetjuhhh`: all three on every line.
- Certbot makes one certificate for all three names. It only works once DNS points to the server (6.3); if it fails, try again an hour later.

### 6.5 The new address in the app
```bash
cd /var/www/inetjuhhh && nano .env
```
```env
APP_URL=https://inetjuhhh.nl
```
```bash
php artisan optimize
php artisan queue:restart
```
From now on, visitors on `www.inetjuhhh.nl` or `nieuw.inetjuhhh.nl` are sent on to the same page on `https://inetjuhhh.nl` (the app does that itself, as soon as `APP_URL` says so). Links in mails point to inetjuhhh.nl.

### 6.6 Check again
The old links from step 5, now on `https://inetjuhhh.nl/…`. `www.inetjuhhh.nl` and `nieuw.inetjuhhh.nl` should end up on inetjuhhh.nl. Log in to `/admin` again (the login is tied to the address). Send yourself a **Testmail naar mij** from a blog in the admin, and check that the mail links to inetjuhhh.nl.

---

## 7. Afterwards

- **Keep the old hosting for a few weeks**, unused, in case something turns out to be missing. Only then cancel it.
- **Google Search Console** (search.google.com/search-console, free): add `inetjuhhh.nl` if it isn't there yet (Google asks for a TXT record at TransIP to prove it's yours). Then **Sitemaps** → `https://inetjuhhh.nl/sitemap.xml` → **Submit**. Google then knows all your pages at once, and the sitemap keeps itself up to date: you never have to submit it again. Under "Pages" you can see whether Google follows the old links to the new pages.
- **Readers with a feed app** (Feedly and the like) who followed your WordPress blog keep getting your new stories: `https://inetjuhhh.nl/feed` (also the old address `/feed/`).
- **New code:** commit and push on your laptop, then on the server `cd /var/www/inetjuhhh && ./deploy.sh` (SERVERSTEPS.md step 12). Your content on the server stays as it is.
- **Backups:** Hetzner makes one every day; now and then an extra copy on your own computer (SERVERSTEPS.md step 13).
- **After a few weeks**, the import code can be removed from the project (`app/Console/Commands/ImportWordPress.php` and `app/Support/WordPress`). **Keep the old links** (`LegacyLinkController`, `app/Support/LegacyLinks`, `config/legacy-links.php`, the routes and the `legacy_links` table): old links keep coming in for years.

## When something goes wrong

| Problem | What to do |
|---|---|
| `site:export` stops with "Still in the database" | Step 1, then run it again |
| `site:export` can't find mysqldump | Laragon not in `C:\laragon`? Add `MYSQLDUMP_PATH=` with the full path to `mysqldump.exe` to your local `.env` |
| Can't log in at `/admin` on the server | `ADMIN_EMAILS` must contain the address you log in with; then `php artisan optimize` |
| Server problems (500, 502, photos missing, uploads failing) | SERVERSTEPS.md "When something goes wrong" |
| Mails don't arrive or land in spam | EMAILSTEPS.md "When something goes wrong" |
| Links in mails point to nieuw.inetjuhhh.nl | Step 6.5 |
| "We konden niet controleren dat je geen robot bent" for everyone | Hostname `inetjuhhh.nl` in the widget (2.1), and site key and secret key from the same widget, not swapped |
| "Alleen voor testen" under the forms on the server | The server has the test keys; the real ones from 2.1 belong there |
| After the switch, the browser warns about the certificate | Step 6.4: certbot for all three names |
