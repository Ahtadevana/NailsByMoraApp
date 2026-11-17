# NAILS VILT setup

- **Front end:** Vue 3 single-file components, Inertia.js for page navigation, Tailwind CSS 4, and Vite.
- **Back end:** Laravel 13 and PHP 8.5 for routes, controllers, validation, and Eloquent; Inertia connects Laravel responses directly to Vue without a separate API.
- **Database:** MySQL.

Laravel's Inertia middleware, Vue/Vite integration, Tailwind scanning, and a sample Inertia page are configured. The default standalone Blade welcome page and remote font download were removed.

## Run locally

1. Create a MySQL database named `nails` (or set `DB_DATABASE` and credentials in `.env`).
2. Install dependencies: `composer install` and `npm install`.
3. If `APP_KEY` is empty, set it with `php artisan key:generate`, then run `php artisan migrate`.
4. Start Laravel with `php artisan serve` and the Vite watcher in another terminal with `npm run dev`.
