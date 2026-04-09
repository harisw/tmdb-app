# Testing Guide

## Overview

This project uses **PHPUnit Feature tests** backed by a real **PostgreSQL** database. Tests are located in `tests/Feature/`.

Run all tests:
```bash
php artisan test
```

Run a specific file:
```bash
php artisan test tests/Feature/Service/MovieServiceTest.php
```

---

## Why PostgreSQL, Not SQLite

The default Laravel setup uses SQLite in-memory for tests (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). This project cannot use SQLite because:

- The full-text search uses PostgreSQL-specific `tsvector`, `ts_rank`, and `plainto_tsquery`
- The search index is a PostgreSQL materialized view
- SQLite does not reliably enforce foreign key constraints (even with `foreign_key_constraints = true`), which makes error-recovery tests non-deterministic

**`phpunit.xml` is configured for PostgreSQL:**
```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="tmdb_test"/>
<env name="DB_USERNAME" value="user"/>
<env name="DB_PASSWORD" value="password"/>
```

**Local setup:** create the test database once against your local Docker PostgreSQL:
```bash
docker exec -it <postgres-container> psql -U user -c "CREATE DATABASE tmdb_test;"
```

**CI (GitHub Actions):** a PostgreSQL service container spins up automatically per run — no external database needed. See `.github/workflows/deploy.yml`.

---

## Unit vs Feature Tests

**`MovieService` and controllers use Feature tests**, not Unit tests.

The reason: their logic is entirely about database state — inserting records, attaching pivot relations, deduplicating slugs, enforcing constraints. Mocking Eloquent would only test that mocks were called, not that the data ends up correct. `RefreshDatabase` keeps each test isolated.

**Rule of thumb:**
- Logic that touches the database → Feature test with `RefreshDatabase`
- Pure computation with no I/O → Unit test

---

## Key Gotchas and Fixes

### 1. Mass Assignment Protection

All models used with `Model::create()` must have `protected $guarded = [];` or explicit `$fillable`. Without it, Laravel silently creates records with no attributes, hitting NOT NULL constraints and failing with no visible error (the exception is caught by `MovieService`'s catch block).

**Models that need `$guarded = []`:** `Movie`, `Genre`, `Keyword`, `ProductionCompany`.

**How it hid itself:** the catch block in `MovieService::insertRow` swallows all exceptions. A mass assignment failure showed up as `insertedRowCount = 0` with no error message.

---

### 2. `Artisan::fake()` Does Not Exist

`Artisan::fake()` is not a Laravel testing helper — do not use it. The fakes that exist are `Bus::fake()`, `Event::fake()`, `Mail::fake()`, `Queue::fake()`, `Notification::fake()`.

To prevent a command from running in tests, guard it in the command's `handle()` method — the same pattern the migrations use:
```php
// In a command:
if (app()->environment('testing')) {
    return;
}
```

Or more precisely for this project, use `DB::transactionLevel()` (see point 4 below).

To mock Artisan calls, bind the concrete kernel — not the interface:
```php
// WRONG — Artisan facade resolves to 'artisan' key, not the interface
$this->mock(\Illuminate\Contracts\Console\Kernel::class)

// RIGHT
$this->mock(\Illuminate\Foundation\Console\Kernel::class)
    ->shouldReceive('call')
    ->withAnyArgs()
    ->andReturn(0);
```

---

### 3. PostgreSQL Aborts the Entire Transaction on Any SQL Error

**This is the most important PostgreSQL-specific behaviour to understand.**

When any SQL error occurs inside a PostgreSQL transaction — even if PHP's `catch` block handles it — PostgreSQL marks the entire transaction as aborted. Every subsequent SQL command in that transaction fails with:

```
current transaction is aborted, commands ignored until end of transaction block
```

`RefreshDatabase` wraps each test in a transaction, so one caught SQL error poisons all subsequent queries in that test.

**The fix: wrap each row's insert in `DB::transaction(fn)`.**

When called inside an existing transaction, Laravel automatically uses a PostgreSQL **SAVEPOINT** instead of a real transaction:
- Failure → rolls back to the savepoint only
- The outer transaction remains valid
- Subsequent rows and queries proceed normally

```php
try {
    DB::transaction(function () use ($row) {
        // ... create movie, attach relations
    });
} catch (\Exception $e) {
    // outer transaction is still alive — safe to continue
}
```

**Never call `DB::rollBack()` manually inside a catch block** when you may be inside an outer transaction — it rolls back the entire outer transaction, not just the current operation.

---

### 4. `REFRESH MATERIALIZED VIEW CONCURRENTLY` Cannot Run Inside a Transaction

PostgreSQL does not allow `REFRESH MATERIALIZED VIEW CONCURRENTLY` inside a transaction block. Since `RefreshDatabase` wraps tests in a transaction, calling this in tests always fails.

**Fix: detect the transaction level and choose the appropriate refresh:**
```php
if (DB::transactionLevel() > 0) {
    DB::statement('REFRESH MATERIALIZED VIEW searchable_movies');
} else {
    DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY searchable_movies');
}
```

- `DB::transactionLevel() > 0` → inside a test transaction → use non-concurrent (safe, single connection)
- `DB::transactionLevel() === 0` → production → use concurrent (no read locks)

---

### 5. Savepoints Roll Back the DB but Not In-Memory Caches

`MovieService` caches genres, keywords, and production companies in `$this->relationCache` to avoid N+1 queries during bulk import. When a row fails and its savepoint is rolled back, newly created relations are removed from the **database** — but they remain in the **in-memory cache**.

The next row then finds stale IDs in cache, tries to attach them, hits a foreign key violation, and also fails.

**Fix: reload the relation cache from DB after any failed row:**
```php
} catch (\Exception $e) {
    $this->reloadRelationCache(); // re-sync cache with DB state after rollback
    // ...
}
```

This is a general principle: **in-memory caches must be treated as invalid after any transaction rollback**.

---

### 6. The Materialized View Requires COALESCE on All Aggregates

The `searchable_movies` materialized view builds a `tsvector` by concatenating weighted vectors. If any `string_agg()` returns `null` (because a movie has no genres or no production companies), the entire concatenation becomes `null` and the movie is excluded from search results.

All `string_agg` calls must use `COALESCE`:
```sql
setweight(to_tsvector('english', coalesce(string_agg(distinct g.name, '| '), '')), 'B')
```

In tests, any test that exercises full-text search must call `Artisan::call('movies:refresh-searchable')` after creating movies to populate the view.

---

### 7. Route Model Binding Requires Matching Parameter Names

Implicit route model binding matches by **variable name**, not by type hint alone.

```php
// Route parameter is {slug}, controller parameter is $genre — NO MATCH
Route::get('/genre/{slug}', [HomeController::class, 'getByGenre']);
public function getByGenre(Genre $genre) // $genre ≠ {slug}
```

When names don't match, Laravel cannot bind the model. The controller receives an empty model instance — `$genre->movies()` returns no results.

**Fix:** match the route parameter name to the controller variable name:
```php
Route::get('/genre/{genre}', [HomeController::class, 'getByGenre']);
```

With `getRouteKeyName()` returning `'slug'` on `Genre`, Laravel automatically resolves `/genre/action` → `Genre::where('slug', 'action')->firstOrFail()`.

---

## CI/CD Configuration

Tests run in GitHub Actions with a PostgreSQL service container. No external database setup is required.

```yaml
services:
  postgres:
    image: postgres:15
    env:
      POSTGRES_DB: tmdb_test
      POSTGRES_USER: user
      POSTGRES_PASSWORD: password
    options: >-
      --health-cmd pg_isready
      --health-interval 10s
      --health-timeout 5s
      --health-retries 5
```

The `--health-cmd pg_isready` options make the job wait until PostgreSQL is ready before running tests, preventing flaky "connection refused" failures.

Environment variables passed directly to the test step override `phpunit.xml`, so the same test configuration works locally (pointing at Docker) and in CI (pointing at the service container) without modification.
