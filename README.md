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

`Campaign → reward definitions + contribution amount → WooCommerce cart/checkout → payment gateway → WooCommerce order → contribution adapter → immutable Core contribution → refund adjustments → net confirmed public progress`

Campaigns, rewards, contributions and financial adjustments are Core domain concepts. WooCommerce owns checkout, orders, refunds and payment gateway interoperability. Reward definitions are not WooCommerce products, and public funding progress reads the Core accounting model rather than WooCommerce totals.

As of **0.4.0**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- immutable original contribution amounts;
- separate generic adjustment ledger for refund accounting;
- applied/reversed adjustment states with exact source/reference idempotency;
- generic versioned rewards domain linked to campaigns;
- stable reward IDs, minimum contribution thresholds, optional claim limits, enabled state and ordering;
- generic reward create/update/find/list repository contracts;
- HPOS-safe WooCommerce order and refund adapters;
- classic checkout and Store API/Checkout Blocks synchronization;
- frontend contribution form and exact-amount cart line creation;
- line-attributed partial refund synchronization through WooCommerce CRUD objects;
- fail-closed handling of ambiguous partial refunds;
- refund deletion reversal without deleting crowdfunding audit history;
- hidden technical WooCommerce product isolated from ordinary catalogue products;
- strict campaign/store currency matching and minimum contribution validation;
- authoritative net confirmed raised amount and campaign progress on catalogue/detail surfaces;
- standalone `[crowdfunding_progress]` output with accessible progressbar semantics.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign or reward domain logic.
- Campaigns, promoters, contributions, adjustments, rewards, claims, moderation and reporting are separate responsibilities.
- Reward definitions are Core records, never WooCommerce products by definition.
- Reward campaign ownership is stable after creation.
- A reward `claim_limit` is a configured maximum; actual availability will be derived from claims rather than a mutable remaining-stock counter.
- Payment gateways remain WooCommerce concerns; Crowdfunding WordPress does not store card data.
- WooCommerce orders and refunds are accessed through CRUD APIs and HPOS-compatible boundaries.
- Contribution checkout never writes the Core ledger directly; order lifecycle synchronization is the authority bridge.
- Original contribution amounts are immutable; financial corrections are additive adjustment records.
- Partial refunds are attributed only when evidence identifies the original crowdfunding line; ambiguous partial refunds fail closed.
- Public raised/progress values are derived from confirmed Core contributions net of applied refund adjustments.
- Deleted refunds reverse their adjustments instead of erasing crowdfunding audit history.
- Frontend-first product experience for campaign owners and supporters.
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
