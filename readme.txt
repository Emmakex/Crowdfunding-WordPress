=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, contributions, refund-safe accounting, rewards, WooCommerce checkout integration and public funding progress.

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
* WooCommerce adapter based on CRUD order/item objects with HPOS compatibility.
* Classic checkout and Store API/Checkout Blocks order synchronization.
* Frontend contribution form that sends an exact campaign amount into WooCommerce checkout.
* Line-attributed WooCommerce partial refunds synchronized without rewriting the original contribution.
* Deleted WooCommerce refunds reverse their matching crowdfunding adjustments.
* Ambiguous partial refunds fail closed instead of being distributed heuristically.
* Internal hidden virtual product used only as the WooCommerce transport line.
* Contribution lines are quantity-locked, tax-free and excluded from WooCommerce coupons.
* No duplicated contributor personal or card data in the contribution or adjustment ledgers.
* Safe activation, versioned upgrades and translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Install and configure WooCommerce if the site will collect contributions.
4. Open **Crowdfunding** in the WordPress admin menu.
5. Create or edit a campaign and fill in its funding goal, currency and deadline.
6. Ensure each campaign currency matches the WooCommerce store currency.
7. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
8. Use `[crowdfunding_campaign id="123"]` to embed one published campaign.
9. Native campaign permalinks automatically show net confirmed funding progress and the contribution form.
10. Use `[crowdfunding_progress id="123"]` when the progress block is needed independently.
11. Use `[crowdfunding_contribute id="123"]` when the contribution form is needed outside the native campaign permalink.
12. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
13. Publish pages with `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/"]` for the promoter workflow.

== Contributions and adjustments ==

Each contribution record stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same reference with the same immutable contribution facts returns the existing contribution; conflicting facts are rejected.

The original contribution amount remains immutable. Refunds are represented in `cfwp_contribution_adjustments` as separate records linked to the contribution. Adjustment records have their own idempotent `source + source_reference`, exact decimal amount and `applied` or `reversed` state.

An applied refund can never exceed the contribution amount still available to refund. This invariant is checked by the generic adjustment repository before persistence.

== Rewards ==

Version 0.4.0 introduces the generic Core rewards domain without coupling a reward to a WooCommerce product.

Rewards are stored in the versioned `cfwp_rewards` table and belong to exactly one crowdfunding campaign. Each reward has a stable ID, title, description, minimum contribution amount, optional claim limit, enabled state and explicit sort order.

A reward inherits the campaign's currency rather than duplicating a currency field. `claim_limit = 0` means unlimited. The claim limit is only the configured maximum; version 0.4.0 does not yet maintain mutable "remaining stock" because reward availability will be derived from a separate claims ledger in a later 0.4.x cut.

The generic `Reward_Repository` can create rewards, update their mutable presentation/eligibility fields, find them by stable ID and list rewards for one campaign. Campaign ownership cannot be reassigned through reward updates.

Version 0.4.0 intentionally does not expose reward selection in checkout and does not create reward claims. Those responsibilities remain separate from the Core reward definition.

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

== WooCommerce adapter ==

The order adapter declares compatibility with High-Performance Order Storage (HPOS) and reads orders through WooCommerce CRUD objects. It observes classic checkout order creation, Store API/Blocks order creation and order status transitions.

Only line items explicitly marked with both `_cfwp_campaign_id` and `_cfwp_contribution_amount` are synchronized. The contribution source is `woocommerce` and the stable source reference is `order:{order_id}:item:{item_id}`.

Paid WooCommerce statuses map to `confirmed`, `refunded` maps to `refunded`, `failed` and `cancelled` map to `cancelled`, and other statuses remain `pending`. The separate refund adapter handles partial-refund accounting without mutating the stored contribution amount.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

`[crowdfunding_promoter_register]` creates the dedicated promoter role server-side when WordPress registration is enabled. `[crowdfunding_submit_campaign]` always creates a pending campaign owned by the current user. `[crowdfunding_edit_campaign]` verifies ownership, draft/pending status and WordPress `edit_post` capability. `[crowdfunding_promoter_dashboard]` always queries the current user's campaigns.

Administrators retain full authority over review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign. Optional `id` selects the campaign.

= [crowdfunding_progress] =
Displays the authoritative net confirmed raised amount, goal, percentage and progress bar. Optional `id` and `compact="yes"`.

= [crowdfunding_contribute] =
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page` (1–50) and `edit_url` attributes.

== Frequently Asked Questions ==

= What counts toward the public amount raised? =

Confirmed Core contributions minus applied refund adjustments. Pending and cancelled contributions do not count; fully refunded contributions are excluded by their Core status.

= Are rewards WooCommerce products? =

No. Reward definitions belong to the generic crowdfunding Core. WooCommerce remains the checkout/payment layer. A later 0.4.x cut will define how a selected reward is carried through checkout and claimed.

= Does a reward claim limit mean remaining stock is already tracked? =

No. `claim_limit` is the configured maximum and zero means unlimited. Actual claimed/remaining availability will be derived from a dedicated claims ledger rather than a mutable counter on the reward.

= Can an existing reward be moved to another campaign? =

Not through the reward repository update contract. A reward's campaign ownership is treated as an identity fact after creation.

= Does a partial refund overwrite the original contribution amount? =

No. Refunds are independent adjustment records so the original transaction and every adjustment remain auditable.

= What happens if a partial WooCommerce refund has no line-item attribution? =

Crowdfunding WordPress fails closed. It does not guess which contribution should be reduced. An integration can observe `cfwp_woocommerce_refund_sync_error` and resolve the ambiguity explicitly.

= Does this plugin process payments? =

Crowdfunding WordPress creates the contribution cart line and sends the supporter to WooCommerce checkout. Payment and payment-provider refunds remain the responsibility of WooCommerce and the installed gateway.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund integration uses WooCommerce CRUD objects rather than direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.4.0 =
* Added the versioned generic `cfwp_rewards` domain table.
* Added stable reward IDs linked to exactly one crowdfunding campaign.
* Added reward title, description, minimum contribution threshold, optional claim limit, enabled state and explicit ordering.
* Added `Reward_Repository` create, update, find and campaign-list contracts.
* Reward campaign ownership is immutable through updates.
* `claim_limit = 0` means unlimited; no mutable remaining-stock field is introduced.
* Kept reward definitions independent from WooCommerce, contribution orders and refund accounting.
* Reward selection, claims and fulfillment remain separate 0.4.x cuts.

= 0.3.4 =
* Added a versioned immutable contribution-adjustment ledger.
* Added idempotent refund adjustments with `applied` and `reversed` states.
* Added exact remaining-refundable validation so cumulative refunds cannot exceed the original contribution.
* Added WooCommerce line-attributed partial refund synchronization via `_refunded_item_id`.
* Added fail-closed handling for ambiguous amount-only partial refunds.
* Added safe full-order-refund allocation of each crowdfunding contribution's remaining refundable balance.
* Added refund deletion reversal without deleting crowdfunding audit history.
* Public campaign progress now uses confirmed contributions net of applied refund adjustments.
* Kept the original contribution amount immutable and kept the generic adjustment ledger independent from WooCommerce.

= 0.3.3 =
* Added authoritative public funding progress derived from confirmed Core contribution totals.
* Added `[crowdfunding_progress]`, catalogue/detail progress and accessible overfunding-aware rendering.

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
