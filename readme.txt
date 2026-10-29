=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.4.6
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
* Authoritative net raised amount derived from confirmed contributions minus applied refund adjustments.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Immutable contribution amounts plus a separate versioned refund-adjustment ledger.
* Generic versioned rewards domain linked to campaigns.
* Authenticated frontend reward management and public reward cards.
* Versioned reward-claims ledger with active/reversed states and source/reference idempotency.
* Finite reward limits derived from active claims instead of mutable stock counters.
* Public finite reward availability derived from active claims.
* Finite availability shown on public reward cards and in the contribution selector.
* Optional reward selection in the WooCommerce-backed contribution form.
* Classic-cart and Store API / Checkout Blocks reward validation.
* Explicit `_cfwp_reward_id` order-item marker for selected rewards.
* A separate WooCommerce reward bridge that creates a Core claim only after the contribution reaches `confirmed`.
* Reward eligibility derived from the contribution amount net of applied refunds.
* Partial refunds automatically reverse reward claims when net contribution drops below the reward minimum.
* Reversed refund adjustments can safely reactivate qualifying claims after full eligibility and capacity checks.
* Cancelled or fully refunded contributions reverse their linked reward claim.
* HPOS-safe WooCommerce order/refund integration through CRUD objects.
* Safe activation, versioned upgrades and translation-ready strings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from WordPress.
2. Activate **Crowdfunding WordPress**.
3. Install and configure WooCommerce if the site will collect contributions.
4. Open **Crowdfunding** in the WordPress admin menu.
5. Create or edit a campaign and fill in its funding goal, currency and deadline.
6. Ensure each campaign currency matches the WooCommerce store currency.
7. Publish a page with `[crowdfunding_campaigns]` for the campaign catalogue.
8. Use `[crowdfunding_campaign id="123"]` to embed one published campaign.
9. Native campaign permalinks automatically show progress, enabled rewards and the contribution form.
10. Use `[crowdfunding_progress id="123"]`, `[crowdfunding_rewards id="123"]` or `[crowdfunding_contribute id="123"]` when those blocks are needed independently.
11. Enable WordPress user registration if public promoter onboarding is required, then publish `[crowdfunding_promoter_register]`.
12. Publish `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/" rewards_url="/manage-rewards/"]` for the promoter workflow.
13. Publish `[crowdfunding_manage_rewards]` on a promoter-only page for reward management.

== Contributions and adjustments ==

Each contribution stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Original contribution amounts are immutable. Refunds are separate `cfwp_contribution_adjustments` records with their own idempotency key and `applied` / `reversed` lifecycle.

An applied refund can never exceed the contribution amount still available to refund. `net_amount_for_contribution()` derives the current contribution value as original amount minus applied refund adjustments. Public progress uses confirmed contributions net of applied refunds.

== Rewards ==

Rewards are generic Core records, not WooCommerce products. They belong to exactly one crowdfunding campaign and have a stable ID, title, description, minimum contribution amount, optional claim limit, enabled state and explicit sort order.

A reward inherits the campaign currency. `claim_limit = 0` means unlimited. The configured limit is definition authority; actual usage and remaining finite capacity are derived from active reward claims, not a mutable stock field.

`[crowdfunding_manage_rewards]` allows authenticated promoters to create, edit, sort and activate/deactivate rewards only on their own `draft` or `pending` campaigns. `[crowdfunding_rewards]` renders enabled rewards for published campaigns and shows the derived remaining count for finite rewards. Unlimited rewards do not expose a numeric availability counter.

== Reward claims ==

The versioned `cfwp_reward_claims` ledger links a stable reward ID to a stable contribution ID. Claims use `active` and `reversed` states and have their own `source + source_reference` idempotency authority.

A new claim requires an enabled reward and a `confirmed` contribution from the same campaign, in the campaign currency, whose current net amount meets the reward minimum. Finite limits are enforced inside a transaction while the reward row is locked. `remaining_for_reward()` returns `null` for unlimited rewards and otherwise derives `max(0, claim_limit - active claims)`.

Claim reversal preserves history; claims are never deleted as part of their normal lifecycle. Version 0.4.5 also permits controlled reactivation of the same historical claim. Reactivation locks the reward row, revalidates confirmed status, campaign, currency, enabled state, net minimum and finite capacity, then returns the existing claim to `active` rather than creating a new claim row.

== Reward availability ==

Version 0.4.6 makes finite reward availability visible without introducing a second stock authority. Public reward cards and the contribution selector both call `Reward_Claim_Repository::remaining_for_reward()`.

Finite rewards show their current derived remaining count. Zero means unavailable and disables selection in the contribution form. Unlimited rewards return `null`, remain selectable and do not show a numeric availability count.

No availability value is stored on the reward definition or copied into WooCommerce. Reversing or reactivating claims therefore changes visible capacity automatically on the next render.

== Reward selection and WooCommerce bridge ==

`[crowdfunding_contribute]` can include an optional reward selection. Only enabled rewards for the current campaign are offered. Finite rewards show their derived current availability and zero-capacity rewards are disabled in the selector.

Reward selection is not a reservation. The server revalidates reward ownership, enabled state, minimum contribution and current finite capacity when the contribution enters the cart and again during both classic WooCommerce cart validation and Store API / Checkout Blocks validation.

The selected reward is transported on the crowdfunding cart/order line as `_cfwp_reward_id`. That order-item marker is evidence, not claim authority.

The separate `WooCommerce_Reward_Adapter` listens to the Core `cfwp_woocommerce_contribution_synced` event. It creates a reward claim only when the contribution status is `confirmed`, using `order:{order_id}:item:{item_id}:reward:{reward_id}` as the stable claim source reference.

If the finite reward fills between cart validation and payment confirmation, the transactional Core claim insert fails closed and emits `cfwp_woocommerce_reward_claim_sync_error`; a successful payment does not fabricate an entitlement.

When the corresponding contribution becomes `cancelled` or fully `refunded`, an existing active WooCommerce-linked reward claim is reversed.

Version 0.4.5 extends this lifecycle to partial refunds. When an applied refund adjustment reduces the confirmed contribution's net amount below the selected reward minimum, the existing active claim is reversed. If that refund adjustment is later reversed, the same claim can reactivate only if the contribution remains confirmed, the reward is still enabled, the net amount again meets the minimum and finite capacity is still available. Reactivation is serialized under the reward-row lock, so restoring a refund cannot overbook a finite reward.

== Funding progress ==

Public progress uses `Contribution_Adjustment_Repository::net_confirmed_total_for_campaign()`. It never reads a manually editable raised amount or WooCommerce order total as authority.

The textual percentage can exceed 100% when a campaign overfunds. The visual progress bar is capped at 100% while preserving the real percentage in text and accessible progress semantics.

== WooCommerce refunds ==

WooCommerce refund lifecycle events are consumed through CRUD objects. Line-attributed partial refunds follow `_refunded_item_id` back to the original crowdfunding line. Ambiguous partial refunds fail closed rather than being distributed heuristically. Deleted WooCommerce refunds reverse their crowdfunding adjustments instead of deleting audit history.

Adjustment creation and reversal events feed reward entitlement reconciliation. The reward claim remains a Core record; WooCommerce refund objects do not directly edit claim rows.

== WooCommerce checkout ==

A published campaign can render `[crowdfunding_contribute]`. The server validates nonce, campaign status, deadline, minimum amount, campaign/store currency and optional reward selection, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product as a transport line only. Contribution lines are quantity-locked, tax-free and excluded from coupons. Checkout does not write the contribution ledger or reward-claims ledger directly.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns. Promoters cannot publish campaigns, edit published campaigns or manage campaigns owned by other users.

Administrators retain full authority over review, publication and lifecycle management.

== Shortcodes ==

= [crowdfunding_campaigns] =
Displays published campaigns. Optional `per_page` (1–24) and `order` (`ASC` or `DESC`).

= [crowdfunding_campaign] =
Displays one published campaign, including its enabled reward definitions. Optional `id` selects the campaign.

= [crowdfunding_progress] =
Displays the authoritative net confirmed raised amount, goal, percentage and progress bar. Optional `id` and `compact="yes"`.

= [crowdfunding_rewards] =
Displays enabled reward definitions for one published campaign, including derived remaining availability for finite rewards. Optional `id` selects the campaign.

= [crowdfunding_contribute] =
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign. Enabled rewards can be selected optionally; finite rewards show their current derived availability and are revalidated server-side.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. Accepted submissions are always pending review.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Only draft and pending campaigns are editable.

= [crowdfunding_manage_rewards] =
Displays authenticated reward management for one owned draft/pending campaign.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns. Optional `per_page`, `edit_url` and `rewards_url` attributes.

== Frequently Asked Questions ==

= Are rewards WooCommerce products? =

No. Reward definitions and claims belong to the generic crowdfunding Core. WooCommerce transports the selected reward and payment/order lifecycle evidence.

= Does selecting a reward reserve one immediately? =

No. Selection is validated before checkout but is not a reservation. The authoritative claim is created only when the Core contribution becomes confirmed.

= Where does the public availability number come from? =

For finite rewards it is derived at render time as `claim_limit - active claims` through `Reward_Claim_Repository::remaining_for_reward()`. It is not stored as stock on the reward or in WooCommerce.

= What happens if the last reward is claimed while another supporter is paying? =

The Core claim insert enforces the finite limit transactionally. If no capacity remains at confirmation time, claim creation fails closed and emits a synchronization error rather than creating an invalid entitlement.

= What happens after a partial refund? =

The contribution's current net amount is recalculated from immutable contribution value minus applied refund adjustments. If the net amount falls below the reward minimum, the active claim is reversed while its historical row is preserved.

= What happens if that partial refund is later reversed? =

The existing claim can reactivate only if the contribution is still confirmed, the reward is enabled, the net amount again meets the minimum and the reward still has capacity. Reactivation uses the same reward-row lock as finite claim creation.

= What happens when a contribution with a reward is cancelled or fully refunded? =

The linked active reward claim is marked `reversed`, preserving history and restoring derived finite capacity.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund/reward bridges use WooCommerce CRUD objects instead of direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.4.6 =
* Added public finite reward availability derived exclusively from active Core claims.
* Added remaining-count output to public reward cards for finite rewards.
* Added remaining-count output to the contribution reward selector.
* Unlimited rewards remain counter-free and zero-capacity finite rewards remain unavailable.
* Kept `claim_limit` as configuration and avoided mutable stock or duplicated availability fields.

= 0.4.5 =
* Added net-per-contribution reward eligibility using applied refund adjustments.
* Added automatic reward-claim reversal when a partial refund drops net contribution below the reward minimum.
* Added safe reactivation of the same historical claim when refund adjustments are reversed.
* Reactivation revalidates confirmed status, reward enablement, campaign/currency consistency, net minimum and finite capacity.
* Finite claim reactivation is serialized under the reward-row transaction lock.
* Added per-contribution adjustment-reversal events for deterministic entitlement reconciliation.

= 0.4.4 =
* Added optional reward selection to the contribution form.
* Added reward/campaign/amount/finite-capacity validation before cart entry.
* Added classic WooCommerce cart and Store API / Checkout Blocks reward validation.
* Added `_cfwp_reward_id` transport on crowdfunding order lines.
* Added a separate WooCommerce reward bridge from confirmed Core contributions to idempotent Core claims.
* Added claim reversal when the linked contribution becomes cancelled or fully refunded.
* Added fail-closed handling for exhausted finite rewards at confirmation time.
* Kept checkout itself free of direct claim writes.

= 0.4.3 =
* Added the versioned `cfwp_reward_claims` Core ledger.
* Added active/reversed claim lifecycle, idempotency and concurrency-safe finite claim limits.

= 0.4.2 =
* Added public reward presentation for enabled rewards on published campaigns.

= 0.4.1 =
* Added secure frontend reward management for owned draft/pending campaigns.

= 0.4.0 =
* Added the versioned generic rewards domain and `Reward_Repository`.

= 0.3.4 =
* Added immutable refund adjustments and net confirmed campaign progress.

= 0.3.3 =
* Added authoritative public campaign progress.

= 0.3.2 =
* Added secure campaign-to-WooCommerce contribution checkout.

= 0.3.1 =
* Added HPOS-safe WooCommerce contribution synchronization.

= 0.3.0 =
* Added the generic contribution ledger.

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
