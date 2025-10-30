# 💱 Currency Conversion API

High-performance currency conversion API built with **Laravel**, following **Domain-Driven Design (DDD)** and **CQRS** principles.

The system uses a multi-level data architecture (Redis cache, local database, and external providers) with rate limiting, fault tolerance (circuit breaker), and background synchronization using median aggregation.

---

## 🚀 Features

- **DDD Architecture** (Domain / Application / Infrastructure layers)
- **CQRS Pattern** – isolated query handler for conversion logic
- **Multi-level Data Access**
  - **L1:** Redis cache (1 second)
  - **L2:** Local database (≤1 hour)
  - **L3:** External APIs (3 fallbacks)
- **Circuit Breaker** and **Retry Logic**
- **Background Job** for hourly rate synchronization
- **Median Aggregation** from multiple providers
- **Strict Rate Limiting** (500 req/min per user, 1000 per IP)
- **Health Check Endpoint** (`/health`)
- **Detailed Logging** for provider events and fallbacks

---

## 📡 API Endpoint

### `GET /api/v1/convert`

#### Parameters

| Name | Type | Required | Description |
|------|------|-----------|-------------|
| `from` | string | ✅ | Base currency (ISO 4217, e.g. `USD`) |
| `to` | string | ✅ | Target currency (ISO 4217, e.g. `EUR`) |
| `amount` | string / number | ✅ | Amount to convert (high precision) |

#### Example Request
```
GET /api/v1/convert?from=USD&to=EUR&amount=100
```

#### Example Response
```json
{
  "data": {
    "from": "USD",
    "to": "EUR",
    "amount": "100.00",
    "result": "93.15",
    "rate": "0.9315",
    "last_updated": "2025-10-30T14:12:00Z"
  },
  "meta": {
    "source": "local_db",
    "execution_time_ms": 12.45
  }
}
```

---

## 🩺 Health Check Endpoint

### `GET /health`

Checks availability of:
- Database
- Cache (Redis)
- External providers

#### Example Response
```json
{
  "status": "ok",
  "components": {
    "database": "ok",
    "cache": "ok",
    "external_providers": {
      "frankfurter": "ok",
      "freecurrencyapi": "ok",
      "currencyfreaks": "ok"
    }
  }
}
```

---

## 🧩 Architecture Overview

```
App/
├── Domain/
│   ├── Entities/
│   │   └── ExchangeRate.php
│   ├── ValueObjects/
│   │   ├── Currency.php
│   │   └── Money.php
│
├── Application/
│   ├── Queries/
│   │   └── ConvertCurrencyQuery.php
│   └── Handlers/
│       └── ConvertCurrencyQueryHandler.php
│
├── Infrastructure/
│   └── Repositories/
│       └── CurrencyRateRepository.php
│
├── Http/
│   └── Controllers/
│       ├── ConvertController.php
│       └── HealthController.php
│
└── Jobs/
    └── SyncCurrencyRatesJob.php
```

---

## ⚙️ Background Jobs and Scheduler

The system keeps currency rates up to date automatically using Laravel Queues and the Scheduler.

### Start the queue worker

Run a worker to process queued jobs:
```bash
php artisan queue:work
```

### Start the scheduler

The scheduler runs the `SyncCurrencyRatesJob` every hour (defined in `routes/console.php`).

Run it locally with:
```bash
php artisan schedule:work
```

---

## 🧰 Installation & Setup

```bash
git clone https://github.com/<your-username>/currency-conversion-api.git
cd currency-conversion-api
composer install
cp .env.example .env
php artisan key:generate
```

Set environment variables:
```
FREECURRENCYAPI_KEY=your_api_key
CURRENCYFREAKS_KEY=your_api_key
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

Run database migrations:
```bash
php artisan migrate
```

Serve the application:
```bash
php artisan serve
```

