# UjuziPlus ICP Context (spine file)

## WHAT UJUZIPLUS IS
UjuziPlus is always a PLATFORM — never a training company.

We offer TWO things that can be sold separately or together:

1. THE PLATFORM (Proposition A)
   Infrastructure for delivering, tracking, and certifying training.
   Sold to: training firms, coaches, consultancies, SACCOs.
   Pitch: "Deliver, track, and scale the training you already do without adding admin."

2. THE PLATFORM + CONTENT BUNDLE (Proposition B)
   Platform infrastructure PLUS a certified partner trainer who delivers a specific programme on it.
   Sold to: corporates, SMEs, NGOs, schools, government agencies, manufacturers.
   Pitch: "A structured learning system for your team — and if you need content, we bring a certified trainer too."

The key distinction: UjuziPlus is always the platform. In the bundle, it is still the platform — just with a partner trainer running on top of it.

## WHO WE SELL TO (ICP WITH ROUTING)

### TYPE A — TRAINING PROVIDERS (Platform only — Proposition A)
Anyone whose business IS delivering training to others:
Corporate Training Firms, HR Consulting Firms, L&D Consultants,
Executive Coaching Firms, Business Coaching Firms,
SACCO Training Consultants, Digital Marketing Training Companies,
Sales Training Providers, Leadership Development Firms
- They SELL training
- They need the PLATFORM to deliver theirs better
- Do NOT offer to bring them a trainer or a course
- Do NOT say "upskill your team" or "professional development for your staff"

### TYPE B — TRAINING BUYERS (Platform + optionally the Bundle — Proposition B)
Organisations that BUY training for their people:
Corporates, SMEs, NGOs, SACCOs, Government Agencies,
Schools, Universities, Manufacturers, Financial Institutions
- They BUY training for their staff/members
- Pitch option 1 (Platform only): structured learning system with dashboards and certifications
- Pitch option 2 (Bundle): platform + certified trainer for a specific programme
- "Upskill your team" IS the RIGHT language for this segment

### SACCO SPECIAL CASE
segment=deer AND category contains SACCO:
- Pitch: member education portal for financial literacy compliance (SASRA records)
- Bundle angle: "We can also connect you with a certified financial literacy trainer"

## THE ONE-SENTENCE POSITION TEST
Before sending, identify which buyer type:

TYPE A — TRAINING PROVIDER (they sell training):
"We help [prospect] deliver more training to more clients without drowning in admin." ✓

TYPE B — TRAINING BUYER (they buy training for their people):
"We give [prospect] a structured learning system for their team — and if they need content, we can bring a certified trainer too." ✓

WRONG (regardless of prospect type):
"We deliver training programmes for your organisation." ✗

## THE ARMSTRONG LESSON (2026-06-24)
Armstrong Global is a training firm. They do not need us to bring them training. They need the platform to deliver theirs better.
The email we sent offered them training content (Proposition B to a Type A buyer). That was wrong.
The correct email would have offered them the platform (Proposition A).
Permanent rule: If the prospect IS a training provider → pitch only the Platform. Never the Bundle.

## PRACTICAL IMPACT ON EMAIL 3 OFFERS BY SEGMENT

| Segment | Email 3 Offer |
|---------|--------------|
| Rabbits (training firms) | "I build your platform inside UjuziPlus — your branding, your courses, your client dashboards." |
| Corporates/NGOs/SMEs | "I build your learning system inside UjuziPlus — and if you need a certified trainer for [specific topic], we can arrange that too." |
| SACCOs (deer) | "I build your member education portal — and if you need a financial literacy facilitator, we can connect you with one." |

## PRE-DRAFT ICP ROUTING CHECKLIST (for Hermes cron)

Before generating any email, run:
1. Read leads.category
2. Is it in TRAINING_PROVIDER list? → Type A (Platform only)
3. Is it in CORPORATE/EMPLOYER list? → Type B (Platform + Bundle)
4. SACCO special case? → member portal + optional facilitator
5. Run the One Test: "Does this organisation hire others to train their people?"
6. Confirm Email 3 offer matches the buyer type
7. If drafting Proposition B for a Type A buyer → STOP, rewrite from scratch
