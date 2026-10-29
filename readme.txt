=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.4.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, contributions, refund-safe accounting, rewards, reward claims, fulfillment lifecycle, WooCommerce checkout integration and public funding progress.

The Core remains independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type with REST support.
* Campaign funding goal, ISO currency and deadline metadata.
* Public campaign catalogue and campaign detail shortcodes.
* Authoritative net raised amount derived from confirmed contributions minus applied refund adjustments.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Immutable contribution amounts plus a separate versioned refund-adjustment ledger.
* Generic versioned rewards domain linked to campaigns.
* Authenticated frontend reward management and public reward cards.
* Versioned reward-claims ledger with active/reversed states and source/reference idempotency.
* Finite reward limits and public availability derived from active claims instead of mutable stock counters.
* Optional reward selection in the WooCommerce-backed contribution form.
* Classic-cart and Store API / Checkout Blocks reward validation.
* Reward eligibility derived from the contribution amount net of applied refunds.
* Safe reward-claim reversal/reactivation after refunds.
* Versioned reward-fulfillment state, one record per claim.
* Immutable reward-fulfillment transition events with source/reference idempotency.
* Automatic claim-lifecycle synchronization into fulfillment state.
* Bounded, idempotent backfill for historical active claims that predate fulfillment synchronization.
* Authenticated campaign-scoped fulfillment management for promoters and administrators.
* HPOS-safe WooCommerce order/refund integration through CRUD objects.
* Safe activation, versioned upgrades and translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Install and configure WooCommerce if the site will collect contributions.
4. Open **Crowdfunding** in the WordPress admin menu.
5. Create or edit a campaign and fill in its funding goal, currency and deadline.
6. Ensure each campaign currency matches the WooCommerce store currency.
7. Publish a page with `[crowdfunding_campaigns]` for the campaign catalogue.
8. Use `[crowdfunding_campaign id="123"]` to embed one published campaign.
9. Native campaign permalinks automatically show progress, enabled rewards and the contribution form.
10. Use `[crowdfunding_progress id="123"]`, `[crowdfunding_rewards id="123"]` or `[crowdfunding_contribute id="123"]` when those blocks are needed independently.
11. Enable WordPress user registration if public promoter onboarding is required, then publish `[crowdfunding_promoter_register]`.
12. Publish `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/" rewards_url="/manage-rewards/" fulfillment_url="/manage-fulfillment/"]` for the promoter workflow.
13. Publish `[crowdfunding_manage_rewards]` on a promoter-only page for reward management.
14. Publish `[crowdfunding_manage_fulfillment]` on an authenticated page for campaign-scoped delivery operations.

== Contributions and adjustments ==

Each contribution stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Original contribution amounts are immutable. Refunds are separate `cfwp_contribution_adjustments` records with their own idempotency key and `applied` / `reversed` lifecycle.

An applied refund can never exceed the contribution amount still available to refund. `net_amount_for_contribution()` derives the current contribution value as original amount minus applied refund adjustments. Public progress uses confirmed contributions net of applied refunds.

== Rewards ==

Rewards are generic Core records, not WooCommerce products. They belong to exactly one crowdfunding campaign and have a stable ID, title, description, minimum contribution amount, optional claim limit, enabled state and explicit sort order.

A reward inherits the campaign currency. `claim_limit = 0` means unlimited. The configured limit is definition authority; actual usage and remaining finite capacity are derived from active reward claims, not a mutable stock field.

`[crowdfunding_manage_rewards]` allows authenticated promoters to create, edit, sort and activate/deactivate rewards only on their own `draft` or `pending` campaigns. `[crowdfunding_rewards]` renders enabled rewards for published campaigns and shows the derived remaining count for finite rewards.

== Reward claims ==

The versioned `cfwp_reward_claims` ledger links a stable reward ID to a stable contribution ID. Claims use `active` and `reversed` states and have their own `source + source_reference` idempotency authority.

A new claim requires an enabled reward and a `confirmed` contribution from the same campaign, in the campaign currency, whose current net amount meets the reward minimum. Finite limits are enforced transactionally. `remaining_for_reward()` returns `null` for unlimited rewards and otherwise derives `max(0, claim_limit - active claims)`.

Claim reversal preserves history. Controlled reactivation revalidates confirmed status, campaign, currency, enabled state, net minimum and finite capacity before returning the same historical claim to `active`.

== Reward availability ==

Finite reward availability is visible without introducing a second stock authority. Public reward cards and the contribution selector both call `Reward_Claim_Repository::remaining_for_reward()`.

Finite rewards show their current derived remaining count. Zero means unavailable and disables selection in the contribution form. Unlimited rewards return `null`, remain selectable and do not show a numeric availability count.

== Reward fulfillment ==

Reward claims and fulfillment remain separate authorities. A claim answers whether the supporter currently has the entitlement; a fulfillment record answers the operational delivery state.

Each claim can have at most one row in `cfwp_reward_fulfillments`. The current state is one of `pending`, `processing`, `fulfilled` or `cancelled`. Every real creation/transition is recorded in the immutable `cfwp_reward_fulfillment_events` ledger with `source + source_reference` idempotency.

A newly created active claim idempotently gets one `pending` fulfillment. Reversing a claim automatically moves `pending` or `processing` fulfillment to `cancelled`. Reactivating that claim returns the same cancelled fulfillment to `pending`; it never creates a second delivery record.

Repeated reversal/reactivation cycles use a fresh event-sequence-based source reference so an old transition cannot masquerade as a later successful transition. `fulfilled` remains terminal: if entitlement is reversed after delivery, the plugin does not automatically undo delivery and instead emits `cfwp_reward_fulfillment_sync_error` for manual review.

Version 0.4.9 adds a bounded historical reconciliation pass for active claims that were created before the fulfillment lifecycle bridge existed. The reconciler selects only active claims with no fulfillment, processes them through the same idempotent `ensure_for_claim()` authority, and records completion only when no missing active claim remains. Failed rows are left untouched for retry and emit `cfwp_reward_fulfillment_backfill_error`.

Backfill runs after the fulfillment schema installer, processes at most 50 claims per request by default (filterable and hard-capped at 200), never mutates claim rows and has no WooCommerce dependency.

Version 0.4.10 adds a dedicated `manage_cfwp_fulfillments` capability and `[crowdfunding_manage_fulfillment]`. Promoters may operate fulfillment only for campaigns they own; administrators can supervise other campaigns through their existing cross-campaign authority. This capability does not grant promoters permission to edit published campaign content.

The management UI derives campaign ownership from fulfillment → claim → reward on the server. A browser-supplied fulfillment ID is only a selector. Manual operations are deliberately restricted to `pending → processing`, `pending → fulfilled` and `processing → fulfilled`. `cancelled` remains controlled by entitlement lifecycle and `fulfilled` remains terminal.

Fulfillment management calls `Reward_Fulfillment_Repository::transition()` and therefore preserves immutable transition events. The UI and its read-only query layer do not integrate with WooCommerce and store no shipping, billing, tracking or customer PII.

== Reward selection and WooCommerce bridge ==

`[crowdfunding_contribute]` can include an optional reward selection. Only enabled rewards for the current campaign are offered. Finite rewards show derived availability and zero-capacity rewards are disabled.

Reward selection is not a reservation. The server revalidates reward ownership, enabled state, minimum contribution and current finite capacity before checkout and during WooCommerce cart validation.

The selected reward is transported on the crowdfunding cart/order line as `_cfwp_reward_id`. That order-item marker is evidence, not claim or fulfillment authority.

The separate `WooCommerce_Reward_Adapter` creates a reward claim only after the contribution status is `confirmed`. The generic fulfillment components listen to or inspect Core claim state only; they do not read WooCommerce orders.

== Funding progress ==

Public progress uses `Contribution_Adjustment_Repository::net_confirmed_total_for_campaign()`. It never reads a manually editable raised amount or WooCommerce order total as authority.

The textual percentage can exceed 100% when a campaign overfunds. The visual progress bar is capped at 100% while preserving the real percentage in text and accessible progress semantics.

== WooCommerce refunds ==

WooCommerce refund lifecycle events are consumed through CRUD objects. Line-attributed partial refunds follow `_refunded_item_id` back to the original crowdfunding line. Ambiguous partial refunds fail closed rather than being distributed heuristically. Deleted WooCommerce refunds reverse their crowdfunding adjustments instead of deleting audit history.

Adjustment creation and reversal events feed reward entitlement reconciliation. WooCommerce refund objects do not directly edit claim or fulfillment rows.

== WooCommerce checkout ==

A published campaign can render `[crowdfunding_contribute]`. The server validates nonce, campaign status, deadline, minimum amount, campaign/store currency and optional reward selection, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product as a transport line only. Contribution lines are quantity-locked, tax-free and excluded from coupons. Checkout does not write contribution, reward-claim or fulfillment ledgers directly.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns and manage fulfillment for rewards belonging to campaigns it owns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

Administrators retain full authority over review, publication and cross-campaign fulfillment supervision.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign, including its enabled reward definitions. Optional `id` selects the campaign.

= [crowdfunding_progress] =
Displays the authoritative net confirmed raised amount, goal, percentage and progress bar. Optional `id` and `compact="yes"`.

= [crowdfunding_rewards] =
Displays enabled reward definitions for one published campaign, including derived remaining availability for finite rewards. Optional `id` selects the campaign.

= [crowdfunding_contribute] =
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign. Enabled rewards can be selected optionally and are revalidated server-side.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_manage_rewards] =
Displays authenticated reward management for one owned draft/pending campaign.

= [crowdfunding_manage_fulfillment] =
Displays authenticated fulfillment operations for one authorized campaign. Promoters are restricted to campaigns they own; administrators may supervise other campaigns. The UI can advance only pending/processing delivery state and cannot manually cancel or reactivate entitlement-owned state.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page`, `edit_url`, `rewards_url` and `fulfillment_url` attributes.

== Frequently Asked Questions ==

= Are rewards WooCommerce products? =

No. Reward definitions, claims and fulfillment belong to the generic crowdfunding Core. WooCommerce transports payment/order lifecycle evidence.

= Is a reward claim the same as fulfillment? =

No. A claim is entitlement. Fulfillment is the independent operational delivery lifecycle for that entitlement.

= Can a promoter cancel or reactivate fulfillment manually? =

No. `cancelled` and the return from `cancelled` to `pending` are controlled by the reward-claim lifecycle. The operational UI can only move active entitlement forward toward delivery.

= Can fulfillment management edit a published campaign? =

No. `manage_cfwp_fulfillments` is a separate capability. Promoters still cannot edit published campaign content.

= What happens when entitlement is reversed? =

A `pending` or `processing` fulfillment is automatically cancelled. If delivery was already marked `fulfilled`, the plugin never undoes delivery automatically and emits a synchronization error for manual review.

= What happens to active claims created before fulfillment existed? =

Version 0.4.9 reconciles them in bounded batches. Only active claims without a fulfillment are selected, and each passes through the normal idempotent fulfillment repository. The backfill is marked complete only after none remain.

= Where does the public availability number come from? =

For finite rewards it is derived at render time as `claim_limit - active claims` through `Reward_Claim_Repository::remaining_for_reward()`. Fulfillment state does not affect reward availability.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund/reward bridges use WooCommerce CRUD objects instead of direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.4.10 =
* Added dedicated `manage_cfwp_fulfillments` capability for promoters and administrators.
* Added `[crowdfunding_manage_fulfillment]` campaign-scoped operational delivery management.
* Added read-only fulfillment → claim → reward campaign authority queries.
* Promoters remain restricted to campaigns they own; administrators can supervise other campaigns.
* Manual operations are restricted to pending/processing forward transitions; cancelled remains entitlement-owned and fulfilled remains terminal.
* Added optional `fulfillment_url` to the promoter dashboard for published campaigns.
* Kept fulfillment management independent from WooCommerce and free of shipping/billing/tracking/customer PII.

= 0.4.9 =
* Added bounded, idempotent backfill for historical active reward claims missing fulfillment.
* Backfill runs after fulfillment schema installation and uses the normal `ensure_for_claim()` authority.
* Missing claims are processed in configurable batches with a hard maximum of 200 per request.
* Failed claims remain untouched for retry and emit a dedicated backfill error hook.
* Backfill completion is recorded only after no active claim remains without a fulfillment.
* Kept reconciliation independent from WooCommerce, claim mutation, supporter PII and destructive history changes.

= 0.4.8 =
* Added automatic Core claim-to-fulfillment lifecycle synchronization.
* Active claim creation idempotently creates one pending fulfillment.
* Claim reversal cancels pending/processing fulfillment while preserving immutable transition history.
* Claim reactivation restores cancelled fulfillment to pending without creating a duplicate row.
* Added event-sequence-based transition references so repeated reversal/reactivation cycles stay idempotent.
* Kept fulfilled delivery terminal and surfaced post-delivery entitlement reversal for manual review instead of automatically undoing delivery.
* Kept the lifecycle bridge independent from WooCommerce, availability and supporter PII.

= 0.4.7 =
* Added versioned `cfwp_reward_fulfillments` Core state, one fulfillment per reward claim.
* Added `pending`, `processing`, `fulfilled` and `cancelled` fulfillment states.
* Added immutable `cfwp_reward_fulfillment_events` transition history with source/reference idempotency.
* Added active-claim validation and row-locked fulfillment transitions.
* Kept fulfillment independent from WooCommerce, reward availability and customer/shipping PII.

= 0.4.6 =
* Added public finite reward availability derived exclusively from active Core claims.
* Added remaining-count output to public reward cards and contribution selector.
* Unlimited rewards remain counter-free and zero-capacity finite rewards remain unavailable.

= 0.4.5 =
* Added net-per-contribution reward eligibility using applied refund adjustments.
* Added automatic reward-claim reversal and safe reactivation after partial refund changes.

= 0.4.4 =
* Added optional reward selection, checkout validation and WooCommerce-to-claim bridge.

= 0.4.3 =
* Added the versioned `cfwp_reward_claims` Core ledger.

= 0.4.2 =
* Added public reward presentation for enabled rewards on published campaigns.

= 0.4.1 =
* Added secure frontend reward management for owned draft/pending campaigns.

= 0.4.0 =
* Added the versioned generic rewards domain and `Reward_Repository`.

= 0.3.4 =
* Added immutable refund adjustments and net confirmed campaign progress.

= 0.3.3 =
* Added authoritative public campaign progress.

= 0.3.2 =
* Added secure campaign-to-WooCommerce contribution checkout.

= 0.3.1 =
* Added HPOS-safe WooCommerce contribution synchronization.

= 0.3.0 =
* Added the generic contribution ledger.

= 0.2.4 =
* Added secure frontend promoter onboarding.

= 0.2.3 =
* Added secure frontend editing of owned draft or pending campaigns.

= 0.2.2 =
* Added the current-user promoter dashboard.

= 0.2.1 =
* Added frontend campaign submission with forced pending review.

= 0.2.0 =
* Added the Crowdfunding Promoter role and campaign-specific capability model.

= 0.1.2 =
* Added public campaign catalogue/detail output and theme-friendly styles.

= 0.1.1 =
* Added secure campaign details editing and ISO currency metadata.

= 0.1.0 =
* Initial product foundation with campaign content type and goal/deadline metadata.
