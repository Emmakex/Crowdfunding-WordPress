=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.4.2
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
* Authenticated frontend reward management for the promoter's own draft/pending campaigns.
* Public reward cards for enabled rewards on published campaigns.
* Standalone `[crowdfunding_rewards]` plus automatic reward presentation on campaign detail/permalink surfaces.
* Public reward presentation exposes configured claim limits but does not invent claimed or remaining availability.
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

A reward inherits the campaign's currency rather than duplicating a currency field. `claim_limit = 0` means unlimited. The claim limit is only the configured maximum; Crowdfunding WordPress does not maintain mutable "remaining stock" because reward availability will be derived from a separate claims ledger in a later 0.4.x cut.

The generic `Reward_Repository` can create rewards, update their mutable presentation/eligibility fields, find them by stable ID and list rewards for one campaign. Campaign ownership cannot be reassigned through reward updates.

`[crowdfunding_manage_rewards]` is authenticated-only and permits create, edit, sort and activate/deactivate operations only when the selected campaign is owned by the current user, remains `draft` or `pending`, and passes WordPress `edit_post` authorization. Every write revalidates that a supplied reward belongs to that authorized campaign.

Version 0.4.2 adds `[crowdfunding_rewards]` for public presentation. It renders only enabled rewards belonging to a published campaign and uses the campaign currency. Rewards are ordered by configured `sort_order`, then minimum contribution and stable ID. The same block is included automatically in `[crowdfunding_campaign]` output and native campaign permalinks.

A positive `claim_limit` can be shown publicly as the configured total limit. Version 0.4.2 deliberately does not display claimed counts, remaining counts or availability labels because no claims ledger is authoritative yet.

Reward presentation does not create reward claims, reserve stock or connect a reward to WooCommerce checkout. Those responsibilities remain separate 0.4.x cuts.

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
Displays enabled reward definitions for one published campaign. Optional `id` selects the campaign; on a campaign context the current campaign is used. Configured claim limits may be shown, but claimed/remaining availability is intentionally absent until the claims ledger exists.

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

No. Reward definitions belong to the generic crowdfunding Core. WooCommerce remains the checkout/payment layer. A later 0.4.x cut will define how a selected reward is carried through checkout and claimed.

= Can a promoter manage rewards on a published campaign? =

Not through the frontend reward manager. That surface is intentionally restricted to the current user's own draft or pending campaigns so reward definitions are finalized before publication.

= Does the public reward list show how many rewards remain? =

No. A configured total claim limit may be displayed, but claimed and remaining availability require a dedicated claims ledger. Version 0.4.2 does not invent those values.

= Does managing or displaying a reward create a claim or reserve stock? =

No. `claim_limit` is configuration only. Actual claimed/remaining availability will be derived from a dedicated claims ledger.

= Can an existing reward be moved to another campaign? =

Not through the reward repository update contract or the frontend manager. A reward's campaign ownership is treated as an identity fact after creation.

= Does a partial refund overwrite the original contribution amount? =

No. Refunds are independent adjustment records so the original transaction and every adjustment remain auditable.

= Does this plugin process payments? =

Crowdfunding WordPress creates the contribution cart line and sends the supporter to WooCommerce checkout. Payment and payment-provider refunds remain the responsibility of WooCommerce and the installed gateway.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund integration uses WooCommerce CRUD objects rather than direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.4.2 =
* Added public `[crowdfunding_rewards]` presentation for enabled rewards on published campaigns.
* Added automatic reward blocks to campaign detail output and native campaign permalinks.
* Reward cards display title, minimum contribution, description and configured total claim limit when present.
* Preserved configured reward ordering by sort order, minimum contribution and stable ID.
* Public reward presentation does not expose claimed or remaining availability before the claims ledger exists.
* Kept public Rewards independent from WooCommerce checkout, contribution accounting and refund accounting.

= 0.4.1 =
* Added authenticated `[crowdfunding_manage_rewards]` frontend management for rewards on owned draft/pending campaigns.
* Added create, update, sort and activate/deactivate operations.
* Added operation/campaign/reward-bound nonces and ownership revalidation on every write.
* Reward IDs and campaign ownership remain stable; updates cannot move a reward between campaigns.
* No reward deletion, claims, remaining-stock mutation or WooCommerce checkout coupling was introduced.

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
