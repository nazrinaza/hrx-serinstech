# HRX · Serinstech HR & Payroll

An internal HR and payroll system for Serinstech, built with Laravel 13 (PHP 8.3+, MySQL). One installation serves **several companies**. Each company's data is kept separate, and a user can belong to more than one company, with a different role in each.

| Module | What it does |
|---|---|
| Companies & users | Company switcher, roles (owner · admin · HR · manager · employee), invite by email |
| Setup | Departments, positions, public holidays, working days, allowances/deductions, leave types |
| Employees | Profile, salary history, recurring allowances, statutory and tax details, CSV import, self-service logins |
| Leave | Employment Act entitlements (8/12/16 annual, 14/18/22 sick), proration, carry forward, half days, manager → HR approval, email notices |
| Payroll | Monthly runs, proration, unpaid leave, one-off items, EPF / SOCSO / EIS / PCB / HRDF, PDF payslips, bank CSV, statutory CSV |
| Self service | My profile, apply for / cancel leave, approvals for managers, published payslips |

## ⚠️ Statutory figures must be verified

All rates live in [`config/statutory.php`](config/statutory.php), and unit tests are in `tests/Unit/Statutory`. Before running live payroll, an accountant should check a sample of payslips against the official calculators: **KWSP** (Third Schedule), **PERKESO** (SOCSO/EIS schedules) and **LHDN's MTD calculator**.

Known limitations:
- **PCB** covers normal monthly pay only. Bonuses or arrears (additional remuneration), zakat and TP1 reliefs are not handled yet.
- The **SOCSO category 2** (employer-only) low-wage bands are derived from the published rule. They are not copied from the official table, so they need confirming.
- **Statutory forms** (Borang A, 8A, CP39, EA) aren't generated yet. The statutory CSV has the figures needed to fill them.
- The **bank file** is a generic CSV. Bank-specific formats (Maybank M2U Biz, CIMB BizChannel, etc.) can be added per bank.

**Going live mid-year:** for each employee, enter their Jan–(go-live) totals under *Employee → Salary & allowances → Year-to-date opening balance*. Without these, PCB under-deducts for the rest of the year.

## Local development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# local: set DB_CONNECTION=sqlite (or point at a local MySQL)
php artisan migrate --seed      # demo data — see database/seeders/DatabaseSeeder.php for demo logins
npm run build && php artisan serve
php artisan test
```

## Deploying to cPanel (hr.serinstech.com)

Full step-by-step guide: **[deploy/CPANEL-GUIDE.md](deploy/CPANEL-GUIDE.md)**.

In short:
0. Verify `serinstech.com` in [Resend](https://resend.com) by adding its DNS records, then create an API key. All email goes out through Resend (`MAIL_MAILER=resend`).
1. Run `bash deploy/build.sh` on your machine. It creates a zip in `dist/` that already contains `vendor/` and the compiled CSS/JS, so the server needs no Composer or Node.
2. Upload the zip and extract it outside `public_html`.
3. Point the domain's document root at `hrx/public`.
4. Create `.env` from [`deploy/env.production.example`](deploy/env.production.example).
5. In the cPanel Terminal, run `key:generate`, `migrate --force`, `storage:link`, `optimize` and `hrx:install`. To check email, run `hrx:mail-test you@example.com`.
6. Add a cron job that runs `schedule:run` every minute.
7. Turn on AutoSSL.

## Architecture notes

- **Tenant isolation:** company-owned models use `App\Models\Concerns\BelongsToCompany`, which adds a global scope and stamps `company_id` automatically. `EnsureCurrentCompany` resolves the company *before* route-model binding (see `bootstrap/app.php`), so a URL with another company's ID returns 404. Child tables (`payslip_lines`, `employee_pay_items`, `leave_entitlements`) are checked through their parent in controllers.
- **Roles:** stored on the `company_user` pivot. The groups `admin` / `hr` / `payroll` / `manager` are mapped in `RequireRole`.
- **Payroll runs:** draft runs can be recalculated as often as needed. Finalizing locks amounts, and PCB for later months builds on finalized months. Publishing makes payslips visible in self service.
- **Payslips snapshot** the employee's name, IC, bank and other details when the run is calculated, so later edits to the employee don't change past payslips.
- **Brand:** see [`docs/brand.md`](docs/brand.md). Buttons use dark text on the orange (`#FF9B51`), because white text on that orange fails contrast.
