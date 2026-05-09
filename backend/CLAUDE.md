# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 12 REST API backend for an AliExpress CIS (Russia) admin panel. Manages orders, logistics (FBS/DBS), shops, teams, and role-based access control. Integrates with AliExpress CIS API (openapi.aliexpress.ru), China Post, and Leiyi/Sz56t logistics providers.

## Development Commands

```bash
# Full project setup (first time)
composer setup

# Start dev environment (Laravel server + queue + logs + Vite)
composer dev

# Run tests
composer test
# or
php artisan test

# Code formatting
./vendor/bin/pint

# Run migrations
php artisan migrate

# Seed dictionary data
php artisan db:seed --class=OrderStatusDictSeeder

# Batch enrich SKU attributes from AliExpress product API
php artisan orders:enrich-sku --limit=500

# Sync orders (usually triggered via API, but can run manually)
php artisan app:auto-sync
```

## Architecture

### Authentication & Authorization
- Laravel Sanctum (token-based API auth)
- Role-based: `super-admin`, `team-admin`, `admin`, `purchaser`
- All protected routes use `auth:sanctum` + `operation.log` middleware

### Key Services (app/Services/)
- **AliExpressService** — Core integration with AliExpress CIS API (JWT auth). Handles order sync, shipping, logistics order creation, handover lists, and SKU attribute enrichment via product/categories APIs.
- **OrderSyncTaskService** — Async order sync with shop-level parallelism using queue jobs.
- **OrderLogisticsService** — Manages logistics state machine (create → ship → track → deliver).
- **ChinaPostService** — E-packet/EMS integration for DBS orders.
- **Sz56tService** — Leiyi logistics integration for DBS orders.
- **OrderFbsWorkflowService** — FBS (AliExpress fulfillment) multi-step workflow.

### Order Status Model
Two independent status dimensions:
- **order_display_status** — From AliExpress API: `WaitSendGoods`, `WaitAcceptGoods`, `InCancel`, `Complete`, `Close`, `InIssue`
- **backend_status** — Internal workflow: `wait_review`, `pending_purchase`, `purchased`, `shipped`, `abandoned`

Dispute status (`InIssue`) is derived from `order_items.issue_status` (per-line from API), not the order-level display status.

### Logistics Types
- **FBS** — AliExpress fulfillment. Multi-step workflow: create logistic order → create handover → print label → confirm pickup.
- **DBS** — Seller ships directly. Providers: Leiyi/Sz56t (AML tracking), China Post (LK/LN tracking), or manual.
- **Logistics template** — Backend-only field (`orders.logistics_template`): `fbs`, `leiyi`, `chinapost`, or empty (default). Never set by sync; only set manually by users.

### Customs Products
Dedicated `customs_products` table for paired CN/EN customs declaration names. Used by ChinaPost and Leiyi shipping dialogs as searchable dropdowns. Accessible by `super-admin`, `team-admin`, and `purchaser` roles. CRUD via `CustomsProductController`.

### Data Dictionary System
Dynamic configuration via `dict_types` + `dict_data` tables. Used for status labels, dropdown options, and tab rendering. Frontend fetches dictionaries and builds label maps for translation.

### Queue Jobs
Database-driven queue. Order sync runs as background jobs (`RunOrderSyncTask`, `RunOrderSyncShopTask`) with progress tracking.

## API Conventions

- All responses use `ApiResponse` trait: `$this->success($data, $message)` / `$this->error($message, $code)`
- Pagination: `?page=1&limit=20` → returns `{ items: [], total: N }`
- Filters passed as query params, dates as `yyyy-MM-dd`

## Database

- MySQL, timezone: Asia/Shanghai
- Key tables: `orders`, `order_items`, `order_logistics`, `shops`, `teams`, `users`, `dict_types`, `dict_data`, `customs_products`
- `order_items.sku_attributes` (JSON) — enriched from AliExpress product/categories API
- `order_items.properties` (JSON) — raw `properties_map` from order sync
- `order_items.issue_status` — per-line dispute status from API

## Frontend Context

The frontend is a separate Vue 2 + Element UI app in `../frontend/`. It consumes this API. Key patterns:
- Dictionary-driven dynamic tabs and labels
- Order list uses grid-based card layout (not a table)
- Status tabs are built from dictionary data, sorted by `DISPLAY_STATUS_ORDER`

## External API Integration

AliExpress CIS API (openapi.aliexpress.ru):
- Auth: JWT token per shop (`shops.access_token`)
- SSL verification disabled (`verify => false`) for local dev
- Rate limiting: 200ms sleep between product API calls during enrichment
- SKU attributes require 3 API calls: product detail → category sku_properties → values-dictionary
