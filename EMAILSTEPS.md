# Email in production: step by step

The blog sends three kinds of email:

| Mail | To | When |
|---|---|---|
| **Confirm your subscription** | A reader who signs up | Right away, after the form under a blog or in the footer is sent |
| **New story: …** | Every confirmed subscriber | When a blog goes online (or, for a planned blog, within 5 minutes of its date) |
| New comment / reply to a comment | You, or the reader who asked for it | When someone comments, or you reply in the admin |

Locally all mail goes to **Mailpit** (http://localhost:8025), so nothing reaches real people. In production you need five things:

1. A mail provider that actually delivers the mail
2. DNS records, so the mail doesn't land in spam
3. The right settings in `.env`
4. A **queue worker** (sends the "new story" mails)
5. The **scheduler** (planned blogs, cleanups)

No extra packages are needed: Laravel sends through SMTP, which works with every provider.

---

## 1. Choose a mail provider

Don't send from your web server itself: that mail almost always ends up in spam. Use a provider:

| Provider | Free | Good to know |
|---|---|---|
| **Brevo** (brevo.com) | 300 mails/day | European (France), Dutch-language dashboard. A good fit for a blog. |
| Mailgun EU (mailgun.com) | 100 mails/day | Choose the **EU region** when you sign up |
| Postmark (postmarkapp.com) | 100 mails/month | Best delivery, paid after the trial |
| Your hosting's mailbox (e.g. blog@inetjuhhh.nl) | Included | Fine to start with; check the hourly limit with your host |

**Watch the daily limit:** with 300 mails a day and 400 subscribers, some of them won't get the mail. Upgrade to a paid plan in time.

Also sign the provider's **data processing agreement** (*verwerkersovereenkomst*), required under the GDPR (AVG): they process your readers' email addresses.

From the provider you get **SMTP details**: host, port, username and password (often called an "SMTP key"). You need these in step 3.

## 2. DNS records for inetjuhhh.nl

Add these records where your domain is registered. The provider shows you the exact values; they always look roughly like this:

| Type | Name | What it does |
|---|---|---|
| TXT | `@` | **SPF**: which servers may send mail for inetjuhhh.nl, e.g. `v=spf1 include:spf.brevo.com ~all`. You can only have **one** SPF record: if one already exists, add the `include:` to it. |
| TXT or CNAME | e.g. `brevo._domainkey` | **DKIM**: a signature that proves the mail really comes from you |
| TXT | `_dmarc` | **DMARC**: what to do with fake mail, to start with: `v=DMARC1; p=none; rua=mailto:ine@inetjuhhh.nl` |

Then click "verify" at the provider. DNS changes can take a few hours.

## 3. Settings in `.env` on the server

```env
APP_URL=https://inetjuhhh.nl            # the links in mails are built from this: really important
APP_LOCALE=nl

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com          # from your provider
MAIL_PORT=587
MAIL_SCHEME=null                        # null for port 587; use smtps for port 465
MAIL_USERNAME=your-login@provider
MAIL_PASSWORD=your-smtp-key
MAIL_FROM_ADDRESS="blog@inetjuhhh.nl"   # an address on your own domain (verified in step 2)
MAIL_FROM_NAME="${APP_NAME}"

QUEUE_CONNECTION=database
ADMIN_EMAILS=ine@inetjuhhh.nl           # gets the mails about new comments
```

After every change to `.env`:

```bash
php artisan config:cache
php artisan queue:restart
```

## 4. The queue worker

The "new story" mails go through a queue, so saving a blog in the admin stays fast, even with hundreds of subscribers. Something on the server has to keep processing that queue.

**On a VPS (with Supervisor).** Create `/etc/supervisor/conf.d/inetjuhhh-queue.conf`:

```ini
[program:inetjuhhh-queue]
command=php /var/www/inetjuhhh/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/inetjuhhh/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start inetjuhhh-queue
```

**With Laravel Forge / Ploi:** add a "Queue worker" in the dashboard (connection `database`).

**On shared hosting without Supervisor:** add a cron job that runs every minute and empties the queue:

```cron
* * * * * cd /path/to/inetjuhhh && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

## 5. The scheduler

One cron job for everything that has to happen on time:

- planned blogs: the mail goes out once their publish date has passed (checked every 5 minutes)
- unconfirmed sign-ups are removed after 30 days
- spam comments are removed after 30 days

```cron
* * * * * cd /path/to/inetjuhhh && php artisan schedule:run >> /dev/null 2>&1
```

(Forge/Ploi: switch on "Scheduler" in the dashboard.)

## 6. On every deploy

```bash
php artisan migrate --force      # creates the subscribers table, among other things
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart        # the queue worker picks up the new code
```

## 7. Test it

1. Send a test mail to yourself:
   ```bash
   php artisan tinker --execute="Mail::raw('Test from inetjuhhh.nl', fn (\$m) => \$m->to('ine@inetjuhhh.nl')->subject('Test'));"
   ```
2. Sign up with your own address through the form under a blog, and click the link in the confirmation mail.
3. Publish a blog. Within a minute you should get "New story: …". Is the photo there, and does the button open the blog?
4. Check your score at **mail-tester.com**: sign up with the address they give you, publish a blog, and aim for 9/10 or higher. A lower score almost always means SPF/DKIM (step 2) isn't right yet.
5. Click "Uitschrijven" (unsubscribe) at the bottom of the mail and check that you disappear from **Admin → Abonnees**.

## How it works (for reference)

- **Double opt-in:** someone is only on the list after clicking the link in the confirmation mail (valid for 7 days). Nobody can sign up someone else, and you can show who gave permission. Unconfirmed sign-ups disappear after 30 days.
- **Each blog is sent only once.** Fixing a typo afterwards doesn't send it again. Blogs that were already online before this feature existed are never sent.
- **Unsubscribing:** every mail has a link at the bottom, plus a header that Gmail, Outlook and Apple Mail turn into their own "unsubscribe" button. The address is then deleted straight away.
- **Admin → Abonnees:** see who's on the list, remove someone, or add someone by hand (only people who asked for it themselves).
- **Privacy statement:** mention that readers can leave their name and email address for this mail, which provider sends it, and that unsubscribing deletes their details.

## When something goes wrong

| Problem | Check |
|---|---|
| No mail at all | `storage/logs/laravel.log`, and whether the queue worker is running (step 4) |
| Mails stay in the queue | `php artisan queue:work --once` shows the error directly |
| Mail failed | `php artisan queue:failed` shows failed mails; fix the cause, then `php artisan queue:retry all` |
| Mail lands in spam | SPF/DKIM/DMARC (step 2), and a `MAIL_FROM_ADDRESS` on your own domain |
| Links in the mail point to localhost | `APP_URL` in `.env`, then `php artisan config:cache` |
| Planned blog was never mailed | Is the scheduler running (step 5)? Run `php artisan blogs:notify-subscribers` by hand |

## Locally (Laragon)

- **Mailpit** catches all mail: http://localhost:8025
- Laragon starts these automatically (see `C:\laragon\usr\Procfile`): Mailpit, the queue worker and the scheduler. They run from the next time you start Laragon, and are visible under Menu → Procfile.
- No queue worker running? Then the "new story" mails wait in the queue. `php artisan queue:work --stop-when-empty` sends them anyway.
