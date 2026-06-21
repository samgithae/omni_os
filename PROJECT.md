# Omni OS — Project Status, Architecture & Developer Guide

> **Living document.** Updated every time a feature is completed.
> Last updated: 2026-06-21
> Strategy source of truth: `Omni-OS-Strategy-Brief.md` (v1)
> This document: technical state of the build, what exists, what's next, and how to continue.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [The Four Brands](#the-four-brands)
3. [Vision & Strategy Summary](#2-vision--strategy-summary)
4. [Tech Stack](#3-tech-stack)
5. [Database Schema](#4-database-schema)
6. [Eloquent Models](#5-eloquent-models)
7. [Filament Admin Panel](#6-filament-admin-panel)
8. [Vue/Inertia Dashboard](#7-vueinertia-dashboard)
9. [Current Data State](#8-current-data-state)
10. [Artisan Commands](#9-artisan-commands)
11. [Development Setup](#10-development-setup)
12. [File Structure](#11-file-structure)
13. [What's Done — Phase 0 Foundation](#12-whats-done--phase-0-foundation-complete)
14. [What's Remaining — Full Roadmap](#13-whats-remaining--full-roadmap)
15. [Known Issues & Pitfalls](#14-known-issues--pitfalls)
16. [Architecture Diagrams](#15-architecture-diagrams)
17. [Changelog](#16-changelog)
18. [How to Update This Document](#17-how-to-update-this-document)

---

## 1. Project Overview

Omni OS is a multi-brand marketing automation platform built for a solo operator (Sam) running four businesses out of Nairobi, Kenya. The platform is a thin record-keeping + analytics + integrity layer powered by Laravel, Vue/Inertia, Filament, and PostgreSQL. Hermes Agent (an open-source AI agent by Nous Research) does the fuzzy work — mining leads, enriching data, drafting emails, classifying replies. This platform records what happened, enforces guardrails, and provides dashboards.

### Core Philosophy (from Strategy Brief)

- **Marketing execution is the priority.** The platform is a thin record + analytics + integrity layer, NOT the project itself.
- **Don't rebuild commodity infrastructure.** Use Laravel's batteries (auth, queues, scheduler, Eloquent), Filament for admin UI, Postgres for the DB.
- **ROI-driven + open-source/self-hosted bias.** One-time costs over recurring; paid APIs only for revenue-generating workflows.
- **Human-in-the-loop** on all external publishing/sending (Telegram approval gate).
- **Geography and brand are data, not code.** Expansion = configuration, not a rewrite.
- **Durability over ban-and-burn.** Stay within platform ToS.
- **The three DB invariants** (dedup, suppression, idempotency) are non-negotiable and enforced at the database level.

### Division of Labor

```
Hermes Agent (mine, enrich, draft, classify replies)
    ↓ calls
Laravel/Postgres (records, invariants, scheduler/queues)
    ↓ sends via
SMTP2GO (email send + open/click tracking)
    ↓ approval via
Telegram (human gate before any external send)
    ↓ visibility via
Filament admin + Vue dashboard (analytics, dashboards)
```

---

## The Four Brands

| ID | Brand | Slug | What They Sell | Customer | Market | Primary KPI | Color |
|----|-------|------|----------------|----------|--------|-------------|-------|
| 1 | Hudutech Innovations Ltd | `hudutech` | Web & software development, Odoo ERP, CRM, digital transformation | SMEs, schools, NGOs, manufacturers | Kenya | Qualified leads → sales revenue | #1a56db |
| 2 | UjuziPlus | `ujuziplus` | White-label LMS + professional training, certification prep, corporate training | Trainers/coaches, professionals, institutions, corporates, NGOs | Kenya / Africa | LMS subscriptions + enrollments → corporate training contracts | #059669 |
| 3 | Phantomflix | `phantomflix` | Licensed reseller of streaming subscriptions, affordable bundled access, M-Pesa payments | Consumers seeking affordable premium entertainment | Kenya + diaspora | Paid subscribers → subscriber retention | #7c3aed |
| 4 | Phantom Tutors | `phantom-tutors` | Academic tutoring, exam prep, personalized learning support | University/college students, parents, adult learners | US & UK | Student enrollments → retention & referrals | #dc2626 |

### Per-Brand Animal (Deal-Size Strategy)

| Brand | Animal | Economics | Motion |
|-------|--------|-----------|--------|
| Phantomflix | Mouse | ~$10/mo, high volume | Referral/virality, retention is the war |
| Phantom Tutors | Rabbit | ~$100/mo | Efficient inbound + outbound |
| UjuziPlus | Rabbit → Deer | ~$100/mo → ~$1k/mo | LMS subs (rabbit) feed corporate contracts (deer) |
| Hudutech | Deer / Elephant | ~$1k+/mo | Inside sales + automated outbound + relationships |

### Brand Voices

- Hudutech = trusted consultant / digital-transformation expert
- UjuziPlus = professional, authoritative, career-growth focused
- Phantomflix = fun, affordable, entertainment-focused
- Phantom Tutors = friendly mentor / academic success partner

---

## 2. Vision & Strategy Summary

### What "Omni OS" Is

One agent-driven system (Hermes Agent) that makes all four brands consistently visible to their audiences, produced and maintained by a single operator.

### Working Definition of "Omnipresence"

Each brand consistently shows up in the 2-3 places its specific audience actually lives, plus in Google and in AI answers (GEO). It is NOT "appear everywhere." Depth in the right channels beats spray-everywhere. The four audiences barely overlap, so "omnipresent" means four small, sharp presences sharing one engine.

### The Omnipresence Loop (per brand)

```
Source → Pillar → Atomize → Distribute → Learn
  ↑                                           |
  └───────────────────────────────────────────┘
```

Take one real audience question → produce one substantial pillar asset that answers it → atomize that single asset into channel-native pieces (blog post + LinkedIn + Reddit + email + WhatsApp) → distribute → feed performance back into memory so the system learns.

### GEO / AEO (Get Found by AI Engines)

Pillar assets must be citation-ready for AI search (ChatGPT, Perplexity, Google AI Overviews):
- First ~200 words directly and completely answer the query
- Include FAQ section and valid schema markup (FAQPage, Article, Service, LocalBusiness)
- Ensure pages are crawlable (server-side rendering)
- GEO is an added layer on solid SEO, not a replacement

### The Hunting Framework (Deal-Size Strategy)

- Mice: ~$10/mo, huge volume, viral/referral/PLG, worst retention
- Rabbits: ~$100/mo, efficient inbound + outbound, better retention, survivors expand
- Deer: ~$1k/mo, inside sales + automated outbound + partners, best retention + faster growth
- Elephants: ~$8k+/mo, ABM, relationships, tenders/RFPs, highest retention

Sequencing validated: start rabbits → add deer → add elephants. ~70% of companies never change what they hunt, so order matters.

### The Context Spine + Refresh Discipline

Per brand: `icp/`, `competitors/`, `positioning/`, `messaging/`, `brand/` (tone of voice + visual identity). Reply outcomes feed a win-loss file → refreshes ICP and messaging on a cadence → next batch is sharper. This IS the "Learn" edge of the loop.

### Phased Roadmap (High-Level)

| Phase | Name | Status | Description |
|-------|------|--------|-------------|
| 0 | Foundation | COMPLETE | Postgres + Laravel/Filament + Vue/Inertia + queues/scheduler; move UjuziPlus off Sheets |
| 1 | UjuziPlus Full Loop | NEXT | Harden + extend + geo-scale the Rabbits/Deer lead pipeline; then layer omnipresence content loop |
| 2 | Hudutech | AFTER | Most similar B2B motion; reuse pipeline + LinkedIn + Google Business Profile |
| 3 | B2C Brands | LATER | Phantomflix (community/referral) + Phantom Tutors (short-form, Reddit, Discord) |
| 4 | Cross-Brand Intelligence | FUTURE | pgvector for CRM memory graph; shared learnings; elephant motion (ABM, tenders) |

---

## 3. Tech Stack

### Backend

| Component | Version | Notes |
|-----------|---------|-------|
| PHP | 8.4.11 | |
| Laravel | 13.16.1 | |
| PostgreSQL | 17.10 | Homebrew, aarch64 — system of record |
| Redis | 6379 | Cache + queue via `predis/predis` v3.5 (NOT phpredis extension) |
| Filament | v4.11.7 | Admin panel (CRUD, filters, dashboard widgets) |
| Laravel Fortify | ^1.37.2 | Auth (passkeys, 2FA) |
| Inertia.js | v3 | Bridges Laravel and Vue without separate API |

### Frontend

| Component | Version | Notes |
|-----------|---------|-------|
| Vue | 3.5 | With Inertia.js |
| Tailwind CSS | 4.1 | |
| Vite | 8 | Dev server + bundler |
| Lucide Icons | ^1.17.0 | UI icons |
| TypeScript | ^5.2.2 | Strict type checking (`vue-tsc --noEmit` passes clean) |

### Infrastructure (Planned / External)

| Component | Status | Purpose |
|-----------|--------|---------|
| Cloudflare Tunnel + Access | CONFIG PREPARED | Manual Linux setup only; Access must be created before routing any hostname |
| SMTP2GO | CONFIG DOCUMENTED | Credentials and env keys documented; send/webhook integration still pending |
| Hermes Agent | EXTERNAL | AI brain for mining/enrichment/drafting (separate from this codebase) |
| Metabase | PLANNED | Analytics dashboards (swappable with Vue dashboards later) |

### Key Composer Packages

```
filament/filament: ^4.0
inertiajs/inertia-laravel: ^3.0
laravel/fortify: ^1.37.2
laravel/chisel: ^0.1.0
laravel/wayfinder: ^0.1.14
predis/predis: ^3.5
```

### Key npm devDependencies

```
vue: ^3.5.13
@inertiajs/vue3: ^3.0.0
tailwindcss: ^4.1.1
@lucide/vue: ^1.17.0
reka-ui: ^2.9.8
vite: ^8.0.0
typescript: ^5.2.2
```

### npm Scripts

```
dev       → vite (dev server with HMR)
build     → vite build (production assets)
build:ssr → vite build && vite build --ssr
format    → prettier --write resources/
lint      → eslint . --fix
types:check → vue-tsc --noEmit
```

---

## 4. Database Schema

### Migration Status (all ran successfully)

```
0001_01_01_000000_create_users_table .............................. [1] Ran
0001_01_01_000001_create_cache_table ................................ [1] Ran
0001_01_01_000002_create_jobs_table ................................. [1] Ran
2024_01_01_000000_create_passkeys_table ............................. [1] Ran
2025_06_20_100000_create_brands_table ............................... [1] Ran
2025_08_14_170933_add_two_factor_columns_to_users_table ............. [1] Ran
2026_06_20_092930_create_leads_table ................................. [1] Ran
2026_06_20_092931_create_suppressions_table .......................... [1] Ran
2026_06_20_092932_create_email_messages_table ........................ [1] Ran
2026_06_20_092933_create_lead_events_table ........................... [1] Ran
2026_06_20_092934_create_mining_targets_table ........................ [1] Ran
2026_06_20_175321_add_approval_workflow_to_email_messages ........... [2] Ran
```

### Tables Overview (16 total)

#### Core Application Tables (7)

| Table | Purpose |
|-------|---------|
| `brands` | The four brands with metadata (name, slug, description, market, KPI, voice, color, active) |
| `leads` | All mined leads across all brands |
| `suppressions` | Do-not-contact list (unsubscribes, bounces, complaints, manual) |
| `email_messages` | Email outreach tracking with idempotency keys |
| `lead_events` | Event log for analytics (imported, enriched, emailed, replied, etc.) |
| `mining_targets` | Geo config for lead mining (country, city, category, search_template, segment, cadence) |
| `users` | Auth (Fortify, passkeys, 2FA) |

#### Laravel Default Tables (9)

| Table | Purpose |
|-------|---------|
| `cache`, `cache_locks` | Redis cache |
| `jobs`, `failed_jobs`, `job_batches` | Queue jobs |
| `migrations` | Migration tracking |
| `passkeys` | Passkey auth |
| `password_reset_tokens` | Password resets |
| `sessions` | Session storage |

### Schema Details

#### `brands` table

```
id              bigint, PK, autoincrement
name            varchar, NOT NULL
slug            varchar, NOT NULL (unique)
description     varchar, nullable
primary_market  varchar, nullable
primary_kpi     varchar, nullable
brand_voice     varchar, nullable
color           varchar, nullable (hex color code)
is_active       boolean, NOT NULL, default true
created_at      timestamp, nullable
updated_at      timestamp, nullable
```

#### `leads` table

```
id                   bigint, PK, autoincrement
brand_id             bigint, FK → brands.id
company_name         varchar(255)
contact_name         varchar, nullable
email                varchar, nullable
phone                varchar, nullable
website              varchar, nullable
segment              enum: rabbit|deer|mouse|elephant (default: rabbit)
category             varchar, nullable
subcategory          varchar, nullable
country              varchar, default: Kenya
city                 varchar, nullable
address              varchar, nullable
status               varchar, default: new
enrichment_attempts  integer, default: 0
email_verified       boolean, default false
score                integer, default 0
source               varchar, nullable
source_url           varchar, nullable
raw_data             JSONB, nullable (full mining payload)
created_at           timestamp
updated_at           timestamp

CONSTRAINTS:
  UNIQUE(brand_id, email)  -- Invariant #1: Dedup
  INDEX on brand_id, status, email, source, created_at
  INDEX on (brand_id, segment), (brand_id, status), (country, city)
```

#### `suppressions` table

```
id          bigint, PK, autoincrement
brand_id    bigint, FK → brands.id
email       varchar, NOT NULL
reason      enum: unsubscribe|hard_bounce|complaint|manual
notes       text, nullable
created_at  timestamp
updated_at  timestamp

CONSTRAINTS:
  UNIQUE(brand_id, email)  -- Invariant #2: Suppression
  INDEX on (brand_id, reason)
```

#### `email_messages` table

```
id              bigint, PK, autoincrement
brand_id        bigint, FK → brands.id
lead_id         bigint, FK → leads.id
sequence_step   integer, NOT NULL
subject         varchar, nullable
body            text, nullable
status          varchar, default: draft (draft|queued|sent|failed)
approval_status varchar, default: pending (pending|approved|rejected)
approved_at     timestamp, nullable
rejected_at     timestamp, nullable
approval_notes  text, nullable
scheduled_for   timestamp, nullable
sent_at         timestamp, nullable
opened_at       timestamp, nullable
clicked_at      timestamp, nullable
error_message   text, nullable
created_at      timestamp
updated_at      timestamp

CONSTRAINTS:
  UNIQUE(lead_id, sequence_step)  -- Invariant #3: Idempotency
  INDEX on (brand_id, status), sent_at
  INDEX on (brand_id, approval_status), (lead_id, approval_status)
```

### Email Approval Workflow

```
draft (approval_status=pending)
  → approved (approval_status=approved, status=queued)
  → sent (status=sent, sent_at=timestamp)
  → opened (opened_at=timestamp)
  → clicked (clicked_at=timestamp)

OR

draft (approval_status=pending)
  → rejected (approval_status=rejected, rejected_at=timestamp)
```

The approval workflow integrates with the Telegram approval gate: emails start as `draft` with `approval_status=pending`. When approved (via Filament action or Telegram), status moves to `queued` for the send pipeline. Rejected emails are terminal.

#### `lead_events` table

```
id          bigint, PK, autoincrement
lead_id     bigint, FK → leads.id
brand_id    bigint, FK → brands.id
event_type  varchar (imported, enriching, enriched, emailed, replied, etc.)
payload     JSONB, nullable (event-specific data)
source      varchar, nullable
created_at  timestamp
updated_at  timestamp

INDEX on (lead_id, event_type), (brand_id, event_type), created_at
```

#### `mining_targets` table

```
id              bigint, PK, autoincrement
brand_id        bigint, FK → brands.id
country         varchar
city            varchar
category        varchar
search_template  varchar
segment         enum: rabbit|deer|mouse|elephant
cadence         enum: daily|weekly|monthly
is_active       boolean, default true
last_mined_at   timestamp, nullable
created_at      timestamp
updated_at      timestamp

INDEX on (brand_id, is_active), (country, city), segment
```

### The Three DB Invariants (from Strategy Brief Section 5.3)

1. **Dedup** — `UNIQUE(brand_id, email)` on leads table prevents duplicate leads per brand. Two crons racing cannot create the same lead twice.
2. **Suppression** — `UNIQUE(brand_id, email)` on suppressions table. Do-not-contact state the send path MUST check. Unsubscribes / hard bounces can never be re-mailed. This is compliance, too important for case-by-case LLM judgment.
3. **Idempotency** — `UNIQUE(lead_id, sequence_step)` on email_messages. "Already sent" is a fact in the DB, not a heuristic. Kills double-sends on retry.

### Lead State Machine

```
new → enriching → enriched
                  ↘ no_email_found (terminal)
```

- `new`: Freshly imported, no email yet (needs enrichment)
- `enriching`: Enrichment in progress (Hermes is looking for email)
- `enriched`: Email found and verified, ready for outreach
- `no_email_found`: Enrichment attempted N times, no email found (terminal state — move on)

---

## 5. Eloquent Models

| Model | File | Key Relationships | Scopes |
|-------|------|-------------------|--------|
| `Brand` | `app/Models/Brand.php` | hasMany: Lead, Suppression, MiningTarget, EmailMessage | `active()` |
| `Lead` | `app/Models/Lead.php` | belongsTo: Brand; hasMany: LeadEvent, EmailMessage | `byBrand()`, `bySegment()`, `byStatus()`, `byCountry()`, `byCity()`, `rabbits()`, `deer()`, `enriched()`, `new()` |
| `Suppression` | `app/Models/Suppression.php` | belongsTo: Brand | `byBrand()`, `unsubscribes()`, `hardBounces()` |
| `EmailMessage` | `app/Models/EmailMessage.php` | belongsTo: Brand, Lead | `byBrand()`, `sent()`, `draft()`, `pendingApproval()`, `approved()`, `rejected()` + approve()/reject() helpers |
| `LeadEvent` | `app/Models/LeadEvent.php` | belongsTo: Lead, Brand | — |
| `MiningTarget` | `app/Models/MiningTarget.php` | belongsTo: Brand | `active()`, `byBrand()`, `byCountry()`, `bySegment()` |
| `User` | `app/Models/User.php` | — | implements `FilamentUser`, `PasskeyUser` |

---

## 6. Filament Admin Panel

**URL:** `/admin`
**Auth:** Same `web` guard as the Vue app (single login for both)

### Resources (5)

| Resource | Path | Features |
|----------|------|----------|
| `BrandResource` | `/admin/brands` | CRUD, leads count per brand, color picker, active toggle |
| `LeadResource` | `/admin/leads` | Full CRUD, view page, filters (brand, segment, status, country, city), badges for segment/status, score column, raw_data viewer, Email Sequence relation manager |
| `EmailMessageResource` | `/admin/email-messages` | Full CRUD, filters (brand, approval status, send status, sequence step), approve/reject actions, bulk approve, view modal with email body |
| `SuppressionResource` | `/admin/suppressions` | CRUD, reason badges, brand filter, reason filter |
| `MiningTargetResource` | `/admin/mining-targets` | CRUD, geo config, cadence, active toggle, segment badges |

### Resource Pages

```
BrandResource/Pages:         CreateBrand, EditBrand, ListBrands
LeadResource/Pages:           CreateLead, EditLead, ListLeads, ViewLead
LeadResource/RelationManagers: EmailMessagesRelationManager
EmailMessageResource/Pages:   ListEmailMessages, CreateEmailMessage, ViewEmailMessage, EditEmailMessage
SuppressionResource/Pages:    CreateSuppression, EditSuppression, ListSuppressions
MiningTargetResource/Pages:   CreateMiningTarget, EditMiningTarget, ListMiningTargets
```

### Dashboard Widgets (4)

| Widget | Type | Shows |
|--------|------|-------|
| `LeadStatsOverview` | Stats cards | Total leads, new/enriched, suppressed, active brands |
| `LeadsByBrandChart` | Bar chart | Lead count per brand (brand colors) |
| `LeadsBySegmentChart` | Doughnut | Rabbit/deer/mouse/elephant distribution |
| `LeadsByCityChart` | Bar chart | Top 10 cities by lead count |

### Filament v4 Compatibility Notes (IMPORTANT)

These are hard-won fixes. If you break them, things crash:

- `form()` method takes `Filament\Schemas\Schema` (NOT `Filament\Forms\Form` as in v3)
- `Section` is in `Filament\Schemas\Components\Section` (NOT `Filament\Forms\Components\Section`)
- Actions (EditAction, ViewAction, DeleteAction, BulkActionGroup, DeleteBulkAction) are in `Filament\Actions\*` (NOT `Filament\Tables\Actions\*`)
- `$navigationIcon` type is `string | BackedEnum | null` (requires `use BackedEnum;`)
- ChartWidget `$heading` is non-static (`protected ?string $heading`) in v4

---

## 7. Vue/Inertia Dashboard

**URL:** `/dashboard` (requires auth)
**Controller:** `App\Http\Controllers\DashboardController`

### Sidebar Navigation

- Dashboard (Inertia route)
- Leads (links to `/admin/leads` via `<a>` tag, NOT Inertia `<Link>`)
- Email Sequences (Inertia route at `/email-sequences` — purpose-built Vue page for sequence review)
- Brands (links to `/admin/brands`)
- Suppressions (links to `/admin/suppressions`)
- Mining Targets (links to `/admin/mining-targets`)
- Full Admin Panel (links to `/admin`)

**IMPORTANT:** Links to `/admin/*` routes MUST use regular `<a>` tags, NOT Inertia `<Link>`. Inertia intercepts clicks and expects JSON responses; Filament returns full HTML, causing errors. This is handled in `NavMain.vue` via an `isExternal()` check.

### Dashboard Content

- 4 stat cards: Total Leads, With Email, Suppressed, Active Brands
- Leads by Brand (horizontal bars with brand colors)
- Leads by Segment (progress bars)
- Leads by Status (progress bars)
- Top 10 Cities (bar chart)
- Recent Activity feed (last 20 events)
- Email Sequences section: total emails, pending/approved/rejected/sent counts, emails by sequence step, approval breakdown, send status badges
- Quick link buttons to all admin sections (including Email Sequences)

### Data Flow

```
DashboardController → Inertia::render('Dashboard', $props)
  → props: stats (lead + email sequence counts), leadsByBrand, leadsBySegment,
    leadsByStatus, topCities, recentEvents, emailsByStep, emailApprovalBreakdown,
    emailStatusBreakdown
  → Dashboard.vue receives as defineProps
```

### DashboardController Details

The controller (`app/Http/Controllers/DashboardController.php`) queries:
- `Lead::count()` — total leads
- `Lead::where('status', 'enriched')->count()` — enriched leads
- `Lead::where('status', 'new')->count()` — new leads
- `Lead::where('status', 'no_email_found')->count()` — no email found
- `Lead::whereNotNull('email')->count()` — leads with email
- `Suppression::count()` — suppressed count
- `Brand::where('is_active', true)->count()` — active brands
- `Lead::where('segment', 'rabbit')->count()` — rabbits
- `Lead::where('segment', 'deer')->count()` — deer
- Leads by brand (withCount)
- Leads by segment (groupBy)
- Leads by status (groupBy)
- Top 10 cities (groupBy, orderByDesc, limit 10)
- Recent 20 events with lead relationship

---

## 8. Current Data State

### Canonical Production Dataset (Linux target after restore)

```
Leads:          608 total
  Rabbits:      199
  Deer:         409
Suppressions:   265
```

Linux Postgres is the canonical system of record going forward. Move the real Mac dataset with `pg_dump` -> `pg_restore`, then keep customer PII on Linux only.

### Local Mac Dev Dataset (sample-only after reset)

```
Users:          1 (dev-admin@example.test)
Brands:         4
Leads:          24 sample-only records
Suppressions:   4 sample-only records
Lead Events:    44 sample-only records
Email Messages: 12 sample-only records
Mining Targets: 4,666 fully-seeded records (UjuziPlus + Hudutech)
Activity Events: 20 records (10 seeded + 10 activity log entries)
```

The Mac dev database now seeds fake/anonymized local data only via `database/seeders/SampleLeadSeeder.php`.

### Email Sequence Data (imported from Google Sheets)

- Rabbits CSV has `email_1` through `email_5` columns (5-step drip)
- Deer CSV has `email_1` through `email_3` columns (3-step drip)
- 259 real email drafts imported (subjects + bodies parsed from CSV)
- Non-email entries skipped (enrichment markers like "Enriched: 18 Jun 2026", "skipped: no website URL")
- All 259 emails have `approval_status=pending` — awaiting review
- Imported via `php artisan emails:import-sequences`

### Data Canonicalization

- Canonical move procedure: see `docs/data-canonicalization.md`
- Recommended transfer path: `pg_dump` on Mac -> restore into Linux Postgres
- Fallback path: `php artisan leads:import-ujuziplus` and `php artisan emails:import-sequences` on Linux against the CSV exports
- Temporary caution: if a local dump or CSV export still exists on the Mac, it is still customer data until removed after Linux restore verification

### Lead Sources

- **Google Sheets** — UjuziPlus pipeline (Rabbits + Deer sheets)
- Imported via `php artisan leads:import-ujuziplus`
- Original CSVs saved at: `storage/app/private/ujuziplus_rabbits.csv`, `storage/app/private/ujuziplus_deer.csv`
- Google Sheet ID and token path are stored in environment config on the operator's machine, NOT in this document. Check `.env` and the operator's private config files.
- **Verify the Google Sheet's sharing permissions are restricted** (not "anyone with link"). It should be limited to the operator's Google account only.

### Top Cities (from imported data)

```
Kisumu: 25, Mombasa: 19, Nairobi: 17, Nakuru: 15, Thika: 7,
Eldoret: 5, Machakos: 5, Malindi: 4, Naivasha: 3, Meru: 2
```

### Import Command Details

The `ImportUjuziPlusLeads` command (`app/Console/Commands/ImportUjuziPlusLeads.php`):
- Reads CSV files from `storage/app/private/`
- Maps columns: `org_name/company_name`, `email/direct_email/company_email`, `phone/phone_wa`, `website`, `category`, `first_name/contact_name`, `role`
- Handles Deer sheet data quality issues (misaligned columns):
  - Truncates `contact_name` to null if > 100 chars (misaligned long text)
  - Truncates `company_name` to 255 chars max
  - Nulls `email` if > 255 chars (misaligned data)
  - Truncates `phone` to 50 chars max
- Deer sanity check result: the current null-email Deer population is not caused by the `>255` email branch in the source CSV; see `docs/data-canonicalization.md`
- Extracts city from raw data or infers from company name (checks against known Kenyan cities)
- Calculates score: email(+30) + phone(+15) + website(+15) + business_insight(+20) + concrete_fact(+10) + deer(+10)
- Checks suppression column for "yes/true/1" values
- Creates Lead + LeadEvent records
- Creates Suppression record if flagged
- Dedup: checks existing lead by (brand_id, email) before creating

---

## 9. Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan leads:import-ujuziplus` | Import leads from Google Sheets CSV into Postgres |
| `php artisan leads:import-ujuziplus --dry-run` | Preview import without writing |
| `php artisan leads:import-ujuziplus --file=path/to/csv` | Import specific CSV file |
| `php artisan emails:import-sequences` | Import email sequences (email_1..email_5) from CSV into email_messages table |
| `php artisan emails:import-sequences --dry-run` | Preview email import without writing |
| `php artisan emails:import-sequences --file=path/to/csv` | Import from specific CSV file |
| `php artisan migrate:fresh --seed` | Reset DB + seed brands + sample-only local dev data |
| `php artisan serve` | Start Laravel dev server (port 8000) |
| `php artisan queue:work redis` | Start queue worker manually (production uses Supervisor config) |
| `php artisan tinker` | Interactive REPL |

---

## 10. Development Setup

### Prerequisites

- PHP 8.3+ (8.4.11 installed)
- PostgreSQL 15+ (17.10 installed)
- Redis (6379)
- Node.js 20+ (for Vite)
- Composer

### First-time Setup

```bash
cp .env.example .env
# Edit .env: set DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=5432,
#   DB_DATABASE=omni_os, DB_USERNAME=im
#   Set REDIS_CLIENT=predis (NOT phpredis — extension not installed on Mac)
#   Set QUEUE_CONNECTION=redis, CACHE_STORE=redis
php artisan key:generate
composer install
npm install
php artisan migrate
php artisan db:seed
```

### Running the Dev Servers

**IMPORTANT:** You need BOTH servers running. Without Vite, the page renders blank/white because CSS and JS are loaded from port 5173.

```bash
# Terminal 1: Laravel backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: Vite frontend (CSS/JS hot reload)
npm run dev
```

If you only run `php artisan serve` without `npm run dev`, the page at http://127.0.0.1:8000/ will be white/blank because Vite serves all the CSS and JavaScript from port 5173. For production (no hot reload), you can instead run `npm run build` once to compile assets into `public/`, then only `php artisan serve` is needed.

### Login

- URL: http://127.0.0.1:8000/login
- Local dev user: `dev-admin@example.test`
- Password: configured in `database/seeders/DatabaseSeeder.php` for local development only

### Key URLs

| URL | What |
|-----|------|
| http://127.0.0.1:8000/ | Welcome page |
| http://127.0.0.1:8000/dashboard | Vue dashboard with stats + charts + email sequence stats |
| http://127.0.0.1:8000/admin | Filament admin panel |
| http://127.0.0.1:8000/admin/leads | Lead management (sample local data by default; canonical data lives on Linux) |
| http://127.0.0.1:8000/admin/email-messages | Email sequence management |
| http://127.0.0.1:8000/admin/brands | Brand management (4 brands) |
| http://127.0.0.1:8000/admin/suppressions | Suppression list |
| http://127.0.0.1:8000/admin/mining-targets | Mining target config |

### Environment (.env key settings)

Use `.env.example` as the full blank checklist for required keys.

Production rules:
- real production `.env` lives only on Linux and is never committed
- `APP_ENV=production`
- `APP_DEBUG=false`
- `REDIS_CLIENT=predis`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`

### Manual Linux Deploy

Deployment is manual only. Do not auto-deploy on push.

Trigger:

```bash
# SSH or Tailscale into the Linux box
cd /srv/omni_os
bash scripts/deploy.sh
```

Committed deploy script:

- `scripts/deploy.sh`
- runs `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, migrations, caches, Filament assets, storage link, and `queue:restart`

Artifact policy:

- `vendor/`, `node_modules/`, and `public/build/` are gitignored and must be rebuilt on Linux
- never copy built binaries from Mac to Linux; Mac is ARM and Linux is x86
- Redis stays on `predis` on both Mac and Linux
- Git remote points at `git@github.com:samgithae/omni_os.git`; verify the repository visibility is private in GitHub settings

### Linux Queue / Scheduler / Backup

Queue worker:

- Supervisor config is committed at `deploy/supervisor/omni-os-queue-worker.conf`
- start/update commands:

```bash
sudo cp deploy/supervisor/omni-os-queue-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start omni-os-queue-worker:*
sudo supervisorctl status omni-os-queue-worker:*
```

- stop/restart commands:

```bash
sudo supervisorctl stop omni-os-queue-worker:*
sudo supervisorctl restart omni-os-queue-worker:*
```

Scheduler:

- app scheduler hook is wired in `bootstrap/app.php`
- install Linux cron:

```bash
* * * * * cd /srv/omni_os && php artisan schedule:run >> /dev/null 2>&1
```

Backups:

- backup script: `scripts/backup-postgres.sh`
- cron example: `deploy/cron/omni-os.cron.example`
- backup retention rotates old dumps with `find ... -mtime`
- set `BACKUP_ROOT` to storage outside the database disk and optionally set `BACKUP_REMOTE_TARGET` for rsync to external/private backup storage

### Cloudflare Tunnel + Access

Committed template:

- `deploy/cloudflare/cloudflared-config.example.yml`

Policy:

- expose only the Laravel web UI hostnames
- dashboard hostname: `CLOUDFLARE_DASHBOARD_HOSTNAME`
- admin hostname: `CLOUDFLARE_ADMIN_HOSTNAME`
- create the Cloudflare Access application before routing the tunnel
- deny by default
- allow only Sam's Google identity via `CLOUDFLARE_ACCESS_ALLOWED_EMAIL`
- never expose Postgres through Cloudflare Tunnel

### Route List (67 routes total)

Key routes:
```
GET  /                         → Welcome (Inertia)
GET  /dashboard                → DashboardController (auth required)
GET  /admin                    → Filament dashboard
GET  /admin/brands             → Brand CRUD
GET  /admin/leads              → Lead CRUD + view
GET  /admin/suppressions       → Suppression CRUD
GET  /admin/mining-targets     → Mining target CRUD
GET  /email-sequences           → EmailSequenceController@index (Vue page, auth required)
GET  /email-sequences/approve   → EmailSequenceController@bulkApprove (POST, auth)
GET  /email-sequences/reject    → EmailSequenceController@bulkReject (POST, auth)
GET  /email-sequences/{id}/approve → EmailSequenceController@approve (POST, auth)
GET  /email-sequences/{id}/reject  → EmailSequenceController@reject (POST, auth)
GET  /login                    → Fortify login
POST /login                    → Fortify authenticate
POST /logout                   → Fortify logout
GET  /settings/profile         → User profile settings
GET  /settings/security        → Security settings (2FA, passkeys)
```

---

## 11. File Structure

```
omni_os/
├── Omni-OS-Strategy-Brief.md          # Strategy source of truth (v1) — READ THIS FIRST
├── Omni-OS-Strategy-Brief.md.pdf      # PDF version of strategy brief
├── PROJECT.md                         # THIS FILE — technical status & developer guide
├── .env                               # Environment config (secrets — don't commit)
├── .env.example                       # Template for .env
├── composer.json                      # PHP dependencies
├── composer.lock                      # PHP dependency lock
├── package.json                       # JS dependencies + scripts
├── package-lock.json                  # JS dependency lock
├── vite.config.ts                     # Vite configuration
├── tsconfig.json                      # TypeScript config
├── eslint.config.js                   # ESLint config
├── phpunit.xml                        # PHPUnit config
├── phpstan.neon                       # PHPStan config
├── pint.json                          # Laravel Pint formatter config
├── components.json                    # shadcn/ui config
├── artisan                            # Laravel CLI entry point
│
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── ImportUjuziPlusLeads.php       # Google Sheets CSV → Postgres lead importer
│   │       └── ImportEmailSequences.php       # Google Sheets CSV → Postgres email sequence importer
│   │
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── BrandResource.php            # Brand CRUD
│   │   │   │   └── Pages/
│   │   │   │       ├── CreateBrand.php
│   │   │   │       ├── EditBrand.php
│   │   │   │       └── ListBrands.php
│   │   │   ├── LeadResource.php             # Lead CRUD + filters + view + email relation manager
│   │   │   │   ├── Pages/
│   │   │   │   │   ├── CreateLead.php
│   │   │   │   │   ├── EditLead.php
│   │   │   │   │   ├── ListLeads.php
│   │   │   │   │   └── ViewLead.php          # Shows email sequence via relation manager
│   │   │   │   └── RelationManagers/
│   │   │   │       └── EmailMessagesRelationManager.php  # Email sequence inline on lead view
│   │   │   ├── EmailMessageResource.php      # Email sequence CRUD + approve/reject
│   │   │   │   └── Pages/
│   │   │   │       ├── ListEmailMessages.php
│   │   │   │       ├── CreateEmailMessage.php
│   │   │   │       ├── ViewEmailMessage.php
│   │   │   │       └── EditEmailMessage.php
│   │   │   ├── SuppressionResource.php      # Suppression CRUD
│   │   │   │   └── Pages/
│   │   │   │       ├── CreateSuppression.php
│   │   │   │       ├── EditSuppression.php
│   │   │   │       └── ListSuppressions.php
│   │   │   └── MiningTargetResource.php     # Mining config CRUD
│   │   │       └── Pages/
│   │   │           ├── CreateMiningTarget.php
│   │   │           ├── EditMiningTarget.php
│   │   │           └── ListMiningTargets.php
│   │   └── Widgets/
│   │       ├── LeadStatsOverview.php      # Stat cards widget
│   │       ├── LeadsByBrandChart.php      # Bar chart widget
│   │       ├── LeadsBySegmentChart.php    # Doughnut chart widget
│   │       └── LeadsByCityChart.php       # Top 10 cities widget
│   │
│   ├── Enums/
│   │   ├── LeadStatus.php           # Lead state machine
│   │   ├── ActivityEventType.php    # Controlled vocabulary for activity feed
│   │   └── ActivitySeverity.php     # info/success/warning/error
│   │
│   ├── Events/
│   │   └── ActivityEventCreated.php # Fired when notify_telegram=true
│   │
│   ├── Listeners/
│   │   └── NotifyTelegram.php       # Stub — future Telegram delivery
│   │
│   ├── Services/
│   │   └── ActivityLogger.php       # Activity::log() — posts to feed
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Controller.php
│   │       ├── DashboardController.php    # Vue dashboard data provider
│   │       ├── ActivityController.php     # Activity feed (index, poll, loadMore)
│   │       ├── EmailSequenceController.php # Email sequences Vue page (index, approve, reject)
│   │       ├── Api/                       # API controllers
│   │       │   ├── EmailController.php
│   │       │   ├── LeadController.php
│   │       │   ├── MiningTargetController.php
│   │       │   ├── StatsController.php
│   │       │   ├── SuppressionController.php
│   │       │   ├── WebhookController.php
│   │       │   └── ActivityEventController.php  # POST /api/v1/events
│   │       └── Settings/
│   │           ├── ProfileController.php
│   │           └── SecurityController.php
│   │
│   ├── Models/
│   │   ├── Brand.php
│   │   ├── Lead.php
│   │   ├── Suppression.php
│   │   ├── EmailMessage.php
│   │   ├── LeadEvent.php
│   │   ├── MiningTarget.php
│   │   ├── ActivityEvent.php         # Activity feed model
│   │   └── User.php
│   │
│   └── Providers/
│       └── Filament/
│           └── AdminPanelProvider.php     # Filament panel configuration
│
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2024_01_01_000000_create_passkeys_table.php
│   │   ├── 2025_06_20_100000_create_brands_table.php
│   │   ├── 2025_08_14_170933_add_two_factor_columns_to_users_table.php
│   │   ├── 2026_06_20_092930_create_leads_table.php
│   │   ├── 2026_06_20_092931_create_suppressions_table.php
│   │   ├── 2026_06_20_092932_create_email_messages_table.php
│   │   ├── 2026_06_20_092933_create_lead_events_table.php
│   │   ├── 2026_06_20_092934_create_mining_targets_table.php
│   │   ├── 2026_06_20_175321_add_approval_workflow_to_email_messages.php
│   │   └── 2026_06_21_171104_create_activity_events_table.php  # Activity feed
│   └── seeders/
│       ├── DatabaseSeeder.php             # Orchestrates all seeders
│       └── BrandSeeder.php               # Seeds 4 brands with full metadata
│
├── resources/
│   ├── js/
│   │   ├── pages/
│   │   │   ├── Dashboard.vue              # Real dashboard with stats + charts
│   │   │   ├── Activity.vue               # Twitter-style activity feed
│   │   │   ├── Welcome.vue                # Landing page
│   │   │   ├── EmailSequences/             # Email sequence review workspace
│   │   │   │   ├── Index.vue              # Main page (stats, filters, lead list, bulk actions)
│   │   │   │   └── components/
│   │   │   │       ├── StatsBar.vue        # Aggregate stats with click-to-filter
│   │   │   │       ├── FilterBar.vue       # Brand/segment/approval/progress/search
│   │   │   │       ├── LeadSequenceRow.vue  # Compact lead row with brand accent + expand
│   │   │   │       ├── SequenceTimeline.vue # 5-step horizontal progress indicator (signature component)
│   │   │   │       ├── ExpandedSequence.vue # Vertical email list with approve/reject/preview
│   │   │   │       ├── EmailPreview.vue    # Sandboxed HTML email body preview
│   │   │   │       └── BulkActionBar.vue   # Sticky bottom bar for batch approve/reject
│   │   │   ├── auth/                       # Login, register, forgot password
│   │   │   └── settings/                   # User settings (profile, security, appearance)
│   │   ├── components/
│   │   │   ├── NavMain.vue                 # Sidebar nav (Inertia + external links)
│   │   │   ├── AppSidebar.vue              # Sidebar config (5 nav items)
│   │   │   ├── AppLogo.vue                 # "Omni OS" branding
│   │   │   └── ui/                         # Reusable UI components
│   │   └── ...
│   └── ...
│
├── routes/
│   ├── web.php                             # Home + dashboard + email-sequences + activity routes
│   ├── api.php                             # API v1 (leads, emails, mining, events, webhooks)
│   └── settings.php                        # Settings routes (profile, security)
│
├── config/                                 # Laravel config files (14 files)
├── bootstrap/                              # App bootstrap
├── public/                                 # Public assets (favicon, index.php)
├── storage/
│   ├── app/
│   │   └── private/
│   │       ├── ujuziplus_rabbits.csv        # Exported Rabbits sheet (258 rows)
│   │       └── ujuziplus_deer.csv           # Exported Deer sheet (431 rows)
│   ├── framework/                           # Laravel framework storage
│   └── logs/                               # Application logs
│
├── tests/                                  # Test suite
├── vendor/                                  # Composer dependencies
└── node_modules/                            # npm dependencies
```

---

## 12. What's Done — Phase 0 Foundation (COMPLETE)

### Infrastructure & Stack

- [x] Laravel 13 + Vue 3/Inertia + Tailwind 4 + PostgreSQL 17 stack running
- [x] Redis configured with predis (phpredis extension not available on Mac)
- [x] TypeScript strict mode passing (`vue-tsc --noEmit` clean)
- [x] AppLogo rebranded to "Omni OS"

### Database & Models

- [x] User seeder with Sam's credentials
- [x] Brand model + BrandSeeder (4 brands with full metadata: name, slug, description, market, KPI, voice, color)
- [x] Core database schema: 5 custom migrations (leads, suppressions, email_messages, lead_events, mining_targets)
- [x] Three DB invariants enforced at schema level:
  - Dedup: `UNIQUE(brand_id, email)` on leads
  - Suppression: `UNIQUE(brand_id, email)` on suppressions
  - Idempotency: `UNIQUE(lead_id, sequence_step)` on email_messages
- [x] Six Eloquent models with relationships, scopes, and casts
- [x] Lead state machine: `new → enriching → enriched | no_email_found`

### Admin Panel (Filament v4)

- [x] Filament v4 admin panel installed and configured
- [x] Five Filament resources: Brand, Lead, Suppression, MiningTarget, EmailMessage
- [x] Four dashboard widgets: stats overview, brand chart, segment chart, city chart
- [x] Lead resource with view page, filters (brand, segment, status, country, city), badges, score column
- [x] EmailMessage resource with approval workflow (approve/reject actions, bulk approve, filters by approval status, send status, sequence step)
- [x] EmailMessages relation manager on Lead view page (shows email sequence inline with approve/reject)
- [x] Fixed Filament v4 compatibility issues (Schema import, Actions namespace, navigationIcon type, ChartWidget heading)

### Vue/Inertia Frontend

- [x] Vue/Inertia dashboard with real data (DashboardController + Dashboard.vue)
- [x] Stat cards: total leads, with email, suppressed, active brands
- [x] Progress bars for segment and status distribution
- [x] Bar chart for leads by brand (with brand colors)
- [x] Top 10 cities bar chart
- [x] Recent activity feed (last 20 events)
- [x] Email sequence stats section (total emails, pending/approved/rejected/sent, by step, approval breakdown, send status badges)
- [x] Sidebar navigation with Activity Feed and Email Sequences links
- [x] Quick links to all admin sections including Email Sequences
- [x] Fixed Inertia `<Link>` vs `<a>` tag issue for Filament admin routes

### Email Sequences Redesign (Phase 1 — UI/UX Enhancement)

- [x] **New Vue/Inertia page at `/email-sequences`** — purpose-built sequence review workspace replacing the flat Filament table as primary operator tool
- [x] **Stats bar** — inline aggregate stats (total, pending, approved, rejected, sent, opened, clicked) with stat-click filtering
- [x] **Filter bar** — brand, segment, approval, progress, and search filters with instant Inertia partial reload
- [x] **LeadSequenceRow** — compact lead rows with 5-step horizontal timeline (SequenceTimeline), brand color accent, expand/collapse
- [x] **SequenceTimeline** — the signature visual: 5 color-coded circles (sent+opened=green, sent=blue, pending=amber, rejected=red, draft=gray, empty=outline/dashed) connected by lines, with step labels and status icons
- [x] **ExpandedSequence** — vertical timeline showing all emails per lead with subjects, timestamps, status badges, single approve/reject, and inline preview toggle
- [x] **EmailPreview** — sandboxed read-only HTML email body render (max-height scroll, gray background)
- [x] **BulkActionBar** — sticky bottom bar for batch approve/reject across selected leads
- [x] **Subject mismatch detection** — warns (⚠️) when subject doesn't contain the lead's company name words
- [x] **Backend** — `EmailSequenceController` with index (paginated, filtered), bulkApprove, bulkReject, approve, reject endpoints
- [x] **Sidebar + Dashboard links updated** — both now point to `/email-sequences`
- [x] **Existing Filament EmailMessageResource untouched** — coexists as individual record editor

### Activity Feed (Command Center)

- [x] **Database** — `activity_events` table with brand_id, source, event_type, title, body, metadata (JSONB), severity, timestamps. Indexed on (brand_id, created_at), (event_type, created_at), and severity.
- [x] **Enums** — `ActivityEventType` (mining_run, enrichment_batch, email_sent_batch, email_approved, email_rejected, reply_classified, suppression_added, daily_brief, system, deployment) and `ActivitySeverity` (info, success, warning, error)
- [x] **ActivityLogger service** — `Activity::log()` facade, callable from any Laravel job/command without API round-trip
- [x] **API endpoint** — `POST /api/v1/events` behind existing `ApiTokenAuth` middleware, accepts brand_slug, source, event_type (validated), title, body, metadata, severity, notify_telegram
- [x] **Event + Listener stub** — `ActivityEventCreated` event fires on notify_telegram=true; `NotifyTelegram` listener is a no-op stub ready for future Telegram integration
- [x] **Vue/Inertia page at `/activity`** — reverse-chronological, day-grouped Twitter-style timeline feed
- [x] **Brand filter pills** — All / Hudutech / UjuziPlus / Phantomflix / Phantom Tutors with brand colors
- [x] **Daily brief pinned/expanded** — `daily_brief` events render as distinct pre-expanded cards at top of their day group
- [x] **Polling endpoint** — lightweight `GET /activity/poll?since={id}` returns new event count; 25s client-side poll shows "X new events" banner without scroll-jump
- [x] **Load more** — `GET /activity/load-more?before={id}` cursor pagination with button, not infinite scroll
- [x] **Severity visual cues** — info (neutral), success (green left-border), warning (amber), error (red); color-coded dots and badges
- [x] **Expand/collapse** — click any event card to reveal body, metadata, and source details
- [x] **Empty state** — "All quiet — no activity in the last 24h" instead of blank page
- [x] **Sidebar navigation** — "Activity Feed" link added to AppSidebar.vue with Activity icon
- [x] **No per-record logging** — batch operations produce exactly one activity_events row
- [x] **No dedup logic** — telemetry table, not business state

### Data Import

- [x] Google Sheets → Postgres lead import command (`leads:import-ujuziplus`)
- [x] 608 UjuziPlus leads imported (199 rabbits + 409 deer, 269 with emails)
- [x] 265 suppressions imported from sheet data
- [x] 608 lead events logged (import tracking)
- [x] Deer sheet data quality issues handled (misaligned columns truncated/nulled)
- [x] Google Sheets → Postgres email sequence import command (`emails:import-sequences`)
- [x] 259 email drafts imported (subjects + bodies parsed from email_1..email_5 columns)
- [x] Non-email entries filtered out (enrichment markers, skip notes)
- [x] All 259 emails set to `approval_status=pending` for review workflow

### Documentation

- [x] Strategy brief written (`Omni-OS-Strategy-Brief.md`)
- [x] This PROJECT.md written

### Mining Targets Configuration (Phase 1.1)

- [x] **`mining:seed-targets` artisan command** — seeds geo config for both UjuziPlus and Hudutech with --append and --brand options
- [x] **4 geo priority tiers**: Kenya (daily cadence), East Africa (weekly), English-speaking Africa (weekly), Global (monthly)
- [x] **UjuziPlus**: 1,998 targets — corporate training/LMS categories (training providers, SACCOs, universities, NGOs, government agencies, etc.) across 4 tiers
- [x] **Hudutech**: 2,664 targets — ERP/automation categories (retail, manufacturing, schools, NGOs, logistics, real estate, etc.) across 4 tiers
- [x] Country-level targets for all tiers; city-level targets for tiers 1-3
- [x] Each target has: brand, country, city (nullable), category, search template, segment (rabbit/deer), cadence (daily/weekly/monthly), is_active
- [x] `Activity::log()` called after seeding — event appears in the Activity Feed

---

## 13. What's Remaining — Full Roadmap

### Phase 0 — Foundation (REMAINING ITEMS)

These are infrastructure items that are not blocking development but need to be done before production:

- [x] **Manual Linux deployment script committed** — `scripts/deploy.sh` is the only supported trigger path for production deploys. Run it manually over SSH/Tailscale. No auto-deploy-on-push.
- [x] **Queue worker config committed** — Supervisor config for `php artisan queue:work redis` is committed with auto-restart and logging.
- [x] **Laravel scheduler hook wired** — App schedule is defined in `bootstrap/app.php`; install the provided Linux cron entry.
- [x] **Backup strategy committed** — `scripts/backup-postgres.sh` plus daily cron example with retention.
- [x] **Cloudflare Tunnel + Access setup applied on Linux** — `omni.hudutech.co.ke` routed through existing tunnel to port 80, DNS routed, tunnel restarted, APP_URL set, trusted proxies configured for Cloudflare SSL
- [x] **Linux production `.env` populated privately** — App, DB, Redis, SMTP2GO, backup settings configured on Linux
- [ ] **Per-brand Hermes profiles + context spine** — External to this codebase. Per brand: `icp/`, `competitors/`, `positioning/`, `messaging/`, `brand/` files. Hermes reads these to draft emails and mine leads with brand-specific voice.
- [ ] **Model routing config in Hermes** — GLM 5.2 for bulk drafting/mining; Qwen for research-heavy tasks; DeepSeek for coding; frontier model only where ROI is obvious.
- [x] **`.env.example` expanded** — Includes application, DB, Redis, SMTP2GO, Cloudflare, queue, cache, and backup keys with blank values only.
- [ ] **Linux production `.env` populated privately** — Real SMTP2GO, Redis password, APP_ENV, APP_DEBUG, and tunnel secrets live only on Linux.
- [ ] **Metabase setup** — Connect to Postgres for analytics dashboards (swappable with Vue dashboards later).

### Phase 1 — UjuziPlus Full Loop (NEXT — THIS IS THE PRIORITY)

This is the core work. The strategy brief says: "Marketing execution is the priority."

#### 1.1 Mining Targets Configuration

- [x] **Seed `mining_targets` table** with initial geo config:
  - Kenya cities × categories (Nairobi, Mombasa, Kisumu, Nakuru, Eldoret, Thika, etc.)
  - Segments: rabbit (private training providers) + deer (SACCOs, larger institutions)
  - Search templates per category
  - Cadence: daily for rabbits, weekly for deer
  - This replaces hardcoded "Nairobi"/"Kenya" in mining scripts
  - Expanding to new countries/cities = inserting config rows (no code changes)
- 4,666 total targets seeded across UjuziPlus (1,998) and Hudutech (2,664)
- 4 geo priority tiers: Kenya (daily), East Africa (weekly), English-speaking Africa (weekly), Global (monthly)
- Country-level targets for all tiers; city-level targets for tiers 1-3

#### 1.2 Enrichment Pipeline (339 leads need email enrichment)

- [x] **Per-lead idempotent enrichment** with hard `no_email_found` exit after N attempts:
  - Lead status transitions: `new → enriching → enriched | no_email_found`
  - `enrichment_attempts` counter incremented each attempt
  - After max attempts (configurable, e.g. 3), set status to `no_email_found` (terminal)
  - One bad lead can't stall the batch (this is why Deer enrichment is stuck)
  - Hermes calls Laravel API or artisan commands instead of writing to Sheets
- [x] **Enrichment job** (queued via Redis):
  - Hermes mines website, social profiles, directories for email
  - Anti-hallucination: tag confidence (verified/inferred/estimated/unavailable)
  - Write "not available" instead of inventing
  - Update lead status + email_verified + score after enrichment
  - Log enrichment event in lead_events
- **Implementation details:**
  - `EmailConfidence` enum: `verified` (score 100, deliverable), `inferred` (75, deliverable), `estimated` (40), `unavailable` (0)
  - `email_confidence`, `enriched_at`, `enrichment_notes` columns added to leads table via migration
  - Lead model: `enrichFound()`, `enrichNoEmail()`, `startEnrichment()` helper methods
  - `PATCH /api/v1/leads/{lead}/enrich` updated: accepts `email_confidence`, uses model helpers for state transitions
  - `leads:enrich-batch` artisan command: `--brand`, `--segment`, `--limit`, `--dry-run` options. Transitions `new` leads to `enriching` for processing
  - ActivityLogger integrated — each batch run posts an `enrichment_batch` event to the Activity Feed
  - `enrichment_notes` stored on the lead for debugging failed attempts

#### 1.3 Email Outreach Pipeline

- [x] **5-email relationship-based drip sequence** (imported from Google Sheets):
  - Rabbits: 5-step sequence (email_1 through email_5), 87 emails imported
  - Deer: 3-step sequence (email_1 through email_3), 172 emails imported
  - 259 total email drafts with subjects + bodies parsed and stored in email_messages table
  - Follows Dale Carnegie principles (genuine interest, observation before insight, curiosity before pitch)
  - Every email must pass the "would a consultant send this?" test
- [x] **Approval workflow built** (Filament admin):
  - All 259 emails have `approval_status=pending` — visible in /admin/email-messages
  - Approve/reject actions per email + bulk approve
  - Email sequence visible inline on lead view page via relation manager
  - Dashboard shows approval breakdown + send status
- [ ] **SMTP2GO integration**:
  - Configure SMTP credentials in `.env`
  - Send emails through SMTP2GO API
  - Track opens/clicks via SMTP2GO webhooks → update email_messages
  - Bounce tracking → create suppression record
- [ ] **Idempotency enforcement in send path**:
  - `UNIQUE(lead_id, sequence_step)` prevents double-sends on retry (DB-level — DONE)
  - Email status: `draft → queued → sent | failed` (model-level — DONE)
  - Queue worker picks up `queued` emails and sends via SMTP2GO (NOT YET BUILT)
- [ ] **Safe-send discipline** (from existing pipeline):
  - MX checks before sending (verify domain accepts email)
  - Randomized delays between sends (avoid burst patterns)
  - Business-hours-only sending (respect recipient timezone)
  - Bounce tracking → automatic suppression on hard bounce
  - Domain warming (don't raise volume faster than reputation)
- [ ] **Telegram approval gate integration**:
  - Before ANY email is sent, draft goes to Telegram for human approval
  - Detailed per-company breakdown: email ID, subject, body summary
  - Requires explicit "APPROVED" before queueing for send
  - This is Sam's non-negotiable requirement — no exceptions
  - Currently approval is done via Filament admin (approve/reject buttons) — Telegram integration is next
- [ ] **Email message scheduling**:
  - Drip sequence with delays between steps (e.g. day 1, day 3, day 7, day 14, day 30)
  - Laravel scheduler dispatches due emails to queue
  - Queue worker sends via SMTP2GO

#### 1.4 Reply Detection + Classification (Hermes)

This is the highest-value missing piece — turns a blast into a pipeline:

- [ ] **Reply ingestion**:
  - SMTP2GO forwards replies (or IMAP polling)
  - Hermes classifies each reply
- [ ] **Classification categories**:
  - `interested` — wants more info, pricing, demo
  - `not_interested` — explicit decline
  - `out_of_office` — auto-reply, retry later
  - `unsubscribe` — opt-out request
  - `bounce` — delivery failure
- [ ] **Outcome routing**:
  - `interested` → flag to Telegram for human follow-up (the sales moment)
  - `unsubscribe` → write suppression immediately (compliance)
  - `not_interested` → close lead, log event
  - `out_of_office` → schedule retry after N days
  - `bounce` → create suppression, update lead status
- [ ] **Log all replies** in lead_events with classification + payload

#### 1.5 Lead Scoring

- [ ] **Simple per-lead score** based on:
  - Segment (deer > rabbit for deal size)
  - Category (some categories convert better)
  - City (geographic concentration)
  - Engagement (opens, clicks, replies)
  - Data completeness (email, phone, website all present)
- [ ] Score visible in Filament admin and Vue dashboard
- [ ] Sort/filter by score in lead management

#### 1.6 Win-Loss Loop (Learning)

- [ ] **Feed outcomes back** so the system biases future mining + drafting:
  - Which categories produce the most replies?
  - Which cities have highest conversion?
  - Which email templates/subjects get opens?
  - Which segments have best retention?
- [ ] **Win-loss file** per brand (updates ICP and messaging)
- [ ] **Refresh cadence**: ICP/messaging ~monthly, positioning ~quarterly
- [ ] Surfaces in analytics dashboard

#### 1.7 Analytics Dashboard Expansion

- [ ] **Daily Lead Report** from Postgres (replaces fragile Sheets report)
- [ ] Email open rates, click rates, reply rates
- [ ] Conversion funnel: leads → enriched → emailed → replied → interested → closed
- [ ] Per-category, per-city, per-template performance
- [ ] Retention metrics (cohort GRR/NRR per segment) — not just acquisition/lead counts
- [ ] Win-rate by segment, city, category, template

#### 1.8 Content / Omnipresence Loop (after pipeline is reliable)

- [ ] **Source**: Identify real audience questions (from replies, search data, community)
- [ ] **Pillar**: One substantial blog post per question, GEO-optimized (citation-ready)
- [ ] **Atomize**: Break pillar into LinkedIn post, Reddit comment, email, WhatsApp message
- [ ] **Distribute**: Push to brand's 2-3 channels
- [ ] **Learn**: Feed performance back into context spine → next batch is sharper
- [ ] **GEO requirements**: First 200 words answer the query directly, FAQ section, schema markup (FAQPage, Article, Service, LocalBusiness), server-side rendering, crawlable pages

### Phase 2 — Hudutech (AFTER UjuziPlus loop is reliable)

Most similar B2B motion — reuse the UjuziPlus pipeline:

- [ ] Seed mining_targets for Hudutech (Kenya B2B: "Odoo ERP Kenya", "POS system M-Pesa", "software company Nairobi")
- [ ] Mine Hudutech leads (SMEs, schools, NGOs, manufacturers, distributors, professional-service firms)
- [ ] Email outreach pipeline (reuse from Phase 1)
- [ ] LinkedIn integration (LinkedIn Helper for outreach + thought leadership)
- [ ] Google Business Profile setup (cheap, high local intent)
- [ ] SEO/GEO content for Hudutech site (case studies, ROI breakdowns, how-tos)
- [ ] Selective value-first Reddit participation
- [ ] Per-brand Hermes profile + context spine for Hudutech

### Phase 3 — B2C Brands (LATER — after B2B loop is reliable)

More platform-sensitive, different compliance, different channels:

#### Phantomflix (B2C entertainment, Kenya + diaspora)

- [ ] Community/referral/organic playbook (NOT paid ads — streaming reseller gets flagged/banned)
- [ ] Opt-in WhatsApp/Telegram channels for warm audiences
- [ ] Referral loop (existing subscribers invite friends) — cheapest highest-ROI growth
- [ ] Emphasize licensed/legal/affordable/local-payment angle (compliance shield + trust)
- [ ] Keep proof-of-licensing available for platform appeals
- [ ] Different compliance: Kenya DPA 2019
- [ ] Retention is the war (mouse segment — worst retention, churn is the whole game)

#### Phantom Tutors (B2C, US & UK students)

- [ ] Short-form video (TikTok/IG study tips, exam hacks) as top-of-funnel
- [ ] Value-first Reddit subject-help subs
- [ ] Discord student servers
- [ ] Campus ambassadors
- [ ] Opt-in WhatsApp study groups
- [ ] Different compliance: US CAN-SPAM, UK PECR
- [ ] Positioning guardrail: legitimate tutoring / exam prep / learning support ONLY — NEVER assignment-completion / essay-mill (illegal in UK since 2022, bannable on platforms)

### Phase 4 — Cross-Brand Intelligence (FUTURE)

- [ ] **pgvector** for contact/CRM memory graph (embeddings in Postgres)
- [ ] Shared learnings across brands (what works for UjuziPlus might inform Hudutech)
- [ ] Contacts graph: relationships between leads, companies, institutions
- [ ] Begin **elephant motion** once deer is repeatable:
  - Named-account ABM
  - Government tenders/RFPs
  - Large NGO training budgets
  - SACCO umbrella bodies
  - Requires sales DNA + runway

---

## 14. Known Issues & Pitfalls

### Development Environment

- **White/blank page at localhost:8000**: You MUST run `npm run dev` alongside `php artisan serve`. Vite serves CSS/JS from port 5173. Without it, the page loads HTML but no styles/scripts render. For production, use `npm run build` instead.
- **Redis client**: `.env` uses `REDIS_CLIENT=predis` (pure PHP). The `phpredis` PHP extension is NOT installed on the Mac. `predis/predis` v3.5 is installed via Composer.

### Filament v4 Migration Notes

- **`form()` signature changed**: Takes `Schema $schema` not `Form $form`. Import `Filament\Schemas\Schema`.
- **Section moved**: Use `Filament\Schemas\Components\Section` not `Filament\Forms\Components\Section`.
- **Actions moved**: All table actions are in `Filament\Actions\*` not `Filament\Tables\Actions\*`. Must import `use Filament\Actions;`.
- **`$navigationIcon` type**: Must be `string | BackedEnum | null` with `use BackedEnum;` imported.
- **ChartWidget `$heading`**: Non-static in v4 (`protected ?string $heading`), not `protected static ?string $heading`.

### Inertia + Filament Coexistence

- Links from Vue/Inertia pages to `/admin/*` MUST use `<a>` tags, not Inertia `<Link>`. Inertia intercepts `<Link>` clicks expecting JSON; Filament returns full HTML.
- Both use the same `web` auth guard — single login works for both.
- The `NavMain.vue` component has an `isExternal()` function that handles this automatically.

### Select Filter Null Values

- `SelectFilter::make('city')->options(...)` must filter out null values from the database. Use `->whereNotNull('city')->filter()->toArray()` to avoid `Argument #2 ($label) must be of type string, null given` errors.

### Google Sheets Integration

- Token file and Sheet ID are stored in environment config on the operator's machine, NOT in this document.
- Python `google-api-python-client` + `google-auth-oauthlib` libraries are installed on the Mac.
- **Action item:** Verify the Google Sheet's sharing permissions are restricted to the operator's Google account only (not "anyone with link").

### Deer Sheet Data Quality

- The Deer Google Sheet has misaligned columns (e.g., `first_name` column contains business insight text). The import command handles this by:
  - Truncating `contact_name` to null if > 100 chars
  - Truncating `company_name` to 255 chars max
  - Nulling `email` if > 255 chars (misaligned data)
  - Truncating `phone` to 50 chars max

### Hybrid Mac + Linux Workflow (Sam's setup)

- Mac is for research, planning, brainstorming (this dev environment)
- Linux laptop is source of truth for all operational commands — cron, lead mining, email ops, sheet ops
- Hermes gateway runs on Linux as systemd user service
- Telegram is second channel for mobile
- DO NOT SYNC to laptop: cron jobs.json, ujuziplus_agents dir, google token, env file
- MUST exclude `.venv/` from any sync (Mac binaries kill Linux venv)
- SSL_CERT_FILE environment variable is critical for uv Python on Linux
- All 12 Mac cron jobs were paused on 2026-06-19 (everything runs on Linux now)
- Internal network details (IP, username, passwords) are NOT documented here. Check the operator's private environment config.

### Compliance Guardrails (Cross-Cutting)

- **Email/SMS**: Keep existing safe-send discipline (MX checks, delays, business hours, bounce tracking). Every email needs working opt-out, honored via suppression. Keep SPF/DKIM/DMARC clean. Kenya DPA 2019 expects legitimate-interest basis + easy opt-out for B2B outreach.
- **Reddit**: Follow 90/10 rule (>=90% genuine value, <=10% promotion). One genuine account per brand. Multi-account promotion = sockpuppeting = site-wide bans. A spam-flagged domain gets every link auto-removed across Reddit (near-irreversible).
- **WhatsApp**: Opt-in only. Free Business app = 256-contact broadcast lists. API requires Business Verification + privacy-policy URL, approved templates, opt-out, business-hours sending. Does not allow general-purpose AI chatbots.
- **Human-review gate** on ALL external publishing/sending. No exceptions.

---

## 15. Architecture Diagrams

### System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        HERMES AGENT                              │
│  (AI brain — mines, enriches, drafts, classifies replies)        │
│  Per-brand profiles + context spine (ICP, competitors, messaging) │
│  Model routing: GLM 5.2 (bulk) / Qwen (research) / DeepSeek (code)│
└──────────┬──────────────────────────┬───────────────────────────┘
           │ API calls / artisan        │ Telegram
           ↓                            ↓
┌──────────────────────────────────────────────────────────────────┐
│                    LARAVEL PLATFORM                               │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────────┐   │
│  │ Vue/Inertia  │  │ Filament v4  │  │ Artisan Commands        │   │
│  │ Dashboard    │  │ Admin Panel  │  │ (import, enrich, etc.) │   │
│  │ /dashboard   │  │ /admin       │  │                        │   │
│  └──────┬───────┘  └──────┬───────┘  └───────────┬────────────┘   │
│         │                  │                       │               │
│         └──────────┬───────┴───────────────────────┘               │
│                    ↓                                              │
│  ┌──────────────────────────────────────────────┐                │
│  │           Eloquent Models + Scopes           │                │
│  │  Brand, Lead, Suppression, EmailMessage,      │                │
│  │  LeadEvent, MiningTarget                      │                │
│  └──────────────────┬───────────────────────────┘                │
│                     │                                             │
│  ┌──────────────────┴───────────────────────────┐                │
│  │        Queue (Redis) + Scheduler              │                │
│  │  Enrichment jobs, email sending, mining       │                │
│  └──────────────────┬───────────────────────────┘                │
│                     │                                             │
│  ┌──────────────────┴───────────────────────────┐                │
│  │         POSTGRESQL 17 (system of record)      │                │
│  │  brands, leads, suppressions, email_messages,  │                │
│  │  lead_events, mining_targets                   │                │
│  │  Invariants: UNIQUE(brand,email) dedup         │                │
│  │           UNIQUE(brand,email) suppression      │                │
│  │           UNIQUE(lead,step) idempotency        │                │
│  └──────────────────────────────────────────────┘                │
└──────────────────────────────────────────────────────────────────┘
                     │
         ┌───────────┴───────────────┐
         ↓                           ↓
┌─────────────────┐        ┌──────────────────┐
│   SMTP2GO        │        │  Telegram        │
│  (email send +   │        │  (human approval │
│   open/click     │        │   gate before    │
│   tracking)      │        │   any send)      │
└─────────────────┘        └──────────────────┘
```

### Data Flow — UjuziPlus Lead Pipeline

```
Google Sheets (legacy)
    ↓ (one-time import via leads:import-ujuziplus)
Postgres leads table (608 leads: 199 rabbits, 409 deer)
    ↓
Hermes enrichment (runs only for leads still in `new`)
    ↓ (per-lead, idempotent, max N attempts)
Postgres leads table (status: enriched | no_email_found)
    ↓
Email draft generation (Hermes, 5-step relationship drip)
    ↓
Telegram approval gate (human reviews each email)
    ↓ (APPROVED → queued)
SMTP2GO sends email
    ↓ (webhooks: opens, clicks, bounces)
Postgres email_messages table (status: sent, opened, clicked)
    ↓
Reply detection + classification (Hermes)
    ↓
interested → Telegram alert (sales moment)
unsubscribe → Suppression record (compliance)
not_interested → Lead closed
out_of_office → Retry scheduled
bounce → Suppression record
    ↓
Win-loss data feeds back into context spine → next batch sharper
```

### Lead State Machine

```
new -> enriching -> enriched -> emailed -> replied -> interested -> closed
                    \                          \-> not_interested
                     \-> no_email_found

suppressed is a terminal state allowed from any active outreach stage:
new, enriching, enriched, emailed, replied, interested -> suppressed
```

---

## 16. Changelog

### 2026-06-21 — Mining Targets Configuration (Phase 1.1)

- [x] Built `mining:seed-targets` artisan command — seeds geo config for UjuziPlus and Hudutech with --append and --brand options
- [x] 4 geo priority tiers: Kenya (daily cadence, 25 cities), East Africa (weekly, 4 countries), English-speaking Africa (weekly, 5 countries), Global (monthly, 6 countries)
- [x] UjuziPlus: 1,998 targets across 27 categories (training providers, SACCOs, universities, NGOs, etc.)
- [x] Hudutech: 2,664 targets across 29 categories (retail, manufacturing, schools, logistics, etc.)
- [x] Country-level targets for all tiers; city-level targets for tiers 1-3
- [x] Activity::log() integrated — seeding appears in the Activity Feed
- [x] PROJECT.md updated: What's Done (Phase 1.1), Current Data State, Changelog

### 2026-06-21 — Enrichment Pipeline (Phase 1.2)

- [x] `EmailConfidence` enum: verified (score 100, deliverable), inferred (75, deliverable), estimated (40), unavailable (0)
- [x] Migration: added `email_confidence`, `enriched_at`, `enrichment_notes` columns to leads table
- [x] Lead model: `enrichFound()`, `enrichNoEmail()`, `startEnrichment()` helper methods with state transitions
- [x] `PATCH /api/v1/leads/{lead}/enrich` updated: accepts `email_confidence`, uses model helpers, returns status + confidence
- [x] `leads:enrich-batch` artisan command: `--brand`, `--segment`, `--limit`, `--dry-run`. Transitions new → enriching for Hermes processing
- [x] ActivityLogger integrated — each batch run posts enrichment_batch event to the Activity Feed
- [x] PROJECT.md updated: Phase 1.2 marked done, changelog

### 2026-06-21 — Activity Feed (Command Center)

- [x] Created `activity_events` table with brand_id, source, event_type, title, body, metadata (JSONB), severity, timestamps. Indexed for fast filtering.
- [x] Added `ActivityEventType` and `ActivitySeverity` backed enums with controlled vocabulary (10 event types, 4 severity levels)
- [x] Built `ActivityLogger` service — callable from any job/command as `Activity::log()`, no API round-trip needed for internal callers
- [x] Added `POST /api/v1/events` endpoint behind existing `ApiTokenAuth` middleware for Hermes to post events remotely
- [x] Added `ActivityEventCreated` event + `NotifyTelegram` listener stub (ready for future Telegram integration)
- [x] Built `ActivityController` with 3 endpoints: index (Inertia page), poll (lightweight new-event count), loadMore (cursor pagination)
- [x] Built `Activity.vue` — day-grouped, brand-filterable, reverse-chronological Twitter-style timeline feed
- [x] Brand filter pills with brand colors, daily brief pinned/expanded cards, severity visual cues, expand/collapse on event cards
- [x] 25s polling — "X new events" banner loads new content without scroll-jump
- [x] "Load more" button at bottom (no infinite-scroll observer)
- [x] Empty state: "All quiet — no activity in the last 24h"
- [x] Sidebar navigation updated with "Activity Feed" link
- [x] Seeded 10 realistic test events across brands + cross-brand system events
- [x] PROJECT.md updated: What's Done, File Structure, Routes, Changelog

### 2026-06-21 — Production Deployment LIVE (Phase 0 Deployment Complete)

**Nginx & Laravel:**
- Configured Nginx on Linux with `/srv/omni_os/public` root and PHP 8.4 FPM
- Enabled Omni OS site, removed default, nginx config test PASS
- Verified app responds HTTP 200 on localhost port 80 with title "Omni OS"
- Set `APP_URL=https://omni.hudutech.co.ke` to fix mixed-content SSL preload issue
- Added `trustProxies(at: '*')` for Cloudflare SSL termination

**Queue Workers:**
- Installed Supervisor config for 2 queue workers (`php artisan queue:work redis`)
- Both workers RUNNING, auto-restart enabled, running as `www-data`

**Scheduler & Backups:**
- Added Laravel scheduler cron (`* * * * *`)
- Added Postgres backup cron (daily 3 AM) with 14-day retention
- Tested backup: 103K `.dump` created successfully

**Cloudflare Tunnel:**
- Updated existing `huris-laptop` tunnel config: added `omni.hudutech.co.ke → localhost:80`
- DNS routed via `cloudflared tunnel route dns`
- Tunnel restarted, 4 active connections
- Cloudflare Access policy configured for Sam's email
- App reachable at https://omni.hudutech.co.ke (HTTP 200)

**Security:**
- Postgres listening on 127.0.0.1:5432 only (not exposed through tunnel)
- No secrets in tracked git files
- Temp files cleaned up (create-admin.php removed)
- Queue workers run as `www-data` (not root)

### 2026-06-20 — Deployment, Ops Foundation, And State Enforcement

**Deployment & Build:**
- Added committed Linux deploy script at `scripts/deploy.sh`
- Kept build artifacts out of git: `vendor/`, `node_modules/`, and `public/build/` stay rebuild-only on Linux
- Documented manual deploy trigger via SSH/Tailscale; no auto-deploy-on-push
- Confirmed Redis stays on `predis` in config defaults and environment guidance

**Environment & Config Hygiene:**
- Replaced `.env.example` with a complete blank-value checklist for app, DB, Redis, queue, cache, SMTP2GO, Cloudflare, and backup settings
- Added config entries for SMTP2GO, Cloudflare, backup, and GitHub backup settings in `config/services.php`
- Scanned the PHP codebase for `env()` outside `config/`; no non-config usages were found, so no code moves were required for `config:cache`

**Operational Foundation:**
- Added Supervisor worker config at `deploy/supervisor/omni-os-queue-worker.conf`
- Wired the Laravel scheduler in `bootstrap/app.php` and documented the Linux cron entry
- Added Postgres backup script at `scripts/backup-postgres.sh` with retention and optional rsync off-host replication
- Added cron example at `deploy/cron/omni-os.cron.example`
- Added Cloudflare tunnel template at `deploy/cloudflare/cloudflared-config.example.yml` and documented the deny-by-default Access policy

**Data Canonicalization:**
- Documented Linux Postgres as canonical and the one-time Mac -> Linux `pg_dump`/restore procedure
- Reset Mac local development to sample-only seeded data via `SampleLeadSeeder`
- Recorded the Deer null-email sanity check: 191 Deer rows lack email, but 0 are recoverable from the current imported payload

**Lead State Machine:**
- Added `App\Enums\LeadStatus` as the canonical lead status definition
- Enforced guarded status transitions in `App\Models\Lead`
- Rejected invalid transitions and logged every valid transition as a `lead_events` record
- Updated the importer to mark missing-email imports as `no_email_found` instead of leaving them in `new`
- Added automated coverage for valid/invalid transition behavior

### 2026-06-20 — Email Sequence Import + Approval Workflow

**Database:**
- Added migration: approval_status, approved_at, rejected_at, approval_notes, scheduled_for columns on email_messages table
- Added indexes on (brand_id, approval_status) and (lead_id, approval_status)

**Models:**
- Updated EmailMessage model: added approval fields, scopes (pendingApproval, approved, rejected), helper methods (approve, reject, isPendingApproval, isApproved, isRejected, isSent, getDisplayStatusAttribute)

**Artisan Commands:**
- Created `emails:import-sequences` command to import email_1..email_5 from Google Sheets CSVs
- Parses Subject: line from email content, extracts body
- Filters out non-email entries (enrichment markers, skip notes)
- Matches leads by company name (exact + fuzzy match)
- Idempotent: skips existing (lead_id, sequence_step) pairs
- Imported 259 email drafts (58 step 1, 37 step 2, 135 step 3, 29 step 4, 0 step 5)

**Filament Admin:**
- Created EmailMessageResource at /admin/email-messages with:
  - Filters: brand, approval status, send status, sequence step
  - Table columns: lead, brand, step, subject, approval badge, status badge, sent/opened/clicked timestamps
  - Actions: view (modal with email body), edit, approve, reject, delete
  - Bulk actions: approve selected, delete selected
- Created EmailMessagesRelationManager on Lead view page:
  - Shows email sequence inline on lead view
  - Approve/reject actions per email
  - Bulk approve from the relation manager
  - Sortable by sequence step
  - Filters by approval status and send status

**Vue/Inertia Dashboard:**
- Updated DashboardController with email sequence stats (total, pending, approved, rejected, sent, queued, draft, failed, opened, clicked, leads with sequences)
- Added emailsByStep, emailApprovalBreakdown, emailStatusBreakdown props
- Updated Dashboard.vue with Email Sequences section (3 cards):
  - Email Sequences overview (counts + stats)
  - Emails by Sequence Step (bar chart)
  - Approval Breakdown (progress bars + send status badges)
- Added "Email Sequences" to sidebar navigation
- Added "Email Sequences" quick link button

### 2026-06-20 — Phase 0 Foundation (COMPLETE)

**Infrastructure & Stack:**
- Created Laravel 13 + Vue 3/Inertia + Tailwind 4 + PostgreSQL 17 project
- Configured Redis with predis (phpredis not available on Mac)
- Set up TypeScript strict mode (vue-tsc --noEmit passes clean)
- Rebranded app logo to "Omni OS"

**Database & Models:**
- Created Brand model + BrandSeeder with 4 brands (full metadata: name, slug, description, market, KPI, voice, color)
- Created 5 core migrations: leads, suppressions, email_messages, lead_events, mining_targets
- Enforced 3 DB invariants: dedup UNIQUE(brand,email), suppression UNIQUE(brand,email), idempotency UNIQUE(lead,sequence_step)
- Created 6 Eloquent models (Brand, Lead, Suppression, EmailMessage, LeadEvent, MiningTarget) with relationships + scopes
- Implemented lead state machine: new → enriching → enriched | no_email_found

**Admin Panel (Filament v4):**
- Installed and configured Filament v4 admin panel
- Created 4 Filament resources: BrandResource, LeadResource, SuppressionResource, MiningTargetResource
- Created 4 dashboard widgets: LeadStatsOverview, LeadsByBrandChart, LeadsBySegmentChart, LeadsByCityChart
- Fixed Filament v4 compatibility: Schema import, Actions namespace, navigationIcon type, ChartWidget heading
- Lead resource has view page, filters (brand, segment, status, country, city), badges, score column

**Vue/Inertia Frontend:**
- Built DashboardController + Dashboard.vue with real data
- Stat cards: total leads, with email, suppressed, active brands
- Progress bars for segment + status distribution
- Bar chart for leads by brand, top 10 cities chart
- Recent activity feed (last 20 events)
- Built sidebar navigation (NavMain.vue) with isExternal() for Filament admin links
- Fixed Inertia `<Link>` vs `<a>` tag issue for Filament admin routes

**Data Import:**
- Created `leads:import-ujuziplus` artisan command
- Imported 608 UjuziPlus leads from Google Sheets CSV (199 rabbits, 409 deer)
- 269 leads have emails (enriched), 339 need enrichment (new)
- Imported 265 suppressions from sheet data
- Logged 608 lead events (import tracking)
- Handled Deer sheet data quality issues (misaligned columns truncated/nulled)

**Documentation:**
- Wrote Omni-OS-Strategy-Brief.md (v1) — strategy source of truth
- Wrote this PROJECT.md — technical status + developer guide

---

### How to Update This Document

When a feature is completed:

1. Update Section 12 (What's Done) — check the completed item
2. Update Section 13 (What's Remaining) — move items or add new ones
3. Update Section 8 (Current Data State) if database counts changed
4. Update Section 9 (Artisan Commands) if new commands were added
5. Update Section 11 (File Structure) if new files were created
6. Update Section 15 (Architecture Diagrams) if data flow changed
7. Add a changelog entry in Section 16 with today's date
8. Update the "Last updated" date at the top

### Security Rules (READ BEFORE EDITING)

- **NEVER put credentials in this document** — no passwords, no API keys, no tokens, no internal IPs, no usernames, no Sheet IDs. This document is shared with AI models and may be committed to git.
- Credentials live in `.env` (gitignored) or the operator's private environment config, not here.
- If you need to reference where credentials are stored, say "check `.env`" or "check the operator's private config" — do not print the actual values.
- The database seeder (`database/seeders/DatabaseSeeder.php`) sets up the initial user. If it contains a hardcoded password, that's fine for local dev seed data but **must not be committed to git** — add it to `.gitignore` or use a factory with a generated password instead.
- The admin panel will eventually be internet-exposed via Cloudflare Tunnel and able to send email. Treat all credentials as production secrets from day one.

---

*End of document. Living document — update after every completed feature.*
*For strategy context, read `Omni-OS-Strategy-Brief.md` first.*
*For technical context, read this file.*
*Together they give the complete picture for any human or AI to continue development.*
