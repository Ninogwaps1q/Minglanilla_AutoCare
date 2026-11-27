# Independent mechanic improvements

## Audit and implementation order

1. Provider identity and permissions. The application already had a `mechanic` user role, the `independent_mechanic` provider type, and a private document checklist. The dashboard and several Premium business routes still treated the mechanic as a shop owner.
2. Booking and service pricing. The appointment flow already had shop locking, service durations and snapshots, cancellations, and customer-initiated time changes. It needed personal capacity enforcement, travel time, coverage validation, fee snapshots, and mechanic-initiated proposals.
3. Daily work and customer discovery. The app had no dedicated mechanic workspace, equipment records, consent-based portfolio, or personal earnings view. The directory already supported provider type, location and other shop filters.
4. Roadside, verification, billing, and notifications. Existing private document storage, dispatch, map directions, PayMongo, reviews, queue, and SMS delivery history gave these features an integration point.

## Provider behavior

| Area | Auto repair shop | Independent mechanic |
| --- | --- | --- |
| Workspace | Shop dashboard, staff, technicians, stock, assignments, and business reports | Personal dashboard, requests, repairs, roadside, availability, coverage, tools, portfolio, and earnings |
| Application | Business permit and mayor’s permit checklist | Personal profile with experience, specializations, vehicle types, credentials and work history. Government ID and qualifications or work evidence are uploaded privately after registration. |
| Schedule | Existing shop capacity, hours, and calendar | One overlapping appointment slot and one active customer visit at a time, with a configurable break and travel buffer |
| Booking | Existing shop appointment and approval flow | Service location and optional configured radius, itemized quote review, accept or decline, propose another time, en route, arrival, inspection and repair |
| Billing | Existing shop pricing and PayMongo | Same billing and payment records, with editable mechanic booking limits, fees, appointment reminders, portfolio limits and live tracking entitlements |
| Public address | Approved providers can publish their shop address | Approved public profiles show the approximate service area and never serialize the private base in the directory or quote response |

## Implemented changes

- Added a mechanic dashboard with today’s appointments, pending requests, active repairs and roadside cases, upcoming home services, completed work, rating, plan and confirmed month-to-date receipts.
- Added profile and availability controls, working-hours and dates-off entry points, service radius presets, selection of multiple barangay, city or province restrictions, and optional home service booking. Coverage coordinates and price estimates use the rounded public service-area center. Older profiles retain their address-only booking flow until a radius is configured.
- Added a quote check that recomputes service, inspection, home visit and travel amounts on the server. Charges the customer sees are saved in cents and carried into dedicated repair line items and the existing invoice. Additional repair work follows the existing estimate approval workflow.
- Added mechanic time proposals using the existing reschedule records. The existing appointment holds its slot until the customer accepts. The new slot is checked again under the provider lock. Mechanics mark travel and arrival before starting a newly booked mobile repair.
- Added equipment and carried-part records, a portfolio with explicit publication consent, and earnings with confirmed receipts, refunds, recorded processing and platform fees, expense records, released transfers, open balances, billed service/travel/parts totals, and CSV.
- Added separate admin verification queues and auditable Under Review and Additional Requirements Needed decisions. Existing approval, suspension and reactivation actions remain available. Admin operations can filter by provider type and see failed SMS counts for the day.
- Added directory filters for home service, declared vehicle type and maximum listed service price. Mechanic listings include personal availability and service area. Approved mechanic profiles show credentials, portfolio, completed repair count and published reviews.
- Added explicit roadside pickup consent and optional responder location sharing only while en route. Only the customer on the request can retrieve the responder’s latest fix. Arrival, cancellation, completion or reassignment clears it. Stale fixes are hidden after two minutes. Existing in-page directions show the route and estimate the drive time with Google Maps. The customer sees distance from the latest reported position, with a contact fallback.
- Added Semaphore and network-free SMS demo modes. The extra mechanic SMS events use the existing preferences, Philippine phone validation, daily limit, after-commit dispatch and delivery ledger. The events cover new bookings, confirmation, upcoming visits, en route, assigned roadside requests, unanswered estimates, plan renewal reminders, maintenance and invoices due. They require an explicitly enabled SMS category.

## Data and permissions

- Migration `2026_11_27_143343_add_independent_mechanic_workspace.php` adds nullable mechanic profile, settings, tool and portfolio fields to `shops`, a booking quote and travel timestamps to `appointments`, and entitlements to `shop_plans`. Migration `2026_11_27_225421_add_mechanic_proposals_and_roadside_location.php` adds the provider-proposal flag and roadside tracking, consent and arrival fields.
- `mechanic_expenses` is the only new table. It links to the owning provider and indexes provider and date. No existing records are converted or deleted by the migration.
- Shop-only inventory, stock movements, employee assignment and business reporting require the appropriate shop role. The mechanic workspace and record mutations require the owning mechanic. Document downloads use the existing private owner/admin authorization.
- Portfolio images use private storage and a scoped public route that checks provider approval, active status, and project consent. A removed project loses its public URL.
- Expired subscriptions apply Free entitlements to new mechanic requests. Existing booked charges, ongoing work, standard roadside status and emergencies remain accessible.

## Routes and files

| Change | Files and route names |
| --- | --- |
| Mechanic workspace | `ShopDashboardController`, `MechanicWorkspaceController`, `resources/views/shop/mechanic/`, app layout. Existing `shop.dashboard` plus `shop.mechanic.*` under `/shop/mobile-work`. |
| Availability, pricing and booking | Customer and shop appointment controllers and views, `StoreAppointmentRequest`, `AppointmentSchedule`, `MechanicBooking`, appointment models, both service-case controllers, repair controllers and view. Adds `customer.appointments.mobile-quote`, `shop.appointments.propose`, `customer.appointments.proposal.decide` and `shop.mechanic.travel`. |
| Registration, admin and discovery | `ShopRegistrationController`, `ShopRegistrationRequest`, `ShopVerificationController`, `ShopPlanController`, `MonitoringController`, `PublicShopController`, provider, plan and admin views. Adds `admin.shops.mechanic-review` and `shops.portfolio.photo`. |
| Roadside | Both emergency request controllers and views, `StoreEmergencyRequest`, `EmergencyRequest`, `EmergencyDispatchService`, `roadside-tracking.js`. Adds `shop.emergency-requests.location` and `customer.emergency-requests.tracking`. |
| Delivery and scheduling | `SmsService`, `SendEventSms`, `Notification`, `ServiceAlert`, `CustomerWorkflowAlerts`, `CustomerWorkflowNotification`, workflow, reminder and roadside cleanup commands, `config/services.php`, `.env.example`, and locale files. |
| Shared UI and permissions | `EnsureShopStaffPermission`, `EnsureShopAcceptingWork`, `EnsureShopPlan`, address and location picker components, app JavaScript, `location-picker.js` and built asset manifest. |
| Coverage | `IndependentMechanicTest.php`, `EmergencyAssistanceTest.php`, `NationwideProviderWorkflowTest.php`, `AdminMonitoringTest.php`, the PayMongo fixture in `PlatformUpgradesTest.php`, the currency assertion in `ServiceExtensionsTest.php`, and Playwright authenticated pages and isolated fixtures. |

## Setup and verification

- Both migrations are recorded in the local MySQL database. On other installations, back up the target database and apply pending migrations with the project’s normal `php artisan migrate` step.
- The final full Laravel suite passed all 549 tests and 3,513 assertions with paid integrations disabled or mocked.
- The 195-test regression run covered shop booking and inventory, PayMongo billing, quotations, aftercare, provider discovery, notifications, and the new mechanic flows. A follow-up run passed 98 tests and 750 assertions after the later scheduling, coverage, SMS and monitoring changes.
- The final focused aftercare and mechanic run passed 22 tests and 286 assertions. The two corrected billing and service-extension suites passed 17 tests and 153 assertions. The booking prefill check and PWA asset test also passed individually.
- `npm run build`, `vendor/bin/pint --dirty --format agent` and `git diff --check` passed. Desktop and mobile screenshots were inspected during the isolated Chrome runs.
- Isolated Playwright passed all 15 workspace, quote, map-direction, consent and GPS-denial cases across 1440px, 390px and 320px. Two additional mobile quote checks passed at 390px and 320px. They verified that the total matches the itemized quote and is visible before confirmation.
- The final 320px navigation retest passed in light and dark mode. The mobile mechanic shortcut uses the shorter Chat label so it fits on one line.
- Keep the queue worker and scheduler running for reminders, delivery and roadside reassignment. Device location needs HTTPS or localhost. Google Maps and PayMongo reuse their existing environment settings.
- To test SMS without charges, use `SMS_DRIVER=demo` and `SMS_MECHANIC_ALERTS=true`. Demo logs `simulated` and sends no network request. The extra events default to off. Live Semaphore needs the existing credentials, the intended queue configuration, and users’ individual SMS consent and category choices.

## Remaining work and verification limits

- Semaphore automatically retries once after an explicit rate-limit rejection when the retry delay is short. Ambiguous delivery attempts are recorded for review. The optional IPROG integration and new admin operational SMS notices are outstanding.
- Separate emergency surcharges and customer-facing driving ETA are outstanding. Current customer tracking reports straight-line distance and the assigned responder sees Google’s route and its estimate.
- Existing shared admin analytics still cover both provider types. Operations and verification have provider-type filters.
- Core labels and SMS templates are available in English, Bisaya and Tagalog. Some detailed workspace form copy is still in English.
- Live carrier, PayMongo, Google Maps and physical-device GPS verification require configured environments. Feature tests and the isolated Chromium suite use fixtures instead of paid services.
