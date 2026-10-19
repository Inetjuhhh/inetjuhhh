# Your own server at Hetzner: step by step

You set up a small Linux server at Hetzner Cloud that runs the blog. Every command is explained; type them one by one and check that each one finishes without errors before going to the next.

**What will run on it:**

| Part | Does what |
|---|---|
| **Ubuntu 24.04** | The operating system |
| **nginx** | The web server: answers visitors and hands PHP pages to PHP |
| **PHP 8.4** | Runs Laravel (the packages need 8.4, not 8.3) |
| **MySQL 8** | The database |
| **Supervisor** | Keeps the queue worker running (it sends the mails) |
| **cron** | Starts the scheduler every minute (planned blogs, cleanups) |
| **Certbot** | Free SSL certificates (https) from Let's Encrypt, renewed automatically |

**Addresses in these steps:** the site first runs on **`nieuw.inetjuhhh.nl`**, while WordPress stays on inetjuhhh.nl. Your Linux user is called **`ine`**. Where you see `<IP>`, use the server's IP address from step 1.

Time: about 2 hours the first time. You can stop after any step and continue later.

---

## 1. Create the server (Hetzner Cloud Console)

**On your laptop first: an SSH key.** That's how you log in to the server without a password. In Git Bash:
```bash
ssh-keygen -t ed25519 -C "inetjuhhh@gmail.com"
```
Press Enter three times (the default location; a passphrase is optional but safer). Then show the public half, which you give to Hetzner:
```bash
cat ~/.ssh/id_ed25519.pub
```
The other file (`id_ed25519`, without `.pub`) is the private key: it never leaves your laptop.

**At console.hetzner.com:** create a project "Inetjuhhh", then **Add Server**:
- **Location:** Falkenstein or Nuremberg (Germany, EU: fine for the AVG/GDPR)
- **Image:** **Ubuntu 24.04** (not a newer one: these steps are written for 24.04, which gets security updates until 2029)
- **Type:** shared vCPU, **x86** (Intel/AMD), **2 GB RAM or more**. First look under *Cost-Optimized* (often the cheapest, but not always available); otherwise *Regular Performance* **CPX12** (2 GB). The blog itself uses about 1–1.5 GB; with 2 GB, add the swap from step 3 for the short moment the styles are built. 1 GB is too little.
- **Networking:** IPv4 and IPv6 on
- **SSH key:** paste the line from `cat` above
- **Firewalls:** create one called "web" with inbound rules **TCP 22** (SSH), **TCP 80** and **TCP 443** (the website), and **ICMP**. Everything else stays closed.
- **Backups:** **on** (+20% of the price): every day a copy of the whole server, the last 7 kept
- **Name:** `inetjuhhh`

After a minute the server is there. Write down its **IPv4 and IPv6 address**.

Good to know: Hetzner **blocks outgoing mail on ports 25 and 465** for new servers. That's no problem: the blog sends through Brevo on port **587** (EMAILSTEPS.md).

## 2. The temporary address

At your domain host, add:

| Type | Name | Value |
|---|---|---|
| A | `nieuw` | the IPv4 address |
| AAAA | `nieuw` | the IPv6 address |

It takes a few minutes to an hour. Check from Git Bash: `ping nieuw.inetjuhhh.nl` should show the new IP.

## 3. First login and making it safe

```bash
ssh root@<IP>
```
The first time it asks whether you trust the server: type `yes`.

**Updates**, and the time zone:
```bash
apt update && apt upgrade -y
timedatectl set-timezone Europe/Amsterdam
```
(The app itself works in UTC and shows Dutch times; this only makes the server's own logs easier to read.)

**Swap** (with 2 GB of memory): a 2 GB file on the disk that Linux uses when memory is briefly full, for example while the styles are built during an update:
```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab     # also after a restart
free -h                          # the line "Swap" now shows 2.0Gi
```

**Your own user**, so you don't work as `root` (the all-powerful account):
```bash
adduser ine                      # asks for a password: you need it for sudo
usermod -aG sudo ine             # may run commands as administrator with "sudo"
rsync --archive --chown=ine:ine ~/.ssh /home/ine   # your SSH key works for ine too
```
**Open a second Git Bash window** and check that this works, **before** the next step:
```bash
ssh ine@<IP>
sudo whoami                      # should answer: root
```

**Close the door for root and passwords** (only your key gets in). Back in the root window:
```bash
cat > /etc/ssh/sshd_config.d/99-inetjuhhh.conf <<'EOF'
PermitRootLogin no
PasswordAuthentication no
EOF
systemctl restart ssh
```

**Firewall on the server itself** (a second lock behind Hetzner's), and **fail2ban** (blocks addresses that keep guessing):
```bash
apt install -y ufw fail2ban
ufw allow OpenSSH
ufw allow 'Nginx Full' 2>/dev/null || ufw allow 80,443/tcp
ufw --force enable
```

Security updates install themselves (Ubuntu's *unattended-upgrades* is on by default). Check: `systemctl status unattended-upgrades` says *active*.

From here on, log in as **`ine`**: `ssh ine@<IP>`. Log out of root with `exit`.

## 4. Install the software

```bash
sudo add-apt-repository -y ppa:ondrej/php     # PHP 8.4 (Ubuntu 24.04 itself only has 8.3)
sudo apt update
sudo apt install -y nginx mysql-server supervisor git unzip certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-gd php8.4-intl php8.4-zip php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-bcmath
```
Check PHP has what the photos need (`true`, `true` and `exif`):
```bash
php -r 'var_dump(gd_info()["WebP Support"], gd_info()["JPEG Support"]);'; php -m | grep exif
```

**Composer** (installs Laravel's packages):
```bash
cd ~ && curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
composer --version
```

**Node 22** (builds the styles; Ubuntu's own Node is too old for Tailwind 4):
```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v                          # v22.something
```

**PHP runs as `ine`**, so files made by the website, the queue worker and your own commands all have the same owner (no "permission denied" in the logs later):
```bash
sudo sed -i 's/^user = www-data/user = ine/; s/^group = www-data/group = ine/' /etc/php/8.4/fpm/pool.d/www.conf
```

**Room for photo uploads** (up to 30 MB a photo; they are made smaller on the server):
```bash
sudo tee /etc/php/8.4/fpm/conf.d/99-inetjuhhh.ini > /dev/null <<'EOF'
upload_max_filesize = 32M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 300
EOF
sudo systemctl restart php8.4-fpm
```

## 5. The database

```bash
openssl rand -base64 24          # a strong password: copy it, you need it twice
sudo mysql
```
In MySQL (replace `PASSWORD` by the one you just made, keep the quotes):
```sql
CREATE DATABASE inetjuhhh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'inetjuhhh'@'localhost' IDENTIFIED BY 'PASSWORD';
GRANT ALL PRIVILEGES ON inetjuhhh.* TO 'inetjuhhh'@'localhost';
EXIT;
```
MySQL only listens on the server itself, so nobody on the internet can reach it.

## 6. The code

```bash
sudo mkdir -p /var/www && sudo chown ine:ine /var/www
cd /var/www
git clone https://github.com/Inetjuhhh/inetjuhhh.git
cd inetjuhhh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
php artisan curator:token
```
`key:generate` makes the app's own secret key, and `curator:token` the key that signs the photo addresses (so nobody can make the server resize photos to any size they like). Both stay on this server; never copy them anywhere.

**Settings:** `nano .env` (save with `Ctrl+O`, Enter; close with `Ctrl+X`). Change or add these lines:
```env
APP_NAME="Inetjuhhh - Blogs"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nieuw.inetjuhhh.nl
APP_LOCALE=nl

LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inetjuhhh
DB_USERNAME=inetjuhhh
DB_PASSWORD="the password from step 5"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_EMAILS=inetjuhhh@gmail.com

ANTHROPIC_API_KEY=...
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```
Plus the mail settings from **EMAILSTEPS.md step 4**. What they do:
- `APP_DEBUG=false`: visitors never see error details. Errors go to `storage/logs/`.
- `LOG_STACK=daily`: a log file per day, old ones removed after 14 days, so the disk doesn't fill up.
- `SESSION_SECURE_COOKIE=true`: the login cookie only travels over https.
- `ADMIN_EMAILS`: only this address gets into `/admin`, and it gets the "new comment" mails.
- The **Turnstile keys** are the real ones from your Cloudflare widget (PRODUCTIONSTEPS.md 3.1). `nieuw.inetjuhhh.nl` is covered by the hostname `inetjuhhh.nl`.

`.env` is not in git and stays on the server only. Make it readable for `ine` only (PHP runs as `ine`, so the site keeps working):
```bash
chmod 600 .env
```

## 7. Your content: database and photos

**On your laptop** (Git Bash, in the project folder), make the export and send it:
```bash
php artisan site:export
scp storage/app/export/inetjuhhh-database.sql.gz storage/app/export/inetjuhhh-photos.tar ine@<IP>:~/
```
The photos file is ~820 MB, so the upload takes a while.

**On the server:**
```bash
cd /var/www/inetjuhhh
gunzip -c ~/inetjuhhh-database.sql.gz | mysql -u inetjuhhh -p inetjuhhh     # asks the database password
mkdir -p storage/app/public && tar -xf ~/inetjuhhh-photos.tar -C storage/app/public
php artisan storage:link
php artisan migrate --force      # nothing to do now; keeps later updates in sync
rm ~/inetjuhhh-database.sql.gz ~/inetjuhhh-photos.tar
shred -u ~/dbpass.txt 2>/dev/null   # the temporary password file, if you made one
```
`storage:link` makes `public/storage` point to the photos, so the browser can reach them. **Never** run `db:seed`, `migrate:fresh` or `wordpress:import` here: that would overwrite your real content.

## 8. The web server and https

Tell nginx about the site:
```bash
sudo tee /etc/nginx/sites-available/inetjuhhh > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name nieuw.inetjuhhh.nl;
    root /var/www/inetjuhhh/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Photos, styles, fonts: cached by the browser for a month. A file that doesn't exist (an old
    # WordPress photo link) still goes to Laravel, which sends it on to the new photo.
    location ~* \.(?:jpe?g|png|webp|gif|svg|ico|css|js|woff2?)$ {
        try_files $uri /index.php?$query_string;
        expires 30d;
        access_log off;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
sudo ln -s /etc/nginx/sites-available/inetjuhhh /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```
`nginx -t` checks the file first: it should say *syntax is ok*.

**https:**
```bash
sudo certbot --nginx -d nieuw.inetjuhhh.nl
```
It asks for an email address (use inetjuhhh@gmail.com, for expiry warnings) and to accept the terms. Certbot adds the https part to the nginx file itself and renews the certificate automatically every few months.

## 9. Queue worker and scheduler

**The queue worker** sends the mails in the background. Supervisor starts it, and restarts it if it ever stops:
```bash
sudo tee /etc/supervisor/conf.d/inetjuhhh-queue.conf > /dev/null <<'EOF'
[program:inetjuhhh-queue]
command=php /var/www/inetjuhhh/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=ine
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/inetjuhhh/storage/logs/queue.log
stopwaitsecs=3600
EOF
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status          # inetjuhhh-queue  RUNNING
```

**The scheduler**, every minute (planned blogs are mailed, old spam and unconfirmed sign-ups removed):
```bash
crontab -e
```
Choose `nano` if it asks, and add this line at the bottom:
```cron
* * * * * cd /var/www/inetjuhhh && php artisan schedule:run >> /dev/null 2>&1
```

## 10. Speed

```bash
cd /var/www/inetjuhhh
php artisan optimize && php artisan filament:optimize
```
These save the settings, routes and pages in a fast form. **After every change to `.env`** run `php artisan optimize` again, plus `php artisan queue:restart` so the worker reads the new settings too.

## 11. Check

- `https://nieuw.inetjuhhh.nl` shows your blogs with photos; `/admin` lets you in with inetjuhhh@gmail.com and your local password
- An old link: `https://nieuw.inetjuhhh.nl/2019/01/12/jutten-of-jatten/` → the blog, and `/wp-content/uploads/2019/01/Foto-04-01-19-20-43-16-768x1024.jpg` → the photo
- Mail: EMAILSTEPS.md step 6
- The rest of the checklist: PRODUCTIONSTEPS.md step 5

## 12. Updates later

When you have pushed new code to GitHub:
```bash
ssh ine@<IP>
cd /var/www/inetjuhhh && ./deploy.sh
```
`deploy.sh` (in the project) fetches the code, installs packages, builds the styles, updates the database, refreshes the caches and restarts the queue worker. It asks your password once, for reloading PHP.

## 13. Backups and keeping an eye on it

- **Hetzner backups** (step 1): the whole server every day, 7 days back. Restore in the Cloud Console → your server → Backups.
- **An extra copy of the content now and then**, on your own computer (for example once a month, and before big changes):
  ```bash
  ssh ine@<IP> "cd /var/www/inetjuhhh && php artisan site:export"
  scp ine@<IP>:/var/www/inetjuhhh/storage/app/export/* ./backup/
  ```
- **Errors:** `storage/logs/laravel-<date>.log`. Quick look: `tail -50 storage/logs/laravel-$(date +%F).log`
- **Disk space:** `df -h /` (stays far below 40 GB for years)

## When something goes wrong

| Problem | What to do |
|---|---|
| `ssh: Permission denied` | Logging in as root? That's switched off: `ssh ine@<IP>`. Different laptop? Its key isn't on the server; add it via the Hetzner console (Rescue/Console) |
| `composer install` says PHP is too old | `php -v` must show 8.4; check step 4 |
| White page or "500 Server Error" | `tail -50 storage/logs/laravel-$(date +%F).log`; often a typo in `.env` (then `php artisan optimize`) |
| "502 Bad Gateway" | PHP isn't running: `sudo systemctl restart php8.4-fpm` |
| Photos missing, text is there | Step 7: the photos weren't unpacked into `storage/app/public`, or `php artisan storage:link` is missing |
| Photo upload in the admin fails | Step 4 (PHP limits) and `client_max_body_size` in step 8 |
| Mails don't go out | `sudo supervisorctl status`; then EMAILSTEPS.md "When something goes wrong" |
| Certbot fails | Does `nieuw.inetjuhhh.nl` point to this server yet (step 2)? Are ports 80/443 open in the Hetzner firewall? |
