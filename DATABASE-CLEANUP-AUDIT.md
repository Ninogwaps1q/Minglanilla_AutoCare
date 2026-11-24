# Database cleanup audit

Scope: read-only inspection of the configured local MySQL-compatible database on 24 November 2026. Laravel Boost `database-schema` reported 63 tables, 700 columns, 247 indexes and 120 foreign keys. I compared the live schema to the migration history and searched each table and column name, model names and relationship conventions across the app, routes, views, JavaScript, config, jobs, commands, seeders, factories and tests. I checked weak matches against the owning paths and used aggregate counts for selected candidates. No production data or query traffic was inspected.

## Candidates for approval

| Item | Evidence | Risk and conditions |
| --- | --- | --- |
| `reports.file_path` | Migration defines it. Both admin and shop `ReportController::download` stream the report and write metadata without reading or writing a file path. Local non-null count is zero. | **Low** after confirming production paths are empty and exports do not use them externally. |

## Keep or investigate first

| Item | Evidence | Risk |
| --- | --- | --- |
| AI assistant service, widget and pages | Customer routes call `AiAssistantController` and `AiAssistantService`. The portal mounts `x-ai-chat-widget`; `AiAssistantTest` and browser tests exercise both entry points. Chat history uses the session. The old AI chat/usage tables already have a drop migration and are absent. | **High.** Active feature. Historical migrations stay. |
| `maintenance_recommendations` | Customer reminders load and display recommendations, the update route applies ownership checks, and `MaintenancePlanningTest` covers status changes. No current writer was found outside tests and the local table is empty. `service_category_id` backs an eager-loaded relation; `recommended_mileage` has no direct runtime consumer. | **Medium.** Keep the table and fields until the recommendation workflow and production rows are reviewed. An empty local table does not establish disuse. |
| Passport tables and vehicle service history | `vehicle_passport_shares` is absent after its existing drop migration. The remaining test asserts the old pages return 404. `vehicle_service_records` are written when repairs complete and read by service history and overview. Optional mileage and attachment fields have no local values. | **High** for service history. Keep all history and migration files. |
| `appointments.status = in_progress` | Repair start actively writes it and adds status history. Scheduling, reports, filters and tests depend on it. One local booking currently uses it. | **High.** Active booking state. |
| `repair_jobs.status = in_progress` | Current repair starts use `repairing`. State transitions, inventory, photos, filters and repair tests still accept or read `in_progress`. Zero local jobs currently use it. | **High.** Compatibility for older repairs. Keep unless a separate state migration is approved after production checks. |
| Legacy booking, quotation and vehicle fields | The primary appointment service, booked price, inclusion flags and scope remain in booking/invoice and repair flows. Quotation adjustment fields have no local nonzero values, and vehicle free-text fields support old and manually entered vehicles. | **High.** Booking, financial and vehicle history. Keep. |
| `shop_subscriptions`, `subscription_plans`, `users.shop_plan`, `users.shop_plan_ends_at`, `users.shop_trial_started_at`, `users.shop_trial_ends_at` | Present in the local database without matching repository migrations or direct runtime references. Current subscriptions use `shop_plans`, `shop_plan_payments` and shop-level plan fields. Local legacy counts are 0 subscriptions, 3 plans and 7 users with at least one old value. | **High.** Schema drift and possible billing/access history. Keep. Compare deployed schema and backups before proposing any migration. |
| `messages.attachment` | No application reader or writer found. The `attachment` literal in `InvoicePdfTest` is an HTTP disposition, not this column. No local non-null values. | **Medium.** Keep customer message history until retention requirements and production values are checked. |
| Legacy `reviews.status = hidden` | Admin filter still shows historical hidden rows. The public query includes only published reviews. New rule-violation removals use `removed` and write an audit record. | **High.** Keep review and audit history. |
| `payments_status_index`, `repair_jobs_status_index`, `reviews_status_index` | Each is a nonunique left prefix of a composite index. Local `EXPLAIN` nevertheless chose the shorter indexes for status-only payment/review counts and a repair status list ordered by creation time. Local index usage statistics were unavailable. | **Medium.** Keep. A matching index prefix alone does not prove redundancy. |
| Legacy address rows and `RegionSeeder` | The import preserved 392 local barangays with older codes. Existing references may still point to them. The demo seeder no longer calls `RegionSeeder`, which still assigns an older code if run directly. | **High** for address rows. **Low** for retiring the standalone seeder after approval if no external setup invokes it. |
| Framework cache, queue, sessions and authentication support | `config/cache.php`, `config/queue.php`, `config/session.php` and Laravel migrations designate these tables. Some columns are written only inside the framework. There were 13 local jobs and zero batch/failed rows at inspection. | **High.** Configured drivers, recovery tooling and framework upgrades can depend on these tables. |

## Coverage by schema area

The table groups below account for the 63 live tables. The findings above identify the columns and indexes with the strongest cleanup signals. Shared IDs, foreign keys, uniqueness and timestamps were checked as schema and ORM contracts even when their exact names did not occur in application queries.

| Tables | Evidence and disposition |
| --- | --- |
| `activity_logs`, `reports`, `notifications` | Audit events, report history and user activity feeds. Preserve rows. See the separate report-path candidate. |
| `appointments`, `appointment_reschedule_requests`, `appointment_services`, `appointment_status_histories` | Booking, rescheduling, booked service snapshots and workflow history. Keep. |
| `barangays`, `municipalities`, `provinces`, `regions` | Import, address-picker endpoints, discovery and stored address relations. Keep. |
| `emergency_requests`, `emergency_dispatch_attempts` | Roadside request and assignment history, including timeout reassignment. Keep. |
| `favorites`, `reviews` | Customer favorites and booking-linked public reviews. Keep. |
| `maintenance_recommendations`, `maintenance_reminders`, `maintenance_schedules` | Customer planner and reminder tooling. Keep. |
| `spare_parts`, `inventory_reservations`, `inventory_transactions` | Catalog, stock reservation and inventory history. Keep. |
| `invoices`, `invoice_items`, `payments` | Invoicing, checkout reconciliation, refunds and shop settlement. Keep. |
| `quotations`, `quotation_approval_records`, `quotation_items` | Estimate line items, approval history and supplemental work. Keep. |
| `repair_jobs`, `repair_job_items`, `repair_job_status_histories`, `repair_photos`, `repair_warranties` | Repair workflow, supporting evidence and aftercare history. Keep. |
| `service_cases`, `service_case_events` | Complaint and follow-up records and decisions. Keep. |
| `services`, `service_categories`, `shops`, `shop_services`, `shop_documents` | Provider discovery, registration, booking and verification. Keep. |
| `shop_plans`, `shop_plan_payments`, `shop_subscriptions`, `subscription_plans` | First two are actively used for plans and billing. Last two are schema drift listed above. Keep. |
| `message_threads`, `messages` | Customer and shop conversations, unread counts and timestamps. Keep. |
| `technicians`, `technician_assignments`, `vehicle_inspections`, `inspection_items` | Staff assignment and inspection report generation. Keep. |
| `vehicles`, `vehicle_makes`, `vehicle_models`, `vehicle_service_records` | Customer vehicle data, catalog selection and service history. Keep. |
| `users`, `social_accounts`, `password_reset_tokens`, `sessions` | Identity, OAuth, password resets and sessions. Keep. Admin sign-in verification is outside cleanup scope. |
| `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel migration ledger, locks, queue and job infrastructure. Keep. |

Cleanup stops here for approval. Before any approved production migration, take and verify a database backup. A rollback can restore schema definitions but cannot reconstruct values removed by a drop.
