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
