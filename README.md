# ShopEase BD - Wholesale Sales Platform

| Part | Stack | Path |
|------|--------|------|
| **Backend** | Laravel 11, MySQL, REST API, queued exports | `backend/` |
| **Frontend** | React 19, TypeScript, Vite, TanStack Query | `frontend/` |
| **Dataset generator** | Python 3 | `generate_sales_data.py` → `sales_data.csv` |

**Base API URL (local):** `http://127.0.0.1:8000/api`  
**Frontend (local):** `http://localhost:5173`

---

## Prerequisites

- **PHP** 8.2+ and **Composer**
- **MySQL** or **MariaDB** (e.g. XAMPP)
- **Node.js** 20+ and **npm** (for the frontend)
- **Python** 3.10+ (for the CSV generator)

---

## Local setup

### 1. Database

Create an empty database (example name matches `.env`):

```sql
CREATE DATABASE shopease_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend (Laravel)

```bash
cd backend
cp .env.example .env
php artisan key:generate
```

Edit `.env`: set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` for your MySQL instance. On **MariaDB** (common with XAMPP), keep:

```ini
DB_COLLATION=utf8mb4_unicode_ci
```

Install dependencies and run migrations:

```bash
composer install
php artisan migrate
```

Start the HTTP server:

```bash
php artisan serve
```

API is available at `http://127.0.0.1:8000/api/...`.

**Large exports (>10,000 rows)** return `202` and a `job_id`. Process the queue in another terminal:

```bash
cd backend
php artisan queue:work
```

### 3. Frontend (React)

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Ensure `VITE_API_URL` points at your API (include the `/api` suffix), e.g. `http://127.0.0.1:8000/api`.

**CORS:** In `backend/.env`, set `FRONTEND_URL` to your Vite origin (default `http://localhost:5173`). `backend/config/cors.php` allows that origin and exposes `Content-Disposition` for downloads.

### 4. (Optional) Generate fresh sample data

From the **repository root** (see [Data generator](#data-generator)):

```bash
python generate_sales_data.py
```

---

## Environment variables

### Backend — `backend/.env`

Values below mirror `backend/.env.example`. **Copy that file to `.env`** and adjust for your machine.

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | Application name (e.g. `ShopEase BD`) |
| `APP_ENV` | `local`, `production`, … |
| `APP_KEY` | Set via `php artisan key:generate` |
| `APP_DEBUG` | `true` in local development |
| `APP_TIMEZONE` | e.g. `UTC` |
| `APP_URL` | Public URL of the Laravel app (e.g. `http://localhost:8000`) |
| **`FRONTEND_URL`** | **Origin allowed by CORS** (e.g. `http://localhost:5173`) |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Localization |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | Maintenance mode |
| `BCRYPT_ROUNDS` | Password hashing (default app user if used) |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL` | Logging |
| **`DB_CONNECTION`** | **`mysql`** |
| **`DB_HOST`** | Database host (e.g. `127.0.0.1`) |
| **`DB_PORT`** | **Default `3306`** |
| **`DB_DATABASE`** | **Database name** (e.g. `shopease_bd`) |
| **`DB_USERNAME`**, **`DB_PASSWORD`** | **MySQL credentials** |
| **`DB_COLLATION`** | **`utf8mb4_unicode_ci`** recommended for MariaDB compatibility |
| `SESSION_*` | Session driver (Laravel default) |
| `BROADCAST_CONNECTION` | Default `log` |
| `FILESYSTEM_DISK` | `local` (import error logs under `storage/app/imports/`) |
| **`QUEUE_CONNECTION`** | **`database`** — required for **async exports** |
| `CACHE_STORE`, `CACHE_PREFIX` | Cache |
| `MEMCACHED_HOST` | If using Memcached |
| `REDIS_*` | If using Redis |
| `MAIL_*` | Mail configuration |
| `AWS_*` | S3 / AWS (optional) |
| `VITE_APP_NAME` | Passed to Vite if using Laravel’s asset bundling (optional here) |

Full verbatim template: **`backend/.env.example`**.

### Frontend — `frontend/.env`

| Variable | Purpose |
|----------|---------|
| **`VITE_API_URL`** | Base URL for API calls **including `/api`**, e.g. `http://127.0.0.1:8000/api` |

Template: **`frontend/.env.example`**.

### Python generator

The generator does **not** read a `.env` file. On Windows, if the console cannot print Bengali characters, you can use:

```powershell
$env:PYTHONIOENCODING = "utf-8"
python generate_sales_data.py
```

---

## Data generator

Script: **`generate_sales_data.py`** (repository root).

Produces **`sales_data.csv`** with UTF-8 **BOM** (Excel-friendly), messy fields (branches, dates, prices, discounts, payment casing, optional Bengali product names, ragged rows, duplicate `sale_id`s), per the assignment.

### Usage

```text
python generate_sales_data.py [--rows N] [--duplicates N] [--out PATH] [--seed S]
```

| Argument | Default | Description |
|----------|---------|-------------|
| `--rows` | `20000` | Number of data rows written |
| `--duplicates` | `250` | Extra rows that reuse existing `sale_id`s |
| `--out` | `sales_data.csv` | Output file path |
| `--seed` | *(none)* | Optional RNG seed for reproducible output |

### Examples

```bash
# Default: 20,000 rows, 250 duplicate sale_ids → sales_data.csv
python generate_sales_data.py

python generate_sales_data.py --rows 25000 --seed 42 --out ./sales_data.csv
```

---

## Importer (HTTP API)

The **importer** is the Laravel endpoint **`POST /api/import`**. It accepts **CSV** or **Excel** (`.csv`, `.txt`, `.xlsx`, `.xls`, `.ods`), streams rows in chunks of **500**, normalizes each row, counts **total / inserted / skipped_duplicate / skipped_invalid**, and optionally writes an **error log CSV** under `backend/storage/app/imports/{import_id}/errors.csv`.

### Example: `curl`

```bash
curl -s -X POST "http://127.0.0.1:8000/api/import" \
  -H "Accept: application/json" \
  -F "file=@sales_data.csv"
```

### Example success response (`200`)

```json
{
  "status": "ok",
  "import_id": "fa50852e-acde-42d7-a47b-b436c943ec48",
  "total": 20000,
  "inserted": 19750,
  "skipped_duplicate": 250,
  "skipped_invalid": 0,
  "error_log_url": "http://127.0.0.1:8000/api/imports/fa50852e-acde-42d7-a47b-b436c943ec48/errors"
}
```

- If `error_log_url` is **`null`**, there were no rows logged to the error file (e.g. no invalid rows and no duplicate log file generated).
- **`GET error_log_url`** returns a CSV download (`row_number`, `reason`, `raw_sale_id`, `raw_row_json`).

### Example validation error (`422`)

Uploading a disallowed extension may return Laravel validation JSON, for example:

```json
{
  "message": "The file field must be a file of type: csv, txt, xlsx, xls, ods.",
  "errors": {
    "file": [
      "The file field must be a file of type: csv, txt, xlsx, xls, ods."
    ]
  }
}
```

---

## API reference

All paths are prefixed with **`/api`**. Replace host/port as needed.

### `GET /api/health`

**Response `200`**

```json
{
  "status": "ok",
  "app": "ShopEase BD",
  "time": "2026-04-24T12:00:00+00:00"
}
```

---

### `POST /api/import`

| | |
|---|---|
| **Body** | `multipart/form-data` field **`file`** (max **50 MB** in validation) |
| **Response** | See [Importer](#importer-http-api) |

---

### `GET /api/imports/{import_id}/errors`

Downloads the **error log CSV** for that import (404 if none).

---

### `GET /api/sales`

Paginated list (**100** per page). **Query parameters** (all optional, combinable):

| Parameter | Example | Description |
|-----------|---------|-------------|
| `branch` | `Mirpur` | Exact normalized branch name |
| `from` | `2024-01-01` | `Y-m-d`, inclusive |
| `to` | `2024-12-31` | `Y-m-d`, inclusive |
| `category` | `Groceries` | Exact match on stored category |
| `payment_method` | `cash` | `cash`, `bkash`, `nagad`, `card` |
| `page` | `2` | Page number |

**Example**

```http
GET /api/sales?branch=Mirpur&from=2024-01-01&to=2024-12-31&payment_method=cash&page=1
```

**Response `200`** (Laravel paginator; `data` is an array of sale objects)

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "sale_id": "1",
      "branch": "Mirpur",
      "sale_date": "2026-03-11",
      "product_name": "Sugar 1kg",
      "category": "Groceries",
      "quantity": 8,
      "unit_price": "607.36",
      "discount_pct": "0.0000",
      "payment_method": "nagad",
      "salesperson": "Nusrat Jahan"
    }
  ],
  "first_page_url": "http://127.0.0.1:8000/api/sales?page=1",
  "from": 1,
  "last_page": 198,
  "last_page_url": "http://127.0.0.1:8000/api/sales?page=198",
  "next_page_url": "http://127.0.0.1:8000/api/sales?page=2",
  "path": "http://127.0.0.1:8000/api/sales",
  "per_page": 100,
  "prev_page_url": null,
  "to": 100,
  "total": 19750
}
```

---

### `GET /api/sales/summary`

Same **filter** query params as **`/api/sales`**, except **`page`** is ignored.

Revenue uses stored (normalized) values:

`SUM(quantity * unit_price * (1 - discount_pct))`

**Example**

```http
GET /api/sales/summary?branch=Mirpur
```

**Response `200`**

```json
{
  "total_revenue": 2800.0,
  "total_quantity": 12,
  "total_rows": 2,
  "average_order_value": 1400.0,
  "top_products": [
    {
      "product_name": "Rice 5kg",
      "revenue": 1000.0,
      "quantity": 10
    }
  ],
  "branch_breakdown": [
    {
      "branch": "Mirpur",
      "rows": 2,
      "revenue": 2800.0,
      "quantity": 12
    }
  ]
}
```

---

### `GET /api/export/csv` and `GET /api/export/excel`

Same filter query params as **`/api/sales`** (no `page`).

| Rows matching filters | Response |
|------------------------|----------|
| **≤ 10,000** | **`200`** — file streamed (**CSV** with UTF-8 **BOM**; **Excel** two sheets: **Sales Data**, **Summary**) |
| **> 10,000** | **`202`** JSON — background job |

**Example — small export (sync CSV)**

```http
GET /api/export/csv?branch=Mirpur
```

Response: **`200`**, `Content-Type: text/csv; charset=utf-8`, file body.

**Example — large export (async)**

```http
GET /api/export/csv
```

**Response `202`**

```json
{
  "status": "queued",
  "job_id": "2deb1e45-6b2f-4240-a8d7-d2fa6d3210c1",
  "row_count": 19750,
  "status_url": "http://127.0.0.1:8000/api/export/status/2deb1e45-6b2f-4240-a8d7-d2fa6d3210c1"
}
```

Run **`php artisan queue:work`** until the job completes.

---

### `GET /api/export/status/{job_id}`

**Response `200` (pending / processing)**

```json
{
  "id": "2deb1e45-6b2f-4240-a8d7-d2fa6d3210c1",
  "status": "processing",
  "format": "csv",
  "row_count": null,
  "file_size_bytes": null,
  "error": null,
  "download_url": null,
  "created_at": "2026-04-24T03:01:25+00:00",
  "started_at": "2026-04-24T03:01:38+00:00",
  "completed_at": null
}
```

**Response `200` (completed)**

```json
{
  "id": "2deb1e45-6b2f-4240-a8d7-d2fa6d3210c1",
  "status": "completed",
  "format": "csv",
  "row_count": 19750,
  "file_size_bytes": 1677238,
  "error": null,
  "download_url": "http://127.0.0.1:8000/api/export/download/2deb1e45-6b2f-4240-a8d7-d2fa6d3210c1",
  "created_at": "2026-04-24T03:01:25+00:00",
  "started_at": "2026-04-24T03:01:38+00:00",
  "completed_at": "2026-04-24T03:01:39+00:00"
}
```

---

### `GET /api/export/download/{job_id}`

Returns **`404`** until status is **`completed`** and the file exists. Then **`200`** with the export file (same MIME rules as sync export).

---

## Tests (backend)

```bash
cd backend
php artisan test
```

Uses SQLite in-memory for PHPUnit (see `phpunit.xml`); API integration matches MySQL behavior for the documented endpoints.

---

## License

Educational / assignment use unless otherwise specified by the course or employer.
