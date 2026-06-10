# Supplier Portal

Supplier Portal is a Laravel application for vehicle rental suppliers that manage pricing, availability, inventory, bookings, reports, and users without direct API or SOAP integrations.

The platform centralizes manual supplier operations, Excel-based uploads, traceability, access control, and future integration readiness.

## Stack

- PHP 8.3+
- Laravel 13
- Livewire 4
- Flux UI 2
- Filament 5
- Fortify
- Pest 4
- Tailwind CSS 4
- Vite
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

## Authentication

Access is handled by Laravel Fortify with username and password authentication.

The login form accepts only:

- `Usuario`: the account username.
- `Contraseña`: the account password.

The registration form requires selecting an active supplier. New registered accounts are related to that supplier and are redirected to the supplier dashboard after login.

Platform administrators do not belong to a supplier and are redirected to the platform dashboard after login.

Two-factor authentication and passkeys have been removed from the application and database schema.

## Access Control

The application distinguishes platform users from supplier-scoped users through `users.supplier_id`.

- Platform administrators have `supplier_id = null`.
- Supplier users, including supplier admins, have `supplier_id` assigned to the supplier they operate.
- Login does not require selecting a supplier. The authenticated user determines the platform or supplier context.
- Supplier-scoped users cannot access the suppliers directory, even if their role is `admin`.
- The sidebar only shows `Proveedores` to platform users.

Supplier-scoped pages automatically use the authenticated user's supplier. Platform users can select a supplier where the workflow requires it.

## Location Catalog Search

Country, city, and zone lookups use the database-backed search scopes in the Eloquent models.

Countries can optionally reference a currency through `countries.currency_id`. The relationship is nullable so the location catalog can grow before every country has a configured currency.

On PostgreSQL, migrations enable:

- `pg_trgm`
- `unaccent`
- trigram GIN indexes for `countries.name`, `cities.name`, and `zones.name`

This makes partial searches fast and accent-insensitive, so searches such as `Mexico` can match `México`, and `Cancun` can match `Cancún`.

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
