# Cleanify Project Documentation

## Scope and source of truth

This document describes the code currently present in this repository. It was derived from Laravel routes, migrations, models, controllers, views, configuration, seeders, and tests—not from assumptions about a deployed database or service. Values that could be secrets are intentionally omitted.

Cleanify is a server-rendered municipal garbage-collection/community-reporting application centred on Surigao City service zones. It has separate regular-user and administrator web areas. There is no `routes/api.php` file and no mobile API currently implemented.

## Project layout

| Path | Purpose |
| --- | --- |
| `app/Http/Controllers/Client` | Regular-user dashboard, schedule, truck tracker, reports, settings, profile, and notification inbox. |
| `app/Http/Controllers/Admin` | Admin dashboards and management of users, reports, schedules, trucks, service zones, settings, and user-to-user reports. |
| `app/Models` | Eloquent models for the application’s active tables. |
| `app/Notifications`, `app/Mail`, `app/Services` | Database/mail notifications, collection-reminder mail, SMS delivery, schedule occurrence logic, and activity logging. |
| `app/Console/Commands` and `routes/console.php` | The reminder command and its scheduler registration. |
| `app/Http/Middleware` | Admin/non-admin/banned-user access control and report throttling. |
| `database/migrations` | Schema history and the effective fresh-install schema described below. |
| `database/seeders` | Development/sample users, reports, trucks, schedules, and an admin user. |
| `resources/views` | Blade pages, layouts, components, email, and inline page JavaScript. |
| `resources/js`, `resources/css` | Vite entrypoints, global interaction helpers, Alpine initialization, and Tailwind CSS. |
| `routes/web.php`, `routes/auth.php` | All HTTP routes. |
| `config/routes.php` | A static Surigao City zone-coordinate list used by `UsersAndReportsSeeder`; live UI/controller assignments instead query `service_zones`. |

## Technology and dependencies

- PHP `^8.2`, Laravel Framework `^12.0`, Laravel Tinker.
- Laravel Breeze is installed for authentication scaffolding. Tests use Pest 4/Pest Laravel and PHPUnit configuration.
- Vite 7 builds `resources/css/app.css` and `resources/js/app.js`; Tailwind CSS 3 with `@tailwindcss/forms` supplies styling.
- Alpine.js is started globally; Axios is made available as `window.axios`. The views mainly use `fetch` directly.
- Font Awesome Free is an npm dependency.
- Leaflet 1.9.4, Leaflet.markercluster 1.5.3, OpenStreetMap tiles, and marker-image assets are loaded from external CDNs in tracker/report/service-zone views. The admin dashboard loads Chart.js from a CDN.
- The configured defaults in source are database sessions/cache/queue and local filesystem storage. The actual drivers are environment-controlled.

## Authentication, roles, and access control

Laravel session authentication is used. Registration creates a non-admin user by default, logs that user in, and redirects regular users to `/dashboard`; the code also contains an admin redirect if a newly created user were an admin. Standard Breeze login, logout, password confirmation, password reset, and email-verification routes exist.

Roles are a boolean `users.is_admin`:

- The `admin` middleware allows only authenticated `isAdmin()` users into `/admin/*`; non-admins are redirected to the regular dashboard.
- The `not.admin` middleware protects regular-user pages and redirects admins to the admin dashboard.
- The `not.banned` middleware logs out a user with a non-null `banned_at` and invalidates the session. Login also rejects banned users.
- Admins cannot remove their own admin role, delete themselves, or ban themselves; admins cannot ban another admin.
- `ThrottleReports` limits report creation to 10 attempts per user/IP per hour. Like and comment routes additionally use Laravel throttles of 60/minute and 30/minute respectively.

All regular-user application routes require `auth`, `not.admin`, and `not.banned`; admin routes require `auth` and `admin`. The landing page (`/`) and guest auth pages are public.

## Effective database schema

The following reflects the forward migration sequence in this repository. Every listed table with `timestamps()` has `created_at` and `updated_at`.

| Table | Columns and constraints |
| --- | --- |
| `users` | `id`; `name`; unique `email`; nullable `email_verified_at`; `password`; nullable `remember_token`; `is_admin` default false; nullable `banned_at`; notification booleans `email_notifications` (true), `sms_notifications` (false), `push_notifications` (true); nullable `service_area`; nullable JSON `notification_preferences`; nullable `phone`, `address`; privacy settings `show_email` (false), `location_sharing` (true), `profile_visibility` (`public`); `tracker_refresh_interval` (30); `language` (`en`); nullable `last_login_at`. |
| `password_reset_tokens` | Primary-key `email`; `token`; nullable `created_at`. |
| `sessions` | String primary-key `id`; nullable/indexed `user_id`; nullable `ip_address` and `user_agent`; `payload`; indexed integer `last_activity`. |
| `cache`, `cache_locks` | Laravel database cache keys, values/owners, and expirations. |
| `jobs`, `job_batches`, `failed_jobs` | Standard Laravel database queue, batch, and failed-job storage. `failed_jobs.uuid` is unique. |
| `reports` | `id`; required FK `user_id` → `users` (cascade); nullable `location`; nullable decimal(10,8) `latitude`, decimal(11,8) `longitude`; `description`; nullable `image_path`; enum `status` (`pending`, `resolved`, `rejected`, default pending); enum `priority` (`low`, `medium`, `high`, `critical`, default medium); nullable `admin_notes`, `rejection_reason`; nullable FK `resolved_by` → `users` (set null); nullable `resolved_at`. |
| `report_likes` | `id`; cascade FKs `report_id`, `user_id`; unique pair (`report_id`, `user_id`). |
| `report_comments` | `id`; cascade FKs `report_id`, `user_id`; `comment`. |
| `report_followers` | `id`; cascade FKs `report_id`, `user_id`; unique pair (`report_id`, `user_id`). |
| `user_reports` | `id`; cascade FKs `reporter_id`, `reported_user_id` → users; enum reason (`spam`, `harassment`, `inappropriate_content`, `fake_account`, `other`); nullable `description`; enum status (`pending`, `reviewed`, `dismissed`, `action_taken`); nullable `admin_notes`; nullable `reviewed_by` FK → users (set null); nullable `reviewed_at`; unique (`reporter_id`, `reported_user_id`). |
| `schedules` | `id`; `area`; enum `schedule_type` (`recurring`, `specific_date`, default recurring); nullable `specific_date`; nullable `days`; `time_start`, `time_end`; `truck` (a string, not an FK); enum `status` (`active`, `pending`, `inactive`, default pending). |
| `service_zones` | `id`; unique `name`; nullable `barangay`; `status` default `active` (a string, not an enum); nullable decimal coordinates. |
| `trucks` | `id`; unique `code`; `driver`; `route` (a string, not an FK); enum status (`active`, `on_break`, `offline`, `maintenance`, default offline); nullable decimal coordinates; nullable `last_updated`. |
| `truck_locations` | `id`; cascade FK `truck_id`; decimal latitude/longitude; `recorded_at`; indexes on (`truck_id`, `recorded_at`) and `recorded_at`. |
| `notifications` | Laravel database notification table: UUID primary `id`, `type`, polymorphic notifiable columns, serialized `data`, nullable `read_at`, timestamps. |
| `activity_logs` | `id`; nullable `user_id` FK (set null); `action`; nullable polymorphic-style `model_type`/`model_id`; nullable description, JSON `changes`, IP address, and user agent; indexes on user/date, model pair, and action. |
| `sms_notifications` | `id`; cascade FKs `user_id`, `schedule_id`; `collection_date`; `reminder_type` default `one_day_before`; phone snapshot; message; scheduled/sent timestamps; status and nullable error; unique per user/schedule/collection-date/reminder-type. |
| `email_notifications` | Same reminder audit structure, with email address and subject instead of phone snapshot; also indexed on (`status`, `collection_date`). |

### Relationships implemented in models

- `User` has many `Report`; Laravel’s `Notifiable` trait supplies notification relationships. The reverse relationships for likes/comments/follows are used by queries but are not declared as methods on `User`.
- `Report` belongs to its author (`user`) and resolver (`resolver`, via `resolved_by`), has many likes/comments, and belongs to many follower users through `report_followers`.
- `ReportLike` and `ReportComment` each belong to a report and user.
- `UserReport` belongs to reporter, reported user, and reviewer (all users).
- `Truck` has many `TruckLocation`; `recentLocations()` is ordered ascending and restricted to the last 24 hours. A location belongs to its truck.
- `SmsNotification` and `EmailNotification` each belong to a user and a schedule.
- `ActivityLog` belongs to its actor and defines a polymorphic `model()` relation.
- `Schedule`, `ServiceZone`, and `Truck` intentionally store their operational links as matching strings (`area`/`route`/`truck`), not foreign keys.

## Models and business rules

`Schedule` formats time ranges and status labels. It supports recurring textual day lists or a one-off date. `ScheduleReminderService` parses recurring days split by commas, `&`, or `and` and checks against the English day name.

`ServiceZone` exposes `display_name`: `name` alone when no separate barangay is present (or it already occurs in the name), otherwise `name - barangay`. Controllers use active database service zones to populate current route/area selection.

`Truck` supplies display/status helpers and location-history relations. Updating a truck location updates its current coordinates and inserts a history record only if no prior point exists or either coordinate differs by more than `0.0001`.

`Report` supports pending/resolved/rejected state and low/medium/high/critical priority. Report images are stored under the public disk’s `reports` directory after image/MIME validation.

## Current user features

- Dashboard: a user’s report totals (all/pending/resolved) and the 10 newest community reports with recent comments and like/comment counts.
- Community reporting: create a pending report with required description, optional location text, optional paired coordinates, and optional JPEG/JPG/PNG/GIF/WebP image up to 4 MiB. Users can like, comment (500 characters), follow/unfollow, browse paginated reports, and retrieve detailed report JSON with a status timeline, coordinate, recent comments, counts, and up to three same-location related reports.
- Profile: view and edit basic profile details and manage the user’s own reports. (The corresponding controller supports JSON or redirect responses.)
- Garbage schedule: select a service area, see active schedules, next pickup and five upcoming pickups, and toggle email/SMS/push flags. Next dates are calculated from recurring day text or `specific_date`.
- Truck tracker: map/list all trucks, polling current truck data and 24-hour route history. Active service zones with coordinates are highlighted. If no truck locations exist, the default map centre is the hard-coded Surigao City coordinate used by the controller.
- Notification inbox: paginate database notifications, filter all/unread and known categories, hide muted categories by default, mark one/all read, dismiss, and configure category preferences.
- Settings: update email/phone/service area, password, global and category notification preferences, privacy settings, tracker interval/language preferences, inspect/revoke non-current database sessions, download selected account/report/comment data as JSON, and permanently delete the account after password confirmation.
- User moderation: submit one report against another user per reporter/reported-user pair.

## Current administrator features

- Dashboard: administrative statistics/charts supplied by `Admin\\DashboardController` and its Blade view.
- User management: search/paginate users, edit name/email/admin role, delete users, ban/unban non-admin users, and write applicable activity logs.
- Report moderation: search/filter/paginate reports; set priority; resolve/reject individual pending reports; bulk resolve/reject selected reports; record admin details, resolver, and timestamp; write activity logs. Individual state transitions notify both owner and followers; bulk actions call only the owner notification helper.
- Schedule management: CRUD schedules, filter/search/status statistics, and select active database service zones/trucks. Creating an active schedule, or updating one after an area change or activation, notifies non-admin users with exactly matching `service_area`.
- Truck tracker: CRUD trucks, set coordinates/status, persist materially changed route points, live data JSON, 24-hour route-history JSON, and maps with clustering/auto-refresh.
- Service zones: CRUD name/barangay/status/coordinates. Deletion is refused if a truck route or schedule area exactly matches the zone’s display name.
- Admin settings: update the admin’s profile/password and global notification flags. The admin settings Blade currently renders the SMS and push checkboxes disabled.
- User-report moderation: search/filter reports of users, change status/notes/reviewer/time, and notify the reporter when a non-pending status changes. The commented code does not automatically ban the reported user.

## HTTP routes

The complete registered set is 82 routes (verified with `php artisan route:list`). These are grouped below; all names are in `routes/web.php`/`routes/auth.php`.

| Area | Paths/actions |
| --- | --- |
| Public/guest | `GET /`; register/login GET+POST; password-reset request and reset GET+POST. |
| Auth support | Email verification prompt/verification/resend; password confirmation GET+POST; `PUT /password`; `POST /logout`. |
| User pages | `GET /dashboard`, `/garbage-schedule`, `/tracker`, `/notifications`, `/community-reports`, `/settings`, `/profile`. |
| User JSON/AJAX-style endpoints | Schedule service-area and notification updates; tracker data and per-truck route history; notification mark/read/delete/preferences; report create/like/comment/comments/detail/follow; profile report update/delete; settings updates, session revoke, and data download; user-report creation. |
| Admin pages/actions | Under `/admin`: dashboard; users (list/update/delete/ban/unban); reports (list, resolve/reject/bulk/priority); schedules CRUD; trucks CRUD/location/data/history; service-zone CRUD; admin settings actions; user-report list/update. |

Route model binding is used for client report and truck detail endpoints; admin endpoints generally accept an ID and call `findOrFail`. No API authentication/token routes are registered.

## Notifications, mail, and SMS

There are two distinct systems:

1. Laravel database/mail notifications: `ScheduleCreatedNotification`, `ReportResolvedNotification`, `ReportRejectedNotification`, and `UserReportReviewedNotification` write database notifications and also select mail when global email notifications and the applicable category flag permit it. They are imported with queue-related traits/interfaces but do **not** implement `ShouldQueue`; their notifications are therefore not declared queued by these classes. `TestEmailNotification` is mail-only and is not invoked by a route/controller found in this code.
2. One-day-before schedule reminders: `sms:send-schedule-reminders` finds active schedules occurring tomorrow (or `--date=Y-m-d`), then selects non-admin users with a matching `service_area`. It independently processes enabled SMS and email users whose `schedule_reminders` preference is not false. It writes channel-specific audit records before sending, and unique constraints make repeated scheduler invocations idempotent per collection date.

SMS normalizes numbers with the configured default country code. Its `log` driver records a simulated message and returns false, so the audit record becomes `simulated`; `twilio` sends using Twilio’s Messages endpoint only when required configuration is present. Email reminders use `GarbageCollectionReminder` and `resources/views/emails/garbage-collection-reminder.blade.php`.

Mail and SMS credentials/configuration come only from environment variables. Do not put credential values in source control or this document.

## Scheduled command and background work

`routes/console.php` schedules `sms:send-schedule-reminders` every minute, in `config('sms.timezone')`, with `withoutOverlapping()`. The code comment explains the frequent check is intended to let an external Windows scheduler recover a missed check. The command’s `--dry-run` prints eligibility without creating audit rows or sending/logging messages.

The composer `dev` script launches a Laravel server, `queue:listen --tries=1`, and Vite concurrently. Queue storage defaults to the database, but the reminder command itself sends synchronously.

## Blade and JavaScript implementation notes

- `resources/js/app.js` installs global modal, dropdown, mobile-menu, admin-menu, and toast functions. Toast body content is inserted as HTML.
- The report, profile, notifications, settings, schedule, and tracker screens use inline JavaScript with `fetch`, CSRF tokens, and UI fallbacks to regular form submissions in several notification flows.
- Leaflet powers report coordinate picking/details, service-zone coordinate picking, and user/admin truck maps. The tracker uses marker clustering, route polylines, and timed polling; maps and third-party tiles require outbound browser access.
- The schedule view calculates countdowns/displays client-side from controller-supplied ISO dates and can update service area/notification toggles asynchronously.
- Reusable Blade components include inputs, selects, textareas, buttons, alerts, badges, modals, spinners, skeletons, mobile menus, sidebars, and a file-upload preview component.

## Removed and historical functionality

Five source migrations created `collection_records`, `waste_segregations`, `recyclable_materials`, `recycled_products`, and `showcases`. The later `2026_09_07_000009_remove_waste_management_tables` migration drops all five in dependency order and deliberately makes rollback impossible by throwing a `LogicException`. No models, controllers, or routes for those tables are present. On a database migrated forward through that later migration, they are not part of the current schema; an older database that has not run it cannot be inferred from the repository alone.

`config/routes.php` retains a static 15-zone Surigao City list. It is used by `UsersAndReportsSeeder`; current operational controllers instead use `service_zones` records. The admin schedule view also contains client-side removal of options marked as legacy schedule areas.

## Confirmed issues and implementation gaps

- `User` does not implement Laravel’s `MustVerifyEmail` contract (the import is commented out), but email-verification routes/controllers are registered and call methods such as `hasVerifiedEmail()`. These routes are not protected by the `verified` middleware, and the verification flow is internally inconsistent with the current User model.
- The app has no JSON API namespace, API auth, token model, versioning, or `api.php` routes. Existing JSON endpoints are session-authenticated web routes and return view-oriented payloads.
- Schedule/truck/service-zone associations are display-name strings. Renaming a service zone does not update schedules, trucks, or users, and deletion guards only exact current display-name matches.
- Global push-notification switches and UI preferences exist, but no push delivery provider, browser service worker, or push notification class is present.
- SMS uses a simulated log driver unless an environment selects/configures Twilio. It is not a working external SMS channel by default.
- `location_sharing`, `profile_visibility`, `show_email`, `tracker_refresh_interval`, `language`, `last_login_at`, and `address` are stored/partially editable but no server-side feature found here enforces or consumes several of them (notably profile visibility/show-email/location sharing in the community report payloads).
- The profile data export includes selected user, authored-report, like-count, and comment information; it does not export every related record such as follows, notifications, sessions, reminder audit history, or activity logs.
- The admin settings page disables SMS and push checkboxes in its Blade template even though its controller accepts these fields.
- A successful `php artisan route:list` emitted an environment-level Xdebug log-file warning; routing still completed. This is an environment/configuration concern rather than a route-registration failure.

## Important configuration and operational setup

- Copy `.env.example` to `.env`, configure database connection values, run migrations, and build front-end assets. Composer’s `setup` script performs install/key generation/migration/npm build; do not commit actual secrets.
- The repository’s example configuration targets MySQL and uses database session/cache/queue storage. Migrate before using those drivers.
- Set a real mailer and sender values for outbound email. Configure `SMS_DRIVER=twilio` plus its required secret settings only when real SMS delivery is intended; otherwise use the log driver.
- Ensure the Laravel scheduler is invoked continuously (for example via the host scheduler calling `php artisan schedule:run`) for reminders. Run a queue worker if future work makes notification jobs queued or other jobs are dispatched.
- Make public-disk report uploads web-accessible using Laravel’s normal storage-link deployment step when serving uploaded images.
- External browser CDN and OpenStreetMap access is required for the mapping/chart dependencies used by the views.

## Proposed future mobile API (not implemented)

This proposal maps the **existing** domain and rules into a future `/api/v1` interface; it is intentionally not a description of current endpoints. Use token-based authentication suitable for mobile clients, return JSON resources with stable IDs/ISO-8601 dates, enforce the same authorization and validation server-side, and avoid exposing passwords/secrets.

| Resource | Suggested endpoints |
| --- | --- |
| Authentication/account | `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `GET/PATCH /me`, `PATCH /me/password`, `DELETE /me`, `GET /me/export`, `GET/DELETE /me/sessions`. |
| Schedule/service area | `GET /schedules?area=&from=`, `GET /schedules/next?area=`, `GET /service-zones`, `PATCH /me/service-area`, `PATCH /me/notification-settings`. |
| Trucks | `GET /trucks`, `GET /trucks/{truck}`, `GET /trucks/{truck}/locations?from=&to=`; admin-only truck CRUD and `POST /admin/trucks/{truck}/locations`. |
| Community reports | `GET/POST /reports`, `GET/PATCH/DELETE /reports/{report}` (owner update/delete only), `POST /reports/{report}/likes`, `DELETE /reports/{report}/likes`, `GET/POST /reports/{report}/comments`, `POST/DELETE /reports/{report}/followers`. Multipart upload or a separate signed-upload flow should carry report images. |
| Notifications | `GET /notifications`, `PATCH /notifications/{notification}/read`, `PATCH /notifications/read-all`, `DELETE /notifications/{notification}`, `PATCH /me/notification-preferences`. |
| User moderation | `POST /users/{user}/reports`; admin list/update endpoints for user reports. |
| Administration | Admin-only report moderation/priority/bulk endpoints; schedules, trucks, service zones, and users resources matching the existing role restrictions. |

Recommended API design decisions based on current limitations: represent service-zone, truck, and schedule relationships with IDs rather than mutable display strings; paginate all collection endpoints; document status/priority enums; authorize every report interaction; make reminders/background sends explicitly queued if desired; and keep the existing web routes separate during migration.

