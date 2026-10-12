# Order Management System --- API Documentation

> Draft: verify all methods, paths, payloads, and responses against the
> running Laravel app before submission.

## Overview

-   Local API base URL: `http://127.0.0.1:8000/api`
-   Request/response format: JSON
-   Protected endpoints require the configured Laravel authentication
    mechanism.
-   Backend authorization is required; hiding frontend buttons is not
    sufficient.

Start Laravel with `php artisan serve`.

## Authentication

  Method   Endpoint        Purpose
  -------- --------------- ------------------------
  POST     `/api/login`    Sign in
  POST     `/api/logout`   Sign out
  GET      `/api/user`     Get authenticated user

For Sanctum personal access tokens, send
`Authorization: Bearer <TOKEN>`. Confirm whether this project uses
bearer tokens or another Sanctum mode and document the actual login
response.

## Endpoint inventory

The following routes were identified during development. Run
`php artisan route:list --path=api` and correct this table to match the
exact registered routes.

  ------------------------------------------------------------------------------------------------------------
  Module            Method                 Endpoint                         Purpose
  ----------------- ---------------------- -------------------------------- ----------------------------------
  Dashboard         GET                    `/api/dashboard`                 Dashboard metrics/trends

  Dashboard         GET                    `/api/admin/dashboard`           Admin dashboard, if registered

  Orders            GET                    `/api/orders`                    List/search/filter/sort/paginate

  Orders            POST                   `/api/orders`                    Create order

  Orders            GET                    `/api/orders/{order}`            Order details

  Orders            PUT/PATCH              `/api/orders/{order}`            Update order; verify method

  Orders            DELETE                 `/api/orders/{order}`            Delete order, if registered

  Orders            PATCH                  `/api/orders/{order}/assign`     Assign order; `assigned_to`

  Orders            POST/PATCH             `/api/orders/{order}/status`     Change status; verify method

  Orders            GET                    `/api/orders/{order}/history`    Status history

  Orders            POST                   `/api/orders/{order}/notes`      Add note

  Orders            GET                    `/api/orders/{order}/activity`   Activity timeline

  Customers         GET/POST               `/api/customers`                 List/create customers

  Customers         GET/PUT/PATCH/DELETE   `/api/customers/{customer}`      Customer operations; verify
                                                                            registered methods

  Products          GET/POST               `/api/products`                  List/create products

  Products          GET/PUT/PATCH/DELETE   `/api/products/{product}`        Product operations; verify
                                                                            registered methods

  Administration    Various                `/api/admin/users...`            User management; confirm exact
                                                                            routes

  Administration    Various                `/api/admin/roles...`            Role management; confirm exact
                                                                            routes

  Administration    Various                `/api/admin/permissions...`      Permission management; confirm
                                                                            exact routes

  Audit logs        GET                    `/api/admin/audit-logs...`       View audit logs; confirm exact
                                                                            route

  Notifications     Various                `/api/notifications...`          Notification actions; confirm
                                                                            exact routes
  ------------------------------------------------------------------------------------------------------------

## Orders

### List orders

`GET /api/orders`

Query parameters identified in testing/UI (confirm support in
controller):

  Parameter      Example                Purpose
  -------------- ---------------------- --------------------
  `search`       `search=ORD654321`     Search
  `status`       `status=processing`    Filter by status
  `priority`     `priority=high`        Filter by priority
  `sort_by`      `sort_by=order_date`   Sort field
  `sort_direction`   `sort_direction=desc`      Sort direction
  `page`         `page=1`               Page
  `per_page`     `per_page=10`          Page size

Example:
`GET /api/orders?sort_by=order_date&sort_direction=desc&per_page=10`

Users with `orders.view_all` may view all authorized orders. Users with
`orders.view_assigned` must be limited to their assigned orders by the
backend.

### Create order

`POST /api/orders`

Illustrative payload; align required fields with the current Form
Request:

``` json
{
  "customer_id": 1,
  "priority": "normal",
  "order_date": "2026-10-11",
  "notes": "Contact customer before delivery.",
  "items": [
    {
      "product_id": 1,
      "product_name": "Example Product",
      "sku": "SKU-001",
      "quantity": 2,
      "unit_price": 100,
      "discount": 0,
      "tax": 0,
      "total": 200
    }
  ]
}
```

The backend should validate the customer/product IDs and quantities and
recalculate critical pricing values server-side rather than blindly
trusting browser totals.

### Assignment, status, notes, history

-   `PATCH /api/orders/{order}/assign` --- illustrative body:
    `{"assigned_to": 3}`.
-   `/api/orders/{order}/status` --- use the exact registered method and
    body from `route:list` and the controller.
-   `GET /api/orders/{order}/history` --- retrieve status history.
-   `POST /api/orders/{order}/notes` --- add a note.
-   `GET /api/orders/{order}/activity` --- retrieve activity timeline.

Expected lifecycle states include Draft, Pending, Confirmed, Processing,
Ready, Completed, and Cancelled. Confirm actual transition rules in the
implementation. The current test pass has been checking the notes status
code and activity response structure, so update those details after
tests pass.

## Customers and products

Customer operations include listing/searching and CRUD or deactivation,
subject to permissions. Product fields identified during development
include `name`, `sku`, `description`, `price`, `stock_quantity`, and
`status`; confirm validation rules in the current code. Document exact
stock deduction/restoration timing from the inventory implementation.

## Permission keys

-   `dashboard.view`
-   `reports.view`
-   `orders.view_all`
-   `orders.view_assigned`
-   `orders.create`
-   `orders.update`
-   `orders.assign`
-   `orders.change_status`
-   `orders.delete`
-   `orders.add_notes`
-   `customers.view`
-   `customers.create`
-   `customers.update`
-   `customers.delete`
-   `products.view`
-   `products.create`
-   `products.update`
-   `products.delete`
-   `users.manage`
-   `roles.manage`
-   `permissions.manage`
-   `audit_logs.view`

Apply permissions at the backend. Order read access can allow either
`orders.view_all` or `orders.view_assigned`, but assigned-only scoping
must be enforced server-side.

## Response conventions

Preferred success example (verify actual controller output before
claiming this is universal):

``` json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {}
}
```

Preferred validation error example:

``` json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["The field is required."]
  }
}
```

## Common status codes

  Code   Meaning
  ------ ---------------------------------
  200    Successful read/update/action
  201    Resource created
  401    Missing/invalid authentication
  403    Authenticated but not permitted
  404    Resource/route not found
  422    Validation failed
  500    Unexpected server error

## Verify before submission

``` bash
php artisan route:list --path=api
php artisan test
```

For every documented endpoint, confirm method, URI, middleware, request
validation, permission requirements, success response, validation
errors, and pagination shape. Remove routes that are not actually
registered.
