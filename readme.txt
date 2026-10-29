=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, contributions, refund-safe accounting, rewards, reward claims, WooCommerce checkout integration and public funding progress.

The Core remains independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type with REST support.
* Campaign funding goal, ISO currency and deadline metadata.
* Public campaign catalogue and campaign detail shortcodes.
* Authoritative net raised amount and funding progress derived from confirmed contributions minus applied refund adjustments.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Immutable contribution amounts plus a separate versioned adjustment ledger.
* Refund adjustments with applied/reversed states and source/reference idempotency.
* Generic versioned rewards domain linked to campaigns.
* Reward minimum contribution thresholds, optional claim limits, enabled state and explicit ordering.
* Authenticated frontend reward management for the promoter's own draft/pending campaigns.
* Public reward cards for enabled rewards on published campaigns.
* Versioned reward-claims ledger with active/reversed states and source/reference idempotency.
* Claim eligibility validation against confirmed contribution, campaign, currency and minimum amount.
* Finite reward limits enforced while the reward row is transaction-locked.
* Active claim counts and remaining finite capacity derived from claim history instead of mutable stock counters.
* Public reward presentation still avoids claimed/remaining messaging until checkout selection is connected to claims.
* WooCommerce adapter based on CRUD order/item objects with HPOS compatibility.
* Classic checkout and Store API/Checkout Blocks order synchronization.
* Frontend contribution form that sends an exact campaign amount into WooCommerce checkout.
* Line-attributed WooCommerce partial refunds synchronized without rewriting the original contribution.
* Deleted WooCommerce refunds reverse their matching crowdfunding adjustments.
* Ambiguous partial refunds fail closed instead of being distributed heuristically.
* No duplicated contributor personal or card data in the Core ledgers.
* Safe activation, versioned upgrades and translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Install and configure WooCommerce if the site will collect contributions.
4. Open **Crowdfunding** in the WordPress admin menu.
5. Create or edit a campaign and fill in its funding goal, currency and deadline.
6. Ensure each campaign currency matches the WooCommerce store currency.
7. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
8. Use `[crowdfunding_campaign id="123"]` to embed one published campaign; enabled rewards are included automatically.
9. Native campaign permalinks automatically show net confirmed funding progress, enabled rewards and the contribution form.
10. Use `[crowdfunding_progress id="123"]` when the progress block is needed independently.
11. Use `[crowdfunding_rewards id="123"]` when enabled reward definitions are needed independently.
12. Use `[crowdfunding_contribute id="123"]` when the contribution form is needed outside the native campaign permalink.
13. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
14. Publish pages with `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/" rewards_url="/manage-rewards/"]` for the promoter workflow.
15. Publish a promoter-only page with `[crowdfunding_manage_rewards]`; pass `id="123"` or `cfwp_campaign_id` to manage rewards for one owned draft/pending campaign.

== Contributions and adjustments ==

Each contribution record stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same reference with the same immutable contribution facts returns the existing contribution; conflicting facts are rejected.

The original contribution amount remains immutable. Refunds are represented in `cfwp_contribution_adjustments` as separate records linked to the contribution. Adjustment records have their own idempotent `source + source_reference`, exact decimal amount and `applied` or `reversed` state.

An applied refund can never exceed the contribution amount still available to refund. This invariant is checked by the generic adjustment repository before persistence.

== Rewards ==

The generic Core rewards domain remains independent from WooCommerce products.

Rewards are stored in the versioned `cfwp_rewards` table and belong to exactly one crowdfunding campaign. Each reward has a stable ID, title, description, minimum contribution amount, optional claim limit, enabled state and explicit sort order.

A reward inherits the campaign's currency rather than duplicating a currency field. `claim_limit = 0` means unlimited. The claim limit is configuration; actual usage is derived from the reward claims ledger rather than a mutable remaining-stock field.

The generic `Reward_Repository` can create rewards, update their mutable presentation/eligibility fields, find them by stable ID and list rewards for one campaign. Campaign ownership cannot be reassigned through reward updates.

`[crowdfunding_manage_rewards]` is authenticated-only and permits create, edit, sort and activate/deactivate operations only when the selected campaign is owned by the current user, remains `draft` or `pending`, and passes WordPress `edit_post` authorization. Every write revalidates that a supplied reward belongs to that authorized campaign.

`[crowdfunding_rewards]` renders only enabled rewards belonging to a published campaign and uses the campaign currency. Rewards are ordered by configured `sort_order`, then minimum contribution and stable ID. The same block is included automatically in `[crowdfunding_campaign]` output and native campaign permalinks.

== Reward claims ==

Version 0.4.3 adds the versioned `cfwp_reward_claims` Core ledger. A claim links a stable reward ID to a stable contribution ID and carries its own `source + source_reference` idempotency key, lifecycle status and timestamps.

Claims use two states: `active` and `reversed`. Reversal preserves the historical row instead of deleting it. The same reward/contribution pair cannot be inserted twice, and replaying one source reference with different immutable claim facts is rejected.

A new active claim is accepted only when the reward is enabled, the contribution is `confirmed`, reward and contribution belong to the same campaign, the contribution currency matches the campaign currency, and the exact contribution amount meets the reward minimum.

For finite rewards, claim creation starts a transaction and locks the reward row before counting active claims and inserting. This keeps the configured `claim_limit` as the definition authority while active claim rows remain the usage authority. `remaining_for_reward()` returns `null` for unlimited rewards and otherwise derives `max(0, claim_limit - active claims)`.

Version 0.4.3 intentionally does not create claims from WooCommerce checkout yet. Public reward cards therefore continue to avoid claimed/remaining availability messaging until the selection-to-claim bridge is introduced in the next reward integration cut.

== Funding progress ==

Public progress uses `Contribution_Adjustment_Repository::net_confirmed_total_for_campaign()`. The net total is the sum of Core `confirmed` contribution amounts minus applied refund adjustments for those contributions.

The raised amount is never read from campaign metadata, WooCommerce order totals, pending payment data or a cached editable field. Campaign cards use a compact progress block, while campaign detail output and native campaign permalinks use the full block. `[crowdfunding_progress]` can render the same authority independently.

The textual percentage may exceed 100% when a campaign raises more than its goal. The visual progress bar is capped at 100% while preserving the real percentage in text and accessible progress semantics.

= WooCommerce refunds =

WooCommerce refund lifecycle events are consumed through CRUD objects. When a refund contains line items, Crowdfunding WordPress follows `_refunded_item_id` back to the original marked crowdfunding order item and records an idempotent refund adjustment.

A partial refund without line-item attribution is not guessed or spread proportionally across crowdfunding lines. If it cannot be attributed safely, the plugin emits `cfwp_woocommerce_refund_sync_error` and leaves the Core accounting unchanged.

A fully refunded WooCommerce order is unambiguous. The plugin applies any remaining refundable balance to each marked crowdfunding contribution in the order. When a WooCommerce refund is deleted, matching adjustments move from `applied` to `reversed` instead of being deleted.

== WooCommerce checkout ==

A published campaign can render `[crowdfunding_contribute]`. The supporter enters an amount, the server validates the nonce, campaign status, deadline, minimum amount and campaign/store currency match, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product created by the plugin when first needed. The product is not crowdfunding authority: the cart line carries explicit campaign, amount and currency markers, and the chosen amount is reapplied during WooCommerce totals calculation.

Contribution lines are sold individually, cannot be discounted by WooCommerce coupons and use no tax in this pure-contribution model. The internal placeholder product cannot be added manually without server-supplied crowdfunding markers.

Reward selection is not yet transported through checkout in 0.4.3. The claim ledger is deliberately Core-first so the future WooCommerce bridge can translate explicit checkout/order evidence into idempotent claims instead of making order metadata the claim authority.

== WooCommerce adapter ==

The order adapter declares compatibility with High-Performance Order Storage (HPOS) and reads orders through WooCommerce CRUD objects. It observes classic checkout order creation, Store API/Blocks order creation and order status transitions.

Only line items explicitly marked with both `_cfwp_campaign_id` and `_cfwp_contribution_amount` are synchronized. The contribution source is `woocommerce` and the stable source reference is `order:{order_id}:item:{item_id}`.

Paid WooCommerce statuses map to `confirmed`, `refunded` maps to `refunded`, `failed` and `cancelled` map to `cancelled`, and other statuses remain `pending`. The separate refund adapter handles partial-refund accounting without mutating the stored contribution amount.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

`[crowdfunding_promoter_register]` creates the dedicated promoter role server-side when WordPress registration is enabled. `[crowdfunding_submit_campaign]` always creates a pending campaign owned by the current user. `[crowdfunding_edit_campaign]` verifies ownership, draft/pending status and WordPress `edit_post` capability. `[crowdfunding_promoter_dashboard]` always queries the current user's campaigns. `[crowdfunding_manage_rewards]` applies the same ownership and pre-publication boundary to reward management.

Administrators retain full authority over review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign, including its enabled reward definitions. Optional `id` selects the campaign.

= [crowdfunding_progress] =
Displays the authoritative net confirmed raised amount, goal, percentage and progress bar. Optional `id` and `compact="yes"`.

= [crowdfunding_rewards] =
Displays enabled reward definitions for one published campaign. Optional `id` selects the campaign; on a campaign context the current campaign is used. Configured claim limits may be shown, but claimed/remaining availability remains intentionally absent until checkout selection creates authoritative claims.

= [crowdfunding_contribute] =
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_manage_rewards] =
Displays authenticated reward management for one campaign owned by the current user. Optional `id` selects the campaign; without it, `cfwp_campaign_id` can select the campaign. Only draft and pending campaigns are manageable.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page` (1–50), `edit_url` and `rewards_url` attributes.

== Frequently Asked Questions ==

= What counts toward the public amount raised? =

Confirmed Core contributions minus applied refund adjustments. Pending and cancelled contributions do not count; fully refunded contributions are excluded by their Core status.

= Are rewards WooCommerce products? =

No. Reward definitions and reward claims belong to the generic crowdfunding Core. WooCommerce remains the checkout/payment layer.

= What is an active reward claim? =

An active claim is an immutable link between one reward and one qualifying confirmed contribution. If the entitlement must be withdrawn, the claim is marked `reversed` rather than deleted.

= How is a finite reward limit enforced? =

The reward stores only its configured `claim_limit`. The current usage is counted from active claim rows. Finite claim creation locks the reward row while checking the count and inserting the new claim.

= Does the public reward list show how many rewards remain? =

Not yet in 0.4.3. The claims ledger can derive the number, but WooCommerce checkout does not create claims until the next integration cut. Public presentation therefore avoids presenting an operational availability number prematurely.

= Does managing or displaying a reward create a claim or reserve stock? =

No. Claim creation is a separate Core operation and is not triggered by reward management or rendering.

= Can an existing reward be moved to another campaign? =

No. A reward's campaign ownership is treated as an identity fact after creation.

= Does a partial refund overwrite the original contribution amount? =

No. Refunds are independent adjustment records so the original transaction and every adjustment remain auditable.

= Does this plugin process payments? =

Crowdfunding WordPress creates the contribution cart line and sends the supporter to WooCommerce checkout. Payment and payment-provider refunds remain the responsibility of WooCommerce and the installed gateway.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund integration uses WooCommerce CRUD objects rather than direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.4.3 =
* Added the versioned `cfwp_reward_claims` Core ledger.
* Added active/reversed claim lifecycle without destructive deletion.
* Added source/reference idempotency and reward/contribution duplicate protection.
* Added confirmed-contribution, campaign, currency and reward-minimum eligibility validation.
* Added finite claim-limit enforcement under a reward-row transaction lock.
* Added derived active-claim counts and remaining finite capacity without mutable stock counters.
* Kept reward claims independent from WooCommerce; checkout-to-claim integration remains a separate cut.

= 0.4.2 =
* Added public `[crowdfunding_rewards]` presentation for enabled rewards on published campaigns.
* Added automatic reward blocks to campaign detail output and native campaign permalinks.
* Public reward presentation does not expose claimed or remaining availability before a live claims bridge exists.

= 0.4.1 =
* Added authenticated `[crowdfunding_manage_rewards]` frontend management for rewards on owned draft/pending campaigns.
* Added create, update, sort and activate/deactivate operations with ownership-bound authorization.

= 0.4.0 =
* Added the versioned generic `cfwp_rewards` domain table and `Reward_Repository`.

= 0.3.4 =
* Added a versioned immutable contribution-adjustment ledger and WooCommerce partial-refund integrity.
* Public campaign progress now uses confirmed contributions net of applied refund adjustments.

= 0.3.3 =
* Added authoritative public funding progress derived from confirmed Core contribution totals.

= 0.3.2 =
* Added the secure campaign-to-WooCommerce contribution checkout path and exact order-line markers.

= 0.3.1 =
* Added an optional WooCommerce contribution adapter with HPOS compatibility and order lifecycle synchronization.

= 0.3.0 =
* Added the versioned payment-provider-neutral contribution ledger and confirmed campaign totals.

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
