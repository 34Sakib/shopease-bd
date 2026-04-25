# ShopEase BD — Frontend (React + TypeScript)

Vite + React 19 + TanStack Query + React Router. Consumes the Laravel API under `/api`.

## Setup

```bash
cd frontend
npm install
```

Copy `.env.example` to `.env` and set `VITE_API_URL` (include the `/api` suffix), for example:

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

## Dev

1. Start MySQL + Laravel (`php artisan serve` in `../backend`).
2. Ensure `../backend/.env` has `FRONTEND_URL=http://localhost:5173` (or your Vite origin) for CORS.
3. Run:

```bash
npm run dev
```

Open http://localhost:5173 — Dashboard checks `/api/health`; Import, Sales, and Export call the Phase 4 endpoints.

## Large exports

If an export returns `202` with a `job_id`, run a queue worker in the backend:

```bash
cd ../backend
php artisan queue:work
```

## Build

```bash
npm run build
npm run preview
```
