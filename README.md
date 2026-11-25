# Minglanilla AutoCare Hub

Laravel application for customers, auto shops, independent mechanics, and administrators. It covers nationwide provider discovery, appointments, estimates, repairs, invoices and payments, roadside assistance, reviews, and maintenance reminders.

## Requirements

-   PHP 8.2 or newer with the extensions required by `composer install`, including PDO MySQL, mbstring, fileinfo, OpenSSL, and ZipArchive for backups
-   Composer 2
-   Node.js `^20.19.0` or `>=22.12.0` and npm (required by the installed Vite version)
-   MySQL 8 for the intended production database; a local MariaDB setup can run the app, but MySQL 8 concurrency sign-off must be done separately
-   A web server that points its document root to `public/`

## Set up a development copy

Run these commands from the repository root. In PowerShell, use `Copy-Item`; on macOS/Linux, use `cp` instead.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Create an **empty** database, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Set `APP_URL` to the address you will use in the browser. Do not commit `.env` or paste its secrets into an issue or chat.

Start the database server before running migrations or opening the app. If login shows `SQLSTATE[HY000] [2002]` / "connection refused", check that MySQL is running and listening on the configured host and port; this is a stopped/unreachable server, not a reason to reset the database.

```powershell
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
php artisan catalog:import-vehicles --no-interaction
php artisan storage:link
npm run build
php artisan app:deployment-check
```

Start the app with `composer run dev`, which also starts the scheduler. Alternatively, run `php artisan serve`, `npm run dev`, `php artisan queue:work`, and `php artisan schedule:work` in separate terminals. Keep the scheduler running for scheduled subscription activation, background PayMongo checkout/refund checks, reminders, quotation expiry, and unanswered roadside reassignment. Payment checks need both the PayMongo secret key and webhook secret; they recover missed callbacks without an open browser.

`php artisan db:seed` loads the nationwide address, service, and vehicle catalogs and can be rerun. For a private local demo, run `php artisan db:seed --class=DemoSeeder --no-interaction` separately. Demo accounts have known passwords. Do not run `migrate:fresh` or reset an existing database to set up this project.

If you do not use demo accounts, create the first administrator with `php artisan app:bootstrap-admin`. The command asks for a strong password privately and refuses to run once an administrator exists.

### Address and vehicle catalogs

`db:seed` and `composer setup` run the bundled PSGC import. To refresh addresses alone, run `php artisan catalog:import-psgc --no-interaction`. The snapshot contains all 18 regions, 82 provinces, 149 cities, 1,493 municipalities, and 42,010 barangays as of 30 September 2026. It uses [ph-psgc's June 2026 normalization](https://github.com/Tenasia/ph-psgc/tree/2026-Q2) with the [PSA's September name correction](https://psa.gov.ph/classification/psgc) and legacy correspondence. Reruns retain older address records and existing IDs.

Shop and mechanic registration, customer profiles, mechanic booking addresses, and roadside pickup addresses use the nationwide catalog. In the directory, search by provider, city, or province, filter by area, or share your location to see nearby providers. Google Maps can center the registration pin picker on a selected area when the Maps JavaScript API and Geocoding API are enabled. Providers still place their pin or enter coordinates.

The vehicle seeders add local makes and models. `catalog:import-vehicles` fetches additional models for configured makes from the NHTSA vPIC API and links matching older vehicle records while keeping their original text. The vehicle picker uses the local catalog after import.

## Images and documents after cloning

Uploaded profile, shop, and vehicle images live in `storage/app/public`, not in Git. `php artisan storage:link` makes them available through `public/storage`. A fresh clone needs the link **and**, when moving an existing installation, a separate copy of the old public uploads. Secure shop documents and other private files in `storage/app/private` also need a separate, access-controlled transfer. Move the matching database backup too; a database row alone cannot recreate a missing image.

If images do not appear:

1. Check that `public/storage` resolves to this installation's `storage/app/public` directory; rerun `php artisan storage:link` if it is missing.
2. Confirm the uploaded file exists in `storage/app/public` and that the web/queue processes can read it.
3. Confirm `APP_URL` matches the browser origin and run `php artisan optimize:clear` after changing `.env`.
4. On Windows, creating the storage link may require Developer Mode or an elevated terminal. Do not copy uploads into `public/` as a substitute for persistent storage.

## Optional integrations

-   Google keys: set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env` for OAuth, and `GOOGLE_MAPS_API_KEY` for browser maps. Enable Maps JavaScript API for maps, Geocoding API for centering provider pins on the selected area, and Routes API for directions. Enable billing for a production key and restrict the browser key to the allowed site origins and APIs. Keep real values out of `.env.example` and source control. Run `php artisan config:clear` after changing the local values.
-   Google login on another device: `localhost` always refers to the device opening the browser. Use one shared HTTPS domain or tunnel URL as `APP_URL`, register that exact origin followed by `/auth/google/callback` in the Google OAuth client's **Authorized redirect URIs**, then run `php artisan config:clear`. Open the shared HTTPS address on every device before signing in. Google does not allow ordinary LAN IP callback URLs. Keep `SESSION_DOMAIN=null`; the app trusts proxy headers only from a tunnel running on the same computer (`127.0.0.1` / `::1`). `GOOGLE_REDIRECT_URI` is not used; the callback is derived from `APP_URL` to keep both addresses consistent.
-   AI assistant: set `AI_PROVIDER=gemini`, `GEMINI_API_KEY`, and `AI_MODEL` in `.env`. Keep the key server-side.
-   Password-reset email: `MAIL_MAILER=log` writes development mail to Laravel logs. Configure a real mail provider before inviting users, then send and redeem a test reset link.
-   Maps and browser location: geolocation requires the user's permission and HTTPS in production (localhost is suitable for local testing).
-   Background work: the default database queue requires a running `php artisan queue:work` process. Production also needs a scheduler that invokes `php artisan schedule:run` every minute.

## Install AutoCare

Open the app over HTTPS (or localhost for development). When supported by the browser, choose **Install app** in the public footer or the signed-in account menu. On iPhone or iPad, open Share and choose **Add to Home Screen**. After the first successful online visit, the app can show an offline notice if a page fails to load. Bookings, accounts, and payments need a connection.


## Privacy and notifications

The public Privacy Policy and Terms are drafts. Set `PRIVACY_CONTACT_EMAIL` to the designated privacy inbox and have a Philippine lawyer review the text and retention periods before launch. Account holders can submit access and deletion requests from their profile. Use **Privacy requests** in the admin workspace to verify the requester, arrange secure fulfillment, and record the outcome. Retain required invoice, payment and dispute records. Marking a request complete records the decision after fulfillment.

Set `SEMAPHORE_API_KEY` and `SEMAPHORE_SENDER_NAME` in the deployment environment for SMS. The queue worker sends only opted-in important alerts to saved Philippine mobile numbers. The default is five attempts per user per Philippine day, adjustable with `SMS_DAILY_LIMIT`. Review failures in the application logs. `WEB_PUSH_PUBLIC_KEY` enables subscription registration over HTTPS for supported browsers. Browser delivery still needs a push transport.

## Uptime and scheduler monitoring

Point an external uptime monitor at `/up` and alert on non-2xx responses. It checks the database and configured queue backend. On servers with an asynchronous worker, set `MONITOR_QUEUE_HEARTBEAT=true` after scheduling `schedule:run` every minute and starting the worker. Use a shared persistent cache so the worker and web server can see the last heartbeat. Allow up to five minutes for the first worker probe. Monitor queue backlog and failed jobs separately.

Set `MONITOR_ALERT_EMAIL` for alerts when the PayMongo reconciliation, subscription activation, or reminder commands fail. Alerts are limited to one email per command per hour. Watch the rotating JSON `operations-*.log` files for `schedule.failed`, `schedule.alert_failed`, `health.check_failed`, and `application.exception`. Watch for stale PayMongo checkouts and failed notification deliveries as well.

## Tests

```powershell
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test --compact
php artisan test --compact tests/Feature/PwaAssetsTest.php
npm run build
```

The normal PHP suite uses an in-memory SQLite database. The four concurrent-request tests run separately through `phpunit.concurrency.xml`, so the normal suite does not report them as skipped warnings. Run them **only** against a disposable MySQL/MariaDB test server whose credentials may create and drop databases named `autocare_concurrency_test_*`. The tests use the configured MySQL connection, create a temporary database per case, and remove it afterward. PayMongo requests are faked; no real checkout or charge is created.

```powershell
composer test:concurrency
```

Browser tests use an isolated temporary SQLite database and a local server on port 8877. Install the browser once with `npx playwright install chrome`, then run:

```powershell
$env:BROWSER_ISOLATED='1'
npm run test:browser
Remove-Item Env:BROWSER_ISOLATED
```

Do not point the browser tests at production. Their temporary database is deleted when the run completes.

## Private backups and recovery

Run `php artisan app:backup` to create a ZIP under `storage/app/private/backups`. It includes a consistent MySQL/MariaDB dump, `storage/app/public` uploads, and `storage/app/private` files, excluding old backups. The dump uses `mysqldump`; set `BACKUP_DUMP_BINARY` to its absolute executable path if it is not on PATH (for example `C:\xampp\mysql\bin\mysqldump.exe`). A failed dump leaves no completed ZIP. Set `BACKUP_ENABLED=true` only after a manual backup succeeds; the scheduler then runs it daily at 02:30 server time. For an off-host copy, configure a remote filesystem disk and set `BACKUP_OFFSITE_DISK` to its name. Set `BACKUP_RETENTION_DAYS` to a positive number to prune only expired local ZIP backups, and `BACKUP_ALERT_EMAIL` to receive failure alerts through the configured mail transport. Both options are disabled by default. Verify a restore on disposable data before relying on the backup.

To verify recovery, copy a backup to a safe test machine, inspect `manifest.json`, check `database.sql` against its SHA-256 hash, and restore the SQL **only into a new disposable database**. Extract `uploads/public` and `uploads/private` to that test installation's `storage/app` directories, then run `php artisan storage:link` and check a real image and protected document. Never overwrite the production database during a restore drill. Preserve `APP_KEY` separately in a secret manager because the archive does not include `.env` or encryption keys.

## Production checklist

Before switching traffic to a new host:

1. Set `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, real database credentials, and a working mail provider. Generate a unique `APP_KEY` for a new installation; preserve the original key when moving an existing installation so encrypted data remains readable. Keep `.env` outside source control.
2. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, and `php artisan migrate --force --no-interaction` on the target host. Run `php artisan db:seed --force --no-interaction` to load the nationwide address, service, and vehicle catalogs before accepting registrations. Run `php artisan catalog:import-vehicles --no-interaction` to add the vPIC models. Then run `php artisan storage:link` and `php artisan optimize`. Ensure `storage/` and `bootstrap/cache/` are writable and the web server serves the manifest and worker from the public root.
3. Start a supervised queue worker and configure the once-per-minute scheduler. Check `php artisan queue:failed` and `php artisan schedule:list`.
4. Run `php artisan app:deployment-check --strict`. It checks configuration, database access and pending migrations, storage link, writable directories, and whether scheduled backups are enabled, but it cannot prove external services are operating.
5. Manually verify a delivered password-reset email, customer/shop/admin workflows, image upload and display, HTTPS, payment review, and a database **backup plus restore into a separate disposable database**. Do not restore over production as a test. Run a short real-user usability session on desktop and mobile; automated browser checks cannot prove that the interface is easy to understand.
6. On a private console, run `php artisan app:bootstrap-admin` to create the first production administrator. The local demo-account seeder intentionally does not create production users.

This repository previously tracked `.env`; removing it from the current Git index does not erase old commits. Rotate any real keys or passwords that were committed, and coordinate history cleanup before publishing the repository.
