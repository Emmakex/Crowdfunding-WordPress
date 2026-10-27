# Crowdfunding WordPress

Open-source crowdfunding plugin for WordPress, built by Kairoseth.

## Product direction

Crowdfunding WordPress is a generic product for creating and managing crowdfunding campaigns inside WordPress. The core must remain independent from any single client, vertical, brand, grant programme, or reference implementation.

CulturalFunding is a reference implementation and a source of validated product requirements. It is **not** an architectural authority for this repository.

## Commercial positioning

**Product name:** Crowdfunding WordPress  
**Manufacturer:** Kairoseth  
**Primary promise:** Create and manage crowdfunding campaigns, contributions and rewards directly in WordPress, with WooCommerce as the commerce and payment layer.

## Architecture principles

- Generic WordPress core; no CulturalFunding-specific business logic.
- WooCommerce is the future commerce/payment integration layer, not the owner of campaign domain logic.
- Campaigns, promoters, contributions, rewards, moderation and reporting are separate responsibilities.
- Frontend-first product experience for campaign owners and supporters.
- WordPress coding standards, escaping, sanitization, capability checks and nonces are mandatory.
- Public APIs and persistent identifiers should be treated as compatibility contracts once released.
- Features are extracted by responsibility, not copied wholesale from the reference implementation.

## Roadmap

- **0.1.x** Foundation and campaign domain.
- **0.2.x** Promoters and frontend campaign submission.
- **0.3.x** WooCommerce contribution flow.
- **0.4.x** Rewards.
- **0.5.x** Dashboards and campaign statistics.
- **0.6.x** Moderation and campaign workflow.
- **0.7.x** Notifications and reporting.
- **0.8.x** Internationalization and accessibility hardening.
- **0.9.x** CulturalFunding compatibility/migration layer.
- **1.0.0** Public commercial release.

## License

GPL-2.0-or-later.
