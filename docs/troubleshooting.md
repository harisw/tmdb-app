# Troubleshooting & Ops Notes

Lessons learned and gotchas worth remembering. Grouped by topic.

For AWS infrastructure (`infra/scheduling/` — Lambda, EventBridge Scheduler, KMS, CloudWatch),
see [troubleshooting-aws.md](troubleshooting-aws.md).

---

## PHP configuration (memory limit, upload size, timeouts)

### How PHP config is applied in this project

PHP settings are set via a **conf.d ini fragment baked into the image** — `docker/php/zz-app.ini`, copied into `/usr/local/etc/php/conf.d/` in the Dockerfile.

- **Scope:** applies to *every* PHP process — PHP-FPM (web requests) **and** CLI/Horizon (queue workers).
- **Why `zz-` prefix:** conf.d fragments load alphabetically; `zz-` loads last so its values win over stock fragments (e.g. `docker-php-ext-*.ini`).
- The official `php` image auto-loads every `*.ini` in `conf.d/` at startup for all SAPIs — no `php.ini` edit or FPM pool config needed.

To change a value: edit `docker/php/zz-app.ini`, then **rebuild the image** (a running container won't pick up the change).

```bash
docker-compose -f docker-compose.prod.yml build --no-cache app
docker-compose -f docker-compose.prod.yml up -d

# Verify (FPM values):
docker-compose -f docker-compose.prod.yml exec app php -i | grep memory_limit
# Verify (CLI/Horizon SAPI):
docker-compose -f docker-compose.prod.yml exec app php -r 'echo ini_get("memory_limit");'
```

### `max_execution_time` does NOT affect queue jobs ⚠️

This is the key gotcha. `max_execution_time`:

- Only bounds **PHP-FPM web requests** (e.g. an Inertia page load or the HTTP request that *uploads* a CSV).
- Counts only CPU time in the script — excludes time blocked on DB queries, network, `sleep()`.
- Is **hard-coded to `0` (unlimited) in the CLI SAPI** — the value in the ini is *ignored* by queue workers and Horizon. Setting it high does nothing for jobs.

**What actually bounds a job** (in order of who fires first):

| Setting | Where | Purpose |
|---|---|---|
| Job `$timeout` | `app/Jobs/*Job.php` (e.g. `TitlesBatchInsertJob::$timeout`) | Kills the job via `pcntl_alarm` (SIGALRM). This is why `pcntl` is installed in the Dockerfile. |
| Supervisor `timeout` | `config/horizon.php` | Worker-level limit. **Must be greater than** the job `$timeout`, or Horizon kills the worker before the job's own timeout fires cleanly. |
| `retry_after` | `config/queue.php` (`DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER`) | **Must be greater than the longest job timeout.** If a job runs longer than `retry_after`, the queue thinks it's stuck and dispatches a *second copy* → double processing. |

> Rule of thumb: `retry_after` > Horizon `timeout` > job `$timeout`.
> Default `retry_after` is 90s — if you have long-running jobs, raise it or you'll get concurrent duplicate runs.

---

## Docker build: `.dockerignore` excludes files you try to COPY

**Symptom** (fails in the GitHub Actions build step):

```
ERROR: failed to build: failed to solve: failed to compute cache key:
failed to calculate checksum of ref ...: "/docker/php/zz-app.ini": not found
```

**Cause:** the file isn't in the build context. Our `.dockerignore` excludes the whole `docker/` directory (its nginx configs are mounted at runtime, not baked in). Any `COPY docker/...` in the Dockerfile then can't find the file — even though it exists locally.

**Fix:** re-include the specific file with a `!` negation *after* the exclusion (last match wins in `.dockerignore`):

```
docker
!docker/php/zz-app.ini
```

**Things to remember:**
- A `COPY <path>` in the Dockerfile only works if `<path>` survives `.dockerignore` filtering.
- CI builds from the **committed** tree — an uncommitted file will "exist locally" but be missing in the Actions build. Always commit new files that the Dockerfile references.
- To test `.dockerignore` behavior fast without running the real build:
  ```bash
  printf 'FROM scratch\nCOPY docker/php/zz-app.ini /x\n' > /tmp/Dockerfile.test
  docker build -f /tmp/Dockerfile.test -t ignore-test .   # succeeds only if file is in context
  ```

---

## Pushing a commit without triggering CI/CD (docs-only changes)

Our workflow (`.github/workflows/deploy.yml`) triggers on **every** push to `master`/`development` — there are no path filters — so a docs-only commit currently runs tests, frontend build, and a full image build + EC2 deploy.

**Per-commit skip (simplest, no workflow change):** include one of GitHub's skip tokens in the commit message. This skips the *entire* workflow run:

```bash
git commit -m "docs: add troubleshooting notes [skip ci]"
```

Recognized tokens: `[skip ci]`, `[ci skip]`, `[no ci]`, `[skip actions]`, `[actions skip]`.
Put it in the **head commit** of the push (GitHub checks the pushed head commit's message).

**Permanent alternative (skip only for docs paths):** add a `paths-ignore` filter to the workflow so docs never trigger builds:

```yaml
on:
  push:
    branches: [master, development]
    paths-ignore:
      - 'docs/**'
      - '**.md'
```

Trade-off: `paths-ignore` is broader (affects everyone, all commits touching only those paths) but you lose per-commit control, and if these checks are ever made "required" for PR merges, a skipped run can block merges. For occasional docs commits, `[skip ci]` is usually the better tool.

---

## Frontend state management (Inertia + Vue + Pinia)

### `router.reload()` doesn't update a list ⚠️

**Symptom:** toggling the adult-content filter in the profile modal called `router.reload()`, the server re-queried `titles` with the new filter, but the on-screen grid didn't change.

**Cause:** `useInfiniteScroll` snapshotted the `titles` prop into a local `items` ref **once** at setup and never reacted to the prop being replaced. Every list page renders `items`, not `props.titles` — so a reload updated the prop while the grid kept the stale snapshot. This is the classic hazard of **mirroring server state into local client state**: you now own two copies and have to reconcile them by hand.

**Fix:** callers pass a reactive getter (`useInfiniteScroll({ titles: () => props.titles })`) and the composable `watch`es it, rebuilding the list when Inertia replaces the prop. The watcher must distinguish the composable's **own** pagination fetches (which append) from **external** prop replacements like a reload (which reset) — otherwise loading page 2 would immediately wipe pages 1–2 down to just page 2 (a visible "snap").

> ⚠️ First attempt used an object-identity check (`res === syncedResource`) to spot our own fetches. That was **unreliable and caused the snap**: the watcher reads the reactive `props.titles` proxy on the *next tick*, which isn't identity-equal to the raw `page.props.titles` captured synchronously in `onSuccess`. The working version sets an `internalUpdate` flag right before our own `router.get`, and the watcher skips (and clears) it — no cross-boundary identity comparison. Also: on pages that start with `titles: null` (`/languages`, `/tags` before a pick), every resource read must be null-safe or setup throws.

### Which tool owns which state

The mess above comes from putting state in the wrong place. The convention:

| Kind of state | Owner | Examples |
|---|---|---|
| **Server data** | Inertia props — never mirror into a store | `titles`, `auth.user`, `genres`, pagination |
| **Global client state** | **Pinia** (`resources/js/stores/`) | title-type filter (`titleType`), which modal is open (`ui`) |
| **Per-page ephemeral state** | composable, scoped to the component | the accumulating list in `useInfiniteScroll`, `useModal` |

- Reach a Pinia store from **outside** a component (e.g. the `router.on('before')` hook in `app.js`) by passing the instance explicitly: `useTitleTypeStore(pinia)`.
- When destructuring store **state**, wrap in `storeToRefs()` to keep reactivity; **actions** can be destructured directly.
- Don't put the infinite-scroll list in a Pinia store — a store is a singleton, so per-page list state leaks between pages. A composable is scoped to the page component and is the right tool.

### Cleaner long-term fix: Inertia v2 merge props

We're on Inertia v2, which has first-class **merge props** + the **`WhenVisible`** component for infinite scroll. Server-side `Inertia::merge(...)` tells Inertia to *append* paginated data instead of replacing it, so the client stops maintaining its own `items` array — deleting the whole internal-vs-external guard and this class of stale-snapshot bug. Prefer this over hand-rolled accumulation when touching `useInfiniteScroll` next.

---

## Tailwind: never build class names dynamically ⚠️

**Symptom:** `LoadingSpinner` rendered as a thin spinning bar instead of a ring — no width, no height, no border color. Looked like a broken daisyUI theme; it wasn't.

**Cause:** the component built its classes at runtime from props:

```html
<!-- BROKEN -->
<div :class="`h-${size} w-${size} border-${color}`"></div>
<p :class="`text-${color}`">…</p>
```

Tailwind has no runtime — it generates CSS at build time by **scanning source files for complete, literal class strings**. It never sees `h-8`, `w-8`, `border-white`, or `text-white` (they only exist after JS interpolation), so that CSS is never emitted. The element is left with only the statically-written classes (here `border-b-2`) → a thin bar. This is a **purge/JIT** issue, unrelated to daisyUI or the theme.

> The give-away: the class works when hard-coded but vanishes when interpolated, and it's always a *dynamic* segment (`${...}`) inside the class string.

**Rules:**

- A Tailwind class must appear **verbatim** somewhere in source. `` `text-${color}` `` will never work.
- If a value is dynamic, either:
  - **drive it with inline `:style`** (best for truly free values like size/color — what `LoadingSpinner` now does: `:style="spinnerStyle"` with `width`/`height`/`borderColor`), or
  - **map to full literal class strings** so the scanner sees them:
    ```js
    const colorClass = { white: 'border-white text-white', madder: 'border-madder text-madder' }[color]
    ```
  - or, as a last resort, add the class to Tailwind's `safelist`.
- Bonus gotcha from the same bug: `size="{8}"` in a template passes the **string** `"{8}"`, not the number `8`. Binding a non-string prop needs `:size="8"`.

**How to catch it yourself:** if a utility "does nothing," grep the *compiled* CSS for it — `grep -o 'h-8' public/build/assets/*.css`. If it's missing there but present in your `.vue` source only as an interpolation, that's this bug.
