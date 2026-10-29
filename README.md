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

`Campaign → reward definition/selection → WooCommerce cart/order marker → confirmed Core contribution → refund adjustments → net entitlement reconciliation → idempotent Core reward claim`

Campaigns, rewards, contributions, financial adjustments and reward claims are Core domain concepts. WooCommerce owns checkout, orders, refunds and payment gateway interoperability. Order-item metadata transports explicit reward selection evidence, but the authoritative claim and entitlement state remain Core records.

As of **0.4.5**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- immutable original contribution amounts and refund-safe adjustments;
- generic versioned rewards domain linked to campaigns;
- frontend reward management and public reward presentation;
- versioned reward-claims ledger with active/reversed lifecycle;
- optional reward selection in the contribution form;
- server-side reward/campaign/amount/availability revalidation before checkout;
- classic-cart and Store API / Checkout Blocks reward validation;
- explicit `_cfwp_reward_id` transport on the crowdfunding order item;
- a separate WooCommerce reward adapter that creates a Core claim only after contribution synchronization reports `confirmed`;
- reward eligibility based on the contribution amount net of applied refund adjustments;
- automatic reversal when a partial refund drops the net contribution below the reward minimum;
- safe reactivation after a refund adjustment is reversed, subject to confirmed status, current reward enablement and finite capacity;
- transactional reactivation under the same reward-row lock used for finite claim creation;
- claim reversal when the corresponding contribution becomes `cancelled` or fully `refunded`;
- HPOS-safe WooCommerce order and refund adapters;
- authoritative net confirmed raised amount and campaign progress on public surfaces.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign, reward or claim domain logic.
- Campaigns, promoters, contributions, adjustments, rewards, claims, moderation and reporting are separate responsibilities.
- Reward definitions are Core records, never WooCommerce products by definition.
- Reward selection in the cart is transport evidence, not a reservation and not a claim.
- Checkout validates current reward eligibility and finite capacity, but final finite-limit authority remains the transactional claim insert.
- A claim is created only after the Core contribution has reached `confirmed`.
- Order-item `_cfwp_reward_id` is evidence for the WooCommerce bridge; it is not the claim ledger itself.
- Reward claims are historical Core records; reversal changes state but never deletes claim history.
- Active reward eligibility uses the contribution amount net of applied refund adjustments, not the immutable gross contribution amount.
- A partial refund that takes the net contribution below the reward minimum reverses the active entitlement while preserving claim history.
- Reversing that refund may reactivate the same historical claim only after full eligibility is revalidated.
- Reactivation requires the contribution to be confirmed, the reward to be enabled, campaign/currency consistency, the net minimum to be met and finite capacity to remain available.
- Finite-limit claim creation and reactivation both serialize against the reward row before counting active claims.
- `source + source_reference` remains the external-event idempotency authority for claims.
- A reward `claim_limit` is configuration; remaining capacity is derived from active claims rather than stored as mutable inventory.
- If capacity changes between cart validation and payment confirmation, claim creation fails closed and emits a synchronization error; successful payment does not fabricate an entitlement.
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
