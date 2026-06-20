# Omni OS — Multi-Brand Marketing Automation
### Strategy & System Design Brief (v1)

---

## 0. How to use this document

This is a context-and-strategy brief for a solo operator building one AI-driven marketing system ("Omni OS") across four businesses. It is written so any AI model or collaborator can read it cold and understand the operator, the goal, the technical architecture, the strategy, and the current priority.

If you are an AI reading this to help: treat it as the source of truth. The operator is technical (computer science background, builds with Laravel/Vue). Default to concrete, opinionated engineering and marketing guidance. Respect the constraints in Section 12 — especially that **marketing execution is the priority and the platform is a thin record/analytics layer, not a place to rebuild commodity infrastructure.**

---

## 1. Operator profile & working style

- **Location:** Nairobi, Kenya. Primary market is Kenya/East Africa; some brands target the US/UK.
- **Background:** Computer science; experienced with **Laravel** and **Vue.js**; vibe-codes working tools in hours-to-days.
- **Operating mode:** Solo, running four brands at once. Wants leverage and automation so one person operates like a team.
- **Philosophy:** Extremely ROI-driven. Strong preference for **open-source, self-hosted, local deployment, one-time costs over recurring subscriptions, and pay-as-you-grow**. Pays for SaaS/APIs only when the ROI is obvious or the workflow is revenue-generating.
- **Wants full control** of the core system (no tool ceilings) — but has agreed **not to rebuild commodity infrastructure** (auth, queues, schedulers, ORMs, BI engines, the database itself).

---

## 2. The brand portfolio

| Brand | What it sells | Customer | Primary market | Primary KPI → Secondary |
|---|---|---|---|---|
| **Hudutech Innovations Ltd** | Web & software development, Odoo ERP, CRM systems, digital transformation | SMEs, schools, NGOs, manufacturers, distributors, professional-service firms | Kenya | Qualified leads → sales revenue |
| **UjuziPlus** | White-label LMS (Kajabi/Teachable-style) + professional training, certification prep, corporate training, workforce development | Trainers/coaches, professionals, training institutions, corporates, NGOs, government agencies | Kenya/Africa | White-label LMS subscriptions + course enrollments → corporate training contracts |
| **Phantomflix** | Licensed reseller of streaming subscriptions / entertainment content (affordable bundled access, local payment e.g. M-Pesa) | Consumers seeking affordable premium entertainment | Kenya + diaspora | Paid subscribers → subscriber retention |
| **Phantom Tutors** | Academic tutoring, exam prep, personalized learning support | University/college students, parents, adult learners | US & UK | Student enrollments → retention & referrals |

**Brand voices:** Hudutech = trusted consultant / digital-transformation expert. UjuziPlus = professional, authoritative, career-growth focused. Phantomflix = fun, affordable, entertainment-focused. Phantom Tutors = friendly mentor / academic success partner.

---

## 3. Vision: what "Omni OS" is and what success looks like

**Omni OS** is one agent-driven system (built on Hermes Agent) that makes all four brands consistently visible to their audiences, produced and maintained by a single operator.

**Working definition of "omnipresence" (important — this replaces a common trap):**
> Each brand consistently shows up in the **2–3 places its specific audience actually lives**, **plus in Google and in AI answers (GEO)** — all produced by one Hermes OS so it's sustainable solo.

It is **not** "appear everywhere" and it is **not** spinning up many thin clone-sites per keyword (that approach fails: there is ~92% correlation between ranking in Google's top 10 and being cited in AI answers, so thin duplicate content does not hold up). Depth in the right channels beats spray-everywhere. The four audiences barely overlap, so "omnipresent" really means four small, sharp presences sharing one engine.

---

## 4. Current technical setup (as-is)

- **Host:** HP laptop — 16GB RAM, Core i5, Linux. Configured to never sleep, on a 24/7 internet connection.
- **Public access:** A **Cloudflare Tunnel** exposes laptop-hosted services to the web.
- **Agent framework:** **Hermes Agent** (Nous Research; open-source, MIT; self-improving; pillars = memory, skills, soul, crons, self-improvement).
- **Model:** **GLM 5.2 via Ollama Cloud** (cloud inference — no local LLM on the laptop, so RAM is free for the data/web stack). Chosen for cost-to-performance.
- **Existing tools:** SMTP2GO (email send), senderID (SMS), LinkedIn Helper (LinkedIn outreach), AdsPower (multi-account antidetect browser).
- **Existing pipeline (UjuziPlus lead gen):** mining (Google Maps/Search) → Google Sheets → cold-email drip. Segmented into **Rabbits** (235 leads: private training providers — fast/small deals) and **Deer** (392 leads: SACCOs + larger institutions — slow/big/higher-value deals). Includes a "safe send" routine (MX checks, randomized delays, business-hours only, bounce tracking), a **Telegram approval gate**, and 5-email relationship-based drips.
- **Known problems to fix:** Daily Lead Report erroring/truncating (Sheets scale); Deer email-enrichment stalled (open-ended job, no terminal state); Sheets won't scale to 100k+ rows; legacy nightly mining overlaps the new chunked mining (dedup churn); the loop never "closes" (no reply handling / learning).

**Budget bands:** AI models ~$10–50/mo; automation tools ~$0–30/mo; research agents mostly self-hosted; paid APIs only for revenue-generating workflows.

---

## 5. Target architecture

### 5.1 The agent brain — Hermes
- **One Hermes instance, one profile per brand** (distinct voice/memory/skills) plus a "control-tower" profile for orchestration. Hermes does the **fuzzy work**: mining, enrichment, drafting, classification, research.
- **Activity-dependent model routing** (cost control): GLM 5.2 for bulk drafting/mining; Qwen for research-heavy tasks; DeepSeek for coding; a frontier model only where ROI is obvious (e.g. final voice-polish on flagship content).
- **Human-in-the-loop gates** on anything that publishes/sends externally (the existing Telegram approval pattern, extended to every channel).

### 5.2 The platform — custom-built, deliberately thin
- **Stack:** Laravel (API + domain) + Vue/Inertia (bespoke UI) + **Filament** (instant admin/CRUD over Eloquent models). Custom-built because the operator wants full control and a long-term, extensible foundation — and has the skills + vibe-coding speed to do it cheaply.
- **Scope (locked):** the platform is for **record-keeping + analytics + a small set of hard data-integrity invariants** — NOT business logic. The marketing engine (Hermes + workflows) does the work; the platform records what happened, lets the operator see it, and enforces a few guardrails.
- **Don't rebuild commodity infra:** stand on Laravel's batteries (auth, queues, scheduler, Eloquent, migrations) and Filament for admin UI. Keep **Metabase** (or Filament dashboards) for analytics early — swappable, replace with Vue dashboards later if desired.
- **Laravel queues + scheduler replace the fragile laptop cron jobs** (retries, backoff, idempotency, resumability).

### 5.3 Data backbone — Postgres
- **Postgres is the single system of record** (replaces Google Sheets). Chosen over MySQL for analytical queries, JSONB (flexible mining payloads), and `pgvector` (future embeddings/memory graph).
- **Multi-brand + geo from day one:** `brand_id` on every table; segmentation by **column, not by tab/sheet**; **geography stored as data** (see Section 10).
- **The three hard invariants the DB must enforce** (integrity, not business logic):
  1. **Dedup** — unique constraint on `(brand, email)` so duplicates can't be created even when crons race.
  2. **Suppression** — a do-not-contact state the send path must check (unsubscribes / hard bounces can never be re-mailed). This is compliance; too important for case-by-case LLM judgment.
  3. **Idempotency** — a key per `(lead, sequence_step)` so "already sent" is a fact in the DB; kills double-sends on retry.

### 5.4 Security — Cloudflare Tunnel + Access
- The tunnel alone publishes services to the **entire internet**. Mandatory: put **Cloudflare Access (Zero Trust)** in front of every web hostname — deny-by-default, allow only the operator's own identity (e.g. Google login). Free tier covers this.
- **Never route Postgres through the tunnel.** The database stays local-only, reachable only by tools on the same laptop. Only the web UIs are exposed, each gated by Access.

### 5.5 How the pieces talk
- Hermes **calls the Laravel API (or runs artisan commands)** instead of writing to Sheets. Hermes = autonomous worker; Laravel/Postgres = durable state + invariants + scheduler/queues; SMTP2GO sends and reports opens/clicks; Telegram = human approval gate; Metabase/Filament = analytics dashboards.
- Hermes becomes **one client** of the platform API — later clients can include a public site, mobile app, webhooks, or a second agent, all hitting the same surface. No tool's roadmap constrains the operator.

### 5.6 Resource note (16GB laptop)
- No local LLM (GLM runs in Ollama Cloud), so RAM is for Postgres + web tools + Hermes harness + cloudflared. A Laravel app (PHP-FPM + Nginx + Postgres + Redis) is lighter than running several Java/Node tools. Run everything in Docker with memory limits. Put a UPS on the laptop — it now hosts the database, and an unclean shutdown mid-write is the main corruption risk.

---

## 6. The marketing engine (the priority)

### 6.1 The omnipresence loop (run per brand)
**Source → Pillar → Atomize → Distribute → Learn.**
Take one real audience question → produce **one** substantial pillar asset that answers it → atomize that single asset into channel-native pieces → distribute to that brand's channels → feed performance back into memory so the system learns. One insight becomes a blog post + LinkedIn post + Reddit comment + short video + email + WhatsApp message — not six separate creation efforts.

### 6.2 GEO / AEO (get found by AI engines, not just Google)
Pillar assets must be **citation-ready for AI search**:
- First ~200 words **directly and completely answer the query** (don't build up to it).
- Include an **FAQ** section and valid **schema markup** (FAQPage, Article, Service, LocalBusiness, HowTo, Product).
- Ensure pages are crawlable (server-side rendering; confirm robots.txt doesn't block AI crawlers).
- GEO is an **added layer on solid SEO**, not a replacement — strong traditional SEO is the prerequisite for AI citation.

---

## 7. Strategic frameworks folded in

### 7.1 The "hunting" framework (deal-size strategy)
Maps the portfolio onto the classic Janz animals — and explains the existing Rabbits/Deer split:

| Animal | Rough economics | Motion | Retention/growth reality |
|---|---|---|---|
| 🐭 Mice | ~$10/mo, huge volume | viral / referral / PLG | worst retention; churn is the whole game |
| 🐰 Rabbits | ~$100/mo | efficient inbound + outbound, funnel optimization | better, but still a churn battle; survivors expand |
| 🦌 Deer | ~$1k/mo | inside sales + automated outbound + partners | best retention + 3–5x faster growth |
| 🐘 Elephants | ~$8k+/mo | ABM, relationships, tenders/RFPs, procurement | highest retention; needs sales DNA + runway |

**Per-brand animal:**
- **Phantomflix → Mouse** (low price, high volume) — referral/virality is existential, expect churn, retention is the war.
- **Phantom Tutors → Rabbit.**
- **UjuziPlus → Rabbit (white-label LMS subs) feeding Deer (corporate/SACCO/government contracts).** Treat the self-serve LMS as the efficient top-of-funnel that you **land-and-expand into deer**, not as the end state.
- **Hudutech → Deer / Elephant** (custom software & ERP — sticky, high-value).

**Implications for the plan:**
- Track **retention** (cohort GRR/NRR, logo churn) per segment in the analytics layer — not just acquisition/lead counts. Acquisition metrics flatter a rabbit hunt; retention tells you if revenue is durable.
- **Sequencing is validated:** start rabbits → add deer → add elephants. Migrating upmarket one animal at a time is the data-backed successful path; ~70% of companies never change what they hunt, so order matters.
- **Elephants are a later, different motion** (named-account ABM, relationships, government tenders/RFPs, large NGO training budgets, SACCO umbrella bodies). Gate on the deer motion being repeatable first. Placeholder, not a build.
- **Caveat:** the underlying data is from SaaS companies and may not map cleanly to the Kenyan training/LMS/SACCO market. Treat the direction (higher-value = stickier, faster-growing) as reliable; treat specific percentages as illustrative.

### 7.2 The context spine + refresh discipline (makes output compound)
Upgrade each brand's memory from a single voice file to a **structured context spine** every skill reads from:
- Per brand: `icp/`, `competitors/`, `positioning/`, `messaging/`, `brand/` (tone of voice + visual identity).
- **Refresh discipline / win-loss loop:** reply outcomes feed a win-loss file → refreshes ICP and messaging on a cadence → next batch is sharper. **This IS the "Learn" edge of the loop.** (Refresh the ICP/messaging spine ~monthly; positioning ~quarterly.)
- **Anti-hallucination hooks** on mining/enrichment: tag every data point's confidence (verified / inferred / estimated / unavailable) and attach source + access date; write "not available" instead of inventing. Prevents Hermes fabricating a SACCO's email or firmographics.
- **Dated, superseding files** (`MMYY-topic.md`) so the agent knows what's current.
- **Git as shared brain** — version-control the context spine (extends the existing Hermes→GitHub backup).
- **What transfers vs. what doesn't:** the *principles* (context spine, refresh, hooks, compounding loop) transfer. The expensive SaaS stack from that playbook (Apollo, Clay, Gong, Smartlead, etc.) does **not** — use the cheaper/self-hosted equivalents already in place (own mining instead of Apollo, SMTP2GO instead of Smartlead). Don't adopt anyone's repo wholesale.

---

## 8. Per-brand playbooks (channels, motion, guardrails)

**Hudutech (B2B, Kenya, KPI: qualified leads).** Considered sale → authority content. Priority: LinkedIn (thought leadership + LinkedIn Helper for outreach), SEO/GEO on-site (own "Odoo ERP Kenya," "POS system M-Pesa," "software company Nairobi"), Google Business Profile (cheap, high local intent), email (replicate the UjuziPlus pipeline), selective value-first Reddit. Content = case studies, ROI breakdowns, how-tos.

**UjuziPlus (two motions — keep them distinct).** (a) Self-serve white-label LMS: content/SEO/GEO ("sell courses online Kenya," "best LMS for African trainers"), trainer/coach communities, LinkedIn, webinars (best authority + lead magnet). (b) Corporate/SACCO contracts: the existing Rabbits/Deer cold-email pipeline + LinkedIn + relationship selling, warmed by the authority content.

**Phantomflix (B2C entertainment, KPI: subscribers + retention).** Channel-sensitive: streaming-reseller promotion gets flagged/banned on most **paid** ad platforms and many subreddits even when licensed. So lead with **organic community + referral loop + opt-in WhatsApp/Telegram**, not paid ads. Emphasize the licensed/legal/affordable/local-payment angle (compliance shield + trust differentiator). Build a referral loop (existing subscribers invite friends) first — cheapest highest-ROI growth. Keep proof-of-licensing available for platform appeals.

**Phantom Tutors (B2C, US/UK students, KPI: enrollments).** Different market → different cron timezones and compliance (US CAN-SPAM, UK PECR). Channels: short-form (TikTok/IG study tips, exam hacks) as top-of-funnel, value-first Reddit subject-help subs, Discord student servers, campus ambassadors, opt-in WhatsApp study groups. **Positioning guardrail:** keep firmly on legitimate tutoring / exam prep / learning support — **not** assignment-completion / essay-mill work (illegal in the UK since 2022, bannable on the relevant platforms, and the legitimate framing is also better marketing to the actual buyers: parents and serious students).

---

## 9. Channel rules & compliance guardrails (cross-cutting)

- **Reddit (appears for all four brands):** follow the 90/10 rule (≥90% genuine value, ≤10% promotion). Use **one genuine account per brand** — AdsPower is for clean per-brand separation, **NOT** for running multiple accounts per brand. Multi-account promotion = sockpuppeting → site-wide bans, and a spam-flagged domain gets every link to it auto-removed across Reddit (near-irreversible, would torch all four brands). Be monitoring-led: contribute where a brand genuinely solves the asker's problem.
- **WhatsApp (all four):** opt-in only. Free Business app = 256-contact broadcast lists (small scale); the API requires Business Verification + privacy-policy URL, approved templates, opt-out, business-hours sending, and **does not allow general-purpose AI chatbots** (only task-oriented flows). Treat WhatsApp as a **retention/referral** channel for warm/opted-in audiences, not cold acquisition.
- **Email / SMS:** keep the existing safe-send discipline (MX checks, delays, business hours, bounce tracking). Every email needs a working opt-out, honored via suppression. Keep SPF/DKIM/DMARC clean; warm the domain; don't raise volume faster than reputation. Kenya DPA 2019 expects a legitimate-interest basis + easy opt-out for B2B outreach; the suppression machinery built now also covers the stricter markets (US/UK) later.
- **Human-review gate** on all external publishing/sending.

---

## 10. Current focus: UjuziPlus lead pipeline — harden + extend + geo-scale

**Scope right now:** Kenya only. After accumulating a good number of leads, expand to other countries/cities. So design for geo-expansion from the start.

**Design principle — geography is data, not code.**
A `mining_targets` config table holds rows of `(country, city, category, search_template, segment, cadence, active)`. Leads carry `country` and `city` columns. Mining/sending code reads this config and never hardcodes "Nairobi"/"Kenya." Expanding = insert config rows + localize templates; the pipeline itself doesn't change.

**Harden (fix what's fragile):**
- Sheets → Postgres (fixes the erroring report; enables real analytics).
- Cron → Laravel scheduler + queue workers (retries, backoff, idempotency, resumability).
- Make enrichment **per-lead and idempotent** with a hard `no_email_found` exit after N attempts, so one bad lead can't stall the batch (this is why Deer is stuck). Lead status = a real state machine: `new → enriching → enriched | no_email_found`.
- Retire the legacy nightly "100 leads across all categories" mining — one mining path, not two.
- Enforce the three DB invariants (dedup, suppression, idempotency).

**Extend (close the loop):**
- **Reply detection + classification** (Hermes): interested / not interested / out-of-office / unsubscribe / bounce. Highest-value missing piece — turns a blast into a pipeline.
- **Outcome routing:** interested → flag to Telegram for the human; unsubscribe → write suppression immediately; not-interested → close.
- **Scoring:** simple per-lead score (segment + category + city + engagement) so the next batch prioritizes likely converters.
- **Learning (win-loss):** feed outcomes back so the system biases future mining + drafting toward the categories/cities/templates that actually produce replies. Surfaces in the analytics dashboard.

**Resulting division of labor:** Hermes (mine, enrich, draft, classify replies) → Laravel/Postgres (records, invariants, scheduler/queues) → SMTP2GO (send + open/click) → Telegram (human gate) → Metabase/Filament (category/city/template performance + retention).

**Starter data model (Laravel migrations):** `brands`, `leads` (with `brand_id`, `segment`, `country`, `city`, `status`, `score`, `email`, `email_verified`, `raw_data JSONB`), `lead_events` (event log for analytics), `mining_targets` (geo config), `email_messages` (with idempotency key), `suppressions`. Indexes on `brand_id`, `status`, `email`, `source`, `created_at`.

---

## 11. Phased roadmap

- **Phase 0 — Foundation:** Postgres + thin Laravel/Filament platform + queues/scheduler behind Cloudflare-Access-gated tunnel; move UjuziPlus off Sheets; stand up per-brand profiles + context spine; set model routing.
- **Phase 1 — UjuziPlus full loop (current):** harden + extend + geo-scale the Rabbits/Deer lead pipeline; then layer the omnipresence content loop on top.
- **Phase 2 — Hudutech:** most similar B2B motion; reuse the pipeline + LinkedIn + add Google Business Profile.
- **Phase 3 — B2C brands (Phantomflix, Phantom Tutors):** community / referral / organic playbook; more platform-sensitive, so after the B2B loop is reliable.
- **Phase 4 — Cross-brand intelligence:** contacts/CRM memory graph (pgvector) for outreach + shared learnings; begin the **elephant** motion once deer is repeatable.

---

## 12. Operating principles & constraints (read these before proposing anything)

1. **Marketing execution is the priority.** The platform is a thin record + analytics + integrity layer, not the project.
2. **Don't rebuild commodity infrastructure.** Use Postgres, Laravel's batteries, Filament, Metabase. Build only what is genuinely the operator's IP (data model, workflows, brand context, orchestration).
3. **ROI-driven + open-source/self-hosted bias.** One-time costs over recurring; paid APIs only for revenue-generating workflows; budget bands in Section 4.
4. **Human-in-the-loop** on all external publishing/sending.
5. **Durability over ban-and-burn.** Stay within platform ToS (Reddit, WhatsApp, ad networks) — fewer genuine accounts beat account-armies; a torched domain is near-irreversible.
6. **Don't let the architecture become a sidequest.** Timebox the foundation; let the four brands' revenue pull features. Compounding comes from a strong context foundation, not from more agents.
7. **Geography and brand are data, not code** — so expansion is configuration, not a rewrite.
8. **Legitimacy guardrails are non-negotiable:** Phantomflix marketed as a licensed reseller (organic/referral, keep licensing proof); Phantom Tutors marketed as genuine tutoring/learning, never assignment-completion.

---

## 13. Tooling map

- **Brain:** Hermes Agent (per-brand profiles) on GLM 5.2 / Ollama Cloud, with model routing (Qwen research, DeepSeek code).
- **Platform:** Laravel + Vue/Inertia + Filament; Postgres (system of record, pgvector future); Redis (queues); Metabase (analytics, swappable).
- **Owned channel tools:** SMTP2GO (email), senderID (SMS), LinkedIn Helper (LinkedIn), AdsPower (clean per-brand account separation only).
- **Access/security:** Cloudflare Tunnel + Cloudflare Access (Zero Trust).
- **Free-tier additions as needed:** Composio (connect Gmail/Calendar/socials to Hermes), n8n (self-hosted workflow glue).
- **Backup:** GitHub (Hermes config + context spine).

---

## 14. Glossary

- **Hermes Agent** — open-source, self-improving personal AI agent (Nous Research). The "brain" doing autonomous work. Alternative in the space: OpenClaw (heavier, orchestration/marketplace-focused).
- **Soul / skills / crons / memory** — Hermes concepts: personality file, reusable task workflows, scheduled tasks, persistent context.
- **Rabbits / Deer / Mice / Elephants** — customer segments by deal size/motion (Section 7.1). Here, Rabbits = fast/small training-provider deals; Deer = slow/large SACCO + institutional deals.
- **GEO / AEO** — Generative/Answer Engine Optimization: structuring content to be cited by AI search (ChatGPT, Perplexity, Google AI Overviews, Claude).
- **ICP** — Ideal Customer Profile.
- **GRR / NRR** — Gross / Net Revenue Retention (retention metrics).
- **Context spine** — the per-brand foundational `.md` files (ICP, competitors, positioning, messaging, brand) every skill reads from.
- **The three invariants** — DB-enforced dedup, suppression, and idempotency (Section 5.3).
- **Omnipresence loop** — Source → Pillar → Atomize → Distribute → Learn.

---

*End of brief (v1). Living document — revise as the system, brands, and markets evolve.*
