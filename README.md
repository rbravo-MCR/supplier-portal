# Supplier Portal

Supplier Portal is a Laravel application for vehicle rental suppliers that manage pricing, availability, inventory, bookings, promotions, reports, and users without direct API or SOAP integrations.

The platform centralizes manual supplier operations, Excel-based uploads, traceability, access control, localized templates, and production readiness for future integrations.

## Stack

- PHP 8.5
- Laravel 13
- Livewire 4
- Flux UI 2
- Filament 5
- Fortify
- Pest 4 / PHPUnit 12
- Tailwind CSS 4
- Vite 8
- Node.js 22 or newer for frontend tooling
- PostgreSQL in production, with `pg_trgm` and `unaccent` for location catalog search

## Requirements

Local development needs:

- PHP with the extensions required by Laravel and the selected database driver.
- Composer.
- Node.js 22+ and npm.
- A configured database. The default `.env.example` uses SQLite, while production targets PostgreSQL.

For PostgreSQL deployments, enable the extensions used by the location catalog migrations:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

## Setup

Install dependencies and prepare the app manually:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Or run the Composer setup script, which performs the same bootstrap flow:

```bash
composer run setup
```

`.env.example` is production-oriented. For local development, set at least:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=false
```

Testing uses in-memory SQLite from `phpunit.xml`. The application runtime uses the configured `DB_CONNECTION`.

Seed the base catalogs and default test user:

```bash
php artisan db:seed
```

The base seeders create currencies, portal roles, and a default user record. Supplier-specific demo data is intentionally separate from the core setup.

## Development

Run the Laravel server, queue listener, and Vite dev server together:

```bash
composer run dev
```

Run only Vite:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

If frontend changes do not appear, rebuild assets or keep Vite running.

Useful application entry points:

```text
/                 # redirects authenticated users by role
/admin            # platform dashboard
/supplier         # supplier dashboard
/bookings
/prices
/promotions
/imports
/offices
/availabilities
/categories
/audit
/status           # authenticated portal status page
/settings/profile
/settings/appearance
/settings/security
/settings/teams
```

## Testing

Run the full project check:

```bash
composer run ci:check
```

This clears config, checks Pint formatting, runs the i18n audit, and executes the Pest suite through the Composer `test` script.

Run only the test suite:

```bash
php artisan test --compact
```

Run a focused test file:

```bash
php artisan test --compact tests/Feature/Api/CalculatePromotionTest.php
```

Run formatter:

```bash
vendor/bin/pint --format agent
```

## Production Deployment

Required production environment values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain.example
CACHE_STORE=failover
QUEUE_CONNECTION=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
HEALTH_SECRET=change-me
```

`APP_URL` must be the real production domain before deployment. Do not deploy with
`https://supplier-portal.example.com` or any placeholder value.

Recommended deployment sequence:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Current production readiness status:

```text
GO condicionado
```

The application is ready from the code and local runtime checks after the following
external deployment controls are confirmed:

- `APP_URL` points to the real production domain.
- The private JSON API is isolated at the network layer so only the Outlet
  microservice can call it.

Production readiness checks:

```bash
composer audit --format=plain
npm audit --omit=dev --audit-level=moderate
php artisan test --compact
php artisan system:health-check
php artisan system:service-level-check
php artisan system:disaster-recovery-check
```

Production validation checklist:

- Laravel migrations apply cleanly.
- Pest suite passes with the expected skips only.
- Production frontend build completes.
- Runtime npm audit is clean with `--omit=dev`.
- Composer audit reports no known advisories.
- Health, service-level, and disaster-recovery checks are green for the target environment.
- `php artisan optimize` completes and writes cache files under `bootstrap/cache`.
- `php artisan queue:failed` reports no failed jobs before handoff.
- `php artisan storage:link` is present when using the local filesystem disk for public files.

## JSON APIs

JSON API routes are throttled at 120 requests per minute.

These endpoints are intentionally not protected with bearer tokens in application
code. They are private integration endpoints for the Outlet microservice only.
Production infrastructure must enforce that boundary with private networking,
security groups, firewall rules, ingress allowlists, or equivalent controls.
Do not expose these API routes directly to the public internet.

### Supplier Service

Create or update a booking:

```text
POST /api/supplier-service/bookings
```

```json
{
  "supplier_code": "AGA",
  "reservation_code": "RES-001",
  "customer_name": "Juan Perez",
  "vehicle_class": "ECAR",
  "pickup_office_code": "GDLMX01",
  "dropoff_office_code": "GDLMX01",
  "pickup_at": "2026-07-01 10:00:00",
  "dropoff_at": "2026-07-05 10:00:00",
  "total_amount": 1250.00,
  "currency": "MXN"
}
```

Returns `201` on creation and `200` on update. The `reservation_code` is the idempotency key per supplier.
The database also enforces uniqueness for `supplier_id + reservation_code`, and
the endpoint uses an upsert flow so retries from Outlet are safe.

Upsert vehicle availability, with a batch size up to 500 items:

```text
POST /api/supplier-service/vehicle-availability
```

```json
{
  "supplier_code": "AGA",
  "items": [
    {
      "office_code": "GDLMX01",
      "vehicle_class": "ECONOMY",
      "acriss_code": "ECAR",
      "available_quantity": 5,
      "valid_from": "2026-07-01",
      "valid_to": "2026-07-31",
      "status": "available"
    }
  ]
}
```

Each item can use `office_code` or `iata_code`, but not both. Location type is inferred automatically.

### Promotion Calculation

The quote flow can calculate applicable supplier promotions before booking persistence:

```text
POST /api/promotions/calculate
```

```json
{
  "supplier_code": "AGA",
  "office_code": "CUN01",
  "acriss_code": "ECAR",
  "pickup_at": "2026-07-01T10:00:00Z",
  "dropoff_at": "2026-07-10T10:00:00Z",
  "base_amount": 1000.00,
  "currency": "USD"
}
```

Example response:

```json
{
  "original_amount": 1000.00,
  "applicable_promotions": [
    {
      "promotion_id": "promotion-uuid",
      "name": "Verano 2026",
      "type": "seasonal",
      "discount_amount": 150.00,
      "discount_percentage": 15.00
    }
  ],
  "total_discount": 150.00,
  "final_amount": 850.00,
  "currency": "USD",
  "rental_days": 9
}
```

Seasonal and volume promotions are evaluated independently. When multiple promotions of the same type overlap, the highest discount candidate is selected for that type.

## Authentication

Access is handled by Laravel Fortify with username and password authentication.

The login form accepts:

- `Usuario`: account username.
- `Contrasena`: account password.

The registration form requires selecting an active supplier. New registered accounts are related to that supplier and are redirected to the supplier dashboard after login.

Platform administrators do not belong to a supplier and are redirected to the platform dashboard after login.

Two-factor authentication and passkeys have been removed from the application and database schema. Email verification and remember-me authentication are not used.

## Access Control

The application distinguishes platform users from supplier-scoped users through `users.supplier_id`.

- Platform administrators have `supplier_id = null`.
- Supplier users, including supplier admins, have `supplier_id` assigned to the supplier they operate.
- Login does not require selecting a supplier. The authenticated user determines the platform or supplier context.
- Supplier-scoped users cannot access the suppliers directory, even if their role is `admin`.
- The sidebar only shows `Proveedores` to platform users.

Portal roles are stored in the `roles` table and linked from `users.role_id`. The legacy `users.role` code is still maintained for policy compatibility.

Seeded role codes:

- `super_admin`
- `admin`
- `auditor`
- `supplier_admin`
- `supplier_reservations`
- `supplier_pricing`
- `supplier_user`

## Supplier Eligibility

The portal is intended for rental suppliers that operate outside Mexico and do not have API or SOAP integration.

Supplier creation enforces:

- The supplier fiscal country must be active and outside Mexico.
- The supplier integration type must be `none`.
- Suppliers with API or SOAP integrations must not be registered for manual portal operation.

Supplier registration for users only lists active suppliers that satisfy those rules.

## Workflows

### Pricing

Rates are published from controlled catalogs instead of free-text values where operational catalogs already exist:

- `office_code` is selected from active offices belonging to the effective supplier.
- `vehicle_class` is selected from active vehicle categories configured for the effective supplier.
- `acriss_code` is selected from the ACRISS codes attached to the selected vehicle category catalog.
- `currency_id` is selected from active ISO 4217 currencies.

Supplier users only see and save data for their assigned supplier. Platform users must select the supplier before choosing supplier-dependent values such as offices, vehicle categories, and ACRISS codes.

### Promotions

Promotions support supplier-scoped seasonal and volume discounts:

- Seasonal promotions apply within a configured date range.
- Volume promotions apply by rental-day tiers.
- Promotions can apply to all offices/categories or be limited to selected catalogs.
- The `/promotions` page is available to supplier users with the expected permissions.
- The `/api/promotions/calculate` endpoint returns the calculated discount breakdown for quote flows.

### Imports

Excel price imports follow the staging workflow:

```text
Excel -> validation -> staging -> approval -> publication
```

The import process only accepts `.xlsx` files generated by Supplier Portal for
the selected supplier. Generated templates include internal supplier metadata
and an HMAC signature based on `APP_KEY`. Uploads are rejected when the workbook:

- was not generated by Supplier Portal;
- was generated for a different supplier;
- has missing or invalid required price columns;
- has rows that fail validation.

The import process uses stable internal column keys from `config/imports.php`;
translated headers are presentation only. Error workbooks are generated for rows
that cannot be imported.

Localized templates are generated for supported locales under:

```text
storage/app/templates/supplier-prices-{locale}.xlsx
```

### Location Catalog

Country, city, and zone lookups use database-backed search scopes in the Eloquent models.

The location catalog is imported from the Fenix database:

- `api_paises` -> `countries`
- `api_destinos` -> `cities`
- `api_zonas` -> `zones`

On PostgreSQL, migrations enable:

- `pg_trgm`
- `unaccent`
- trigram GIN indexes for `countries.name`, `cities.name`, and `zones.name`

This makes partial searches fast and accent-insensitive.

Import Fenix locations:

```bash
php artisan catalog:import-fenix-locations
```

Apply migrations and import the GPS vehicle category catalog:

```bash
php artisan migrate
php artisan catalog:import-gps-vehicle-categories
```

Validate the GPS import without writing data:

```bash
php artisan catalog:import-gps-vehicle-categories --dry-run
```

## Currency Catalog

The `currencies` master catalog stores ISO 4217 currency metadata:

- `code`: unique 3-letter ISO code such as `USD`, `MXN`, or `EUR`.
- `numeric_code`: 3-digit ISO numeric code.
- `name`: official currency name.
- `symbol`: visual display symbol.
- `decimal_places`: ISO minor units.
- `is_active`: disables selection without deleting historical data.

`CurrencySeeder` loads the initial operating catalog for major supplier markets. It is idempotent and can be rerun as the catalog grows.

Tariffs use `rates.currency_id`; the old free-text `rates.currency` column is removed by migration. Backend workflows may receive ISO codes, but persistence resolves them to `currency_id`.

Prices are stored as captured by the supplier. Currency conversion is not applied when saving rates.

## Internationalization

The portal supports localized UI, Excel templates, and import/export messages. Locale preference is stored in `users.preferred_locale` and applied on login and authenticated navigation.

Locale changes are handled by:

```text
POST /settings/locale
```

The route requires authentication, CSRF protection, backend whitelist validation, and a basic rate limit. Invalid or empty locales fall back safely to the default locale.

Regional metadata lives in `config/locales.php`, including display name, suggested country, date format, datetime format, timezone, default currency metadata, and text direction.

Use display helpers for dates, datetimes, numbers, and money:

```blade
{{ format_date($date) }}
{{ format_datetime($date) }}
{{ format_number($value) }}
{{ format_money($amount, $currency) }}
```

Do not infer business currency from language. Currency must come from supplier, country, tariff, or the relevant business context.

I18N quality checks:

```bash
php artisan i18n:audit
php artisan i18n:missing
php artisan test --compact tests/Feature/I18nQualityTest.php tests/Feature/LocalePreferenceTest.php tests/Feature/LocaleRegionalSupportTest.php
```

No new module should be approved with visible hardcoded text. All visible UI text must go through `lang/`.

Full i18n documentation is available in [docs/I18N.md](docs/I18N.md).

## Observability

Health check endpoints expose system component status. They are protected by `HEALTH_SECRET` when configured:

```text
GET /health
GET /health/db
GET /health/redis
GET /health/queue
GET /health/storage
GET /health/outbox
GET /health/failed-jobs
```

Set `HEALTH_SECRET` in `.env` to require a bearer token from monitoring tools:

```text
Authorization: Bearer <secret>
```

or:

```text
X-Health-Secret: <secret>
```

When `HEALTH_SECRET` is empty, the endpoints are public. The internal portal status page at `/status` is always auth-protected.

## Resilience

Database circuit breaker protects both web and API routes. When the primary database is unavailable:

- Requests return `503` with a safe incident message and `incident_id`.
- The circuit opens after `APP_DB_CIRCUIT_BREAKER_FAILURE_THRESHOLD` failures.
- The circuit remains open for `APP_DB_CIRCUIT_BREAKER_OPEN_SECONDS` seconds.

Runbook commands:

```bash
php artisan system:database-circuit-reset
php artisan system:database-circuit-probe
php artisan system:health-check
php artisan system:service-level-check
php artisan system:disaster-recovery-check
```

Connection timeout is controlled by `DB_CONNECT_TIMEOUT`, defaulting to 5 seconds for PostgreSQL.

Unhandled exceptions in production return a safe incident message. Debug mode shows full details only in non-production environments.

## Documentation

Detailed product and engineering documentation is in `docs/`:

- [Product README](docs/01_README_PRODUCT.md)
- [Software Design Document](docs/02_SDD--supplier-portal.md)
- [Data Model](docs/06_DATA_MODEL.md)
- [Permission Matrix](docs/07_PERMISSION_MATRIX.md)
- [Runbook](docs/16_RUNBOOK.md)
- [I18N](docs/I18N.md)

## Repository

GitHub:

```text
https://github.com/rbravo-MCR/supplier-portal
```
