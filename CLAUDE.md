# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Technology Stack

- **Backend:** Laravel 12 + PHP 8.3
- **Frontend:** Vue 3 + Inertia.js + Tailwind CSS v4 + Vite
- **Database:** PostgreSQL 15 (with full-text search via `titles_searchable_index`)
- **Infrastructure:** Docker + Nginx

## Development Commands

```bash
# Full dev environment (PHP server + queue + logs + Vite, all concurrent)
composer run dev

# Frontend only
npm run dev

# Build frontend assets
npm run build

# Run tests
composer run test

# Or directly
php artisan test
```

## Architecture Overview

This is a Laravel + Vue 3 SPA using **Inertia.js** as the bridge — there are no API endpoints consumed by the frontend; Inertia handles page transitions server-side.

### Backend Structure

- `routes/web.php` — all routes (no separate API routes used by frontend)
- `app/Http/Controllers/` — HomeController, Auth controllers, CsvUploadController
- `app/Models/` — Movie, Genre, Keyword, Language, ProductionCompany, SearchableMovie
- `app/Service/` — MovieService (query logic), CsvUploadService (CSV import)
- `app/Jobs/ProcessCsv` — queued job for bulk CSV imports

**Movie relationships:** Movies belong to many genres, keywords, and production companies (pivot tables). Languages are a direct foreign key on titles.

**Search:** Uses a dedicated `titles_searchable_index` table with PostgreSQL tsvector for full-text search, populated during CSV import.

### Frontend Structure

- `resources/js/Pages/` — Inertia page components (mirrors route structure)
- `resources/js/Components/` — shared reusable components
- `resources/js/Composables/` — Vue composables
- Single layout: `resources/js/Layouts/AppLayout.vue`

Key pages: `Home.vue`, `AllGenres.vue`, `MovieByGenre.vue`, `MovieByLanguage.vue`, `MovieByKeyword.vue`, `MovieSearchAll.vue`, `Auth/Login.vue`, `Auth/Register.vue`

## Environment Setup

Copy `.env.example` to `.env`. Key variables:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tmdb
DB_USERNAME=user
DB_PASSWORD=password
ALLOW_REGISTER=false          # Toggle user registration
MOVIE_DB_IMG_URL=<tmdb-image-base-url>
```

Sessions, cache, and queues all use the database driver.

## Docker

```bash
# Local development (app + nginx on :8000 + postgres)
docker-compose -f docker-compose.local.yml up

# Production (postgres only — app deployed separately)
docker-compose up
```

The Dockerfile is a multi-stage build: Node 20 Alpine builds frontend assets, PHP 8.3 FPM Alpine serves the app.

## Data Import

Movies are loaded via CSV upload through a modal UI (`CsvUploadController` + `ProcessCsv` job). The queue must be running (`php artisan queue:work`) to process imports.
