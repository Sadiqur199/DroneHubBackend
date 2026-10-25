# DroneHabBackend

Backend engine for **DroneHab Australia** — Laravel 11 · PHP 8.3 · MySQL 8 · Redis.

This repository is the **backend only**. The React + Vite storefront lives in its own
repository: [Sadiqur199/DroneHub-Frontend](https://github.com/Sadiqur199/DroneHub-Frontend).

---

## 📦 Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 (PHP 8.3) |
| Database | MySQL 8 (110-table schema) |
| Cache / Session / Queue | Redis 7 |
| Web server | Nginx + PHP-FPM (Supervisor-managed) |
| Object storage | Cloudflare R2 (S3-compatible) |
| Payments | Stripe (incl. Afterpay), Cashier |
| Media | Spatie Media Library, Intervention Image |
| Deploy target | Railway (Dockerfile builder) |

---

## 📁 Repository layout

```
DroneHubBackend/
├── app/                     # Controllers, Middleware, Services, Enums
│   ├── Enums/               # OrderStatus, JobStatus, ClaimStatus, CustomerGroupSlug
│   ├── Http/Controllers/Api # Auth, Product, Order, Setting, Tutorial
│   ├── Http/Middleware/     # ResolveCustomerGroup, EnsureVisible
│   └── Services/            # PricingService (AUD + 10% GST, multi-tier pricing)
├── bootstrap/               # Laravel bootstrap + cached files
├── config/                  # Laravel config (cors, etc.)
├── database/
│   ├── migrations/          # Customer groups, users, addresses, settings, audit, catalog
│   └── seeders/             # CustomerGroupSeeder, DatabaseSeeder
├── docker/
│   ├── nginx/templates/     # Nginx vhost template (PORT aware)
│   ├── php/local.ini        # PHP production settings
│   ├── start.sh             # Container entrypoint
│   └── supervisord.conf     # PHP-FPM + Nginx process manager
├── public/                  # Nginx document root
├── routes/                  # web.php, api.php (prefix: /api/v1), console.php
├── storage/                 # Framework cache, sessions, views, logs, uploads
├── Dockerfile               # Production image (PHP-FPM + Nginx + Supervisor)
├── railway.json             # Railway build/deploy configuration
└── composer.json
```

---

## 🚀 Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

Full stack with Docker (run from the parent `DroneHub/` monorepo folder):

```bash
docker compose up -d
# Frontend  : http://localhost:5173
# Backend   : http://localhost:8000
# Mailpit   : http://localhost:8025
# Health    : http://localhost:8000/up
```

---

## 🐳 Docker image

```bash
docker build -t dronehub-backend .
docker run -p 8080:8080 --env-file .env dronehub-backend
```

The image is a single container running **Nginx + PHP-FPM** under **Supervisor**.
`docker/start.sh` renders the Nginx vhost for `$PORT` (defaults to `8080`) and
prepares the Laravel writable directories before booting the workers.

---

## ☁️ Railway deployment

1. Create a new Railway project from this repository (Dockerfile builder is detected).
2. Add a **MySQL** service and a **Redis** service.
3. Copy the variables from `.env.example` into the Railway service variables and set
   real values (`APP_KEY`, `DB_*`, `REDIS_*`, `STRIPE_*`, `AWS_*` for R2, `MAIL_*`).
4. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<your-domain>`.
5. Deploy — `railway.json` runs `php artisan migrate --force` as the release step and
   health-checks `/up`.

---

## 🔌 API

Base prefix: `/api/v1` · Health: `GET /up` · Status: `GET /` · API health: `GET /api/v1/health`

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/auth/register` | Customer registration |
| POST | `/api/v1/auth/login` | Issue auth token |
| GET | `/api/v1/auth/me` | Current authenticated user |
| GET | `/api/v1/products` | Catalogue with role-based pricing |
| GET | `/api/v1/products/{id}` | Product detail + visibility rules |
| GET | `/api/v1/tutorials` | Flight academy content |
| POST | `/api/v1/checkout` | Checkout (AUD, 10% GST) |
| GET | `/api/v1/settings` | Australian store settings (ABN, GST) |
| GET | `/api/v1/settings/banner` | Dynamic hero banner |
| POST/PATCH/DELETE | `/api/v1/admin/*` | Admin product, order, tutorial & banner management |

Customer groups (Retail / Wholesale / Agent) drive pricing through `PricingService` and
are resolved by the `ResolveCustomerGroup` middleware.

---

## 🏢 Company

- **Company** — DroneHab Australia · **ABN** 48 612 908 124 (GST Registered)
- **Base** — Cnr Gordon & Gregory Streets, Mackay, Queensland 4740
- **Phone** — 1300 544 968 · **Email** — enquiries@dronehab.com.au
- **Accreditation** — CASA.ReOC.8842 Certified Commercial RPAS Operator
