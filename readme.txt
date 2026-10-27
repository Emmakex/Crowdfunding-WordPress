=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, an authoritative contribution ledger and a WooCommerce checkout adapter.

The Core remains independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type with REST support.
* Campaign funding goal, ISO currency and deadline metadata.
* Public campaign catalogue and campaign detail shortcodes.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Contribution states: pending, confirmed, refunded and cancelled.
* Confirmed campaign totals derived only from confirmed contribution records.
* WooCommerce adapter based on CRUD order/item objects with HPOS compatibility.
* Classic checkout and Store API/Checkout Blocks order synchronization.
* Frontend contribution form that sends an exact campaign amount into WooCommerce checkout.
* Internal hidden virtual product used only as the WooCommerce transport line.
* Contribution lines are quantity-locked, tax-free and excluded from WooCommerce coupons.
* No duplicated contributor personal or card data in the contribution ledger.
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
9. Native campaign permalinks automatically show the contribution form when WooCommerce is available.
10. Use `[crowdfunding_contribute id="123"]` when the contribution form is needed outside the native campaign permalink.
11. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
12. Publish pages with `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/"]` for the promoter workflow.

== Contributions ==

Each contribution record stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same reference with the same campaign, amount and currency returns the existing contribution. Reusing it with different immutable contribution facts is rejected.

Only `confirmed` records are included in authoritative campaign totals.

== WooCommerce checkout ==

Version 0.3.2 adds the first complete campaign-to-checkout path.

A published campaign can render `[crowdfunding_contribute]`. The supporter enters an amount, the server validates the nonce, campaign status, deadline, minimum amount and campaign/store currency match, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product created by the plugin when first needed. The product is not used as crowdfunding authority: the cart item carries explicit campaign, amount and currency markers. The chosen amount is forced back onto the cart line during WooCommerce totals calculation.

Contribution lines are sold individually so their quantity remains one. Multiple contributions may still exist as separate unique cart lines because each server-created contribution receives a unique cart key. WooCommerce coupons are not allowed to discount contribution lines, and the technical product has no tax. This keeps the paid contribution amount aligned with the explicit crowdfunding amount in this first checkout model.

The internal placeholder product cannot be added manually without the server-supplied crowdfunding markers. Ordinary WooCommerce products are unaffected.

The `cfwp_minimum_contribution_amount` filter controls the minimum accepted contribution amount per campaign. The default is `1.00` in the store currency.

When WooCommerce builds the order, the checkout adapter persists `_cfwp_campaign_id` and `_cfwp_contribution_amount` on the order line. WooCommerce's Store API currently builds line items through the shared checkout line-item path, so the same marker persistence works for classic checkout and Checkout Blocks. The WooCommerce adapter then maps order lifecycle states into the Core contribution ledger.

== WooCommerce adapter ==

The WooCommerce adapter declares compatibility with High-Performance Order Storage (HPOS) and reads orders through WooCommerce CRUD objects. It observes classic checkout order creation, Store API/Blocks order creation and order status transitions.

Only line items explicitly marked with both `_cfwp_campaign_id` and `_cfwp_contribution_amount` are synchronized. Unmarked WooCommerce products are ignored. The contribution source is `woocommerce` and the stable source reference is `order:{order_id}:item:{item_id}`.

WooCommerce paid statuses map to `confirmed`, `refunded` maps to `refunded`, `failed` and `cancelled` map to `cancelled`, and other statuses remain `pending`. Partial-refund allocation is intentionally not implemented yet.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

`[crowdfunding_promoter_register]` creates the dedicated promoter role server-side when WordPress registration is enabled. `[crowdfunding_submit_campaign]` always creates a pending campaign owned by the current user. `[crowdfunding_edit_campaign]` verifies current-user ownership, draft/pending status and WordPress `edit_post` capability before render and save. `[crowdfunding_promoter_dashboard]` always queries the current user's campaigns.

Administrators retain full authority over review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign. Optional `id` selects the campaign.

= [crowdfunding_contribute] =
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign; on a native campaign permalink the current campaign is used automatically.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page` (1–50) and `edit_url` attributes.

== Frequently Asked Questions ==

= Does version 0.3.2 process payments? =

Crowdfunding WordPress now creates the contribution cart line and sends the supporter to WooCommerce checkout. Payment itself remains the responsibility of WooCommerce and whichever gateway the store has installed, such as Stripe, PayPal, Redsys or bank transfer.

= Does it work with Checkout Blocks? =

The plugin uses WooCommerce's cart/order APIs and the shared order-line creation path used by the current Store API checkout flow. The WooCommerce adapter also observes Store API checkout order creation explicitly.

= Is the plugin HPOS compatible? =

Yes. The WooCommerce adapter declares HPOS compatibility and uses WooCommerce CRUD order/item objects instead of direct order-table or post-table access.

= Can a customer change the technical product quantity to alter the contribution? =

The internal product is sold individually, so each crowdfunding cart line has quantity one. The chosen contribution amount is stored separately and reapplied during cart total calculation.

= Can coupons discount a contribution? =

Not in the 0.3.2 contribution model. Contribution lines are excluded from WooCommerce coupons so the amount chosen by the supporter remains the amount represented in the crowdfunding ledger.

= Can campaigns use a different currency from WooCommerce? =

Not in this checkout cut. The campaign currency must match the WooCommerce store currency before a contribution can be added to checkout.

= Are partial refunds supported? =

Not yet. Full WooCommerce order refunds synchronize when the order reaches refunded status. Partial-refund allocation across individual crowdfunding lines remains a separate cut.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.3.2 =
* Added `[crowdfunding_contribute]` and automatic contribution forms on native campaign permalinks.
* Added nonce-bound frontend contribution submission for guests and signed-in supporters.
* Added server-side validation for published/open campaigns, minimum amount and campaign/store currency match.
* Added an internal hidden virtual WooCommerce product used as a transport line for contributions.
* Added exact contribution cart pricing and campaign display data.
* Added classic and Store API-compatible order-item marker persistence through WooCommerce's shared line-item creation path.
* Contribution lines are sold individually, tax-free and excluded from coupons.
* Direct/manual adds of the internal placeholder product are rejected when crowdfunding markers are missing.
* Checkout still does not write the Core contribution ledger directly; the WooCommerce lifecycle adapter remains the authority bridge.

= 0.3.1 =
* Added an optional WooCommerce contribution adapter.
* Declared WooCommerce HPOS compatibility.
* Added classic checkout and Store API/Blocks order-created synchronization.
* Added order-status synchronization through WooCommerce CRUD objects.
* Only explicitly marked crowdfunding line items are synchronized.
* Added stable WooCommerce source references based on order ID and line-item ID.
* Added Core status mapping for paid, refunded, cancelled, failed and pending WooCommerce orders.

= 0.3.0 =
* Added the versioned `cfwp_contributions` ledger schema.
* Added generic contribution statuses: pending, confirmed, refunded and cancelled.
* Added a payment-provider-neutral `source + source_reference` idempotency contract.
* Added repository methods for create, lookup, status updates and confirmed campaign totals.
* Added idempotency conflict detection when immutable contribution facts differ.
* Contribution totals include only confirmed records.
* The Core ledger does not duplicate contributor names, email addresses or payment-card data.

= 0.2.4 =
* Added `[crowdfunding_promoter_register]` for frontend promoter onboarding with forced promoter role and automatic sign-in.

= 0.2.3 =
* Added `[crowdfunding_edit_campaign]` for secure frontend editing of owned draft or pending campaigns.

= 0.2.2 =
* Added `[crowdfunding_promoter_dashboard]` scoped to the current user's campaigns.

= 0.2.1 =
* Added `[crowdfunding_submit_campaign]` with forced current-user ownership and pending review status.

= 0.2.0 =
* Added the Crowdfunding Promoter role and campaign-specific capability model.

= 0.1.2 =
* Added public campaign catalogue/detail output and theme-friendly styles.

= 0.1.1 =
* Added secure campaign details editing and ISO currency metadata.

= 0.1.0 =
* Initial product foundation with campaign content type and goal/deadline metadata.
