# Crowdfunding WordPress

Open-source crowdfunding plugin for WordPress, built by Kairoseth.

## Product direction

Crowdfunding WordPress is a generic product for creating and managing crowdfunding campaigns, promoters, contributions and rewards inside WordPress. The Core remains independent from any single client, vertical, brand, grant programme, payment gateway or reference implementation.

CulturalFunding is a reference implementation and a source of validated product requirements. It is **not** an architectural authority for this repository.

## Commercial positioning

**Product name:** Crowdfunding WordPress  
**Manufacturer:** Kairoseth  
**Primary promise:** Create and manage crowdfunding campaigns, contributions and rewards directly in WordPress, using WooCommerce as the commerce and payment layer.

## Current architecture

`Campaign → reward definition/selection → WooCommerce cart/order marker → confirmed Core contribution → refund adjustments → net entitlement reconciliation → idempotent Core reward claim → derived public availability → synchronized fulfillment lifecycle → operational fulfillment management → read-only fulfillment audit → account/campaign dashboards`

Campaigns, rewards, contributions, financial adjustments, reward claims and reward fulfillment are Core domain concepts. WooCommerce owns checkout, orders, refunds and payment gateway interoperability. Order-item metadata transports explicit reward selection evidence, but entitlement, availability, fulfillment, account dashboards and campaign statistics remain separate Core authorities.

As of **0.5.2**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- immutable original contribution amounts and refund-safe adjustments;
- authenticated current-user contribution dashboard derived from Core records;
- dashboard net amounts derived from applied refund adjustments, never order totals;
- dashboard reward entitlement derived from Core reward claims;
- authenticated campaign-owner aggregate statistics;
- campaign net funding, progress, contribution-state counts and reward-claim counts derived from Core authorities;
- bounded day/week/month campaign contribution trends over selectable date ranges;
- trend net value derived from current confirmed contributions minus current applied refunds and attributed to contribution creation periods;
- generic versioned rewards domain linked to campaigns;
- frontend reward management and public reward presentation;
- versioned reward-claims ledger with active/reversed lifecycle;
- optional reward selection in the contribution form;
- classic-cart and Store API / Checkout Blocks reward validation;
- reward eligibility based on contribution value net of applied refunds;
- safe claim reversal/reactivation after refunds;
- public finite reward availability derived from active claims;
- versioned reward-fulfillment state separate from entitlement;
- immutable fulfillment transition events with source/reference idempotency;
- automatic claim-lifecycle synchronization into fulfillment state;
- bounded, idempotent backfill for historical active claims missing fulfillment;
- authenticated campaign-scoped fulfillment management for promoters and administrators;
- read-only campaign-scoped fulfillment audit timelines with event origin and stable idempotency reference;
- explicit protection against manually cancelling/reactivating entitlement-owned fulfillment states;
- HPOS-safe WooCommerce order and refund adapters;
- authoritative net confirmed raised amount and campaign progress.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign, reward, claim, fulfillment or dashboard domain logic.
- Reward definitions are Core records, never WooCommerce products by definition.
- Reward selection is transport evidence, not a reservation or claim.
- Reward claims represent entitlement; fulfillment represents operational delivery state.
- A claim is created only after the Core contribution reaches `confirmed`.
- Reward claims are historical Core records; reversal changes state but never deletes history.
- Finite availability is derived from active claims and never stored as mutable inventory.
- Fulfillment does not own stock, payment state or reward eligibility.
- Each claim can have at most one fulfillment record.
- Fulfillment current state is stored for efficient reads, while every real transition is preserved in an immutable event ledger.
- New active claims create one `pending` fulfillment idempotently.
- Reversed claims cancel only `pending` or `processing` fulfillment work.
- Reactivated claims recover a cancelled fulfillment back to `pending` rather than creating a duplicate.
- Repeated active/reversed cycles use distinct idempotent fulfillment event references derived from event sequence.
- `fulfilled` is terminal and is never automatically changed when a claim is later reversed; the mismatch is surfaced for manual review.
- Historical active claims missing fulfillment are reconciled in bounded batches through the same `ensure_for_claim()` authority used by normal Core creation.
- Backfill reads claims but never mutates claim rows; completion is recorded only after no active claim remains without fulfillment.
- Fulfillment management has its own `manage_cfwp_fulfillments` capability and does not grant promoters permission to edit published campaigns.
- The management UI derives campaign authority from fulfillment → claim → reward and never trusts a browser-supplied campaign ID as permission.
- Manual fulfillment management can advance only `pending → processing/fulfilled` and `processing → fulfilled`; cancellation/reactivation remain claim-lifecycle responsibilities.
- Administrators may supervise other campaigns through their existing cross-campaign authority; promoters remain restricted to campaigns they own.
- Fulfillment audit is a separate read-only surface using the same campaign authority and immutable event ledger.
- Audit events are ordered chronologically and expose event ID, origin and stable source reference without introducing a second history store.
- The audit component registers no write action and has no direct repository-transition, WooCommerce or SQL-write authority.
- The contribution dashboard is authenticated and always scopes reads to `get_current_user_id()`; browser input cannot select another account.
- Dashboard financial values come from the contribution and adjustment Core ledgers, not WooCommerce orders or editable user metadata.
- Dashboard reward entitlement comes from Core reward claims; technical source references and customer PII are not exposed.
- Guest contributions with `user_id = 0` are not attached heuristically to an authenticated account.
- Campaign statistics are campaign-scoped aggregate reads; browser-selected campaign IDs are reauthorized against campaign ownership.
- Campaign statistics reuse the authoritative net-confirmed total and expose only aggregate contribution/claim counts, never supporter rows or PII.
- Campaign trend filters are selectors only; authorization is resolved before any aggregate trend query runs.
- Campaign trends are bounded to 366 days and use an allowlisted `day`, `week` or `month` bucket.
- Trend net values use current Core status/refund facts and are attributed to contribution `created_at`; they are not presented as a reconstructed historical balance because contribution state transitions are not yet versioned as an event ledger.
- `updated_at` is not treated as evidence of when a contribution entered a specific historical state.
- Fulfillment stores no billing, shipping, tracking or customer PII in this management/audit foundation.
- Payment gateways remain WooCommerce concerns; Crowdfunding WordPress does not store card data.
- Original contribution amounts are immutable; financial corrections are additive adjustment records.
- Escaping, sanitization, capability checks, nonces and idempotency are mandatory.
- Public APIs and persistent identifiers are compatibility contracts once released.
- Features are extracted by responsibility, not copied wholesale from the reference implementation.

## Roadmap

- **0.1.x** Foundation and campaign domain — complete.
- **0.2.x** Promoters and frontend campaign workflow — complete.
- **0.3.x** WooCommerce contribution flow, refund integrity and authoritative funding progress — complete.
- **0.4.x** Rewards — complete.
- **0.5.x** Contribution dashboards and campaign statistics — in progress.
- **0.6.x** Moderation and campaign workflow expansion.
- **0.7.x** Notifications and reporting.
- **0.8.x** Internationalization and accessibility hardening.
- **0.9.x** CulturalFunding compatibility/migration layer.
- **1.0.0** Public commercial release.

## License

GPL-2.0-or-later.
