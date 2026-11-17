# NailsByMora Project Guide

A practical map of where to find the frontend, Laravel backend, database structure, and supporting files in this VILT project.

## How a page works

Most pages follow this flow:

1. A URL and HTTP method are registered in `routes/web.php`.
2. A Laravel controller in `app/Http/Controllers/` reads or changes data.
3. For a Vue page, the controller returns `Inertia::render('Folder/Page', [...])`.
4. Inertia resolves that name to `resources/js/Pages/Folder/Page.vue`.
5. The Vue page renders the interface and uses reusable pieces from `resources/js/Components/`.

The admin routes are protected by the `auth:admin` middleware. Login and logout are handled separately from the default Laravel `User` account.

## Frontend: Vue, Inertia, and Tailwind

| Location | What it contains |
| --- | --- |
| `resources/js/app.js` | Vue/Inertia entry point; resolves Inertia page names to Vue files. |
| `resources/js/Pages/` | Full-screen pages: dashboard, customers, services, appointments, inventory, expenses, reports, settings, and login. |
| `resources/js/Components/` | Shared UI such as forms, detail modals, customer lookup, charts, stock adjustment, and confirmation dialogs. |
| `resources/js/Layouts/AppLayout.vue` | Shared authenticated layout, navigation/sidebar, and company logo. |
| `resources/css/app.css` | Tailwind CSS import and the shared brand colors and font theme. |
| `resources/images/LOGO.jpg` | Source logo imported by the frontend. |
| `vite.config.js` | Vite, Vue, Tailwind, and Laravel build configuration. |
| `public/build/` | Generated production assets. Do not edit these files directly; Vite regenerates them. |

To find a screen, start with the matching page under `resources/js/Pages/`. For example, appointment dialogs and forms are separate components in `resources/js/Components/`, used by the appointments page.

## Blade views

Blade is used in a small number of places rather than for the main application screens:

| File | Purpose |
| --- | --- |
| `resources/views/app.blade.php` | Base HTML document that loads Vite and mounts the Inertia application. |
| `resources/views/reports/print.blade.php` | Printable report page. |

## Backend: routes, controllers, models

### Routes

`routes/web.php` is the main route map. It defines login/logout and the authenticated routes for the dashboard, settings, reports, customers, services, appointments, inventory, and expenses. It also defines endpoints for actions such as bulk deletion, stock history, and stock adjustments.

### Controllers

Find request handling and server-side validation in `app/Http/Controllers/`:

| Controller | Responsibility |
| --- | --- |
| `AdminAuthController.php`, `AdminSettingsController.php` | Admin login/logout and account settings. |
| `DashboardController.php` | Dashboard summary data. |
| `CustomerController.php`, `ServiceController.php` | Customer and service management. |
| `AppointmentController.php` | Appointments and their selected services. |
| `InventoryController.php`, `InventoryCategoryController.php` | Inventory items, stock movements, and categories. |
| `ExpenseController.php`, `ExpenseCategoryController.php` | Expenses and categories. |
| `ReportController.php`, `ReportExportController.php` | Report screen, exports, and print view. |

### Models

Eloquent models are in `app/Models/`. The main domain models are `Admin`, `Customer`, `Service`, `Appointment`, `Inventory`, `InventoryCategory`, `InventoryMovement`, `Expense`, and `ExpenseCategory`. `User` is Laravel's default user model; admin login uses the separate `Admin` model and guard.

`app/Services/ReportGenerator.php` contains report-building logic shared by the report screens and exports.

## Database: MySQL

The active connection and local database name are configured in `.env` under `DB_*`. Keep local credentials in `.env`; do not publish or commit secrets. `.env.example` is the safe template for environment setup.

Database table definitions live in `database/migrations/`. These migrations, rather than this guide, are the source of truth for exact columns, indexes, constraints, and foreign keys.

The application tables are:

| Tables | Purpose |
| --- | --- |
| `admins` | Admin login accounts. |
| `customers` | Salon customers. |
| `services` | Service catalog and current prices/durations. |
| `appointments`, `appointment_services` | Customer bookings and the services included in each booking. Service price and duration are stored on the appointment-service records as booking-time snapshots. |
| `inventory_categories`, `inventory`, `inventory_movements` | Inventory catalog, current stock, and the history of stock changes. |
| `expense_categories`, `expenses` | Expense records and their categories. |

Laravel's standard infrastructure migrations also define tables such as `users`, `sessions`, `cache`, and queue-related tables.

Demo/sample business data is defined in the dated migrations in `database/migrations/` whose names start with `seed_demo_business_`. Factories and seeders, where present, are in `database/factories/` and `database/seeders/`.

## Other useful locations

| Location | What it contains |
| --- | --- |
| `config/auth.php` | Admin and default Laravel authentication guards/providers. |
| `config/app.php` | Application name, locale, and other app-wide configuration. |
| `lang/id/` | Indonesian validation and translation strings. |
| `tests/Feature/` | End-to-end Laravel feature tests for application behavior. |
| `tests/Unit/` | Focused unit tests. |
| `package.json` | Frontend dependencies and Vite commands. |
| `composer.json` | PHP/Laravel dependencies and Artisan scripts. |
| `DESIGN.md` | Brand and visual design reference. |
| `NAILS_LOG.md` | Project work log. |

## Common commands

Run these from the project root:

```bash
php artisan migrate
php artisan test
npm run dev
npm run build
```

Use `npm run dev` while developing the frontend and `npm run build` to generate production assets.

## Where to start when making a change

- **Change a screen:** edit its Vue file in `resources/js/Pages/`.
- **Change a shared modal or UI piece:** look in `resources/js/Components/` first.
- **Change data loading or validation:** find the route in `routes/web.php`, then edit its controller.
- **Change a database table:** create a new migration in `database/migrations/`; do not edit a migration that has already been applied to a shared database.
- **Change a model relationship or cast:** edit the corresponding file in `app/Models/`.
- **Change a printable report:** edit `resources/views/reports/print.blade.php`; the interactive report screen is a Vue page.
- **Change shared styling:** start with `resources/css/app.css` and follow existing Tailwind classes in nearby Vue components.
