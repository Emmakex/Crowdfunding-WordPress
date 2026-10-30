=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding system for WordPress with campaigns, promoters, moderation, contributions, refund-safe accounting, rewards, fulfillment, statistics and WooCommerce-backed checkout.

The plugin keeps crowdfunding Core authority separate from WordPress editorial state, WooCommerce commerce state and notification transport. It is designed as a reusable commercial product rather than a client-specific implementation.

= Main features =

* Generic crowdfunding campaign post type with REST support.
* Funding goal, ISO currency and campaign deadline.
* Public campaign catalogue and campaign detail shortcodes.
* Frontend promoter registration, submission, editing and dashboard.
* Dedicated Crowdfunding Promoter role and campaign capabilities.
* Independent Core moderation with `submitted`, `in_review`, `approved` and `rejected` states.
* Transactional moderation decisions and immutable moderation event history.
* Publication eligibility gate without taking over WordPress editorial authority.
* Versioned contribution ledger with source/reference idempotency.
* Immutable contribution amounts plus refund-adjustment ledger.
* WooCommerce-backed contribution checkout and HPOS-safe order/refund integration.
* Generic rewards, reward claims, finite availability and refund-safe entitlement handling.
* Reward fulfillment lifecycle with immutable transition history.
* Campaign statistics, trends, fulfillment workload and reconciliation health.
* Promoter portfolio statistics grouped safely by currency.
* Authenticated contributor dashboard.
* Channel-neutral notification events, recipient resolution and delivery intents.
* Idempotent WordPress email delivery ledger.
* Notification delivery enable/disable control.
* Administrative delivery history with sent/failed filtering.
* Safe manual retry for failed email deliveries.
* Recipient email addresses are resolved only at delivery time and are not stored in the notification ledger.
* Translation-ready strings with the `crowdfunding-wordpress` text domain.
* Accessible status/error announcements, keyboard focus states, labelled controls and semantic administrative tables.
* Read-only Getting Started diagnostics for first-time setup.
* Clean installable ZIP packaging validated from the exact release head.
* Versioned database upgrades.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding → Getting Started** to review Core readiness, WooCommerce availability, permalink recommendations, public-registration status and the essential shortcodes.
4. Install and configure WooCommerce only if the site will collect contributions through checkout.
5. Create or edit a campaign and configure its funding goal, currency and deadline.
6. If WooCommerce checkout is used, ensure the campaign currency matches the WooCommerce store currency.
7. Publish `[crowdfunding_campaigns]` for the campaign catalogue.
8. Use `[crowdfunding_campaign id="123"]` to embed one published campaign.
9. Use `[crowdfunding_progress id="123"]`, `[crowdfunding_rewards id="123"]` and `[crowdfunding_contribute id="123"]` when those blocks are required independently.
10. Enable WordPress user registration when public promoter onboarding is required and publish `[crowdfunding_promoter_register]`.
11. Publish `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard]` for the promoter workflow.
12. Publish `[crowdfunding_manage_rewards]` for reward management.
13. Publish `[crowdfunding_manage_fulfillment]` and optionally `[crowdfunding_fulfillment_audit]` for fulfillment operations.
14. Publish `[crowdfunding_contribution_dashboard]` for account-linked contribution history.
15. Publish `[crowdfunding_campaign_statistics]`, `[crowdfunding_fulfillment_health]` and `[crowdfunding_promoter_portfolio]` as required.
16. Administrators can use **Crowdfunding → Moderation** for Core moderation decisions.
17. Administrators can use **Crowdfunding → Notifications** to enable/disable email delivery, inspect recent deliveries and retry failed email notifications.

== Moderation model ==

Core moderation is independent from WordPress `post_status`. Approval means that a moderated campaign is eligible for editorial publication; it never publishes a campaign automatically.

The current state graph is:

* no state → `submitted`
* `submitted` → `in_review`, `approved` or `rejected`
* `in_review` → `approved` or `rejected`
* `rejected` → `submitted`
* `approved` is terminal in the current model

Administrator decisions use the dedicated `moderate_cfwp_campaigns` capability and are stored transactionally with immutable decision events. Promoter resubmission remains a separate lifecycle operation and never impersonates a moderator decision.

== Contributions and refunds ==

Contributions store only Core crowdfunding facts: campaign, optional WordPress user, source/reference, amount, currency, status and timestamps. Customer/payment data remains with WooCommerce and the selected payment gateway.

`source + source_reference` is the contribution idempotency key. Original amounts are immutable. Refunds are represented by a separate adjustment ledger, and public funding progress is derived from confirmed contributions net of applied refunds.

== Campaign statistics ==

Version 0.5.2 adds bounded contribution trends grouped by day, week or month over a maximum range of 366 days. Trend values use current confirmed/refund-adjusted Core facts attributed to contribution creation periods; they are not presented as reconstructed historical balances for past dates.

Campaign and portfolio statistics remain aggregate and read-only. They never expose supporter rows, email addresses, billing/shipping data or WooCommerce customer records.

== Rewards and fulfillment ==

Rewards belong to campaigns and can have finite limits. Availability is derived from active reward claims rather than mutable stock counters.

Reward selection travels through the WooCommerce contribution flow as explicit order-item evidence. Refunds may reverse/reactivate entitlement through Core reward claims.

Fulfillment is a separate Core lifecycle with one fulfillment record per claim and immutable transition events. Promoters and administrators can manage campaign-scoped fulfillment without obtaining access to unrelated campaigns.

== Notifications ==

Version 0.7.0 introduced the notification and reporting foundation without making transport part of crowdfunding Core authority.

Committed Core events are normalized into the channel-neutral `cfwp_notification_event` contract. Recipient resolution produces stable WordPress-user references rather than email addresses or other delivery destinations. Delivery intents add stable `intent_key` and semantic `message_key` values for downstream idempotency.

The WordPress email adapter resolves the current WordPress account email only at send time. Email addresses are never persisted in the notification delivery ledger. Guest contributions with `user_id = 0` are not heuristically attached to an email address, billing record or WooCommerce customer.

The delivery ledger uses the unique pair `intent_key + channel` as its idempotency authority. It records recipient reference, semantic message key, status, attempt count, last error and timestamps. It is a delivery-attempt ledger only and never becomes a second crowdfunding domain-event authority.

Administrators can open **Crowdfunding → Notifications** to enable or disable WordPress email delivery, view recent sent/failed deliveries and explicitly retry failed email deliveries. Retry is nonce-protected, requires `manage_options`, reuses the original intent identity and resolves the destination again at retry time.

Notification failure is isolated from crowdfunding transactions. A mail failure cannot roll back a contribution, moderation decision, reward claim or fulfillment transition.

== Accessibility and internationalization ==

Version 0.8.0 hardens the user-facing and administrative accessibility contract without changing crowdfunding domain semantics.

Campaign submission feedback now exposes live status/error semantics for assistive technology, required fields and currency guidance are explicitly associated with their controls, and public interactive elements receive visible keyboard focus treatment. Funding progress preserves `progressbar` semantics with translated value text.

The notification administration surface uses semantic navigation for filters, `aria-current` for the active view, a caption and scoped headers for the delivery table, associated help text for notification settings, uniquely named retry actions, and status/alert roles for operation feedback.

User-facing strings continue to use the `crowdfunding-wordpress` text domain, which is loaded from the plugin `languages` directory for distributable installations.

== Product setup and distribution ==

Version 0.9.0 adds a read-only **Crowdfunding → Getting Started** screen and a matching Plugins-screen action link. The screen reports installation readiness without creating pages, changing WordPress options or modifying WooCommerce configuration.

WooCommerce is optional when a site only needs campaign publishing and management. It is required when contributions will be collected through the supported checkout flow.

Release packaging is generated from the exact certified Git head. The installable ZIP contains only runtime plugin files and excludes repository CI, development documentation and any external migration/compatibility layer.

== Data retention and uninstall ==

Uninstalling Crowdfunding WordPress intentionally preserves campaign, contribution, reward, fulfillment, moderation and notification data. Removing operational crowdfunding data must be an explicit site-administrator decision; the uninstall handler performs no destructive cleanup.

Internal version upgrades may run bounded, versioned reconciliation/backfill routines for Crowdfunding WordPress's own historical data contracts. The plugin does not import or migrate external crowdfunding installations.

== Privacy ==

Crowdfunding WordPress avoids duplicating commerce/customer data into Core crowdfunding records. Notification delivery records do not persist email addresses, names, billing details or shipping details.

Sites remain responsible for configuring their WordPress privacy policy, WooCommerce/payment-gateway privacy obligations and any external email infrastructure used by WordPress.

== Frequently Asked Questions ==

= Does the plugin require WooCommerce? =

No for campaign publishing and management. WooCommerce is required when the site will collect contributions through the supported checkout/payment flow. Crowdfunding Core records remain separated from WooCommerce order/customer authority.

= Does approving a campaign publish it automatically? =

No. Core moderation approval only makes the campaign eligible for publication. WordPress editorial permissions and actions remain separate.

= Are notification email addresses stored in the plugin delivery ledger? =

No. The email address is resolved from the current WordPress user only when an email is being delivered.

= Can failed notification emails be retried? =

Yes. Administrators can retry failed email deliveries from **Crowdfunding → Notifications**. Sent deliveries cannot be retried through that action, preserving idempotency.

= Can guest contributions receive email notifications? =

The Core notification resolver intentionally does not infer guest identity from billing, shipping or customer email data. Guest-channel support can be added later through an explicit commerce/privacy adapter rather than by leaking those details into Core events.

= What happens to crowdfunding data when the plugin is uninstalled? =

Crowdfunding WordPress preserves its operational data. Uninstall does not silently delete campaigns, contributions, rewards, fulfillment, moderation or notification records.

== Changelog ==

= 0.9.0 =
* Added a read-only Crowdfunding → Getting Started screen and Plugins-screen shortcut for first-use guidance.
* Added Core, WooCommerce, permalink and public-registration readiness diagnostics without storing setup state.
* Clarified that WooCommerce is optional for campaign publishing and required for checkout-based contributions.
* Added clean distribution packaging from the exact certified Git head.
* Added package validation that excludes CI/development files and rejects external migration/reference-implementation coupling.
* Added packaged-PHP linting and installable ZIP artifacts in CI.
* Made data-preserving uninstall behavior an explicit stable product policy.
* Added release-acceptance checks for public metadata, onboarding, packaging and non-destructive uninstall behavior.

= 0.8.0 =
* Added a regression contract for accessibility and internationalization across public and administrative surfaces.
* Added live-region status/error semantics to campaign submission feedback.
* Associated required-field and currency guidance with campaign submission controls.
* Added visible keyboard focus treatment to primary public crowdfunding controls.
* Hardened notification administration with semantic filter navigation, active-state semantics, table caption/scoped headers and accessible retry labels.
* Preserved accessible funding progress semantics and enforced consistent `crowdfunding-wordpress` text-domain usage.
* Added release-version consistency checks so plugin header, runtime constant, stable tag and changelog cannot drift.

= 0.7.0 =
* Added channel-neutral normalized notification events for committed moderation, contribution, reward-claim and fulfillment facts.
* Added stable recipient resolution using WordPress user references without recipient PII.
* Added channel-neutral delivery intents with stable intent and semantic message keys.
* Added WordPress email delivery with current-address resolution at send time.
* Added versioned idempotent notification delivery ledger with sent/failed states and retry attempts.
* Added administrative notification enable/disable control.
* Added administrative recent-delivery view and failed-email retry operation.
* Added fail-closed notification boundaries so transport failures never alter crowdfunding domain state.

= 0.6.6 =
* Closed moderation publication and destructive-deletion boundaries.
* Required valid Core approval before moderated campaigns can newly enter public/private publication states.
* Added explicit REST publication conflict handling and moderation-aware delete protection.

= 0.6.5 =
* Added moderation-aware campaign editing lock across frontend, REST/editor and stale-form paths.

= 0.6.4 =
* Added promoter rejection correction and explicit resubmission flow.

= 0.6.3 =
* Added native administrator moderation queue, decisions and immutable history UI.

= 0.6.2 =
* Added transactional administrator moderation decision repository and immutable decision ledger.

= 0.6.1 =
* Added bounded compatibility backfill for legacy campaign moderation state.

= 0.6.0 =
* Added independent Core campaign moderation workflow.

== Upgrade Notice ==

= 0.9.0 =
Adds first-use diagnostics, clean distribution packaging and an explicit data-preserving uninstall policy. No crowdfunding domain authority or schema changes are introduced by this release.

= 0.8.0 =
Adds accessibility and internationalization hardening without changing crowdfunding domain data or authority. Existing 0.7.x notification delivery data remains compatible.
