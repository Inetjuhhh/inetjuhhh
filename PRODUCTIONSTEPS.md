# Going live: your steps

The new blog goes from your laptop to the server of your host (Cloudways). **He** sets up the server with [HOSTING.md](HOSTING.md); **you** prepare the content, send it, check it, and later connect inetjuhhh.nl yourself.

**How it works:** the code is already on his server via GitHub. Everything else (blogs, photos, comments, trips, subjects, the Ik & wij page) is in your local database and in `storage/app/public`. You pack those two into files, he puts them on the server. No WordPress import happens on the server.

| Step | Who | What | Time |
|---|---|---|---|
| 1 | you | Clean up test content | 10 min |
| 2 | you | Make the export | 1 min |
| 3 | you | Gather the secret settings | 30–60 min (mail provider takes longest) |
| 4 | you | Send everything | 15 min |
| 5 | host | Server setup (HOSTING.md) | his part |
| 6 | you | Check on the temporary address | 30 min |
| 7 | you | Connect inetjuhhh.nl | 30 min + waiting for DNS |
| 8 | you | Afterwards | spread over weeks |

**The most important rule:** from the moment you make the export (step 2), **edit only on the server**. Changes you make locally after that are not sent along, and making a new export later would overwrite what you did on the server.

---

## 1. Clean up test content

Things that exist only for testing should not go online:

- **Admin → Blogs** (filter "Concept" and "Gepubliceerd"): delete the three blogs that start with **"Test:"** (Costa Rica).
- **Admin → Reizen**: delete **Costa Rica 2027** (the test trip; its description starts with "TESTREIS"). Planning a real trip? Add it again on the server later.
- **The test account** `test@example.com`, from the Breeze starter kit:
  ```bash
  php artisan tinker --execute "App\Models\User::where('email', 'test@example.com')->delete();"
  ```
- Glance through **Admin → Abonnees** and **Reacties** for anything you made yourself while testing.

You don't have to clean up the drafts: they stay invisible to readers, and you can finish them online.

## 2. Make the export

```bash
php artisan site:export
```

What happens:
- It first checks for test content (step 1). Finds some? It stops and lists it. Nothing has been exported then.
- **Database** → `storage/app/export/inetjuhhh-database.sql.gz` (~1.6 MB). Every table, with all rows except things that only matter on your laptop: login sessions, the cache and queued jobs (these go along empty).
- **Photos** → `storage/app/export/inetjuhhh-photos.tar` (~820 MB). Every photo in the media library, in every size.
- At the end a table with the totals. Write them down; you check them in step 6. Locally it was about 274 blogs (254 published), 4,076 photos, 340 comments, 4,053 old links.

Both files are ignored by git, so they never end up on GitHub. After sending them (step 4) you can delete the `export` folder.

## 3. Gather the secret settings

He needs these for the server's `.env`. Never put them in a file in the project (see the API key mishap of 17 October).

| Setting | Where you get it |
|---|---|
| `ADMIN_EMAILS` | Your admin login: **inetjuhhh@gmail.com**. Only this address gets into `/admin` on the server, and the "new comment" mails go here too. More addresses: separate them with a comma. |
| `MAIL_…` (host, username, password, from address) | Your mail provider. Choose one and set the DNS records (SPF/DKIM) for inetjuhhh.nl: **EMAILSTEPS.md steps 1 and 2**. You manage the domain, so this one is yours. |
| `ANTHROPIC_API_KEY` | console.anthropic.com → API keys (the one in your local `.env`, or a new one). Optional: without it the "Stel voor" button explains it is off. |
| `TURNSTILE_SITE_KEY` + `TURNSTILE_SECRET_KEY` | Cloudflare dashboard → *Turnstile* → *Add widget*, mode *Managed*. Hostnames: `inetjuhhh.nl` **and the temporary Cloudways address** (ask him for it). Free. Without them the robot check is off and only the lighter spam checks run, so you want these. |

He makes the database password and the app key himself.

## 4. Send everything

- **The two files** from step 2. The photos file is too big for email: use WeTransfer or a shared Google Drive/OneDrive folder.
- **The secrets** from step 3, **separately** from the files: a password manager share, or a message that disappears. Not in the same email as the download link.
- **HOSTING.md**: it's in the GitHub repository he already has. Point him to it.

## 5. The host's part

He follows [HOSTING.md](HOSTING.md): server settings, the code, `.env`, loading the database and the photos, the queue worker and scheduler for mails, and a few checks. When he's done, he gives you the temporary address.

What you need to know about it:
- **Varnish** (Cloudways' page cache) is switched off for your site. Otherwise logged-in pages, comment forms and hearts can show someone else's or old pages. The app has its own cache.
- The **queue worker** sends the mails (new blog, comments, confirmations) in the background; the **scheduler** sends "new story" mails for planned blogs and removes old spam every night.
- He **never** runs the WordPress import or `db:seed`: that would overwrite your content.

## 6. Check on the temporary address

- [ ] Log in at `/admin` with inetjuhhh@gmail.com and your local password (the database came along, so your password did too)
- [ ] **Admin → Blogs**: the counts from step 2 (filter on Gepubliceerd and Concept)
- [ ] Open a few blogs from different years: text, photos, galleries, featured image, the place on the photo
- [ ] **Reizen**: the timeline and trip cards; **Kaart**: pins in the right countries
- [ ] **Lifestyle**: the subject bubbles; **Ik & wij**: the text with three photos
- [ ] Search for a word from an old blog
- [ ] Upload a photo in a blog (checks the server's upload limits), and save
- [ ] **Stel voor** next to Tags gives suggestions (if you sent the Anthropic key)
- [ ] Old links, with the temporary address in front:
  - `/2019/01/12/jutten-of-jatten/` → the blog
  - `/wp-content/uploads/2019/01/Foto-04-01-19-20-43-16-768x1024.jpg` → the photo
  - `/category/thailand/` → the Thailand page
- [ ] Mail: sign up for the newsletter with your own address. Is there a robot check, does the confirmation mail arrive, and does the link work? Then post a comment in a private window: you get a "new comment" mail
- [ ] mail-tester.com: aim for 9/10 or higher (EMAILSTEPS.md step 7)

Something wrong? Tell him which item; most fixes are on his side (settings) and take minutes.

**From here on, write and edit on the server.** Search engines don't know the temporary address, so readers won't find it yet.

## 7. Connect inetjuhhh.nl

You do this yourself, when you're happy with step 6.

### 7.1 A final backup of WordPress
A **full backup** of the old site, files and database (your old host's backup function, or a plugin like UpdraftPlus). Keep it somewhere safe, on an external drive *and* in the cloud.

### 7.2 Comments placed on WordPress since your import
Your local import was made from a WordPress export from 13 October. Comments placed on WordPress after that didn't come along. Copy the few that matter by hand (Admin → Reacties), or let them go.

### 7.3 Point the domain to the server
- In **Cloudways** (or ask him): add `inetjuhhh.nl` and `www.inetjuhhh.nl` as the domain of the app, then request the free **Let's Encrypt** certificate for both.
- At your **domain host**: change the DNS records of `inetjuhhh.nl` and `www` to the Cloudways server IP (he can tell you which). It can take a few hours before everyone sees the new site, usually much less.
- Let's Encrypt only works once DNS points to the server; if the request fails, try again an hour later.

### 7.4 The new address in the app
On the server, in `.env`:
```env
APP_URL=https://inetjuhhh.nl
```
then:
```bash
php artisan optimize
php artisan queue:restart
```
No SSH access? Ask him; it takes him a minute. Without this step, links in mails keep pointing to the temporary address.

### 7.5 Check again
The old links from step 6, now on `https://inetjuhhh.nl/…`. Also try `www.inetjuhhh.nl`: it should end up at the same site. Publish a blog (or a test draft you publish and take back) and check that the mail links to inetjuhhh.nl.

---

## 8. Afterwards

- **Keep the old hosting for a few weeks**, unused, in case something turns out to be missing. Only then cancel it.
- **Turnstile:** remove the temporary address from the widget's hostnames.
- **Google Search Console** (if you use it): the domain stays the same, so nothing has to move. Under "Pages" you can see whether Google follows the old links to the new pages.
- **After a few weeks**, the import code can be removed from the project (`app/Console/Commands/ImportWordPress.php` and `app/Support/WordPress`). **Keep the old links** (`LegacyLinkController`, `app/Support/LegacyLinks`, `config/legacy-links.php`, the routes and the `legacy_links` table): old links keep coming in for years.
- **New code** (after you commit and push): he runs the deploy steps from HOSTING.md. Your content on the server stays as it is.

## When something goes wrong

| Problem | What to do |
|---|---|
| `site:export` stops with "Still in the database" | Step 1, then run it again |
| `site:export` can't find mysqldump | Laragon not in `C:\laragon`? Add `MYSQLDUMP_PATH=` with the full path to `mysqldump.exe` to your local `.env` |
| Can't log in at `/admin` on the server | `ADMIN_EMAILS` must contain the address you log in with |
| Photos missing on the server, text is there | The photos file wasn't unpacked into `storage/app/public`, or `php artisan storage:link` is missing (his side) |
| Old photo links give a 404 | nginx answers image requests itself (his side, HOSTING.md → Check) |
| Mails don't arrive | Queue worker not running, or mail settings (EMAILSTEPS.md "When something goes wrong") |
| Links in mails point to the temporary address | Step 7.4 |
| You see an old version of a page when logged out | Varnish is on (his side) |
