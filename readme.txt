=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.1.1
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
* Safe activation and deactivation routines.
* Translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Open **Crowdfunding** in the WordPress admin menu.
4. Create or edit a campaign and fill in its funding goal, currency and deadline.

== Frequently Asked Questions ==

= Does version 0.1.1 process payments? =

No. The 0.1.x line establishes the campaign domain and campaign editing experience. WooCommerce contribution flows are planned for the 0.3.x line.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.1.1 =
* Added a secure campaign details editor in WordPress admin.
* Added ISO 4217 campaign currency metadata.
* Added nonce, capability and sanitization checks when saving campaign details.

= 0.1.0 =
* Initial product foundation.
* Added campaign content type and campaign goal/deadline metadata.
