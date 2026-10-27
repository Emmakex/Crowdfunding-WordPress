# Crowdfunding WordPress

Open-source crowdfunding plugin for WordPress, built by Kairoseth.

## Product direction

Crowdfunding WordPress is a generic product for creating and managing crowdfunding campaigns, promoters and contributions inside WordPress. The Core remains independent from any single client, vertical, brand, grant programme, payment gateway or reference implementation.

CulturalFunding is a reference implementation and a source of validated product requirements. It is **not** an architectural authority for this repository.

## Commercial positioning

**Product name:** Crowdfunding WordPress  
**Manufacturer:** Kairoseth  
**Primary promise:** Create and manage crowdfunding campaigns, contributions and rewards directly in WordPress, using WooCommerce as the commerce and payment layer.

## Current architecture

`Campaign → contribution amount → WooCommerce cart/checkout → payment gateway → WooCommerce order → contribution adapter → Core ledger`

The Core owns campaign and contribution meaning. WooCommerce owns checkout, orders and payment gateway interoperability.

As of **0.3.2**, the repository includes:

- generic campaign domain and public campaign views;
- promoter registration, submission, editing and dashboard flows;
- payment-provider-neutral contribution ledger with idempotency;
- HPOS-safe WooCommerce order adapter;
- classic checkout and Store API/Checkout Blocks synchronization;
- frontend contribution form and exact-amount cart line creation;
- hidden technical WooCommerce product isolated from ordinary catalogue products;
- strict campaign/store currency matching and minimum contribution validation.

## Architecture principles

- Generic WordPress Core; no CulturalFunding-specific business logic.
- WooCommerce is the commerce/payment integration layer, not the owner of campaign domain logic.
- Campaigns, promoters, contributions, rewards, moderation and reporting are separate responsibilities.
- Payment gateways remain WooCommerce concerns; Crowdfunding WordPress does not store card data.
- WooCommerce orders are accessed through CRUD APIs and declared HPOS-compatible boundaries.
- Contribution checkout never writes the Core ledger directly; order lifecycle synchronization is the authority bridge.
- Frontend-first product experience for campaign owners and supporters.
- Escaping, sanitization, capability checks, nonces and idempotency are mandatory.
- Public APIs and persistent identifiers are compatibility contracts once released.
- Features are extracted by responsibility, not copied wholesale from the reference implementation.

## Roadmap

- **0.1.x** Foundation and campaign domain — complete.
- **0.2.x** Promoters and frontend campaign workflow — complete.
- **0.3.x** WooCommerce contribution flow — in progress.
- **0.4.x** Rewards.
- **0.5.x** Contribution dashboards and campaign statistics.
- **0.6.x** Moderation and campaign workflow expansion.
- **0.7.x** Notifications and reporting.
- **0.8.x** Internationalization and accessibility hardening.
- **0.9.x** CulturalFunding compatibility/migration layer.
- **1.0.0** Public commercial release.

## License

GPL-2.0-or-later.
