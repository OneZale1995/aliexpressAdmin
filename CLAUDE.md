# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AliExpress CIS (Russia) admin panel — monorepo with a Vue 2 frontend and a Laravel backend (separate git repo in `backend/`).

## Repository Structure

```
aliexpressAdmin/          ← this git repo (frontend + config)
├── frontend/             ← Vue 2 + Element UI SPA
├── backend/              ← Laravel 12 API (separate git repo, has its own CLAUDE.md)
└── .claude/              ← Claude Code config
```

## Development Commands

```bash
# Frontend dev server
cd frontend && npm run dev

# Frontend lint
cd frontend && npm run lint

# Frontend build
cd frontend && npm run build:prod

# Backend (see backend/CLAUDE.md for full details)
cd backend && composer dev
```

## Frontend Architecture

### Tech Stack
- Vue 2.6 + Vue Router + Vuex
- Element UI 2.13
- vue-element-admin template (v4.4)
- Axios for HTTP, ECharts for charts

### Key Directories
- `src/views/order/` — Order management (main business module)
- `src/views/system/` — System admin (users, roles, permissions, menus, dict, logs)
- `src/views/team/` — Team management
- `src/views/shop/` — Shop management
- `src/api/` — API request functions (one file per module)
- `src/router/index.js` — Route definitions with role-based access

### Order Module Structure (src/views/order/)
- `index.vue` — Main order page (list, filters, dialogs orchestration)
- `constants.js` — Shared constants, default form factories, dict code mappings
- `utils.js` — Pure utility functions (label lookups, calculations)
- `components/OrderListSection.vue` — Order card grid rendering
- `components/OrderCommentDialog.vue` — Purchase amount / logistics fee input
- `components/ChinaPostShipDialog.vue` — China Post (E-packet) shipping form with address book
- `components/LeiyiShipDialog.vue` — Leiyi/Sz56t shipping form
- `statistics.vue` — Order statistics with ECharts

### Dictionary System
Frontend fetches dictionaries via `fetchDictBatch(codes)` and builds `dictLabelMap` for translating codes to labels. Dict codes are defined in `constants.js` → `ORDER_DICT_CODE`.

### Logistics Templates
Three types: `fbs` (FBS), `leiyi` (雷翼), `chinapost` (中国邮政). Default is empty. Template is backend-only, never set by sync.

### Customs Products
Paired CN/EN customs declaration names stored in `customs_products` table. Used as searchable dropdowns in ChinaPost and Leiyi shipping dialogs. Selecting one language auto-fills the other (linkage). Managed under Order module (`/order/customs-product`), accessible by team-admin and purchaser roles.

### Shipping Dialogs
- Declaration amounts are randomly generated (1-10 USD) on dialog open, never using original product values
- Sender info auto-fills from address book on dialog open
- Receiver address fields are manual input (no region dropdowns)

### Role-Based Routing
Routes use `meta.roles` array. Key roles: `super-admin`, `team-admin`, `admin`, `purchaser`. The order module is accessible to `super-admin`, `team-admin`, and `purchaser`.

## API Conventions

- All API functions in `src/api/` use POST method
- Response format: `{ code: 0, data: { items: [], total: N }, message: '' }`
- Auth via Bearer token (Sanctum), stored in cookie
