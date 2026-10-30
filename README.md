# Crowdfunding WordPress

Open-source crowdfunding plugin for WordPress, built by Kairoseth.

## Product direction

Crowdfunding WordPress is a clean, generic product for creating and managing crowdfunding campaigns, promoters, contributions and rewards directly in WordPress.

The plugin is not tied to any client, vertical, grant programme, previous crowdfunding implementation or migration source. WooCommerce is used only as the commerce and payment interoperability layer; Crowdfunding WordPress owns its campaign, moderation, contribution, reward, entitlement, fulfillment and reporting domains.

## Commercial positioning

**Product name:** Crowdfunding WordPress  
**Manufacturer:** Kairoseth  
**Primary promise:** Create and manage crowdfunding campaigns, contributions and rewards directly in WordPress, using WooCommerce for checkout and payments.

## Core architecture

`Campaign → Core moderation → WordPress editorial publication → reward selection → WooCommerce checkout → Core contribution → refund adjustments → reward entitlement → fulfillment → dashboards and reporting`

### Authority boundaries

- WordPress `post_status` owns editorial publication and visibility.
- Core moderation owns review state and moderator decisions.
- WooCommerce owns checkout, orders, refunds and payment gateways.
- Core contribution records own crowdfunding accounting.
- Core reward claims own entitlement.
- Core fulfillment owns delivery workflow.
- Reporting and notifications consume committed Core facts; they do not become domain authorities.

## Main capabilities

- Campaign creation and management.
- Promoter registration, dashboard, submission and editing flows.
- Independent moderation states: `submitted`, `in_review`, `approved`, `rejected`.
- Immutable moderation decision history and idempotent operations.
- Publication eligibility gates separated from moderation state.
- WooCommerce-backed contributions with HPOS-safe adapters.
- Immutable contribution amounts with additive refund adjustments.
- Net confirmed funding progress.
- Generic rewards and optional reward selection during contribution checkout.
- Reward claims with active/reversed lifecycle.
- Finite reward availability derived from active claims.
- Fulfillment lifecycle, management, audit and reconciliation health.
- Contribution dashboard for authenticated supporters.
- Campaign statistics and bounded contribution trends.
- Promoter portfolio statistics with currency-safe aggregation.
- Notification events, recipient resolution, delivery intents and email delivery operations.
- Internationalization and accessibility hardening.

## Engineering principles

- One authority per responsibility.
- No client-specific business logic in Core.
- No migration or compatibility layer for external crowdfunding installations.
- Public APIs and persistent identifiers become compatibility contracts once released.
- Fail closed when authority or state is ambiguous.
- Financial corrections are additive; original contribution amounts stay immutable.
- Reward availability is derived, not stored as mutable inventory.
- Guest identities are never heuristically attached to authenticated users.
- Capability checks, nonces, sanitization, escaping and idempotency are mandatory.
- Features are extracted by responsibility rather than copied from another implementation.

## Internal upgrade compatibility

The plugin may include bounded reconciliation/backfill logic for **its own previously released data contracts** when necessary. These internal upgrade mechanisms are distinct from importing or migrating an external crowdfunding system and remain governed by explicit versioned contracts.

## Roadmap

- **0.1.x** Foundation and campaign domain — complete.
- **0.2.x** Promoters and frontend campaign workflow — complete.
- **0.3.x** WooCommerce contribution flow, refund integrity and authoritative funding progress — complete.
- **0.4.x** Rewards — complete.
- **0.5.x** Contribution dashboards and campaign statistics — complete.
- **0.6.x** Moderation and campaign workflow expansion — complete.
- **0.7.x** Notifications and reporting — complete.
- **0.8.x** Internationalization and accessibility hardening — complete.
- **0.9.x** Product hardening, packaging, installation UX and release acceptance.
- **1.0.0** Public commercial release.

## License

GPL-2.0-or-later.
