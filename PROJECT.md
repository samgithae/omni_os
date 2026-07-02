# Omni OS — Project Status, Architecture & Developer Guide

> **Living document.** Update this when behavior, schema, operations, or roadmap status changes.
>
> Last updated: 2026-07-02
> Strategy source of truth: `Omni-OS-Strategy-Brief.md` (v1)
> This document describes the code currently present in this repository. Production counts and external-service state must be verified on the Linux host.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Brands and Strategy](#2-brands-and-strategy)
3. [Current Architecture and Tech Stack](#3-current-architecture-and-tech-stack)
4. [Database and Models](#4-database-and-models)
5. [Backend Application](#5-backend-application)
6. [Web UI and Filament](#6-web-ui-and-filament)
7. [Routes and APIs](#7-routes-and-apis)
8. [Commands, Jobs, and Scheduler](#8-commands-jobs-and-scheduler)
9. [Data and Integrations](#9-data-and-integrations)
10. [Development and Verification](#10-development-and-verification)
11. [Deployment and Operations](#11-deployment-and-operations)
12. [Repository Structure](#12-repository-structure)
13. [Current Status and Roadmap](#13-current-status-and-roadmap)
14. [Known Issues and Pitfalls](#14-known-issues-and-pitfalls)
15. [Recent Changelog](#15-recent-changelog)
16. [Documentation Maintenance](#16-documentation-maintenance)

---

## 1. Project Overview

Omni OS is a multi-brand marketing operations platform for a solo operator running four businesses from Nairobi. Laravel and PostgreSQL provide the system of record, workflow invariants, scheduling, queues, APIs, and operational UI. Vue/Inertia is the main operator interface; Filament remains an administrative CRUD surface. Hermes Agent is external to this repository and performs fuzzy work such as mining, enrichment, drafting, and classification through Omni OS APIs.

### Core principles

- Marketing execution is the priority; Omni OS is a record, analytics, and integrity layer.
- Geography, brand, segment, and sequence behavior are configuration rather than hard-coded campaigns.
- External sends require human approval.
- PostgreSQL is authoritative for operational state.
- Deduplication, suppression, and send idempotency are database-enforced.
- Commodity infrastructure should remain Laravel/PostgreSQL/Redis/Filament rather than custom replacements.

### Division of labor

```text
Hermes / named agents
  mine, enrich, draft, classify, post activity
          |
          | authenticated JSON API
          v
Laravel + PostgreSQL
  records, constraints, scheduler, queues, monitoring
          |
          +--> SMTP2GO: outbound mail and engagement webhooks
          +--> Telegram: approvals, alerts, agent interaction
          +--> Vue/Inertia + Filament: operator and admin UIs
```

---

## 2. Brands and Strategy

| Brand | Slug | Offer | Primary market | Primary KPI | Strategy |
|---|---|---|---|---|---|
| Hudutech Innovations Ltd | `hudutech` | Web/software development, Odoo ERP, CRM, digital transformation | Kenya | Qualified leads to revenue | Deer / elephant |
| UjuziPlus | `ujuziplus` | White-label LMS, professional and corporate training | Kenya / Africa | Subscriptions, enrollments, corporate contracts | Rabbit to deer |
| Phantomflix | `phantomflix` | Licensed streaming subscription resale and M-Pesa access | Kenya + diaspora | Paid subscribers and retention | Mouse |
| Phantom Tutors | `phantom-tutors` | Tutoring, exam preparation, personalized learning | US / UK | Enrollment, retention, referrals | Rabbit |

Brand voice, market, KPI, sender pools, and settings are stored on `brands`. The strategic context-spine concept remains:

```text
icp/  competitors/  positioning/  messaging/  brand/
```

Only `icp/ujuziplus.md` currently exists in this repository; fuller per-brand context is still external/missing.

The long-term content loop remains:

```text
Source -> Pillar -> Atomize -> Distribute -> Learn
```

---

## 3. Current Architecture and Tech Stack

### Runtime

| Area | Current requirement / package constraint |
|---|---|
| PHP | `^8.3` |
| Laravel | `^13.7` |
| Filament | `^4.0` |
| Inertia Laravel adapter | `^3.0` |
| PostgreSQL | Primary production database |
| Redis | Cache and queue, using `predis/predis ^3.5` |
| Authentication | Fortify `^1.37.2`, passkeys, 2FA |
| Static analysis | Larastan `^3.9`, PHPStan config in `phpstan.neon` |
| Tests | PHPUnit `^12.5.23` |

### Frontend

| Area | Package constraint |
|---|---|
| Vue | `^3.5.13` |
| Inertia Vue adapter | `^3.0.0` |
| Tailwind CSS | `^4.1.1` |
| Vite | `^8.0.0` |
| TypeScript | `^5.2.2` |
| UI primitives | Reka UI, class-variance-authority, Lucide, VueUse |
| Notifications | `vue-sonner` |

Exact installed versions are lock-file controlled. Do not copy old point versions from this document into operational decisions.

### Important application configuration

- `config/services.php`: SMTP2GO, Cloudflare, backup, GitHub backup, Telegram, business hours, and IMAP.
- `config/schedule-jobs.php`: metadata used by the jobs-monitoring UI. It is not the scheduler source of truth.
- `bootstrap/app.php`: route registration, middleware, trusted proxies, CSRF exception for manual job runs, and the actual scheduler.
- `config/database.php`: PostgreSQL plus Redis/Predis configuration.
- `config/fortify.php`: registration, reset, verification, 2FA, and passkey behavior.
- `config/inertia.php`: Inertia SSR/testing settings.

### Composer scripts

| Script | Purpose |
|---|---|
| `composer setup` | Install dependencies, create `.env`, generate key, migrate, install npm packages, build assets |
| `composer dev` | Run Laravel, queue listener, Pail, and Vite concurrently |
| `composer lint` / `lint:check` | Run Pint in fix/check mode |
| `composer types:check` | Run PHPStan |
| `composer test` | Clear config, run Pint check, PHPStan, and PHPUnit |
| `composer ci:check` | Run frontend lint/format/type checks, then Composer test pipeline |

### npm scripts

| Script | Purpose |
|---|---|
| `npm run dev` | Vite development server |
| `npm run build` | Production client build |
| `npm run build:ssr` | Client and SSR builds |
| `npm run lint` / `lint:check` | ESLint fix/check |
| `npm run format` / `format:check` | Prettier fix/check under `resources/` |
| `npm run types:check` | `vue-tsc --noEmit` |

---

## 4. Database and Models

There are 28 migration files: three Laravel base migrations, passkeys/2FA, and 24 Omni OS schema changes through 2026-07-01.

### Application tables

| Table | Model | Purpose |
|---|---|---|
| `brands` | `Brand` | Brand metadata, sender-email pools, JSON settings |
| `leads` | `Lead` | Prospects, enrichment state, general score, hiring-signal score, source payload |
| `suppressions` | `Suppression` | Per-brand do-not-contact records |
| `email_messages` | `EmailMessage` | Sequence drafts, approval state, scheduling, send and engagement state |
| `lead_events` | `LeadEvent` | Per-lead event history |
| `mining_targets` | `MiningTarget` | Brand/geography/category/source mining configuration |
| `sequence_schedules` | `SequenceSchedule` | Per-brand/segment/step day gaps and purpose |
| `brand_sequence_configs` | `BrandSequenceConfig` | Drafting prompts and step counts, optionally conditioned by lead source |
| `webhook_events` | `WebhookEvent` | Raw SMTP2GO webhook persistence and processing status |
| `replies` | `Reply` | Inbound/outbound conversation records and classifications |
| `activity_events` | `ActivityEvent` | Operational activity feed, optionally attributed to an agent |
| `activity_event_comments` | `ActivityEventComment` | Human/agent comments and instruction queue state |
| `cron_job_runs` | `CronJobRun` | Scheduled/manual job execution history |
| `agents` | `Agent` | Named agent roster, role/profile, token hash, avatar, status |
| `agent_documents` | `AgentDocument` | Files attached to agent records |

Laravel also creates `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and `passkeys`; the `migrations` table is framework-managed.

### Key schema evolution

- 2026-06-20: brands, leads, suppressions, email messages, lead events, and mining targets.
- 2026-06-20/21: email approval workflow, activity events, and enrichment fields.
- 2026-06-22: sequence schedules, webhook events, replies, brand sender/settings fields, cron tracking, activity comments, and brand sequence configs.
- 2026-06-25: `hiring_signal_score`, agents, agent documents, and activity-to-agent attribution.
- 2026-07-01: source-conditioned sequence configs and replacement of the old `(brand_id, segment)` uniqueness rule.

### Database invariants

1. **Lead email deduplication:** unique `(brand_id, email)` on `leads`.
2. **Suppression deduplication:** unique `(brand_id, email)` on `suppressions`.
3. **Sequence idempotency:** unique `(lead_id, sequence_step)` on `email_messages`.
4. **Schedule uniqueness:** unique `(brand_id, segment, step)` on `sequence_schedules`.
5. **Agent identity:** unique `codename`; generated API token hashes are unique.

PostgreSQL permits multiple `NULL` values in the lead email unique index. It therefore does **not** deduplicate no-email leads by company or domain. The hiring-signal command’s comment suggesting the email index also provides company-name deduplication is incorrect.

### Lead state

Canonical enrichment transitions are defined by `App\Enums\LeadStatus` and guarded by `Lead` helpers:

```text
new -> enriching -> enriched
                  \-> no_email_found
```

Reply routing can move leads into later sales/outcome statuses such as `interested`, `replied`, `closed`, and `suppressed`. Consult the enum/model before adding a transition.

### Models and cross-cutting behavior

- `BelongsToBrand` and `BrandScope` apply active-brand scoping where used.
- `BrandSequenceConfig::resolveFor()` selects a source-conditioned prompt first, then falls back to an unconditioned brand/segment prompt.
- `LeadScoringService` calculates the general 0–100 CRM score.
- `HiringSignalScoreCalculator` separately calculates a 0–150 job-demand score from vacancies, role types, company size/branches, HR contact, and public email.
- `ReplyService` routes classified outcomes and creates suppressions where required.
- `WinLossService` aggregates funnel, engagement, outcome, and dimension metrics.

### Seeders

- `BrandSeeder`: the four brands and their metadata.
- `SampleLeadSeeder`: deterministic fake local data—24 leads, eight mining targets, sample events/messages, and one suppression per brand.
- `DatabaseSeeder`: creates `dev-admin@example.test` with local password `password`, then runs the brand and sample seeders.

Agent roster, mining targets, sender pools, sequence schedules, and deer sequence prompts are command-seeded rather than part of `DatabaseSeeder`.

---

## 5. Backend Application

### Controllers

The authenticated web controllers serve:

- dashboard, scored lead list, analytics, inbox/conversations/replies;
- activity feed, polling, pagination, and comments;
- agent roster CRUD, token generation, avatar/document management;
- brands and per-brand settings;
- email sequence review and bulk/single approval;
- email message browsing;
- sequence config and schedule CRUD;
- suppression and mining-target management;
- scheduled-job dashboard, history, and manual runs;
- user profile/security settings.

API controllers cover:

- lead list/bulk creation, enrichment, scoring, and email-content submission;
- mining target retrieval/update;
- suppression checks;
- email creation, approval/rejection, batch sending, and needs-content updates;
- activity events/comments and instruction polling;
- classified replies;
- stats and win-loss;
- sequence config resolution;
- SMTP2GO and Telegram webhooks;
- Telegram Bot API proxying.

### Services

| Service | Responsibility |
|---|---|
| `ActivityLogger` | Persist activity events and dispatch notification event |
| `CommentResponseService` | Generate deterministic agent replies/actions for activity comments |
| `LeadScoringService` | General lead score and breakdown |
| `HiringSignalScoreCalculator` | Hiring-specific 0–150 score |
| `ReplyService` | Classification outcome routing and suppression |
| `TelegramService` | Telegram API calls, approvals, and notifications |
| `WinLossService` | Funnel, engagement, and outcome aggregation |

### Queue jobs

| Job | Responsibility |
|---|---|
| `ProcessSequenceProgressions` | Create the next `needs_content` sequence step after schedule, suppression, reply, weekday, and daily-send checks |
| `RespondToComment` | Process activity comments/instructions asynchronously |
| `SendLeadReply` | Send an operator reply through SMTP2GO and persist outbound conversation state |
| Eight scraper jobs | Fetch/parse hiring signals from job sources |

Hiring source implementations:

```text
BrighterMonday, Fuzu, MyJobMag, Corporate Staffing,
Glassdoor, known company careers pages, Google JobPosting schema,
LinkedIn via mcporter/linkedin-mcp
```

Scrapers implement `JobSourceScraper`; several also implement `ShouldQueue`, although the current mining command instantiates and executes them synchronously.

### Events and listeners

- `ActivityEventCreated` is emitted for activity records requesting Telegram notification.
- `NotifyTelegram` sends those notifications.
- `TrackCronJobRuns` records scheduler lifecycle events into `cron_job_runs`.

---

## 6. Web UI and Filament

### Vue/Inertia pages

All operational pages below require authentication unless noted.

| URL | Page | Purpose |
|---|---|---|
| `/` | `Welcome.vue` | Public landing page |
| `/dashboard` | `Dashboard.vue` | Cross-brand stats, distributions, scores, sequences, recent events |
| `/leads` | `Leads/Index.vue` | Filtered/sorted scored lead workspace |
| `/inbox` | `Inbox/Index.vue` | Reply inbox, conversation thread, compose |
| `/activity` | `Activity.vue` | Polling activity timeline with comments/instructions |
| `/analytics` | `Analytics/Index.vue` | Funnel, engagement, outcomes, dimensions, brand summary |
| `/analytics/jobs` | `Analytics/Jobs.vue` | Schedule status, recent runs, manual execution |
| `/email-sequences` | `EmailSequences/Index.vue` | Primary sequence approval/review workspace |
| `/email-messages` | `EmailMessages/Index.vue` | Message list/detail |
| `/agents` | `Agents/Index.vue` | Agent roster and management |
| `/brands` | `Brands/Index.vue` | Brand list |
| `/brands/{brand}/edit` | `Brands/Edit.vue` | Brand editing |
| `/brands/{slug}/settings` | `Brands/Settings.vue` | Operational brand settings |
| `/mining-targets` | `MiningTargets/Index.vue` | Mining target management |
| `/sequence-configs` | `SequenceConfigs/Index.vue` | Prompt/step config management |
| `/sequence-schedules` | `SequenceSchedules/Index.vue` | Sequence timing management |
| `/suppressions` | `Suppressions/Index.vue` | Suppression management |
| `/settings/*` | settings pages | Profile, security, appearance |
| `/login`, `/register`, reset/verify routes | auth pages | Fortify authentication flows |

`SequenceConfigs/Create.vue` does not exist; the create route renders `SequenceConfigs/Edit.vue` in create mode.

### Shared frontend

- `AppLayout.vue`, sidebar/header variants, and auth/settings layouts provide the shells.
- `BrandSwitcher.vue` stores the active brand in the session; shared Inertia props expose brands, active brand, auth user, flash state, sidebar state, and unread reply count.
- Reusable page-specific components exist for leads and email sequences.
- `resources/js/components/ui/` contains local Reka/shadcn-style primitives.
- Wayfinder generates typed route helpers during Vite builds.

### Filament admin

Filament is mounted at `/admin`, uses the same web guard, and currently exposes nine resource families:

1. Agents, including attached-document relation management.
2. Brands.
3. Brand sequence configs.
4. Email messages.
5. Leads, including email-message relation management.
6. Mining targets.
7. Sequence schedules.
8. Suppressions.
9. Read-only webhook events.

The four dashboard widgets are lead stats plus brand, segment, and city charts.

When linking from Inertia to Filament, use a normal `<a>` navigation. An Inertia `<Link>` expects an Inertia response and will mishandle Filament HTML.

---

## 7. Routes and APIs

`php artisan route:list` currently reports 160 total routes, including framework auth/passkey routes and Filament internals.

### Authenticated web route groups

- Dashboard and `POST /brand/switch`.
- Leads and analytics.
- Inbox list, conversation JSON, and reply submission.
- Brand list/edit/settings.
- Jobs dashboard, history, and manual run.
- Email sequences with bulk/single approve and reject.
- Activity list/poll/load-more/comment.
- Agent CRUD, token generation, document upload/delete.
- Sequence config CRUD and sequence schedule CRUD.
- Suppression list/create/delete.
- Mining target list/create/toggle/delete.
- Email message list/detail.
- Profile, password, security, passkey, 2FA, and appearance routes.

### Public integration routes

| Method | Path | Authentication |
|---|---|---|
| `POST` | `/api/webhooks/smtp2go` | Webhook-specific validation |
| `POST` | `/api/webhooks/telegram` | Telegram webhook secret |
| `ANY` | `/api/telegram-proxy/bot/{path}` | Proxy route; behavior in controller |
| `GET` | `/up` | Laravel health route |

### `/api/v1` routes

Most use `ApiTokenAuth` and the `OMNI_API_TOKEN` bearer token.

| Area | Endpoints |
|---|---|
| Stats | `GET stats`, `GET stats/winloss` |
| Leads | `GET leads`, `POST leads/bulk`, `PATCH leads/{lead}/enrich`, `PATCH leads/{lead}/score` |
| Lead drafting | `GET leads/needs-email-generation`, `POST leads/{lead}/email-content-batch` |
| Mining | `GET mining-targets`, `PATCH mining-targets/{target}/mined` |
| Suppression | `GET suppressions/check` |
| Emails | `GET/POST emails`, approve/reject, `POST emails/send-batch` |
| Scheduled drafts | `GET email-messages/needs-content`, `PATCH email-messages/{message}/content` |
| Replies | `POST replies` |
| Activity | `POST events`, event comments list/create |
| Instructions | `GET instructions`, `PATCH instructions/{comment}` |
| Sequence prompts | `GET sequence-configs/{brandSlug}/{segment}` |
| Telegram proxy | `ANY telegram-proxy/{path}` |

`POST /api/v1/events` deliberately removes legacy API-token middleware and uses `AgentTokenAuth`. It accepts a per-agent bearer token and retains legacy-token compatibility in that middleware.

### Route ordering caution

Within the leads prefix, dynamic `PATCH leads/{lead}/*` routes currently appear before the static `GET leads/needs-email-generation` route, so method differences prevent collision. Preserve explicit HTTP methods and put new static routes before broad dynamic routes where possible.

`routes/debug.php` contains a route that returns API-token values, but it is **not registered** by `bootstrap/app.php`. It must never be included in production routing.

---

## 8. Commands, Jobs, and Scheduler

### Application Artisan commands

| Command | Purpose / important options |
|---|---|
| `activity:daily-brief` | Post daily funnel/system brief |
| `activity:seed-test-data` | Seed activity feed test records |
| `agents:seed-roster` | Idempotently seed six core agents |
| `cron:cleanup-runs --older-than=30` | Fail orphaned running job records |
| `emails:generate-content --limit=` | Identify/generate content through the API workflow |
| `emails:identify-incomplete-sequences [--fix]` | Report/reset partial sequences |
| `emails:import-sequences [--file=] [--dry-run]` | Import CSV sequence cells |
| `emails:notify-telegram --limit=` | Send pending approvals to Telegram |
| `emails:send-batch [--limit=] [--force]` | Send approved queued mail via SMTP2GO |
| `inbox:poll [--days=3] [--limit=30]` | Poll IMAP and persist replies |
| `leads:backfill-json` | Recover filtered legacy JSON leads |
| `leads:enrich-batch [--brand=] [--segment=] [--limit=] [--dry-run]` | Move eligible leads into enrichment |
| `leads:hiring-signal-digest [--brand=] [--dry-run]` | Post the consolidated Hiring Deer digest |
| `leads:import-ujuziplus [--file=] [--dry-run]` | Import legacy UjuziPlus CSV data |
| `leads:mine-hiring-signals [--source=] [--brand=] [--dry-run]` | Run one/all job-signal sources |
| `leads:monitor-mining [--hours=]` | Monitor external Hermes mining health |
| `leads:score [--brand=] [--segment=] [--limit=] [--dry-run]` | Recalculate general lead scores |
| `brands:seed-senders [--brand=]` | Seed per-brand sender pools |
| `mining:seed-targets [--append] [--brand=]` | Seed geographic and hiring-source targets |
| `sequence:seed-schedules` | Seed per-brand rabbit/deer timings |
| `seed:deer-sequence-config` | Seed generic and hiring-source UjuziPlus deer prompts |
| `telegram:poll-approvals` | Process Telegram text/callback approvals |
| `winloss:generate [--json]` | Generate and post win-loss report |

Use `php artisan list` and `php artisan help <command>` for authoritative current signatures.

### Scheduler source of truth

The following schedules are defined in `bootstrap/app.php`:

| Schedule | Work |
|---|---|
| Every 5 minutes | Telegram approval poll |
| Every 10 minutes | IMAP reply poll |
| Every 15 minutes | Send up to 20 approved emails |
| Every 30 minutes | Content pipeline check, Telegram approval notifications, cron-run cleanup |
| Every 2 hours | Lead-mining health monitor |
| Daily 02:30 | Prune failed queue jobs older than 14 days |
| Daily 03:00 | General lead scoring |
| Daily 05:00 | Sequence progression job; job itself skips weekends |
| Daily 07:00 | Activity daily brief |
| Monday 06:00 | Win-loss report |

Hiring Deer is staggered daily:

| Time (application timezone) | Source/work |
|---|---|
| 00:00 | BrighterMonday |
| 00:10 | Fuzu |
| 00:20 | MyJobMag |
| 00:30 | Corporate Staffing |
| 00:40 | Glassdoor |
| 00:50 | Known company careers pages |
| 01:20 | Google Jobs / `JobPosting` schema |
| 01:35 | LinkedIn |
| 02:00 | Deer enrichment batch |
| 02:10 | Consolidated hiring-signal digest |

Every scheduled entry uses the application timezone (default `UTC` unless `APP_TIMEZONE`/config is changed; `config/app.php` currently hard-codes `UTC`). Business timezone is separately configured as `Africa/Nairobi`.

### Agent registry

`agents:seed-roster` creates six codenamed agents:

- The Professor — orchestration/strategy.
- Tokyo — lead mining.
- Bogotá — enrichment.
- Nairobi — drafting.
- Rio — reply classification.
- Denver — analytics/learning.

Per-agent bearer tokens are generated once through the UI and only hashes are stored. Activity events can be attributed to agents. Attached documents are stored through the configured filesystem.

### Activity comments and instructions

Humans can comment on activity events. Comments may be marked as instructions and polled through `/api/v1/instructions`; agents acknowledge/update their status. `RespondToComment` and `CommentResponseService` support automated responses/actions.

---

## 9. Data and Integrations

### Data authority

- Linux PostgreSQL is the intended canonical operational database.
- Local development is sample-only after `migrate:fresh --seed`.
- Historical production counts in the old document (608, 756, etc.) were snapshots, not reliable current state, and are intentionally not presented as current.
- Use `docs/data-canonicalization.md` for the one-time Mac-to-Linux transfer and validation procedure.
- Legacy CSV imports expect private files under `storage/app/private/`; those files are not tracked in the current repository inventory.

### SMTP2GO

Implemented behavior:

- API-based sends, message state updates, sender rotation, MX checks, per-domain warming limits, randomized pacing, and sequence-day enforcement.
- Raw webhook persistence before processing.
- Open/click updates.
- Hard bounce, complaint, and unsubscribe suppression.
- Reply ingestion when the provider forwards reply events.

Required configuration:

```text
SMTP2GO_API_KEY
SMTP2GO_API_ENDPOINT
SMTP2GO_WEBHOOK_KEY
MAIL_*
```

Provider-side webhook and reply-forwarding settings are external state and must be verified in SMTP2GO.

### Telegram

Implemented behavior:

- Approval notifications and inline/text approval handling.
- Interested-reply and activity alerts.
- Webhook and polling paths.
- Laravel proxy routes used by Hermes where direct Telegram connectivity is unreliable.

Required configuration:

```text
TELEGRAM_BOT_TOKEN
TELEGRAM_CHAT_ID
TELEGRAM_WEBHOOK_SECRET
```

### IMAP

`inbox:poll` provides a fallback reply source. Required keys are `IMAP_HOST`, `IMAP_PORT`, `IMAP_USERNAME`, and `IMAP_PASSWORD`. The PHP IMAP extension/runtime availability is an environment prerequisite.

### Hermes and API authentication

- Legacy integrations use `OMNI_API_TOKEN`.
- Agent activity posting uses per-agent tokens through `AgentTokenAuth`, with legacy compatibility.
- Hermes itself, its skills, cron definitions, model routing, and most context-spine files are outside this repository.

### Hiring Deer pipeline

The pipeline mines Kenyan job-demand signals for UjuziPlus deer leads:

1. Mining targets whose categories begin `hiring_signal_` select sources.
2. Scrapers filter target job titles and excluded organizations.
3. Listings roll up by company within a source run.
4. Leads are created with `source=hiring_signal_{source}`, `segment=deer`, and signal details in `raw_data`.
5. A separate score prioritizes demand strength.
6. Enrichment follows after all source windows.
7. A daily activity digest summarizes new leads.
8. Source-conditioned `BrandSequenceConfig` prompts customize drafting for these leads.

LinkedIn requires both `LINKEDIN_ENABLED` and `config/mcporter.json`; those are not represented in `.env.example` and the config file is not present in the tracked inventory.

---

## 10. Development and Verification

### Prerequisites

- PHP 8.3+ and Composer.
- Node.js compatible with Vite 8 and npm.
- PostgreSQL for production-like development; SQLite exists as the Laravel starter fallback.
- Redis when using the repository’s normal cache/queue configuration.

### Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

Or run `composer setup`, then review the generated environment before treating it as production-like.

For normal development:

```bash
composer dev
```

That starts Laravel, a queue listener, log tailing, and Vite together. Alternatively run `php artisan serve`, `php artisan queue:work redis`, and `npm run dev` separately.

Local login after seeding:

```text
Email: dev-admin@example.test
Password: password
```

Never use this seeded account/password in production.

### Environment groups

`.env.example` currently covers:

- application, API token, logging;
- database, cache, session, filesystem, queue, Redis;
- SMTP/mail, Telegram, IMAP;
- Cloudflare Tunnel/Access;
- backups and optional GitHub backup;
- AWS/object storage and optional mail/Slack integrations;
- frontend application name;
- business timezone/send-hour values.

It does not currently list `LINKEDIN_ENABLED`, although `LinkedInJobsScraper` uses it.

### Test suite

Current tests are starter/auth/settings-heavy:

- seven Fortify auth feature files;
- profile and security settings tests;
- dashboard access;
- lead status transition behavior;
- starter feature/unit example tests.

There is no automated coverage for the core email send/webhook pipeline, Telegram flows, agents/instructions, sequence resolution, hiring scrapers, reply routing, or most authenticated CRUD pages.

Run:

```bash
composer test
npm run lint:check
npm run format:check
npm run types:check
```

For the full combined pipeline:

```bash
composer ci:check
```

---

## 11. Deployment and Operations

Deployment is manual:

```bash
cd /srv/omni_os
bash scripts/deploy.sh
```

The deploy script pulls Git, installs optimized Composer dependencies, installs/builds npm assets, runs migrations, caches Laravel state, upgrades Filament assets, ensures the storage link, and restarts queue workers.

### Queue

Supervisor template:

```text
deploy/supervisor/omni-os-queue-worker.conf
```

It runs two Redis workers under `www-data`. Copy it to `/etc/supervisor/conf.d/`, then reread/update Supervisor.

### Scheduler

Install one Linux cron entry:

```cron
* * * * * cd /srv/omni_os && php artisan schedule:run >> /dev/null 2>&1
```

Use `php artisan schedule:list` after each scheduler change.

### Backups

- Script: `scripts/backup-postgres.sh`.
- Cron template: `deploy/cron/omni-os.cron.example`.
- Supports retention through `BACKUP_RETENTION_DAYS` and optional off-host `rsync` via `BACKUP_REMOTE_TARGET`.
- Put `BACKUP_ROOT` on storage independent from the database disk.

### Cloudflare

Template: `deploy/cloudflare/cloudflared-config.example.yml`.

Expose only the Laravel web UI, protect it with Cloudflare Access, and never expose PostgreSQL. `bootstrap/app.php` trusts all proxies, so the deployment perimeter must prevent untrusted direct access to the app.

### Build artifacts

`vendor/`, `node_modules/`, and `public/build/` are rebuild-only and must not be copied between ARM Mac and x86 Linux.

---

## 12. Repository Structure

```text
omni_os/
├── app/
│   ├── Console/Commands/       # 23 command classes
│   ├── Contracts/              # scraper contract
│   ├── Enums/                  # lead/activity/email/comment vocabularies
│   ├── Events/ + Listeners/    # activity Telegram + cron tracking
│   ├── Filament/               # nine resource families + four widgets
│   ├── Http/
│   │   ├── Controllers/        # web, API, and settings controllers
│   │   ├── Middleware/         # API/agent auth + Inertia/appearance
│   │   └── Requests/           # settings/activity/reply validation
│   ├── Jobs/                   # sequence, replies, comments, eight scrapers
│   ├── Livewire/               # Filament brand switcher
│   ├── Models/                 # 16 application models + scopes/concerns
│   ├── Providers/
│   └── Services/
├── bootstrap/app.php           # routing, middleware, actual scheduler
├── config/                     # Laravel + services + jobs UI metadata
├── database/
│   ├── migrations/             # 28 migration files
│   ├── seeders/                # brands, sample data, root seeder
│   └── factories/
├── deploy/
│   ├── cloudflare/
│   ├── cron/
│   └── supervisor/
├── docs/data-canonicalization.md
├── icp/ujuziplus.md
├── resources/
│   ├── css/app.css
│   ├── js/
│   │   ├── components/         # app shell and reusable UI
│   │   ├── composables/
│   │   ├── layouts/
│   │   ├── lib/
│   │   ├── pages/              # auth, settings, and operational pages
│   │   └── types/
│   └── views/
├── routes/
│   ├── api.php
│   ├── console.php
│   ├── debug.php               # intentionally not registered
│   ├── settings.php
│   └── web.php
├── scripts/
│   ├── backup-postgres.sh
│   └── deploy.sh
├── tests/
├── .env.example
├── composer.json / composer.lock
├── package.json / package-lock.json
├── pnpm-workspace.yaml
├── vite.config.ts
├── tsconfig.json
├── eslint.config.js
├── phpstan.neon
├── phpunit.xml
├── pint.json
├── components.json
├── Omni-OS-Strategy-Brief.md
├── Omni-OS-Strategy-Brief (1).md
├── PROJECT.md
├── omni_logo.png / omni_logo_min.png
└── INV_2026_00002.pdf
```

### Unexpected root files

The working tree contains seven stray filenames apparently produced by malformed `tee`/Tinker shell commands:

```text
0,
=, now()->subDay())->count();
echo 'Sent since yesterday: ' . \$since . '\n';
\$total = DB::table('email_messages')->where('status', 'sent')->count();
echo 'Total sent all time: ' . \$total . '\n';
"

=, now()->subDay())->count();
echo '\n';
echo 'Total sent all time: ' . DB::table('email_messages')->where('status', 'sent')->count();
echo '\n';
"

=, now()->subDay())->count() . '\n';
echo 'Total sent all time: ' . DB::table('email_messages')->where('status', 'sent')->count() . '\n';
"
```

The multi-line text above represents three filenames plus `0,`, not source files. They are untracked/stray artifacts and should be deleted in a separate cleanup after confirming they contain no needed data. They are intentionally excluded from the structural tree.

`Omni-OS-Strategy-Brief (1).md`, the invoice PDF, and duplicate root logo files may also be intentional local artifacts; confirm before deleting or tracking them.

---

## 13. Current Status and Roadmap

### Implemented

- Four-brand model, active-brand context, and brand settings.
- PostgreSQL core schema with lead, suppression, and sequence invariants.
- Auth with registration, verification, password reset, 2FA, and passkeys.
- Vue/Inertia operator UI for dashboard, leads, email sequences/messages, inbox, activity, analytics/jobs, agents, brands, suppressions, mining, schedules, and prompts.
- Filament fallback/admin CRUD and webhook inspection.
- UjuziPlus CSV lead and sequence import paths.
- Lead enrichment state machine and API workflow.
- General lead scoring and analytics/win-loss reporting.
- SMTP2GO send path, pacing, sequence enforcement, raw webhook storage, and suppression handling.
- Telegram approval/notification/webhook/polling/proxy paths.
- IMAP reply polling, reply classification routing, inbox, and outbound responses.
- Sequence scheduling and source-conditioned drafting prompts.
- Agent roster, per-agent tokens, documents, activity attribution, comments, and instruction polling.
- Hiring Deer mining across eight source implementations, dedicated scoring, enrichment window, and daily digest.
- Manual Linux deployment, Supervisor queue workers, scheduler cron, backup tooling, and Cloudflare templates.

### Partially implemented or operationally dependent

- Hermes mining/enrichment/drafting/classification is external and must be deployed/configured separately.
- Scraper success depends on third-party HTML, anti-bot behavior, network access, and LinkedIn MCP configuration.
- SMTP2GO webhooks, tracking, and reply forwarding require provider-side configuration.
- Telegram reliability depends on bot/webhook/polling and network configuration.
- Job-monitoring definitions do not yet cover all actual scheduled work.
- The test suite does not substantively cover most business-critical workflows.

### Next engineering priorities

1. Add integration tests around suppression, approval, sequence timing, send idempotency, webhooks, reply routing, and source-conditioned config resolution.
2. Fix no-email hiring-lead deduplication using a normalized company/domain key rather than relying on `(brand_id, email)`.
3. Synchronize `config/schedule-jobs.php` with `bootstrap/app.php`, ideally from a single schedule definition.
4. Move LinkedIn environment access into config, add keys to `.env.example`, and document/provision `mcporter.json`.
5. Validate each hiring scraper with fixtures and explicit source-health telemetry.
6. Expand and version the per-brand context spine.
7. Verify production SMTP2GO, Telegram, IMAP, queue, scheduler, backup restore, and Cloudflare Access behavior.

### Strategic roadmap

- **UjuziPlus:** harden the existing rabbit/deer loop, prove reply-to-revenue learning, then add the pillar/atomize/distribute content loop.
- **Hudutech:** reuse the proven B2B pipeline, add Hudutech-specific prompts/context and local-intent channels.
- **Phantomflix / Phantom Tutors:** implement separate B2C, geography, consent, channel, and compliance playbooks.
- **Cross-brand intelligence:** later add CRM/contact graph and pgvector only after the operational loops produce useful learning data.

---

## 14. Known Issues and Pitfalls

### Confirmed code/documentation issues

- `config/schedule-jobs.php` is stale: it says Telegram runs every minute while the actual scheduler runs every five minutes, and it omits Hiring Deer jobs. The jobs UI therefore does not fully describe the scheduler.
- `LinkedInJobsScraper` calls `env('LINKEDIN_ENABLED')` outside config, which can fail under `config:cache`; `.env.example` lacks this key.
- The hiring mining command does not reliably deduplicate leads with `NULL` email. PostgreSQL’s email unique index permits multiple nulls.
- The scheduler’s displayed times use Laravel’s application timezone, currently hard-coded to UTC in `config/app.php`, while comments and operator expectations often say EAT. Set scheduler timezone explicitly before relying on those labels.
- `CommentResponseService` still says approved mail sends in the next “business-hours window,” while send behavior and prior documentation have changed over time. Verify the current intended policy and align copy/config/code.
- `routes/debug.php` exposes secrets if registered. Keep it unregistered or delete it.
- The malformed root files listed above remain cleanup debt.

### Operational cautions

- Running only `php artisan serve` without Vite development assets or a completed production build can produce an unstyled/nonfunctional page.
- Keep `REDIS_CLIENT=predis` unless the deployment deliberately installs/configures PhpRedis.
- Inertia links must not be used for Filament destinations.
- Filament v4 uses `Filament\Schemas\Schema`, schema components under `Filament\Schemas\Components`, and actions under `Filament\Actions`.
- Filter option arrays must remove null labels.
- The UjuziPlus deer CSV importer contains defensive truncation/nulling for historically misaligned columns.
- `trustProxies('*')` assumes the origin cannot be reached by untrusted clients.
- Never commit `.env`, Google tokens, SMTP/Telegram credentials, agent plaintext tokens, customer CSVs, database dumps, or private agent context.

### Compliance guardrails

- Apply suppression before every external send and provide a working opt-out.
- Keep human approval for external publishing/sending.
- Treat WhatsApp as opt-in only.
- Follow platform terms for job boards, LinkedIn, Reddit, and other mined/distribution channels.
- Phantom Tutors must remain legitimate tutoring/exam support, not assignment completion.
- Compliance differs across Kenya, US, and UK markets; obtain current legal advice before production expansion.

---

## 15. Recent Changelog

### 2026-07-02 — Documentation audit

- Reconciled this guide with the complete current repository inventory.
- Added agents, documents, comments/instructions, source-conditioned sequence configs, Hiring Deer commands/jobs/schedules, current Vue pages, current API routes, and deployment files.
- Replaced stale point-in-time production counts with explicit verification guidance.
- Corrected Filament resource, model, migration, route, and command inventories.
- Documented malformed root filenames and confirmed known code/config inconsistencies.

### 2026-07-01 — Hiring intelligence and source-conditioned drafting

- Added the Hiring Deer pipeline with eight source implementations.
- Added deterministic 0–150 hiring-signal scoring and a daily digest.
- Added UjuziPlus deer prompt rules for hiring-signal leads.
- Added `source_condition` to `brand_sequence_configs` and resolution fallback.
- Replaced old brand/segment-only sequence config uniqueness.
- Wired LinkedIn job search through mcporter/linkedin-mcp.

### 2026-06-25 — Agent registry and rebrand

- Added agents, agent documents, agent activity attribution, per-agent tokens, Filament resources, and Vue roster management.
- Added six core agent personas.
- Rebranded the landing page, sidebar/auth logo, and favicon assets.
- Added/fixed Telegram proxy paths and reliability work.

### 2026-06-22 — Operational pipeline expansion

- Added sequence schedules/progression, `needs_content`, source prompt configuration, and Hermes drafting endpoints.
- Added webhook persistence, reply records, inbox, outbound reply job, and IMAP polling.
- Added general lead scoring, analytics, win-loss reporting, cron monitoring, and activity comments/instructions.
- Added sender pools and per-brand JSON settings.
- Retired Google Sheets as the intended operational system of record.

### 2026-06-20–21 — Foundation and first full loop

- Created Laravel/Vue/Inertia/Filament/PostgreSQL foundation and core schema.
- Added sample-safe local seeding and canonicalization documentation.
- Imported legacy UjuziPlus leads/sequences and built approval UI.
- Added activity feed, enrichment, SMTP2GO send/tracking, Telegram approval, and reply classification.
- Added manual deployment, queue, scheduler, backup, and Cloudflare templates.

Git history is authoritative for commit-level details:

```bash
git log --oneline -30
```

---

## 16. Documentation Maintenance

When updating this file:

1. Read the implementation, migrations, routes, command signatures, scheduler, package manifests, `.env.example`, tests, and recent Git history.
2. Describe code-backed behavior as implemented; label external state and planned work explicitly.
3. Do not preserve stale production counts as current facts.
4. Update the date and changelog.
5. Never add secrets, private host details, customer PII, plaintext agent tokens, or credentials.
6. Keep `bootstrap/app.php` documented as scheduler truth and `config/schedule-jobs.php` as UI metadata until they are unified.
