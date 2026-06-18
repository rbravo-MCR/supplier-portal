# Supplier Portal

Supplier Portal is a Laravel application for vehicle rental suppliers that manage pricing, availability, inventory, bookings, reports, and users without direct API or SOAP integrations.

The platform centralizes manual supplier operations, Excel-based uploads, traceability, access control, and future integration readiness.

## Stack

- PHP 8.5
- Laravel 13
- Livewire 4
- Flux UI 2
- Filament 5
- Fortify
- Pest 4
- Tailwind CSS 4
- Vite
- Node.js 22
- PostgreSQL with `pg_trgm` and `unaccent` for location catalog search

## Setup

Install dependencies and prepare the app:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Or use the Composer setup script:

```bash
composer run setup
```

Node.js 22 is the supported runtime for frontend tooling.

## Development

Run the Laravel server, queue listener, and Vite dev server together:

```bash
composer run dev
```

Run only Vite:

```bash
npm run dev
```

## Testing

Run the test suite:

```bash
php artisan test
```

Run formatter:

```bash
vendor/bin/pint --format agent
```

Run the Composer CI check:

```bash
composer run ci:check
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
```

Recommended deployment sequence:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Production readiness checks:

```bash
composer audit --format=plain
npm audit --omit=dev --audit-level=moderate
php artisan test --compact
```

Current production validation status:

- Laravel migrations apply cleanly.
- Full Pest suite passes with one intentionally skipped test.
- Production frontend build completes.
- Runtime npm audit is clean with `--omit=dev`.
- Composer audit reports no known advisories.

## Supplier Service API

Two JSON endpoints receive push data from the external supplier service. Requests are throttled at 120 per minute.

**Create or update a booking:**

```
POST /api/supplier-service/bookings
```

```json
{
  "supplier_code": "AGA",
  "reservation_code": "RES-001",
  "customer_name": "Juan Pérez",
  "vehicle_class": "ECAR",
  "pickup_office_code": "GDLMX01",
  "dropoff_office_code": "GDLMX01",
  "pickup_at": "2026-07-01 10:00:00",
  "dropoff_at": "2026-07-05 10:00:00",
  "total_amount": 1250.00,
  "currency": "MXN"
}
```

Returns `201` on creation, `200` on update. The `reservation_code` is the idempotency key per supplier.

**Upsert vehicle availability (batch up to 500 items):**

```
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

Each item can use `office_code` or `iata_code` (not both). Location type is inferred automatically.

## Observability

Health check endpoints expose system component status. They are protected by `HEALTH_SECRET` when configured:

```
GET /health           # aggregate status
GET /health/db
GET /health/redis
GET /health/queue
GET /health/storage
GET /health/outbox
GET /health/failed-jobs
```

Set `HEALTH_SECRET` in `.env` to require a bearer token from monitoring tools:

```
Authorization: Bearer <secret>
```

or:

```
X-Health-Secret: <secret>
```

When `HEALTH_SECRET` is empty, the endpoints are public (suitable for load balancer probes that already use `/up`).

The internal portal status page at `/status` is always auth-protected.

## Resilience

**Database circuit breaker** protects both web and API routes. When the primary database is unavailable:

- Requests return `503` with `{"message": "...", "incident_id": "INC-..."}`.
- The circuit opens after `APP_DB_CIRCUIT_BREAKER_FAILURE_THRESHOLD` failures and remains open for `APP_DB_CIRCUIT_BREAKER_OPEN_SECONDS` seconds.
- Runbook commands: `php artisan system:database-circuit-reset`, `php artisan system:database-circuit-probe`.

**Connection timeout** is controlled by `DB_CONNECT_TIMEOUT` (default 5 seconds). This prevents connection hangs from consuming the full PHP execution window before the circuit breaker can respond.

**Error handling** — all unhandled exceptions in production return `503` with a safe incident message. No stack traces, internal paths, or exception details are exposed. Debug mode shows full details only in non-production environments.

## Authentication

Access is handled by Laravel Fortify with username and password authentication.

The login form accepts only:

- `Usuario`: the account username.
- `Contraseña`: the account password.

The registration form requires selecting an active supplier. New registered accounts are related to that supplier and are redirected to the supplier dashboard after login.

Platform administrators do not belong to a supplier and are redirected to the platform dashboard after login.

Two-factor authentication and passkeys have been removed from the application and database schema.

Email verification and remember-me authentication are not used. The `users` table does not include `email_verified_at` or `remember_token`.

## Access Control

The application distinguishes platform users from supplier-scoped users through `users.supplier_id`.

- Platform administrators have `supplier_id = null`.
- Supplier users, including supplier admins, have `supplier_id` assigned to the supplier they operate.
- Login does not require selecting a supplier. The authenticated user determines the platform or supplier context.
- Supplier-scoped users cannot access the suppliers directory, even if their role is `admin`.
- The sidebar only shows `Proveedores` to platform users.

Supplier-scoped pages automatically use the authenticated user's supplier. Platform users can select a supplier where the workflow requires it.

## Supplier Eligibility

The portal is intended for rental suppliers that operate outside Mexico and do not have API or SOAP integration.

Supplier creation enforces:

- The supplier fiscal country must be active and outside Mexico.
- The supplier integration type must be `none`.
- Suppliers with API or SOAP integrations must not be registered for manual portal operation.

Supplier registration for users only lists active suppliers that satisfy those rules.

Portal roles are stored in the `roles` table and linked from `users.role_id`. The legacy `users.role` code is still maintained for policy compatibility.

Seeded role codes:

- `super_admin`
- `admin`
- `auditor`
- `supplier_admin`
- `supplier_reservations`
- `supplier_pricing`
- `supplier_user`

## Location Catalog Search

Country, city, and zone lookups use the database-backed search scopes in the Eloquent models.

The location catalog is imported from the Fenix database:

- `api_paises` -> `countries`
- `api_destinos` -> `cities`
- `api_zonas` -> `zones`

Countries store ISO2 and ISO3 codes when available. Cities are linked to countries by ISO2, and zones are linked to cities by destination code.

Countries can optionally reference a currency through `countries.currency_id`. The relationship is nullable so the location catalog can grow before every country has a configured currency.

On PostgreSQL, migrations enable:

- `pg_trgm`
- `unaccent`
- trigram GIN indexes for `countries.name`, `cities.name`, and `zones.name`

This makes partial searches fast and accent-insensitive, so searches such as `Mexico` can match `México`, and `Cancun` can match `Cancún`.

The offices page uses native Livewire/Flux selects for dependent location selection:

- Selecting a country loads only its cities.
- Selecting a city loads only its zones.
- Office country selection is open to any country in `countries`; the outside-Mexico restriction applies only to supplier creation.

## Currency Catalog

The `currencies` master catalog stores ISO 4217 currency metadata:

- `code`: unique 3-letter ISO code such as `USD`, `MXN`, or `EUR`.
- `numeric_code`: 3-digit ISO numeric code.
- `name`: official currency name.
- `symbol`: visual display symbol.
- `decimal_places`: ISO minor units, including 0-decimal currencies such as `JPY`, `KRW`, and `CLP`.
- `is_active`: disables selection without deleting historical data.

`CurrencySeeder` loads the initial operating catalog for major supplier markets. It is idempotent and can be rerun as the catalog grows.

Tariffs use `rates.currency_id`; the old free-text `rates.currency` column is removed by migration. Backend workflows may receive ISO codes, but persistence resolves them to `currency_id`. Frontend displays use `symbol + amount`, while payment and integration logic should use the related currency `code`.

Prices are stored as captured by the supplier. Currency conversion is not applied when saving rates; conversion belongs in query/search workflows using exchange rates.

## Pricing Workflow

Rates are published from controlled catalogs instead of free-text values where operational catalogs already exist:

- `office_code` is selected from active offices belonging to the effective supplier.
- `vehicle_class` is selected from active vehicle categories configured for the effective supplier.
- `acriss_code` is selected from the ACRISS codes attached to the selected vehicle category catalog.
- `currency_id` is selected from active ISO 4217 currencies.

Supplier users only see and save data for their assigned supplier. Platform users must select the supplier before choosing supplier-dependent values such as offices, vehicle categories, and ACRISS codes.

## Internationalization

The portal supports four locales:

- `es`: Español
- `en`: English
- `pt`: Português
- `fr`: Français

User language preference is stored in `users.preferred_locale` and applied on login and authenticated navigation. The current locale is also stored in session for the active request flow, but the database remains the source of truth.

Locale changes are handled by:

```text
POST /settings/locale
```

The route requires authentication, CSRF protection, backend whitelist validation, and a basic rate limit. Only `es`, `en`, `pt`, and `fr` are accepted. Invalid or empty locales fall back safely to `es`.

Regional metadata lives in `config/locales.php`, including display name, suggested country, date format, datetime format, timezone, default currency metadata, and text direction. Display helpers should be used for dates, datetimes, numbers, and money:

```blade
{{ format_date($date) }}
{{ format_datetime($date) }}
{{ format_number($value) }}
{{ format_money($amount, $currency) }}
```

Do not infer business currency from language. Currency must come from supplier, country, tariff, or the relevant business context.

Excel templates and import/export messages are localized. Templates are generated for:

```text
storage/app/templates/supplier-prices-es.xlsx
storage/app/templates/supplier-prices-en.xlsx
storage/app/templates/supplier-prices-pt.xlsx
storage/app/templates/supplier-prices-fr.xlsx
```

Import processing uses stable internal column keys from `config/imports.php`; translated headers are presentation only.

I18N quality checks:

```bash
php artisan i18n:audit
php artisan i18n:missing
php artisan test --compact tests/Feature/I18nQualityTest.php tests/Feature/LocalePreferenceTest.php tests/Feature/LocaleRegionalSupportTest.php
```

No new module should be approved with visible hardcoded text. All visible UI text must go through `lang/`.

Full i18n documentation is available in [docs/I18N.md](docs/I18N.md).

## Vehicle Category Catalog

The GPS category source file lives at:

```text
docs/gps_categorias.csv
```

Apply migrations and import the master vehicle category catalog:

```bash
php artisan migrate
php artisan catalog:import-gps-vehicle-categories
```

Validate the import without writing data:

```bash
php artisan catalog:import-gps-vehicle-categories --dry-run
```

## Documentation

Detailed product and engineering documentation is in `docs/`:

- [Product README](docs/01_README_PRODUCT.md)
- [Software Design Document](docs/02_SDD--supplier-portal.md)
- [Data Model](docs/06_DATA_MODEL.md)
- [Permission Matrix](docs/07_PERMISSION_MATRIX.md)
- [Runbook](docs/16_RUNBOOK.md)

## Repository

GitHub:

```text
https://github.com/rbravo-MCR/supplier-portal
```
