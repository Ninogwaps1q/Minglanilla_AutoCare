# Minglanilla AutoCare Hub

Laravel application for customers, auto shops, and administrators. It covers shop discovery, appointments, estimates, repairs, invoices and payments, roadside assistance, service passports, and maintenance reminders.

## Requirements

- PHP 8.2 or newer with the extensions required by `composer install`, including PDO MySQL, mbstring, fileinfo, and OpenSSL
- Composer 2
- Node.js `^20.19.0` or `>=22.12.0` and npm (required by the installed Vite version)
- MySQL 8 for the intended production database; a local MariaDB setup can run the app, but MySQL 8 concurrency sign-off must be done separately
- A web server that points its document root to `public/`

## Set up a development copy

Run these commands from the repository root. In PowerShell, use `Copy-Item`; on macOS/Linux, use `cp` instead.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Create an **empty** database, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Set `APP_URL` to the address you will use in the browser. Do not commit `.env` or paste its secrets into an issue or chat.

```powershell
php artisan migrate --no-interaction
php artisan storage:link
npm run build
php artisan app:deployment-check
```

Start the app with `composer run dev`, or run `php artisan serve`, `npm run dev`, and `php artisan queue:work` in separate terminals. Run `php artisan schedule:work` in another terminal if you want reminders, quotation expiry, and unanswered roadside reassignment to execute locally.

`php artisan db:seed` is optional **only on a fresh local database**. It creates demo accounts with known passwords; never expose those accounts to the internet and change their passwords if you keep them. Do not run `migrate:fresh` or reset an existing database to set up this project.

If you do not use demo accounts, create the first administrator with `php artisan app:bootstrap-admin`. The command asks for a strong password privately and refuses to run once an administrator exists.

## Images and documents after cloning

Uploaded profile, shop, and vehicle images live in `storage/app/public`, not in Git. `php artisan storage:link` makes them available through `public/storage`. A fresh clone needs the link **and**, when moving an existing installation, a separate copy of the old public uploads. Secure shop documents and other private files in `storage/app/private` also need a separate, access-controlled transfer. Move the matching database backup too; a database row alone cannot recreate a missing image.

If images do not appear:

1. Check that `public/storage` resolves to this installation's `storage/app/public` directory; rerun `php artisan storage:link` if it is missing.
2. Confirm the uploaded file exists in `storage/app/public` and that the web/queue processes can read it.
3. Confirm `APP_URL` matches the browser origin and run `php artisan optimize:clear` after changing `.env`.
4. On Windows, creating the storage link may require Developer Mode or an elevated terminal. Do not copy uploads into `public/` as a substitute for persistent storage.

## Optional integrations

- AI assistant: set `AI_PROVIDER=gemini`, `GEMINI_API_KEY`, and `AI_MODEL` in `.env`. Keep the key server-side.
- Password-reset email: `MAIL_MAILER=log` writes development mail to Laravel logs. Configure a real mail provider before inviting users, then send and redeem a test reset link.
- Maps and browser location: geolocation requires the user's permission and HTTPS in production (localhost is suitable for local testing).
- Background work: the default database queue requires a running `php artisan queue:work` process. Production also needs a scheduler that invokes `php artisan schedule:run` every minute.

## Tests

```powershell
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test --compact
npm run build
```

The normal PHP suite uses an in-memory SQLite database. Four concurrent-request tests are skipped unless explicitly enabled. Run them **only** against a disposable MySQL 8 test server whose credentials may create and drop databases named `autocare_concurrency_test_*`:

```powershell
$env:RUN_MYSQL_CONCURRENCY='1'
php artisan test --compact tests/Feature/ConcurrentWorkflowTest.php
Remove-Item Env:RUN_MYSQL_CONCURRENCY
```

Browser tests use an isolated temporary SQLite database and a local server on port 8877. Install the browser once with `npx playwright install chrome`, then run:

```powershell
$env:BROWSER_ISOLATED='1'
npm run test:browser
Remove-Item Env:BROWSER_ISOLATED
```

Do not point the browser tests at production. Their temporary database is deleted when the run completes.

## Production checklist

Before switching traffic to a new host:

1. Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, an HTTPS `APP_URL`, real database credentials, and a working mail provider. Keep `.env` outside source control.
2. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force --no-interaction`, `php artisan storage:link`, and `php artisan optimize` on the target host. Ensure `storage/` and `bootstrap/cache/` are writable by the application.
3. Start a supervised queue worker and configure the once-per-minute scheduler. Check `php artisan queue:failed` and `php artisan schedule:list`.
4. Run `php artisan app:deployment-check --strict`. It checks configuration, database access, storage link, and writable directories, but it cannot prove external services are operating.
5. Manually verify a delivered password-reset email, customer/shop/admin workflows, image upload and display, HTTPS, payment review, and a database **backup plus restore into a separate disposable database**. Do not restore over production as a test.
6. On a private console, run `php artisan app:bootstrap-admin` to create the first production administrator. The local demo-account seeder intentionally does not create production users.

This repository previously tracked `.env`; removing it from the current Git index does not erase old commits. Rotate any real keys or passwords that were committed, and coordinate history cleanup before publishing the repository.
