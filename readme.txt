=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters, an authoritative contribution ledger, WooCommerce checkout integration and public funding progress.

The Core remains independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type with REST support.
* Campaign funding goal, ISO currency and deadline metadata.
* Public campaign catalogue and campaign detail shortcodes.
* Authoritative raised amount and funding progress derived only from confirmed contribution records.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Contribution states: pending, confirmed, refunded and cancelled.
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
9. Native campaign permalinks automatically show confirmed funding progress and the contribution form.
10. Use `[crowdfunding_progress id="123"]` when the progress block is needed independently.
11. Use `[crowdfunding_contribute id="123"]` when the contribution form is needed outside the native campaign permalink.
12. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
13. Publish pages with `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/"]` for the promoter workflow.

== Contributions ==

Each contribution record stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same reference with the same campaign, amount and currency returns the existing contribution. Reusing it with different immutable contribution facts is rejected.

Only `confirmed` records are included in authoritative campaign totals.

== Funding progress ==

Version 0.3.3 exposes the contribution ledger's confirmed total on public campaign surfaces.

The raised amount is never read from campaign metadata, WooCommerce order totals or pending payment data. `Campaign_Progress` consumes `Contribution_Repository::confirmed_total_for_campaign()` and compares that confirmed total with the campaign goal.

Campaign cards use a compact progress block. Campaign detail output and native campaign permalinks use the full block. `[crowdfunding_progress]` can also render the same authority independently.

The textual percentage may exceed 100% when a campaign raises more than its goal. The visual progress bar is capped at 100% while preserving the real percentage in the text. The progress element includes accessible progressbar semantics and an explicit textual description.

= [crowdfunding_progress] =

Displays confirmed funding progress for one published campaign.

* `id` — optional campaign ID. On a campaign context the current campaign is used.
* `compact` — `yes` or `no`. Default: `no`.

No progress is rendered when the campaign has no valid positive goal or currency.

== WooCommerce checkout ==

A published campaign can render `[crowdfunding_contribute]`. The supporter enters an amount, the server validates the nonce, campaign status, deadline, minimum amount and campaign/store currency match, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product created by the plugin when first needed. The product is not used as crowdfunding authority: the cart item carries explicit campaign, amount and currency markers. The chosen amount is forced back onto the cart line during WooCommerce totals calculation.

Contribution lines are sold individually so their quantity remains one. WooCommerce coupons cannot discount contribution lines and the technical product has no tax. The internal placeholder product cannot be added manually without server-supplied crowdfunding markers. Ordinary WooCommerce products are unaffected.

The `cfwp_minimum_contribution_amount` filter controls the minimum accepted contribution amount per campaign. The default is `1.00` in the store currency.

When WooCommerce builds the order, the checkout adapter persists `_cfwp_campaign_id` and `_cfwp_contribution_amount` on the order line. The WooCommerce adapter then maps order lifecycle states into the Core contribution ledger.

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

= [crowdfunding_progress] =
Displays the authoritative confirmed raised amount, goal, percentage and progress bar. Optional `id` and `compact="yes"`.

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

Only contribution rows in Core status `confirmed`. Pending, cancelled and refunded contributions do not count.

= Can the progress shown on a campaign be edited manually? =

No. The public raised amount is derived from the contribution ledger. Campaign metadata defines the goal, not the raised amount.

= Does version 0.3.3 process payments? =

Crowdfunding WordPress creates the contribution cart line and sends the supporter to WooCommerce checkout. Payment itself remains the responsibility of WooCommerce and the installed gateway.

= Does it work with Checkout Blocks? =

The plugin uses WooCommerce cart/order APIs and the shared order-line creation path used by the Store API checkout flow. The WooCommerce adapter also observes Store API checkout order creation explicitly.

= Is the plugin HPOS compatible? =

Yes. The WooCommerce adapter declares HPOS compatibility and uses WooCommerce CRUD order/item objects instead of direct order-table or post-table access.

= Can coupons discount a contribution? =

No in the current pure-contribution model. The amount chosen by the supporter remains aligned with the amount represented in the crowdfunding ledger.

= Can campaigns use a different currency from WooCommerce? =

Not in the current checkout flow. The campaign currency must match the WooCommerce store currency before a contribution can be added to checkout.

= Are partial refunds supported? =

Not yet. Full WooCommerce order refunds synchronize when the order reaches refunded status. Partial-refund allocation across individual crowdfunding lines remains a separate cut.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.3.3 =
* Added authoritative public funding progress derived exclusively from confirmed contribution ledger totals.
* Added `[crowdfunding_progress]` with optional campaign ID and compact mode.
* Added confirmed raised amount, goal and percentage to campaign catalogue cards and campaign detail surfaces.
* Native campaign permalinks now show the same confirmed progress automatically.
* Visual progress caps at 100% while textual percentage preserves overfunding above the goal.
* Added accessible progressbar semantics and standalone theme-friendly styling.
* Added regression protection preventing public progress from querying WooCommerce or contribution states directly.

= 0.3.2 =
* Added `[crowdfunding_contribute]` and automatic contribution forms on native campaign permalinks.
* Added nonce-bound frontend contribution submission for guests and signed-in supporters.
* Added server-side validation for published/open campaigns, minimum amount and campaign/store currency match.
* Added an internal hidden virtual WooCommerce product used as a transport line for contributions.
* Added exact contribution cart pricing and campaign display data.
* Added classic and Store API-compatible order-item marker persistence through WooCommerce's shared line-item creation path.
* Contribution lines are sold individually, tax-free and excluded from coupons.
* Direct/manual adds of the internal placeholder product are rejected when crowdfunding markers are missing.
* Checkout does not write the Core contribution ledger directly; the WooCommerce lifecycle adapter remains the authority bridge.

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
