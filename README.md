# Crowdfunding WordPress

Open-source crowdfunding plugin for WordPress, built by Kairoseth.

## Product direction

Crowdfunding WordPress is a clean, generic product for creating and managing crowdfunding campaigns, promoters, contributions and rewards directly in WordPress.

The plugin is not tied to any client, vertical, grant programme, previous crowdfunding implementation or migration source. WooCommerce is used only as the commerce and payment interoperability layer; Crowdfunding WordPress owns its campaign, moderation, contribution, reward, entitlement, fulfillment and reporting domains.

## Commercial positioning

**Product name:** Crowdfunding WordPress  
**Manufacturer:** Kairoseth  
**Current public release:** 1.0.0  
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
- Canonical notification event names with bounded failed-delivery retries and fail-closed processing states.
- Internationalization and accessibility hardening.

## Installation and first use

Activation installs the plugin's own versioned tables and capabilities, registers the campaign post type and refreshes rewrite rules. No external migration or client-specific setup runs during activation.

Administrators can open **Crowdfunding → Getting Started** or use the **Getting Started** link on the Plugins screen. This page is intentionally read-only: it shows installation status, WooCommerce availability, permalink and public-registration recommendations, direct links to campaign/notification operations, and the essential frontend shortcodes.

WooCommerce is optional when a site only needs to publish and manage campaigns. It is required when contributions will be collected through checkout.

## Distribution packaging

The repository defines a clean Git archive contract for distribution. Pull requests build and validate an installable `crowdfunding-wordpress.zip` that contains the plugin runtime, `readme.txt`, assets and uninstall handler while excluding repository CI, development documentation and compatibility/migration code.

The distribution gate also lints every packaged PHP file and rejects client/reference-implementation leakage before the ZIP can be considered releasable.

## Notification reliability

Notification delivery remains downstream from committed crowdfunding facts. Canonical event names such as `reward_claim.created` and `reward_fulfillment.status_changed` are preserved exactly when message keys are built.

Confirmed failed email deliveries have bounded retries. `processing` rows are exposed for administrator review and are not automatically reclaimed, because an interrupted external send can have an ambiguous outcome. A delivery is reported as successful only after the delivery ledger has also confirmed its `sent` transition.

## Data retention and uninstall

Uninstalling the plugin intentionally preserves crowdfunding operational data. Campaigns, contributions, rewards, fulfillment, moderation and notification records are not silently deleted. Any future destructive cleanup must remain an explicit administrator decision rather than an uninstall side effect.

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
- **0.9.x** Product hardening, clean packaging, installation UX and release acceptance — complete.
- **1.0.0** Public commercial release — complete.

## License

GPL-2.0-or-later.
