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

`Campaign → reward definitions → contribution → reward claim → WooCommerce integration adapters → immutable Core accounting / claim history`

Campaigns, rewards, contributions, financial adjustments and reward claims are Core domain concepts. WooCommerce owns checkout, orders, refunds and payment gateway interoperability. Reward definitions are not WooCommerce products, and claim history is not stored in order metadata as its authority.

As of **0.4.3**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- immutable original contribution amounts and refund-safe adjustments;
- generic versioned rewards domain linked to campaigns;
- frontend reward management and public reward presentation;
- versioned `cfwp_reward_claims` ledger with stable claim IDs;
- `source + source_reference` claim idempotency;
- active/reversed claim lifecycle without destructive deletion;
- reward/contribution campaign, currency, confirmed-status and minimum-amount validation;
- finite claim-limit enforcement under a reward-row transaction lock;
- derived active-claim counts and remaining capacity without mutable stock counters;
- HPOS-safe WooCommerce order and refund adapters;
- authoritative net confirmed raised amount and campaign progress on public surfaces.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign, reward or claim domain logic.
- Campaigns, promoters, contributions, adjustments, rewards, claims, moderation and reporting are separate responsibilities.
- Reward definitions are Core records, never WooCommerce products by definition.
- Reward claims are historical Core records; reversal changes state but never deletes claim history.
- Claim creation requires an enabled reward and a confirmed contribution from the same campaign, in the same campaign currency, meeting the reward minimum.
- `source + source_reference` is the external-event idempotency authority for claims.
- A reward `claim_limit` is configuration; remaining capacity is derived from active claims rather than stored as mutable inventory.
- Finite-limit claim creation serializes against the reward row before counting and inserting.
- 0.4.3 does not yet create claims from WooCommerce checkout and therefore public reward cards still avoid claimed/remaining availability messaging.
- Payment gateways remain WooCommerce concerns; Crowdfunding WordPress does not store card data.
- WooCommerce orders and refunds are accessed through CRUD APIs and HPOS-compatible boundaries.
- Original contribution amounts are immutable; financial corrections are additive adjustment records.
- Escaping, sanitization, capability checks, nonces and idempotency are mandatory.
- Public APIs and persistent identifiers are compatibility contracts once released.
- Features are extracted by responsibility, not copied wholesale from the reference implementation.

## Roadmap

- **0.1.x** Foundation and campaign domain — complete.
- **0.2.x** Promoters and frontend campaign workflow — complete.
- **0.3.x** WooCommerce contribution flow, refund integrity and authoritative funding progress — complete.
- **0.4.x** Rewards — in progress.
- **0.5.x** Contribution dashboards and campaign statistics.
- **0.6.x** Moderation and campaign workflow expansion.
- **0.7.x** Notifications and reporting.
- **0.8.x** Internationalization and accessibility hardening.
- **0.9.x** CulturalFunding compatibility/migration layer.
- **1.0.0** Public commercial release.

## License

GPL-2.0-or-later.
