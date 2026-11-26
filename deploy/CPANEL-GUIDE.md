# Deploying Serinstech HR to cPanel (hr.serinstech.com)

This guide uses cPanel **Git™ Version Control**. cPanel pulls the code from GitHub, and **Deploy HEAD Commit** copies it to the live site and updates it.
After the first setup, an update is just: push from your Mac → two clicks in cPanel.

The first setup takes about 30–45 minutes.

**What you need:**
- a cPanel login with **Terminal** (or SSH) access;
- the database name, user and password you want to use;
- a free [Resend](https://resend.com) account, which sends the app's emails.

### How it fits together

```
GitHub (nazrinaza/hrx-serinstech)
   │  cPanel: "Update from Remote"
   ▼
~/repositories/hrx-serinstech      ← Git copy. Never edit files here.
   │  cPanel: "Deploy HEAD Commit"  (runs .cpanel.yml → deploy/cpanel-deploy.sh)
   ▼
~/hrx                              ← live app. .env and uploaded files live here.
   └─ public/                      ← what hr.serinstech.com shows
```

Each deploy does the following:
1. Puts the site in maintenance mode for a few seconds.
2. Copies the code into `~/hrx`.
3. Runs `composer install`.
4. Updates the database (`migrate`) and the caches.
5. Brings the site back up.

It **never** touches `.env`, uploaded files or logs. If a deploy fails, the site still comes back up.

---

## 1. Set up Resend (email)

The app sends its emails through Resend: leave requests and decisions, memos, and set-password links. Do this step first, because DNS changes can take a while to take effect.

1. **Sign up** at resend.com.
2. **Add the domain:** Resend → **Domains** → *Add Domain* → enter `serinstech.com` → choose region **Tokyo (ap-northeast-1)**, which is the closest to Malaysia.
3. **Add the DNS records:** Resend now shows 3–4 records. Usually these are an **MX** and a **TXT** record for `send.serinstech.com` (SPF), and a **TXT** record for `resend._domainkey` (DKIM). Copy each one into cPanel → **Zone Editor** → `serinstech.com` → **Manage** → *Add Record*:
   - **Type:** the type Resend shows (MX or TXT).
   - **Name:** the name Resend shows, e.g. `send` or `resend._domainkey`. cPanel adds `.serinstech.com` itself.
   - **Value / Record:** paste the value exactly as Resend shows it. For MX, also set the priority Resend gives, e.g. `10`.

   > These records sit on the `send.` subdomain and on `resend._domainkey`, so they **don't affect** your existing email at `@serinstech.com`. Don't edit or delete your existing MX or SPF records for `serinstech.com`.
   >
   > If your domain's DNS is managed somewhere else (for example Cloudflare, or the registrar), add the records there instead of in cPanel.
4. Back in Resend, click **Verify DNS Records**. It usually turns green within minutes, but it can take up to a few hours.
5. **Create the API key:** Resend → **API Keys** → *Create API Key* → name it `hrx-production` → permission **Sending access** → domain `serinstech.com`. Copy the key, which starts with `re_`. It is shown **only once**.

   > Treat the key like a password. Only put it in `.env` on the server. Don't email it, and don't commit it to GitHub.

## 2. Set the PHP version

cPanel → **MultiPHP Manager** → tick `hr.serinstech.com` (or the main domain) → set **PHP 8.3** → Apply.

Then go to cPanel → **Select PHP Version** → **Extensions** (only if your host shows this page). Make sure these are ticked:

`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `gd`, `intl`, `zip`, `dom`.

## 3. Create the database

cPanel → **MySQL® Databases**:

1. **Create New Database:** `hrx`. cPanel adds your account prefix, giving for example `abc123_hrx`.
2. **Add New User:** `hrx`, with a strong password. This gives for example `abc123_hrx`.
3. **Add User To Database:** pick that user and database, then tick **ALL PRIVILEGES**.

Write down the full prefixed names. You need them in step 7.

## 4. Check PHP and Composer in Terminal

Open cPanel → **Terminal** and run:

```bash
php -v
composer -V
```

- **`php -v` shows 8.3 or newer:** fine, use `php` in the commands below.
- **`php -v` shows an older version:** use the full path **`/opt/cpanel/ea-php83/root/usr/bin/php`** wherever this guide says `php`, including in the cron job in step 9. Some CloudLinux hosts use `/opt/alt/php83/usr/bin/php` instead.

  The deploy script finds PHP 8.3 by itself, so this only matters for the commands you type.
- **Install Composer into your home folder.** Do this even if `composer -V` works in Terminal, because cPanel's deploy runner often can't see the system Composer:
  ```bash
  cd ~
  curl -sS https://getcomposer.org/installer | /opt/alt/php83/usr/bin/php
  ```
  This creates `~/composer.phar`, which the deploy script finds automatically. If your server uses EasyApache PHP instead of `/opt/alt/...`, use `/opt/cpanel/ea-php83/root/usr/bin/php`.

## 5. Clone the repository

cPanel → **Git™ Version Control** → **Create**:

- **Clone a Repository:** on.
- **Clone URL:** `https://github.com/nazrinaza/hrx-serinstech.git`
- **Repository Path:** `repositories/hrx-serinstech`
- **Repository Name:** `hrx-serinstech`

Click **Create** and wait for the clone to finish.

> The repository is public, so no GitHub login is needed. If you make it private later, set up an SSH deploy key first: in cPanel → **SSH Access**, generate a key, then add its public part in GitHub → repo → Settings → *Deploy keys*. After that, clone with the `git@github.com:…` URL.

**First deploy:** go to Git™ Version Control → **Manage** (next to `hrx-serinstech`) → **Pull or Deploy** tab → **Deploy HEAD Commit**.

This creates `~/hrx` and installs the PHP packages, which takes 1–3 minutes the first time. Because there is no `.env` yet, it stops there on purpose. The log says *"First deploy: code is in place"*.

> **Where's the log?** In File Manager, turn on *Show Hidden Files*, then open `.cpanel/logs/` in your home folder. The newest `vc_…_git_deploy.log` is the latest deploy.

## 6. Point the domain at `hrx/public`

cPanel → **Domains**:

- **If `hr.serinstech.com` doesn't exist yet:** click *Create A New Domain* → enter `hr.serinstech.com` → untick *Share document root* → set the **Document Root** to `hrx/public` → Submit.
- **If it already exists:** click **Manage** next to it → change the Document Root to `hrx/public` → Update.

> The code sits **outside** `public_html`. Only the `public` folder is reachable from the web.

## 7. Create `.env`

In Terminal:

```bash
cd ~/hrx
cp deploy/env.production.example .env
```

Then open `hrx/.env` in File Manager (turn on *Settings → Show Hidden Files*) → **Edit**, and fill in:
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: the values from step 3.
- `RESEND_API_KEY`: the `re_…` key from step 1.

If a password contains spaces or `#`, wrap it in double quotes, like this: `DB_PASSWORD="my#pass word"`.

## 8. Finish the install

In Terminal:

```bash
cd ~/hrx
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan hrx:install
```

What each command does:
- `key:generate` creates the encryption key and writes it into `.env`. Run it **once only**: running it again later logs everyone out and makes encrypted data unreadable.
- `migrate` creates all the tables.
- `storage:link` lets company logos display.
- `optimize` caches the config, routes and views so pages load faster.
- `hrx:install` creates **your** super-admin login. It asks for your name, email and a password of at least 12 characters.

## 9. Cron job (emails)

cPanel → **Cron Jobs** → *Common Settings:* **Once Per Minute** → Command:

```
cd /home/<cpaneluser>/hrx && php artisan schedule:run >> /dev/null 2>&1
```

Replace `<cpaneluser>` with your cPanel username. Use the full PHP path from step 4 if you needed it there.

This cron job sends the queued emails (leave requests, leave decisions, memos and set-password links) through Resend.

## 10. SSL

cPanel → **SSL/TLS Status** → tick `hr.serinstech.com` → **Run AutoSSL**.

Wait until it shows a valid certificate. The app requires HTTPS, because its login cookies are marked secure.

## 11. First sign-in

1. Open **https://hr.serinstech.com** and sign in with the super-admin login from step 8.
2. **Companies → New company:** add Serinstech, including its EPF, SOCSO, LHDN and HRDF numbers.
3. **Setup:** check the departments, positions, public holidays, leave types and pay items.
4. **Employees:** add employees one by one or import them from CSV. Then click **Create login** for each employee who should use self-service.
5. **Users:** give HR staff and managers their roles.

### Quick checks
- **Email settings:** in Terminal, run `php artisan hrx:mail-test you@yourmail.com`. This sends one email immediately and shows any error on screen.
- **Email flow:** submit a test leave request and confirm the approver receives an email within about a minute.
- **Payslips:** run a draft payroll and download one payslip PDF.

---

## Updating later

**On your Mac:**

```bash
cd ~/Desktop/"HR apps"/hrx-serinstech
npm run build
git add -A
git commit -m "Describe the change"
git push
```

Only run `npm run build` if you changed CSS, JS or Blade views. The compiled files in `public/build` are committed to git, so the server never needs Node.

**In cPanel:**
1. Go to Git™ Version Control → **Manage** → **Pull or Deploy**.
2. Click **Update from Remote**. This pulls the new commits from GitHub.
3. Click **Deploy HEAD Commit**. The site goes into maintenance for a few seconds, updates, and comes back up.

The "HEAD Commit" box shows which commit is live.

## Troubleshooting

| What you see | Fix |
|---|---|
| **Deploy button is greyed out** | The Git copy in `~/repositories/hrx-serinstech` has local changes. Never edit files there. In Terminal, run `cd ~/repositories/hrx-serinstech && git status`. To throw local changes away, run `git checkout -- . && git clean -fd`. |
| **Deploy did nothing / not sure if it worked** | Read the newest `~/.cpanel/logs/vc_…_git_deploy.log`. It should end with `[hrx-deploy] Done: …`. |
| **"PHP 8.3 or newer not found"** (deploy log) | Find your PHP 8.3 path with `ls /opt/cpanel/ /opt/alt/`. Then edit `.cpanel.yml` (on your Mac, then commit and push) so the task line reads `- HRX_PHP=/path/to/php /bin/bash deploy/cpanel-deploy.sh`. |
| **"Composer not found"** (deploy log) | Install `~/composer.phar` as in step 4, then click **Deploy HEAD Commit** again. |
| **Composer "Allowed memory size exhausted"** | Ask your host to raise the PHP CLI `memory_limit` to 512M, or use the zip method below. |
| **500 error / blank page** | Read `~/hrx/storage/logs/laravel-<date>.log`. The usual causes are a wrong `DB_*` value in `.env`, or forgetting to run `php artisan optimize` again after editing `.env`. |
| **"Your Composer dependencies require PHP >= 8.3"** | The domain is using an older PHP version. Redo step 2. |
| **Page shows no styling** | The document root is not `hrx/public`. Redo step 6. |
| **Can't log in / "Page Expired" (419)** | Open the site with `https://`, not `http://`, and make sure SSL from step 10 is active. |
| **Emails don't arrive** | Run `php artisan hrx:mail-test you@yourmail.com`. If the test arrives but leave or memo emails don't, the cron job in step 9 isn't running. Also check Resend → **Emails** to see what was sent and why anything bounced. |
| **"The serinstech.com domain is not verified"** | The DNS records from step 1 haven't been added yet, or haven't spread yet. Check Resend → Domains. |
| **"API key is invalid"** | Check `RESEND_API_KEY` in `.env`, then run `php artisan optimize` again. |
| **Site stuck on "Service Unavailable" (503)** | A deploy was interrupted while in maintenance mode. Run `cd ~/hrx && php artisan up`. |
| **Changed `.env` but nothing changed** | Run `php artisan optimize` again. The config is cached. |

## Alternative: upload a zip (no Git or Composer on the server)

Use this only if your host has no Git™ Version Control or no Composer.

1. On your Mac, run `bash deploy/build.sh`. This creates `dist/hrx-serinstech-<version>.zip`, which already contains the PHP packages.
2. In File Manager, upload the zip to your home folder → **Extract**. This creates or updates `~/hrx`.
3. Continue from step 6 above.

**To update later:** extract a new zip over `~/hrx` (choose overwrite), then run `cd ~/hrx && php artisan migrate --force && php artisan optimize`.

## Before running live payroll

- Have an accountant compare a few payslips against the official calculators from KWSP, PERKESO and LHDN (the PCB/MTD calculator).
- The unpaid-leave deduction is not implemented yet, so it currently deducts RM0. PCB does not handle bonus or zakat yet.
