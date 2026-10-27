=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, an authoritative contribution ledger and an optional WooCommerce adapter.

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
* Optional WooCommerce adapter based on WooCommerce CRUD objects, not order post tables.
* HPOS compatibility declaration.
* Classic checkout and Store API/Blocks order-created synchronization hooks.
* WooCommerce order-status synchronization for explicitly marked crowdfunding line items.
* No duplicated contributor personal or card data in the contribution ledger.
* Safe activation, versioned upgrades and translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding** in the WordPress admin menu.
4. Create or edit a campaign and fill in its funding goal, currency and deadline.
5. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
6. Use `[crowdfunding_campaign id="123"]` to embed one published campaign.
7. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
8. Publish a page with `[crowdfunding_submit_campaign]` for campaign submission.
9. Publish a page with `[crowdfunding_edit_campaign]` for secure promoter editing.
10. Publish a page with `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/"]` for the promoter dashboard.

== Contributions ==

Each contribution record stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with the payment adapter or commerce system.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same reference with the same campaign, amount and currency returns the existing contribution. Reusing it with different immutable contribution facts is rejected.

Only `confirmed` records are included in authoritative campaign totals.

== WooCommerce adapter ==

Version 0.3.1 adds the first WooCommerce adapter without making WooCommerce a dependency of the Core contribution domain.

The adapter declares compatibility with WooCommerce High-Performance Order Storage (HPOS) and reads orders through WooCommerce CRUD objects. It observes classic checkout order creation, Store API/Blocks order creation and order status transitions.

Only line items explicitly marked with both `_cfwp_campaign_id` and `_cfwp_contribution_amount` are synchronized. Unmarked WooCommerce products are ignored. The contribution source is `woocommerce` and the stable source reference is `order:{order_id}:item:{item_id}`.

WooCommerce paid statuses map to `confirmed`, `refunded` maps to `refunded`, `failed` and `cancelled` map to `cancelled`, and other statuses remain `pending`. Full order refunds therefore synchronize to the Core ledger when WooCommerce moves the order to refunded. Partial-refund accounting is intentionally not implemented in this cut.

Version 0.3.1 still does not create crowdfunding cart items or checkout sessions. A later cut will own the cart-to-order marker creation so that the adapter never has to infer contribution amounts from taxes, discounts or reward product totals.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

`[crowdfunding_promoter_register]` creates the dedicated promoter role server-side when WordPress registration is enabled. `[crowdfunding_submit_campaign]` always creates a pending campaign owned by the current user. `[crowdfunding_edit_campaign]` verifies current-user ownership, draft/pending status and WordPress `edit_post` capability before render and save. `[crowdfunding_promoter_dashboard]` always queries the current user's campaigns.

Administrators retain full authority over review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign. Optional `id` selects the campaign.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page` (1–50) and `edit_url` attributes.

== Frequently Asked Questions ==

= Does version 0.3.1 process payments? =

No. It synchronizes explicitly marked WooCommerce order items into the Core contribution ledger. Creating the crowdfunding cart/checkout flow is a separate 0.3.x cut.

= Is the plugin HPOS compatible? =

The 0.3.1 WooCommerce adapter declares HPOS compatibility and uses WooCommerce CRUD order/item objects rather than direct order-table or post-table access.

= Does any WooCommerce product become a crowdfunding contribution? =

No. The adapter ignores ordinary products. A line item must explicitly contain `_cfwp_campaign_id` and `_cfwp_contribution_amount` markers.

= Why is the contribution amount stored explicitly on the order item? =

The Core must not guess the crowdfunding amount from WooCommerce line totals, taxes, discounts or future reward pricing. The checkout adapter will write the authoritative crowdfunding amount explicitly.

= Are partial refunds supported? =

Not yet. Version 0.3.1 synchronizes the full order lifecycle, including orders that reach WooCommerce's refunded status. Partial-refund allocation across crowdfunding line items remains a separate cut.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.3.1 =
* Added an optional WooCommerce contribution adapter.
* Declared WooCommerce HPOS compatibility.
* Added classic checkout and Store API/Blocks order-created synchronization.
* Added order-status synchronization through WooCommerce CRUD objects.
* Only explicitly marked crowdfunding line items are synchronized.
* Added stable WooCommerce source references based on order ID and line-item ID.
* Added Core status mapping for paid, refunded, cancelled, failed and pending WooCommerce orders.
* Kept partial-refund allocation and checkout/cart creation outside this cut.

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
