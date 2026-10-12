<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Order Management System (OMS)

A full-stack Order Management System built with **Laravel 12**, **Vue
3**, and **Tailwind CSS**. It manages orders through their lifecycle and
includes customer/product management, inventory handling,
permission-based access, dashboards, order notes/history/activity,
notifications, and audit logs.

> Before submission, run the test suite and confirm the documented API
> routes match the actual application. Only state that tests pass if the
> latest run succeeds.

## Contents

-   [Features](#features)
-   [Technology stack](#technology-stack)
-   [Requirements](#requirements)
-   [Installation on Windows with
    XAMPP](#installation-on-windows-with-xampp)
-   [Environment configuration](#environment-configuration)
-   [Database setup](#database-setup)
-   [Run the application](#run-the-application)
-   [Authentication and
    authorization](#authentication-and-authorization)
-   [API documentation](#api-documentation)
-   [Project structure](#project-structure)
-   [Testing](#testing)
-   [Production build](#production-build)
-   [Security notes](#security-notes)
-   [Architecture and trade-offs](#architecture-and-trade-offs)
-   [AI usage disclosure](#ai-usage-disclosure)

## Features

### Orders

-   Create, view, update, and delete orders according to permissions.
-   Search, filter, sort, and paginate orders.
-   Assign orders to users.
-   Validate status transitions and record status history.
-   Add notes and inspect order activity.
-   Calculate order-item pricing, discounts, tax, subtotal, and total.
-   Integrate order operations with inventory according to implemented
    business rules.

### Customers and products

-   Customer listing, search, create, update, and delete/deactivate
    where permitted.
-   Product listing, search, create, update, SKU, price, stock quantity,
    and status.

### Administration

-   Authentication and protected routes.
-   User, role, and permission management.
-   Dashboard metrics and trends.
-   Audit logs.
-   Read/unread notifications and mark-as-read actions.

### Frontend

-   Vue 3 single-page application.
-   Vue Router, Pinia, Axios, and Tailwind CSS.
-   Loading, empty, validation, and error states where implemented.

## Technology stack

  Layer               Technology
  ------------------- -------------------------------------
  Backend             PHP, Laravel 12
  API                 Laravel REST APIs
  Frontend            Vue 3
  Styling             Tailwind CSS
  Routing             Vue Router
  State management    Pinia
  HTTP client         Axios
  Database            MySQL/MariaDB for local development
  Local environment   XAMPP on Windows
  Testing             Laravel feature tests / PHPUnit

## Requirements

-   PHP version compatible with Laravel 12 and the project's Composer
    dependencies.
-   Composer.
-   Node.js and npm.
-   MySQL or MariaDB.
-   Git.

## Installation on Windows with XAMPP

### 1. Clone or open the project

``` bash
git clone <YOUR_REPOSITORY_URL>
cd Order_Management_System
```

If already cloned, open a terminal in the project directory, for example
`C:\xampp\htdocs\Order_Management_System`.

### 2. Install backend dependencies

``` bash
composer install
```

### 3. Configure `.env`

``` bash
copy .env.example .env
php artisan key:generate
```

### 4. Create a database

Start MySQL in the XAMPP Control Panel. In phpMyAdmin
(`http://localhost/phpmyadmin`), create a database such as
`order_management_system`.

### 5. Configure local database variables

Example only for a typical local XAMPP setup:

``` dotenv
APP_NAME="Order Management System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_management_system
DB_USERNAME=root
DB_PASSWORD=
```

Use the credentials configured on your machine. Never commit real
credentials or secrets.

### 6. Run migrations and seeders

``` bash
php artisan migrate
php artisan db:seed
```

If the project uses specific seeder classes, document them here after
checking `database/seeders`. **Do not run
`php artisan migrate:fresh --seed` on a database containing data you
need**, because it drops existing tables.

### 7. Install frontend dependencies

``` bash
npm install
```

### 8. Start the application

Open two terminals in the project root.

Terminal 1:

``` bash
php artisan serve
```

Terminal 2:

``` bash
npm run dev
```

Open the local URL printed by Laravel, commonly `http://127.0.0.1:8000`.

## Environment configuration

Use `.env` for local configuration. Keep secrets out of source control.
Use `APP_DEBUG=false` and secure production credentials in production.
The committed `.env.example` should contain placeholders, not real
passwords or API keys.

## Database setup

Schema changes are managed by Laravel migrations. Seeders should prepare
required roles, permissions, and demo records.

``` bash
php artisan migrate:status
php artisan migrate
php artisan db:seed
```

Confirm the configured seeder names and demo account details from the
source code before documenting them. Do not publish real passwords.

## Run the application

``` bash
php artisan serve
npm run dev
```

The Vue frontend is served through Laravel/Vite. The Axios client is
configured in `resources/js/services/api.js`; verify the actual API base
URL and authentication handling there.

## Authentication and authorization

Protected requests must use the authentication mechanism configured by
the project. If the application uses Sanctum bearer tokens, include:

``` http
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json
```

Permission keys used by the application include:

`dashboard.view`, `reports.view`, `orders.view_all`,
`orders.view_assigned`, `orders.create`, `orders.update`,
`orders.assign`, `orders.change_status`, `orders.delete`,
`orders.add_notes`, `customers.view`, `customers.create`,
`customers.update`, `customers.delete`, `products.view`,
`products.create`, `products.update`, `products.delete`, `users.manage`,
`roles.manage`, `permissions.manage`, `audit_logs.view`.

Frontend permission checks improve UX but do not replace backend
authorization. Users with assigned-order-only access must not be able to
retrieve other users' orders by manually calling APIs.

## API documentation

See [API_DOCUMENTATION.md](API_DOCUMENTATION.md). This link assumes the
file is stored in the repository root; update it if the file is stored
under `docs/`.

Before submission, compare every documented route with:

``` bash
php artisan route:list --path=api
```

Document the actual method, URI, authentication, permissions, request
parameters/body, success response, validation response, and pagination
shape.

## Project structure

``` text
app/
  Http/Controllers/Api/     # API controllers
  Http/Requests/            # Request validation, if used
  Models/                   # Eloquent models
database/
  migrations/               # Database schema
  seeders/                  # Roles, permissions, demo data
resources/
  js/
    pages/                  # Vue pages
    services/api.js         # Axios setup
    # Router and Pinia store files
  views/app.blade.php       # Vue app entry view
routes/
  api.php                   # API routes
  web.php                   # Web routes/fallback
tests/
  Feature/                  # API and application feature tests
```

Adjust this tree if the actual repository structure differs.

## Testing

Run all backend tests:

``` bash
php artisan test
```

Run the order management test class:

``` bash
php artisan test --filter=OrderManagementTest
```

Build the frontend:

``` bash
npm run build
```

Before submission, verify authentication, role permissions, order
creation/update/assignment/status transitions, invalid transitions,
notes/history/activity, customer/product CRUD, inventory edge cases,
search/sort/filter/pagination, dashboard metrics, and notification
actions.

The test suite may use in-memory SQLite while local development uses
MySQL. Database-specific SQL must be compatible with the test driver or
handled explicitly.

## Production build

``` bash
npm run build
```

For deployment, configure production environment variables, use
`APP_DEBUG=false`, serve Laravel's `public/` directory, run migrations
as part of deployment, and keep secrets outside Git. Follow the hosting
environment's Laravel deployment guidance.

## Security notes

-   Validate request data server-side.
-   Enforce authorization in backend middleware/controllers/policies.
-   Recalculate important financial values server-side.
-   Validate order status transitions on the backend.
-   Use query builder/Eloquent parameter binding for user input.
-   Protect mass assignment with `$fillable` or `$guarded`.
-   Never commit `.env`, tokens, private keys, or credentials.
-   Use HTTPS and disable debug output in production.
-   Apply assigned-order scoping to every endpoint that returns order
    information.

## Architecture and trade-offs

-   **Laravel REST API + Vue 3:** Laravel handles validation, data
    access, workflow rules, authentication, and authorization; Vue
    provides the interactive UI.
-   **Pinia + Axios:** Pinia centralizes frontend state such as the
    authenticated user and permissions; Axios centralizes API
    configuration and request handling.
-   **Permission-based access:** UI checks control navigation and
    actions, while backend checks are the security boundary.
-   **Order history/activity:** Status changes and order events provide
    traceability.
-   **Testing:** Feature tests exercise API behavior; SQLite/MySQL
    differences need explicit consideration.

Potential future improvements include formal OpenAPI/Swagger
documentation, a Postman collection, more concurrency tests for stock
updates, CI checks, query/index review, rate limiting, and queued
processing where justified. Clearly distinguish completed work from
planned improvements.

## AI usage disclosure

AI tools were used as a development aid for research, debugging,
reviewing errors, implementation ideas, and documentation. Customize
this section before submission to accurately identify which tools were
used, where they helped, what was implemented or reviewed manually, and
which suggestions were changed or rejected. The developer is responsible
for understanding and explaining all submitted code.
