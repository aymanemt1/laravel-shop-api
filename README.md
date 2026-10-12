# 🛒 Laravel Shop API

A complete **RESTful e-commerce API** built with Laravel 12 — authentication, catalog, cart and orders, with role-based admin access and a full test suite.

## ✨ Features

- 🔐 **Sanctum token auth** — register, login, logout, profile
- 📦 **Product catalog** — search, filter by category/price, sort, pagination
- 🗂️ **Categories** — with product counts
- 🛒 **Cart** — add/update/remove items, live subtotal
- 📋 **Orders** — atomic checkout (DB transaction), stock decrement, status workflow
- 👮 **Admin role** — product/category management, order status updates
- ✅ **Tested** — 18 feature tests covering auth, catalog, cart, checkout and permissions

## 🛠️ Stack

Laravel 12 · PHP 8.3 · Sanctum · SQLite/MySQL · PHPUnit

## 🚀 Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite (zero config)
touch database/database.sqlite
# in .env: DB_CONNECTION=sqlite

php artisan migrate --seed
php artisan serve
```

Seeded accounts:

| Role     | Email              | Password   |
|----------|--------------------|------------|
| Admin    | admin@shop.test    | `password` |
| Customer | customer@shop.test | `password` |

## 📡 API reference

Base URL: `http://localhost:8000/api`

### Auth

| Method | Endpoint        | Auth | Description              |
|--------|-----------------|------|--------------------------|
| POST   | `/register`     | —    | Register + receive token |
| POST   | `/login`        | —    | Login + receive token    |
| GET    | `/me`           | ✅   | Current user             |
| POST   | `/logout`       | ✅   | Revoke current token     |

### Catalog (public)

| Method | Endpoint                      | Description                                    |
|--------|-------------------------------|------------------------------------------------|
| GET    | `/categories`                 | List categories with product counts            |
| GET    | `/categories/{category}`      | Single category                                |
| GET    | `/products`                   | Paginated products — `?search=&category=&min_price=&max_price=&in_stock=1&sort=price_asc\|price_desc\|newest` |
| GET    | `/products/{slug}`            | Single product                                 |

### Cart & orders (authenticated)

| Method   | Endpoint               | Description                          |
|----------|------------------------|--------------------------------------|
| GET      | `/cart`                | Cart items + subtotal                |
| POST     | `/cart`                | Add/update item `{product_id, quantity}` |
| DELETE   | `/cart/{product}`      | Remove item                          |
| DELETE   | `/cart`                | Clear cart                           |
| POST     | `/checkout`            | Create order from cart (transaction) |
| GET      | `/orders`              | Order history                        |
| GET      | `/orders/{order}`      | Order details                        |

### Admin only

| Method | Endpoint                        | Description              |
|--------|---------------------------------|--------------------------|
| POST   | `/categories`                   | Create category          |
| DELETE | `/categories/{category}`        | Delete category          |
| POST   | `/products`                     | Create product           |
| PUT    | `/products/{product}`           | Update product           |
| DELETE | `/products/{product}`           | Delete product           |
| PATCH  | `/orders/{order}/status`        | Update status (`pending→paid→shipped→delivered→cancelled`) |

Auth header: `Authorization: Bearer <token>`

## 🧪 Tests

```bash
php artisan test
```

18 feature tests — auth flows, catalog filters, cart math, atomic checkout, stock handling, admin/customer authorization boundaries.

## 📁 Structure

```
app/
├── Http/
│   ├── Controllers/Api/   # Auth, Category, Product, Cart, Order
│   ├── Middleware/        # EnsureUserIsAdmin
│   ├── Requests/          # FormRequest validation
│   └── Resources/         # JSON API resources
└── Models/                # Category, Product, CartItem, Order, OrderItem
database/
├── factories/             # Category, Product factories
├── migrations/            # 5 domain migrations
└── seeders/               # 4 categories × 12 products + demo users
tests/Feature/             # Auth, Product, Cart+Order tests
```

## 🔮 Roadmap

- Stripe checkout integration
- Product image uploads (S3)
- React storefront client
- Order confirmation emails (queue)

---

Built by [Aymane Moutoute](https://github.com/aymanemt1) · Full Stack Software Developer
