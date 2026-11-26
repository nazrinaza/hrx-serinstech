# Deploying Serinstech HR to cPanel (hr.serinstech.com)

This guide uses the **upload-a-zip** method, so the server needs no Composer and no Node.
It takes about 30 minutes the first time.

**What you need:**
- a cPanel login;
- the database name, user and password you want to use;
- a free [Resend](https://resend.com) account, which sends the app's emails.

---

## 0. Build the package (on your Mac)

```bash
cd ~/Desktop/"HR apps"/hrx-serinstech
bash deploy/build.sh
```

This creates `dist/hrx-serinstech-<version>.zip` (about 13 MB). The zip contains:
- the code;
- the production PHP libraries;
- the compiled CSS and JS.

It never contains `.env`, the local database, or uploaded files.

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

Write down the full prefixed names. You need them in step 6.

## 4. Upload and extract

cPanel → **File Manager** → go to your **home folder**. This is the folder that contains `public_html`, *not* `public_html` itself.

1. **Upload** the zip.
2. Right-click the zip → **Extract**. This creates a folder called `hrx`.
3. Delete the zip.

You now have `/home/<cpaneluser>/hrx/` with `app`, `public`, `vendor` and the other folders inside.

> The code must sit **outside** `public_html`. Only the `public` folder should be reachable from the web.

## 5. Point the domain at `hrx/public`

cPanel → **Domains**:

- **If `hr.serinstech.com` doesn't exist yet:** click *Create A New Domain* → enter `hr.serinstech.com` → untick *Share document root* → set the **Document Root** to `hrx/public` → Submit.
- **If it already exists:** click **Manage** next to it → change the Document Root to `hrx/public` → Update.

## 6. Create `.env`

1. In File Manager, open `hrx/deploy/`.
2. Copy `env.production.example` to `hrx/` and rename the copy to **`.env`**. Turn on *Settings → Show Hidden Files* to see it.
3. Edit `.env` and fill in these values:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: the values from step 3.
   - `RESEND_API_KEY`: the `re_…` key from step 1.

   If a password contains spaces or `#`, wrap it in double quotes, like this: `DB_PASSWORD="my#pass word"`.

## 7. Run the install commands

Open cPanel → **Terminal**, then:

```bash
cd ~/hrx
php -v
```

Check the version that `php -v` shows. **If it is not 8.3**, use the full path `/opt/cpanel/ea-php83/root/usr/bin/php` instead of `php` in every command below, and in the cron job in step 8.

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan hrx:install
```

What each command does:
- `key:generate` creates the encryption key and writes it into `.env`.
- `migrate` creates all the tables.
- `storage:link` lets company logos display.
- `optimize` caches the config, routes and views so pages load faster.
- `hrx:install` creates **your** super-admin login. It asks for your name, email and a password of at least 12 characters.

> **No Terminal in your cPanel?** Ask your host to enable it, or to enable "SSH access". Enabling one of them is the reliable way to run these commands.

## 8. Cron job (emails)

cPanel → **Cron Jobs** → *Common Settings:* **Once Per Minute** → Command:

```
cd /home/<cpaneluser>/hrx && php artisan schedule:run >> /dev/null 2>&1
```

Replace `<cpaneluser>` with your cPanel username. Use the full PHP path from step 7 if you needed it there.

This cron job sends the queued emails (leave requests, leave decisions, memos and set-password links) through Resend.

## 9. SSL

cPanel → **SSL/TLS Status** → tick `hr.serinstech.com` → **Run AutoSSL**.

Wait until it shows a valid certificate. The app requires HTTPS, because its login cookies are marked secure.

## 10. First sign-in

1. Open **https://hr.serinstech.com** and sign in with the super-admin login from step 7.
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

1. On your Mac, run `bash deploy/build.sh`.
2. Upload the new zip to your home folder and extract it over the existing `hrx` folder (choose overwrite). `.env` and the uploaded files are not inside the zip, so they stay untouched.
3. In Terminal, run:
   ```bash
   cd ~/hrx
   php artisan migrate --force
   php artisan optimize
   ```

## Troubleshooting

| What you see | Fix |
|---|---|
| **500 error / blank page** | Read `hrx/storage/logs/laravel-<date>.log`. The usual causes are a wrong `DB_*` value in `.env`, or forgetting to run `php artisan optimize` again after editing `.env`. |
| **"Your Composer dependencies require PHP >= 8.3"** | The domain or terminal is using an older PHP version. Redo step 2, or use the full `ea-php83` path. |
| **Page shows no styling** | The document root is not `hrx/public`. Redo step 5. |
| **Can't log in / "Page Expired" (419)** | Open the site with `https://`, not `http://`, and make sure SSL from step 9 is active. |
| **Emails don't arrive** | Run `php artisan hrx:mail-test you@yourmail.com`. If the test arrives but leave or memo emails don't, the cron job in step 8 isn't running. Also check Resend → **Emails** to see what was sent and why anything bounced. |
| **"The serinstech.com domain is not verified"** | The DNS records from step 1 haven't been added yet, or haven't spread yet. Check Resend → Domains. |
| **"API key is invalid"** | Check `RESEND_API_KEY` in `.env`, then run `php artisan optimize` again. |
| **Changed `.env` but nothing changed** | Run `php artisan optimize` again. The config is cached. |

## Before running live payroll

- Have an accountant compare a few payslips against the official calculators from KWSP, PERKESO and LHDN (the PCB/MTD calculator).
- The unpaid-leave deduction is not implemented yet, so it currently deducts RM0. PCB does not handle bonus or zakat yet.
