=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic campaign domain for WordPress and is being built to support contributions, rewards, promoter dashboards and WooCommerce-based payment flows.

The plugin core is intentionally independent from any single crowdfunding platform or vertical implementation.

= Current 0.1.x foundation =

* Generic crowdfunding campaign post type.
* REST API support.
* Campaign funding goal and ISO currency metadata.
* Campaign deadline metadata.
* Secure campaign details editor in WordPress admin.
* Public campaign catalogue and campaign detail shortcodes.
* Minimal theme-friendly public styles.
* Safe activation and deactivation routines.
* Translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding** in the WordPress admin menu.
4. Create or edit a campaign and fill in its funding goal, currency and deadline.
5. Publish a page with `[crowdfunding_campaigns]` to display the campaign catalogue.
6. Use `[crowdfunding_campaign id="123"]` to embed one published campaign, or use `[crowdfunding_campaign]` inside a campaign context.

== Shortcodes ==

= [crowdfunding_campaigns] =

Displays published campaigns. Optional attributes:

* `per_page` — number of campaigns, from 1 to 24. Default: 6.
* `order` — `ASC` or `DESC`. Default: `DESC`.

= [crowdfunding_campaign] =

Displays one published campaign. Use the optional `id` attribute to select a campaign.

== Frequently Asked Questions ==

= Does version 0.1.2 process payments? =

No. The 0.1.x line establishes the campaign domain and public campaign experience. WooCommerce contribution flows are planned for the 0.3.x line.

= Why is there no raised amount or progress bar yet? =

Those values will be introduced together with the contribution domain so that public funding metrics always come from real contribution records.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

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
