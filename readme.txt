=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, promoters and a contribution ledger designed for WooCommerce-based payment adapters.

The plugin core is intentionally independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type.
* REST API support.
* Campaign funding goal and ISO currency metadata.
* Campaign deadline metadata.
* Secure campaign details editor in WordPress admin.
* Public campaign catalogue and campaign detail shortcodes.
* Frontend promoter registration when site registration is enabled.
* Frontend campaign submission shortcode for authorized promoters.
* Submitted campaigns always enter `pending` review status.
* Personal frontend promoter dashboard scoped to the current user's campaigns.
* Secure frontend editing for the current user's own draft or pending campaigns.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Contribution states: pending, confirmed, refunded and cancelled.
* Confirmed campaign totals derived only from contribution records in confirmed state.
* No duplicated contributor personal data in the contribution ledger.
* Safe activation and versioned schema upgrades.
* Translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding** in the WordPress admin menu.
4. Create or edit a campaign and fill in its funding goal, currency and deadline.
5. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
6. Use `[crowdfunding_campaign id="123"]` to embed one published campaign, or use `[crowdfunding_campaign]` inside a campaign context.
7. Enable WordPress user registration if your site should allow public promoter onboarding, then publish a page with `[crowdfunding_promoter_register]`.
8. Publish a page with `[crowdfunding_submit_campaign]` to provide the promoter submission form.
9. Publish a page with `[crowdfunding_edit_campaign]` to provide the secure promoter editor.
10. Publish a page with `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/"]` for the signed-in promoter's personal campaign overview and edit links.

== Contributions ==

Version 0.3.0 introduces the internal contribution ledger but does not yet create payments or checkout sessions.

Each contribution record stores only the crowdfunding facts required by the Core: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, contribution status and timestamps. Payment/customer details remain with the payment adapter or commerce system instead of being duplicated in the Core ledger.

The pair `source + source_reference` is unique and acts as an idempotency key. Replaying the same source reference with the same campaign, amount and currency returns the existing contribution. Reusing that source reference with different immutable contribution facts is rejected as an idempotency conflict.

Only `confirmed` contribution records are included in authoritative campaign totals. WooCommerce integration will be added as a separate adapter in the 0.3.x line.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns and upload campaign media. Promoters cannot publish campaigns, edit published campaigns, access private campaigns or manage campaigns owned by other users.

The `[crowdfunding_promoter_register]` surface is available to signed-out visitors only when WordPress user registration is enabled. The server always creates the account with the `cfwp_promoter` role; role and capability values are never accepted from the browser. After a valid registration the new promoter is signed in and returned to the originating page.

The default registration switch can be customized by developers through the `cfwp_promoter_registration_enabled` filter. The default value always follows the WordPress `users_can_register` setting.

The `[crowdfunding_submit_campaign]` form is available only to signed-in users with the `edit_cfwp_campaigns` capability. Valid submissions are created as pending campaigns owned by the current user and must be reviewed before publication.

The `[crowdfunding_edit_campaign]` surface accepts a campaign ID only as a selector. Before rendering or saving, the plugin verifies that the campaign belongs to the current user, has `draft` or `pending` status and passes WordPress `edit_post` capability mapping. The editor never changes campaign author or publication status.

The `[crowdfunding_promoter_dashboard]` surface is permission-gated and always queries campaigns where the author is the current signed-in user. Draft and pending campaigns may link to the editor when the `edit_url` attribute is configured; published campaigns remain view-only.

Administrators retain full authority over campaign review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =

Displays published campaigns. Optional attributes:

* `per_page` — number of campaigns, from 1 to 24. Default: 6.
* `order` — `ASC` or `DESC`. Default: `DESC`.

= [crowdfunding_campaign] =

Displays one published campaign. Use the optional `id` attribute to select a campaign.

= [crowdfunding_promoter_register] =

Displays public promoter registration when WordPress user registration is enabled. The form collects username, email and password. Successful accounts always receive the `cfwp_promoter` role and are signed in automatically.

= [crowdfunding_submit_campaign] =

Displays the frontend campaign submission form. The user must be signed in and have permission to create campaigns. The form collects title, summary, story, funding goal, ISO currency and deadline. Every accepted submission is stored with `pending` status for review.

= [crowdfunding_edit_campaign] =

Displays the frontend editor for one campaign owned by the current signed-in user. The campaign may be selected with the optional `id` attribute or through the `cfwp_campaign_id` query parameter. Only `draft` and `pending` campaigns are editable. Published campaigns are never editable from this surface.

= [crowdfunding_promoter_dashboard] =

Displays the current signed-in user's draft, pending and published campaigns. Optional attributes:

* `per_page` — campaigns per dashboard page, from 1 to 50. Default: 12.
* `edit_url` — URL of the page containing `[crowdfunding_edit_campaign]`. When configured, draft and pending cards receive an edit link with the campaign selector appended.

The dashboard does not accept an author/user parameter and never queries campaigns owned by another account.

== Frequently Asked Questions ==

= Does version 0.3.0 process payments? =

No. Version 0.3.0 creates the authoritative contribution ledger and repository. WooCommerce checkout and payment-state synchronization are separate 0.3.x cuts.

= Why does the contribution ledger use a source and source reference? =

They provide a payment-provider-neutral idempotency key. A WooCommerce adapter can later map a stable order/item reference without making WooCommerce part of the Core contribution model.

= When will a contribution count toward the amount raised? =

Only when its Core contribution status is `confirmed`. Pending, cancelled and refunded records are excluded from the confirmed total.

= Can a promoter register without an administrator creating the account? =

Yes, when WordPress user registration is enabled. The registration surface always assigns the dedicated `cfwp_promoter` role and never accepts a role from request data.

= Can a promoter publish a campaign directly? =

No. Promoters can prepare and submit their own campaigns, while publication remains under administrator control.

= Can promoters edit campaigns from the frontend? =

Yes, but only their own `draft` or `pending` campaigns. The frontend editor checks ownership and WordPress edit capability on every render and save. Published campaigns remain locked.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.3.0 =
* Added the versioned `cfwp_contributions` ledger schema.
* Added generic contribution statuses: pending, confirmed, refunded and cancelled.
* Added a payment-provider-neutral `source + source_reference` idempotency contract.
* Added repository methods for create, lookup, status updates and confirmed campaign totals.
* Added idempotency conflict detection when immutable contribution facts differ.
* Contribution totals include only confirmed records.
* The Core ledger does not duplicate contributor names, email addresses or payment-card data.

= 0.2.4 =
* Added `[crowdfunding_promoter_register]` for frontend promoter onboarding.
* Public registration follows the WordPress user-registration switch by default.
* New accounts are always assigned the dedicated `cfwp_promoter` role server-side.
* Added nonce validation, username/email/password validation and safe redirects.
* Successful promoter registrations are signed in automatically.
* Added `cfwp_promoter_registration_enabled` filter and `cfwp_promoter_registered` action for integrations.

= 0.2.3 =
* Added `[crowdfunding_edit_campaign]` for secure frontend editing of owned draft or pending campaigns.
* Editor authorization verifies current-user ownership, closed editable statuses and WordPress `edit_post` capability.
* Frontend updates never change campaign ownership or publication status.
* Dashboard now supports an optional `edit_url` attribute to link draft and pending campaigns to the editor.
* Added nonce-bound campaign updates, input validation, safe redirects and an editor authority regression gate.

= 0.2.2 =
* Added `[crowdfunding_promoter_dashboard]` as a read-only frontend promoter dashboard.
* Dashboard queries are forced to the current signed-in user and closed campaign statuses.
* Added campaign status, funding goal and deadline overview with pagination.
* Published campaigns can be opened from the dashboard; pending campaigns clearly show review state.

= 0.2.1 =
* Added `[crowdfunding_submit_campaign]` frontend submission form.
* Added nonce and promoter capability validation through the WordPress `admin-post.php` flow.
* Added server-side validation for campaign title, story, goal, currency and deadline.
* Frontend submissions are always created as pending campaigns owned by the current user.
* Added redirect-safe submission result messages and theme-friendly form styles.

= 0.2.0 =
* Added the dedicated Crowdfunding Promoter role.
* Added campaign-specific capabilities instead of relying on generic WordPress post permissions.
* Promoters can manage only their own unpublished campaigns and cannot publish directly.
* Administrators retain full campaign management capabilities.
* Added versioned role/capability upgrades so updates do not require plugin reactivation.

= 0.1.2 =
* Added `[crowdfunding_campaigns]` public campaign catalogue.
* Added `[crowdfunding_campaign]` campaign detail output.
* Added minimal responsive public styles designed to inherit the active theme.
* Public output intentionally exposes no funding progress until contributions become an authoritative domain.

= 0.1.1 =
* Added a secure campaign details editor in WordPress admin.
* Added ISO 4217 campaign currency metadata.
* Added nonce, capability and sanitization checks when saving campaign details.

= 0.1.0 =
* Initial product foundation.
* Added campaign content type and campaign goal/deadline metadata.
