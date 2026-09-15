# Cleanify

## Project overview

Cleanify is a Laravel application for coordinating community garbage collection. Residents can view collection schedules, follow truck locations, submit and discuss community reports, and manage notifications. Administrators can moderate reports, manage users, schedules, service zones, and trucks, and update truck locations.

This repository contains the Laravel web application and the versioned API used by mobile clients.

## Main features

### Resident experience

- View active garbage collection schedules for a selected service area.
- See the next collection and upcoming pickups.
- Receive in-app, email, and SMS schedule reminders according to account preferences.
- Submit community reports with a description, optional location coordinates, and an optional image.
- Like, comment on, and follow reports through the web interface.
- Track garbage trucks and view their recent route history on Leaflet/OpenStreetMap maps.
- Manage account details, notification preferences, privacy settings, sessions, and profile photos.

### Administration

- Manage and ban user accounts.
- Search, filter, prioritize, resolve, reject, bulk-moderate, and permanently delete reports.
- Manage recurring and specific-date collection schedules.
- Manage trucks, statuses, assigned service-zone routes, locations, and route history.
- Manage service zones and their map coordinates.
- Review user-submitted account reports.

### Mobile API

- Laravel Sanctum bearer-token authentication.
- Mobile-facing endpoints for reports, schedules, notifications, trucks, profile photos, and account settings.
- Paginated report and notification responses.

## Tech stack

- PHP 8.2 or newer
- Laravel 12
- Laravel Sanctum 4
- Blade templates
- Tailwind CSS 3 and Alpine.js 3
- Vite 7
- Font Awesome
- Leaflet 1.9.4, OpenStreetMap tiles, and Leaflet.markercluster loaded by the map views
- Pest 4 with PHPUnit-compatible Laravel testing

## Requirements

Install the following before setting up the application:

- PHP 8.2+ with the extensions required by Laravel and the selected database driver
- Composer
- Node.js and npm
- A supported database server; `.env.example` is currently structured for MySQL

The application also uses Laravel's database-backed sessions, cache, queue, notifications, and personal access tokens, so all migrations must be applied.

## Installation

```bash
git clone <repository-url> cleanify
cd cleanify
composer install
npm install
```

Create a local `.env` from the variable names in `.env.example`. Before copying the example file, confirm that every credential field contains only a blank value or a safe placeholder. Never commit the populated `.env` file.

```powershell
# Windows PowerShell
Copy-Item .env.example .env
```

```bash
# macOS or Linux
cp .env.example .env
```

Generate a unique application key locally:

```bash
php artisan key:generate
```

## Environment setup

Use `.env.example` as the source of supported variable names. At minimum, review these groups in your local `.env`:

- Application: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- Database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Sessions, cache, and queue: `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`
- Public files: `FILESYSTEM_DISK`
- Mail: the `MAIL_*` variables required by the selected mail provider
- SMS reminders: `SMS_DRIVER`, `SMS_REMINDER_TIME`, `SMS_TIMEZONE`, and `SMS_DEFAULT_COUNTRY_CODE`
- Twilio, when explicitly enabled: `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, and `TWILIO_FROM`

Use private values only in the untracked local `.env` or the deployment platform's secret store. Do not place real credentials in this README or `.env.example`.

The default SMS driver is `log`, which simulates SMS delivery in local logs. Configure a supported real provider only when the deployment requires it.

## Database setup

Create the configured database, then run:

```bash
php artisan migrate
```

The migrations create the user and authentication tables together with reports, schedules, service zones, trucks and location history, database notifications, activity logs, and channel-specific reminder records.

## Public storage setup

Report images and profile photos are stored on Laravel's `public` disk. Create the public storage link after installation:

```bash
php artisan storage:link
```

Ensure `storage` and `bootstrap/cache` are writable by the application process.

## Running the application

For the standard local development stack, run:

```bash
composer run dev
```

This starts the Laravel development server, a queue listener, and the Vite development server. They can also be run separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Build production frontend assets with:

```bash
npm run build
```

The health-check route is available at `/up`.

## Authentication

The web application uses Laravel's session authentication. Resident pages require an authenticated, non-admin, non-banned user; admin pages require an authenticated administrator.

The mobile API is rooted at `/api/v1`. Registration and login return a Laravel Sanctum personal access token. Send that token to protected endpoints as a bearer token:

```http
Authorization: Bearer <token>
Accept: application/json
```

Public authentication endpoints:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/forgot-password`

Authenticated account endpoints include:

- `POST /api/v1/auth/logout`
- `GET /api/v1/me`
- `GET /api/v1/settings`
- `PATCH /api/v1/settings/account`
- `PATCH /api/v1/settings/password`
- `PATCH /api/v1/settings/notifications`

## API overview

All endpoints below require `auth:sanctum`:

| Area | Method and path | Purpose |
| --- | --- | --- |
| Reports | `GET /api/v1/reports` | Paginated public community feed |
| Reports | `POST /api/v1/reports` | Create a report |
| Reports | `GET /api/v1/me/reports` | Paginated report history for the authenticated user |
| Schedules | `GET /api/v1/schedules` | Active schedules and upcoming pickups for the user's service area |
| Schedules | `GET /api/v1/schedules/next` | Next collection for the user's service area |
| Trucks | `GET /api/v1/trucks` | Truck, map, and active service-zone data |
| Trucks | `GET /api/v1/trucks/{truck}/route-history` | A truck's recent location history |
| Notifications | `GET /api/v1/notifications` | Paginated notifications, filters, categories, and unread count |
| Notifications | `POST /api/v1/notifications/mark-all-read` | Mark every notification as read |
| Notifications | `POST /api/v1/notifications/{notification}/read` | Mark one owned notification as read |
| Notifications | `DELETE /api/v1/notifications/{notification}` | Delete one owned notification |
| Profile photo | `POST /api/v1/me/profile-photo` | Upload or replace a profile photo |
| Profile photo | `DELETE /api/v1/me/profile-photo` | Remove a profile photo |

Profile photo uploads accept JPG, JPEG, PNG, or WebP images up to 4 MB.

## Community reports

New reports are created with `pending` status and `medium` priority. A description is required. A location label, latitude/longitude pair, and image are optional; coordinates are range-validated, and report images accept JPEG, PNG, GIF, or WebP files up to 4 MB.

The public web and API feeds share the same visibility rule:

- Pending and rejected reports remain visible.
- Resolved reports remain public for 72 hours after `resolved_at`.
- After 72 hours, resolved reports are hidden from public feeds.
- Hidden resolved reports remain available in the submitting user's history and in admin management.

Administrators can permanently delete a report. Permanent deletion also removes its safely scoped public image and associated report notifications/activity records; this is separate from resolving or rejecting a report.

The web report feed additionally supports likes, comments, and follows. Report creation is throttled by the application's report middleware.

## Schedule and reminder system

Schedules can be recurring by weekday or tied to a specific date. Mobile schedule responses are restricted to active schedules matching the authenticated user's active service area and include formatted time ranges and calculated upcoming pickups.

The custom reminder command is:

```bash
php artisan sms:send-schedule-reminders
```

Despite its historical command name, it processes both eligible SMS and email reminders for collections occurring the next day. It respects the user's channel and schedule-reminder preferences, records each channel attempt, and relies on database uniqueness constraints to prevent duplicate reminders for the same user, schedule, date, and reminder type.

Safe operational options are available:

```bash
php artisan sms:send-schedule-reminders --dry-run
php artisan sms:send-schedule-reminders --date=2026-09-16 --dry-run
```

The command is registered with Laravel's scheduler to be checked every minute in the configured SMS timezone without overlapping. In development, run:

```bash
php artisan schedule:work
```

In production, configure the platform to invoke Laravel's scheduler using its standard `schedule:run` integration. Reminder emails include the schedule's real formatted collection time range. The local `log` SMS driver does not contact an external provider.

## Notifications

Report moderation and schedule events create Laravel database notifications. Depending on the user's channel and category preferences, relevant notifications can also be delivered by email. The web and API interfaces support listing, unread counts, marking one or all notifications as read, category filtering, and deletion.

Schedule reminder attempts are also recorded separately in the email and SMS notification tables so delivery state and errors can be tracked.

## Truck tracker

The resident and admin trackers use Leaflet with OpenStreetMap tiles and marker clustering. Truck payloads include status, coordinates, and last-update information; trucks without coordinates are handled separately from located trucks.

Location updates create history records only when the position changes beyond the controller's small coordinate threshold. Route-history endpoints return locations recorded during the last 24 hours in chronological order.

Administrators can create, update, and delete trucks, assign routes from active service zones, update truck coordinates, and view tracker data. Residents and authenticated mobile clients have read-only tracker and route-history access.

## Admin functionality

Routes under `/admin` are protected by the `auth` and `admin` middleware. The admin interface provides:

- Dashboard statistics and activity information
- User account management, including ban and unban actions
- Report search, status/priority filters, moderation, bulk actions, priorities, and permanent deletion
- Collection schedule management
- Truck and truck-location management
- Service-zone management
- Admin profile, password, and notification settings
- Review of reports submitted about user accounts

## Testing

The test suite uses Pest. Feature tests run with an in-memory SQLite database, array-backed mail, synchronous queues, and isolated array session/cache configuration.

Run the full PHP suite with either command:

```bash
composer test
php artisan test
```

Run a focused test file when working on a specific area:

```bash
php artisan test tests/Feature/Api/V1/ScheduleTest.php
```

Validate frontend compilation with:

```bash
npm run build
```

## Development notes

- Web routes are defined in `routes/web.php`; mobile-facing routes are versioned in `routes/api.php`.
- API request/response behavior is covered by tests under `tests/Feature/Api/V1`.
- Shared business rules live in services such as `ReportCreator`, `ScheduleReminderService`, and `ProfilePhotoService`.
- Report and profile-photo uploads use the public filesystem disk and require the storage link.
- Map views load Leaflet and OpenStreetMap resources from external CDNs, so those views require network access in the browser.
- Keep private environment values outside version control and use deployment secret storage in production.

## License

Cleanify is built with the [Laravel framework](https://laravel.com), which is open-sourced under the [MIT license](https://opensource.org/licenses/MIT). This repository also declares the MIT license in `composer.json`.
