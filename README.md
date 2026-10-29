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

`Campaign → reward definition/selection → WooCommerce cart/order marker → confirmed Core contribution → refund adjustments → net entitlement reconciliation → idempotent Core reward claim → derived public availability → synchronized fulfillment lifecycle`

Campaigns, rewards, contributions, financial adjustments, reward claims and reward fulfillment are Core domain concepts. WooCommerce owns checkout, orders, refunds and payment gateway interoperability. Order-item metadata transports explicit reward selection evidence, but entitlement, availability and fulfillment remain separate Core authorities.

As of **0.4.8**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- immutable original contribution amounts and refund-safe adjustments;
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
- explicit protection against automatically reversing a fulfillment already marked `fulfilled`;
- HPOS-safe WooCommerce order and refund adapters;
- authoritative net confirmed raised amount and campaign progress.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign, reward, claim or fulfillment domain logic.
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
- Forward fulfillment progress requires an active reward claim.
- Fulfillment stores no billing, shipping or customer PII in this lifecycle bridge.
- Payment gateways remain WooCommerce concerns; Crowdfunding WordPress does not store card data.
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
