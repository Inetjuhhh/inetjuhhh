# Email in production: Brevo, step by step

The blog sends three kinds of email:

| Mail | To | When |
|---|---|---|
| **Confirm your subscription** | A reader who signs up | Right away, after the form under a blog or in the footer is sent |
| **New story: …** | Every confirmed subscriber | When **you** click **Mail abonnees** at a blog in the admin (never by itself), once per blog |
| New comment / reply to a comment | You, or the reader who asked for it | When someone comments, or you reply in the admin |
| New password | You | "Wachtwoord vergeten?" on the admin login |

**How it's set up:**
- **Brevo** delivers the mail. The Hetzner server can't send mail itself (Hetzner blocks ports 25 and 465), and mail sent straight from a web server lands in spam anyway. The blog hands every mail to Brevo on port **587**, which Hetzner allows.
- **Sender:** `blog@inetjuhhh.nl`. It doesn't have to be a real mailbox: nobody reads it. The mail has to come from your own domain, because only for your own domain can you prove (with DNS records) that the mail is really yours. A @gmail.com sender sent through Brevo fails that proof and ends up in spam.
- **Replies** go to **inetjuhhh@gmail.com**. A reader who hits "reply" reaches your Gmail.

Locally all mail goes to **Mailpit** (http://localhost:8025), so nothing reaches real people.

Time: about 45 minutes, plus waiting for DNS (minutes to a few hours) and possibly for Brevo's account check (up to a day).

---

## 1. A Brevo account

1. Sign up at **brevo.com** (the free plan: 300 mails a day) with inetjuhhh@gmail.com.
2. Brevo **may** ask a few questions about you and what you send (at sign-up, or later when you add a sender or send the first mail; sometimes not at all). If it asks, answer honestly: a personal blog, mails to readers who signed up themselves, with double opt-in. Then open **Transactional** in the left menu: if there's a banner about activating or a review, follow it (Brevo sometimes checks new accounts, up to a day). No banner: you may send.
3. **Data processing agreement** (*verwerkersovereenkomst*, required under the AVG/GDPR because Brevo handles your readers' email addresses): at Brevo it is part of the terms you accepted at sign-up, so there is nothing to sign. Download the "Data Processing Agreement" from the legal pages on brevo.com (links at the bottom of their website) and keep it with your papers.

**The daily limit:** 300 mails a day. A new blog goes to every subscriber at once, so above ~280 subscribers some would miss it. Brevo's smallest paid plan has no daily limit; switch in time. *Admin → Abonnees* shows how many you have.

## 2. Your domain at Brevo

Brevo needs to know that inetjuhhh.nl is yours. In Brevo: the **⚙ gear** at the top right → **Senders, domains, IPs** → tab **Domains** → **Add a domain** → `inetjuhhh.nl`. Choose to set the records **manually** (Brevo may offer to connect to your domain host automatically; manual shows you exactly what is added).

Brevo shows a few DNS records. Add them **exactly as shown** at your domain host. They look roughly like this:

| Type | Name | What it does |
|---|---|---|
| TXT | `@` | `brevo-code:…`: proves to Brevo that you own the domain |
| CNAME | `brevo1._domainkey` and `brevo2._domainkey` | **DKIM**: every mail gets a signature that proves it really comes from inetjuhhh.nl |
| TXT | `_dmarc` | **DMARC**: tells Gmail and Outlook what to do with mail that fails the checks. Start with `v=DMARC1; p=none` |

Good to know:
- **SPF** (`v=spf1 …`): if Brevo shows one, you can only have **one** SPF record per domain. If one already exists (from the old WordPress host), add Brevo's `include:` to it instead of making a second one.
- **DMARC reports:** Brevo may suggest `rua=mailto:rua@dmarc.brevo.com`. Copy that as it is: Brevo collects the reports for you. Don't put your Gmail address there: reports to an address on another domain only arrive when that domain gives permission, which Gmail doesn't.
- **At TransIP** (where inetjuhhh.nl is): *Mijn TransIP → Domeinen → inetjuhhh.nl → DNS*. Type the name without your domain (`brevo1._domainkey`), and end a CNAME value with a **dot** (`…dkim.brevo.com.`), otherwise TransIP puts inetjuhhh.nl after it. The existing SPF record (`include:_spf.transip.email`) and MX record are for your TransIP mailbox: leave them.
- **Old mail records** (MX) at your domain host: leave them alone. They're about receiving mail, which the blog doesn't do.

Then click **Authenticate this domain** in Brevo. DNS can take a few hours; try again later if it isn't green yet.

## 3. Sender and SMTP key

1. **⚙ gear → Senders, domains, IPs → tab Senders → Add a sender:** name `Inetjuhhh - Blogs`, address `blog@inetjuhhh.nl`. Because the domain is authenticated (step 2), the address doesn't have to exist. If Brevo still wants to send a confirmation mail to it, ask your domain host for a forwarding from `blog@inetjuhhh.nl` to inetjuhhh@gmail.com, confirm, and remove the forwarding afterwards if you like.
2. **SMTP & API → SMTP:** here you see the **SMTP server** (`smtp-relay.brevo.com`), **port** (587) and your **login** (something like `1234abc@smtp-brevo.com`). Click **Generate a new SMTP key**, name it `inetjuhhh server`, and copy it right away: Brevo shows it only once. This key is the password for step 4; treat it like one.
3. **Tracking anonymous** (privacy): Brevo counts opens and clicks. Your readers didn't ask for that. Go to **⚙ → Transactional emails → Tracking** and set *Anonymous email tracking?* to **Yes**, then **Save**: Brevo then only counts totals, not who opened or clicked what (a full "off" isn't offered there). Also check **⚙ → UTM parameters** and switch them off, so links in your mails stay clean.

## 4. Settings in `.env` on the server

```bash
ssh ine@<IP>
cd /var/www/inetjuhhh && nano .env
```
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=1234abc@smtp-brevo.com    # the login from step 3
MAIL_PASSWORD=the-smtp-key-from-step-3
MAIL_FROM_ADDRESS="blog@inetjuhhh.nl"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_REPLY_TO_ADDRESS="inetjuhhh@gmail.com"

ADMIN_EMAILS=inetjuhhh@gmail.com        # gets the "new comment" mails
```
- `MAIL_SCHEME=null` with port 587: the connection starts normally and is then encrypted (STARTTLS). Don't use 465: Hetzner blocks it.
- `MAIL_REPLY_TO_ADDRESS`: where replies to any blog mail go.
- `APP_URL` (SERVERSTEPS.md step 6) matters here too: every link in a mail is built from it.

Then:
```bash
php artisan optimize
php artisan queue:restart
```
The queue worker that actually sends the mails is set up in SERVERSTEPS.md step 9, the scheduler for planned blogs in the same step.

## 5. Privacy statement

Mention on the site (for example on **Ik & wij** or a privacy page):
- readers can leave their name and email address to get a mail about new blogs;
- **Brevo** (France, EU) sends those mails for you;
- every mail has an unsubscribe link, and unsubscribing deletes their details straight away;
- people who leave a comment give a name and email address; the address is never shown.

## 6. Test it

1. **A test mail** to yourself, on the server:
   ```bash
   php artisan tinker --execute="Mail::raw('Test van inetjuhhh.nl', fn (\$m) => \$m->to('inetjuhhh@gmail.com')->subject('Test'));"
   ```
   It arrives within a minute. In Gmail, open it → the three dots → **Show original**: `SPF`, `DKIM` and `DMARC` should all say **PASS**. Landed in spam the first time? Mark it "Not spam"; that helps.
2. **Reply** to that mail: the reply should go to inetjuhhh@gmail.com.
3. **Sign up** with your own address through the form under a blog, and click the link in the confirmation mail.
4. **A new blog:** open a published blog in the admin and click **Testmail naar mij**: you get "Nieuw verhaal: …" straight away, and nothing is marked as sent. Is the photo there, and does the button open the blog on the right address? When you really want to send it to everyone: **Mail abonnees**.
5. **mail-tester.com:** sign up with the address they give you, publish a blog, and aim for 9/10 or higher. A lower score almost always means a DNS record from step 2 isn't right yet.
6. **Unsubscribe** with the link at the bottom of the mail and check that you disappear from **Admin → Abonnees**.
7. In Brevo under **Transactional → Logs** (or *Statistics*) you can see every mail that went out, and whether it was delivered.

## How it works (for reference)

- **Double opt-in:** someone is only on the list after clicking the link in the confirmation mail (valid for 7 days). Nobody can sign up someone else, and you can show who gave permission. Unconfirmed sign-ups disappear after 30 days.
- **Each blog is sent only once.** Fixing a typo afterwards doesn't send it again. Blogs that were already online before this feature existed are never sent.
- **Unsubscribing:** every mail has a link at the bottom, plus a header that Gmail, Outlook and Apple Mail turn into their own "unsubscribe" button. The address is then deleted straight away.
- **Spam protection** on the sign-up form: the robot check (Turnstile), a hidden field, and at most 5 sign-ups an hour from one internet address, so nobody can make your blog mail a list of strangers.
- **Admin → Abonnees:** see who's on the list, remove someone, or add someone by hand (only people who asked for it themselves).

## When something goes wrong

| Problem | Check |
|---|---|
| No mail at all | `sudo supervisorctl status` (queue worker RUNNING?), then `tail -50 storage/logs/laravel-$(date +%F).log` |
| Mails stay in the queue | `php artisan queue:work --once` shows the error directly |
| "Connection could not be established" / timeout | Port 587 in `.env` (not 25 or 465: Hetzner blocks those), host `smtp-relay.brevo.com` |
| "Authentication failed" | `MAIL_USERNAME` is the Brevo SMTP **login** (…@smtp-brevo.com), not your Gmail; `MAIL_PASSWORD` the SMTP key; then `php artisan optimize && php artisan queue:restart` |
| Mail failed | `php artisan queue:failed` shows failed mails; fix the cause, then `php artisan queue:retry all` |
| Brevo says "sender not valid" | Step 2 (domain authenticated?) and step 3 (sender added?) |
| Mail lands in spam | Gmail → Show original: which of SPF/DKIM/DMARC fails? Then step 2 |
| Links in the mail point to nieuw.inetjuhhh.nl or localhost | `APP_URL` in `.env`, then `php artisan optimize && php artisan queue:restart` |
| A new blog was never mailed | Mails go out only when you click **Mail abonnees** at that blog in the admin (the list, or the editor). After a click: is the queue worker running (`sudo supervisorctl status`)? |
| Some subscribers didn't get a new blog | The 300-a-day limit (step 1); Brevo's logs show it |

## Locally (Laragon)

- **Mailpit** catches all mail: http://localhost:8025
- Laragon starts these automatically (see `C:\laragon\usr\Procfile`): Mailpit, the queue worker and the scheduler. They run from the next time you start Laragon, and are visible under Menu → Procfile.
- No queue worker running? Then mails wait in the queue. `php artisan queue:work --stop-when-empty` sends them anyway.
