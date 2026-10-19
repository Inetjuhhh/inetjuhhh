<p align="center"><img src="ine.jpg" alt="ine-avatar" width="50px" class="rounded-xl"></p>

# Inetjuhhh

After all these years of no priority I decided to make my own blog instead of using the easy WordPress template. Travel stories with Bas and Sam, family and lifestyle, on inetjuhhh.nl. A Laravel site with a Filament admin panel, moved over from WordPress with all its stories, photos, comments and old links.

## What's in it
- **Blogs** with a block editor: photos, galleries (mixed portrait/landscape rows), tips and maps
- **Reizen**: a timeline of trips, a countdown for the next trip and its planned route on the map
- **Kaart**: every story on a travel map
- **Lifestyle**: stories by subject, as bubbles
- **Comments and hearts** without an account, with approval and spam protection (Cloudflare Turnstile)
- **A mail for every new blog**, with confirmation and one-click unsubscribe
- **Admin**: photo library (Curator) with duplicate check, AI tag suggestions (Claude), drafts and planned posts
- **Old WordPress links** (posts, photos, categories) keep working via redirects

## Documentation
| File | For |
|---|---|
| [PRODUCTIONSTEPS.md](PRODUCTIONSTEPS.md) | Going live: the whole route, from export to switching inetjuhhh.nl over |
| [SERVERSTEPS.md](SERVERSTEPS.md) | My own server at Hetzner: setting it up, updates (`deploy.sh`), backups |
| [UPDATESTEPS.md](UPDATESTEPS.md) | Elke update van laptop naar server, en wat te doen als de server moeilijk doet (ook vanaf je telefoon) |
| [EMAILSTEPS.md](EMAILSTEPS.md) | Mail in production with Brevo: account, DNS, settings, testing |

## 🔰 Getting started (local)

### 1. Prerequisites
- [Laragon](https://laragon.org) with **PHP 8.4** (the minimum: the packages need it) and **MySQL 8**
- [Composer](https://getcomposer.org/) and **Node 20+**
- [Git for Windows](https://gitforwindows.org/); the commands below assume Git Bash

### 2. Code and dependencies
```bash
git clone https://github.com/Inetjuhhh/inetjuhhh.git
cd inetjuhhh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 3. `.env`
- **Database**: `DB_DATABASE=inetjuhhh`, `DB_USERNAME=root`, `DB_PASSWORD=` (Laragon's defaults); create the `inetjuhhh` database first
- **Mail** goes to Mailpit: `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`. Read the mails at http://localhost:8025
- **Robot check**: Cloudflare's test keys, which always pass:
  ```env
  TURNSTILE_SITE_KEY=1x00000000000000000000AA
  TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
  ```
- **AI tag suggestions** (optional): `ANTHROPIC_API_KEY=` from console.anthropic.com. Only ever in `.env`, never in a committed file

### 4. Content
Either example content:
```bash
php artisan migrate --seed
```


### 5. Run it
- The site: http://inetjuhhh.test (Laragon) or `php artisan serve`
- Styles and scripts: `npm run build`, or `npm run dev` while working on them. **Stop `npm run dev` when you're done**: as long as `public/hot` exists, every page waits for it (that made the admin slow once)
- Mails, newsletter and planned posts need the queue worker and the scheduler. `C:\laragon\usr\Procfile` starts them with Laragon (plus Mailpit). By hand: `php artisan queue:work` and `php artisan schedule:work`

### 6. Tests
```bash
php artisan test
```

## Handy commands
| Command | What it does |
|---|---|
| `php artisan site:export` | Database + photos as two files for the production server |
| `php artisan blogs:subjects` | Subjects (bubbles) per story from `config/subjects.php` |
| `php artisan media:fingerprint` | Fingerprints for the duplicate-photo check |
| `php artisan media:describe --dry-run` / `--limit=10` / `--batch` | Claude writes the photo descriptions (alt text) of photos without one; `--batch` does all of them at half the price, the scheduler collects the results. One photo: "Beschrijf met AI" in the media library |
| `php artisan site:test-alert` | On the server: a pretend error, to check that error mails ("Foutje op …") arrive |
| `php artisan site:backup` | A copy of the database in `storage/app/backups` (the scheduler makes one every night, kept 14 days) |
| `php artisan blogs:check-planned` | Refreshes the menu and map when a planned blog goes online (the scheduler, every 5 minutes). Subscribers are mailed with the button **Mail abonnees** in the admin |
| `php artisan wordpress:import` / `wordpress:import-about` / `wordpress:keep-places` | The WordPress move; not needed after going live |

## 🔧 Technologies and packages
- PHP 8.4, MySQL 8
- Laravel 13, Filament 5 (admin), Livewire, Curator (photo library)
- Vite 6, Tailwind 4, Alpine.js 3
- Leaflet (maps), Font Awesome 5
- Pest (tests)
- Cloudflare Turnstile (spam), Anthropic Claude (tag suggestions)

## 😵‍💫 Issues
In my repository you can see what I'm working on. Feel free to add an issue if you think there's a feature you wouldn't want to miss.

## 🤗 Contributing
Want to contribute or have a suggestion? Feel free to contact me.
