=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic campaign domain for WordPress and is being built to support contributions, rewards, promoter dashboards and WooCommerce-based payment flows.

The plugin core is intentionally independent from any single crowdfunding platform or vertical implementation.

= Current 0.2.x foundation =

* Generic crowdfunding campaign post type.
* REST API support.
* Campaign funding goal and ISO currency metadata.
* Campaign deadline metadata.
* Secure campaign details editor in WordPress admin.
* Public campaign catalogue and campaign detail shortcodes.
* Minimal theme-friendly public styles.
* Dedicated Crowdfunding Promoter role.
* Campaign-specific capability model isolated from normal WordPress posts.
* Promoters can create and edit their own unpublished campaigns but cannot publish or manage campaigns owned by other users.
* Versioned role/capability installation for safe plugin upgrades.
* Safe activation and deactivation routines.
* Translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding** in the WordPress admin menu.
4. Create or edit a campaign and fill in its funding goal, currency and deadline.
5. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
6. Use `[crowdfunding_campaign id="123"]` to embed one published campaign, or use `[crowdfunding_campaign]` inside a campaign context.

== Promoters ==

Version 0.2.0 introduces the `cfwp_promoter` role. Promoters can create and edit their own draft or pending campaigns and upload campaign media. They cannot publish campaigns, edit published campaigns, access private campaigns or manage campaigns owned by other users.

Administrators retain full authority over campaign review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =

Displays published campaigns. Optional attributes:

* `per_page` — number of campaigns, from 1 to 24. Default: 6.
* `order` — `ASC` or `DESC`. Default: `DESC`.

= [crowdfunding_campaign] =

Displays one published campaign. Use the optional `id` attribute to select a campaign.

== Frequently Asked Questions ==

= Does version 0.2.0 process payments? =

No. The 0.2.x line introduces promoter workflows on top of the campaign domain. WooCommerce contribution flows are planned for the 0.3.x line.

= Can a promoter publish a campaign directly? =

No. Promoters can prepare and submit their own campaigns, while publication remains under administrator control.

= Why is there no raised amount or progress bar yet? =

Those values will be introduced together with the contribution domain so that public funding metrics always come from real contribution records.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

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
