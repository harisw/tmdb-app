# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

<!-- Inferred: Laravel 13 + Inertia.js v2 + Vue 3 server-driven SPA, no native client or wrapper. No contradicting evidence in the repo. -->

## Users

Primary users are movie and TV watchers deciding what to watch next. They arrive
with a loose intent ("something like this", "what's good on my streaming
services", "what's trending") rather than a specific title, and they want to
resolve that intent quickly and then act on it (save it, mark it, rate it).
Accounts are supported (email + Google OAuth) and unlock a personal taste layer;
guests can still browse, search, and explore the graph. Registration is currently
gated (`ALLOW_REGISTER=false`).

## Product Purpose

Chill is a discovery product for an audience of real viewers: browse and search
the movie/TV catalogue, understand *why* two titles are related, and track a
personal taste profile. Success is a user landing with "what should I watch"
and leaving with a concrete pick they've saved or started — repeatedly enough
to come back.

## Positioning

Chill's value is the combination of three capabilities working together, none of
which is subordinate to the others:

1. **Similarity knowledge graph** — similarity is precomputed (pgvector cosine
   KNN over title embeddings), and every neighbour pair carries ranked,
   human-readable reasons (shared genres, keywords, cast, crew, semantic
   themes) produced by a pipeline. Surfaced at `/title/{title}/similar` and as
   a d3-force force-directed graph explorer (`/graph`, `Pages/TitleGraph.vue`).
2. **Personal taste layer** — like / watchlist / watched actions, ratings on
   likeable and watched titles, "My List", and country-aware streaming
   availability.
3. **Breadth of browsing** — trending / popular / upcoming plus faceted browse
   by genre, keyword/tag, provider, and language, with full-text search
   (PostgreSQL tsvector) across everything.

A neighbouring product (Letterboxd, JustWatch, TMDB's own site) has some slice
of this, but not the reasoned similarity graph tied to a personal taste profile
and regional availability in one place.

## Operating Context

- Catalogue is populated from TMDB on a schedule (trending/popular/upcoming
  fetch jobs in `routes/console.php`), not user-triggered; enrichment
  (credits, providers, knowledge graph, embeddings) runs on background queues.
- The movie/TV distinction is a global toggle (`titleType` Pinia store) that
  reaches the database through a global scope on every request — it is a
  pervasive part of the browsing experience, not a per-page filter.
- Streaming/provider availability is regional; users set a country on their
  profile and a country switcher is present in the UI.
- Adult (pornographic) content is hidden by default and controlled by a
  per-user `hide_adult` preference; this flag is not an age rating.
- Newly ingested titles are not immediately searchable (the searchable index
  rebuilds every six hours) and newly seeded genres/providers lag behind
  long-lived caches.

## Capabilities and Constraints

**Confirmed capabilities**

- Browse trending / popular / upcoming for movies and TV.
- Faceted browse: by genre, keyword/tag, provider, language; "all X" index
  pages for genres, keywords, providers, user lists.
- Full-text search (single-type `/search`, cross-type `/search-all`).
- Title detail page with cast/crew, providers, genres, keywords, rating badge.
- Similar-titles view with ranked reasons; force-directed knowledge graph with
  lazy neighbour loading (`/graph/neighbors`).
- Auth: email/password with email verification, Google OAuth (Socialite).
- Personal actions: like, watchlist, watched; ratings on likeable/watched
  titles; "My List" views by action type.
- Profile: avatar (style presets), country, adult-content preference.
- Admin: Laravel Pennant feature flags at `/admin/feature-flags` behind
  `can:admin` (e.g. knowledge-graph enrichment toggle).

**Constraints**

- Stack is fixed by the existing codebase: Laravel 13 / PHP 8.4, Vue 3 +
  Inertia v2, Tailwind CSS v4 + daisyUI 5, Pinia, PostgreSQL 15 + pgvector.
- No JSON API for the SPA — every screen is an Inertia page served from
  `routes/web.php`; data arrives as Inertia props, including shared props
  (`topGenres`, `countries`, `topProviders`, `base_url`, `auth.user`, enums,
  flash, `avatarStyles`).
- Similarity and embeddings are inert without `OPENAI_API_KEY`; graph/similar
  features degrade if enrichment queues aren't running.
- Build TMDB image URLs from `usePage().props.base_url`, never hardcoded.

**Terminology**

- **Title** — a movie or TV show (one table, discriminated by `type`).
- **Provider** — a streaming service / watch provider (regional).
- **Keyword / tag** — TMDB keyword used as a browse facet.
- **Reason** — a ranked human-readable explanation for why two titles are
  similar.

## Brand Commitments

- **Name:** Chill. Already present in the UI (`Components/Navbar.vue`, styled in
  a "madder" red accent).
- **Attribution (required, must remain visible):**
  - TMDB: this product uses the TMDB API / data but is not endorsed or
    certified by TMDB.
  - JustWatch: streaming availability data is attributed to JustWatch.
- No other voice, logo, or identity constraints have been fixed; future design
  work may define them around the Chill name and the required attributions.

## Evidence on Hand

- Real, continuously refreshed catalogue data from TMDB (titles, credits,
  genres, keywords, providers, images).
- Real regional streaming availability data (JustWatch-sourced, via TMDB
  providers).
- Computed similarity graph data (`title_similarities`) and generated reasons
  when enrichment/embedding pipelines have run.
- No testimonials, user counts, press, pricing, or case studies exist — future
  work must not fabricate them.
- Documentation: `docs/deployment.md`, `docs/testing.md`,
  `docs/troubleshooting*.md`. `README.md` is unmodified Laravel boilerplate.

## Product Principles

1. **Start from intent, end with a pick.** Every surface should move a user
   from a vague "what to watch" toward a concrete, saved or started title.
2. **Relationships are explained, never asserted.** When Chill says two titles
   are similar, it shows the reasons — the graph and reason pipeline are
   first-class, not decoration.
3. **The three pillars are peers.** Similarity graph, personal taste layer, and
   breadth of browsing are equally load-bearing; don't let one crowd the
   others out of the experience.
4. **Region and taste are always in effect.** Provider availability is
   country-specific and results are personalized for signed-in users; design
   must make the active country and taste state legible.
5. **Honor the movie/TV toggle everywhere.** It's a global mode, not a filter;
   both ends must stay in sync or filtering silently breaks.

## Accessibility & Inclusion

No product-specific accessibility standard has been established. Adult content
is hidden by default and gated per user; keep that default and its control
clear. Apply standard WCAG-oriented practice (contrast, focus, keyboard
navigation) as a baseline until a specific requirement is set.
