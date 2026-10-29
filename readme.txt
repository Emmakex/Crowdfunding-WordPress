=== Crowdfunding WordPress ===
Contributors: kairoseth
Tags: crowdfunding, fundraising, woocommerce, campaigns, rewards
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.6.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage crowdfunding campaigns directly in WordPress. Built by Kairoseth.

== Description ==

Crowdfunding WordPress provides a generic crowdfunding domain for WordPress with campaigns, independent campaign moderation, promoters, contributions, refund-safe accounting, rewards, reward claims, fulfillment lifecycle, account dashboards, campaign statistics, promoter portfolio statistics, WooCommerce checkout integration and public funding progress.

The Core remains independent from any single crowdfunding platform, payment gateway or vertical implementation.

= Current foundation =

* Generic crowdfunding campaign post type with REST support.
* Campaign funding goal, ISO currency and deadline metadata.
* Independent Core campaign moderation state separate from WordPress editorial post status.
* `submitted`, `in_review`, `approved` and `rejected` moderation states with an explicit transition graph.
* Dedicated `moderate_cfwp_campaigns` authority granted to administrators and explicitly denied to promoters.
* New frontend campaign submissions remain WordPress `pending` while independently entering Core moderation as `submitted`.
* Versioned, bounded moderation backfill for eligible pre-0.6.0 campaigns that still lack moderation metadata.
* Legacy WordPress `pending` campaigns map to Core `submitted`; legacy `publish` campaigns are grandfathered as Core `approved` for compatibility.
* Draft/private/trash campaigns and campaigns with any existing moderation metadata are never rewritten by the compatibility backfill.
* Transactional administrator moderation decision authority for `in_review`, `approved` and `rejected` transitions.
* Immutable `cfwp_campaign_moderation_events` decision ledger with moderator user ID, source/reference idempotency and chronological reads.
* Moderation decisions update Core state and audit history atomically while remaining unable to publish or unpublish WordPress content.
* Native WordPress moderation queue for `submitted` and `in_review` campaigns.
* Campaign moderation detail with repository-governed decisions and immutable decision history.
* Nonce, capability and per-form UUID idempotency protection for every administrative moderation request.
* Rejected campaign owners can edit their still-pending campaign and explicitly resubmit it back to Core `submitted` without moderator authority.
* Moderation-aware campaign content locking prevents non-moderators from editing while Core state is `submitted`, `in_review` or `approved`.
* The editing lock is enforced through WordPress `edit_post` capability mapping plus frontend render/POST reauthorization, so stale forms and REST/editor paths cannot bypass it.
* `rejected` campaigns remain editable so their owner can make changes before explicit resubmission.
* Campaigns that have entered Core moderation can newly enter WordPress `publish`, `future` or `private` only when the single valid Core state is `approved`.
* Invalid or duplicated moderation metadata fails closed for new publication attempts, while campaigns with no moderation metadata retain editorial compatibility.
* REST publication attempts return an explicit conflict when Core moderation is not publication-eligible.
* Non-moderators cannot delete `submitted`, `in_review` or `approved` campaign snapshots; `rejected` remains correctable and removable.
* Promoter dashboard and editor surface WordPress editorial state separately from Core moderation state.
* Public campaign catalogue and campaign detail shortcodes.
* Authoritative net raised amount derived from confirmed contributions minus applied refund adjustments.
* Frontend promoter registration, campaign submission, editing and personal dashboard.
* Dedicated Crowdfunding Promoter role and campaign-specific capabilities.
* Versioned contribution ledger with source/reference idempotency.
* Immutable contribution amounts plus a separate versioned refund-adjustment ledger.
* Authenticated current-user contribution dashboard derived from Core ledgers.
* Contribution dashboard net amounts derived from applied refund adjustments.
* Contribution dashboard reward entitlement derived from Core reward claims.
* Authenticated campaign-owner statistics with aggregate contribution and reward-claim counts.
* Campaign statistics reuse authoritative refund-adjusted net funding totals and never expose supporter rows.
* Bounded campaign contribution trends grouped by day, week or month across selectable date ranges.
* Trend net values use current confirmed/refund-adjusted Core facts attributed to contribution creation periods.
* Aggregate campaign fulfillment counts by pending, processing, fulfilled and cancelled Core state.
* Pending-delivery workload and completion rate derived exclusively from Core fulfillment records.
* Read-only fulfillment reconciliation health diagnostics scoped to authorized campaigns.
* Aggregate lifecycle-drift and post-fulfillment entitlement-reversal counts without supporter rows or repair actions.
* Authenticated current-promoter portfolio statistics across every owned draft, pending and published campaign.
* Portfolio campaign counts, contributions, active entitlements, pending delivery and reconciliation-health totals derived from existing Core authorities.
* Portfolio net raised grouped by campaign currency so unlike currencies are never cross-summed.
* Generic versioned rewards domain linked to campaigns.
* Authenticated frontend reward management and public reward cards.
* Versioned reward-claims ledger with active/reversed states and source/reference idempotency.
* Finite reward limits and public availability derived from active claims instead of mutable stock counters.
* Optional reward selection in the WooCommerce-backed contribution form.
* Classic-cart and Store API / Checkout Blocks reward validation.
* Reward eligibility derived from the contribution amount net of applied refunds.
* Safe reward-claim reversal/reactivation after refunds.
* Versioned reward-fulfillment state, one record per claim.
* Immutable reward-fulfillment transition events with source/reference idempotency.
* Automatic claim-lifecycle synchronization into fulfillment state.
* Bounded, idempotent backfill for historical active claims that predate fulfillment synchronization.
* Authenticated campaign-scoped fulfillment management for promoters and administrators.
* Read-only campaign-scoped fulfillment audit timelines from immutable transition events.
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
12. Publish `[crowdfunding_submit_campaign]`, `[crowdfunding_edit_campaign]` and `[crowdfunding_promoter_dashboard edit_url="/edit-campaign/" rewards_url="/manage-rewards/" fulfillment_url="/manage-fulfillment/" statistics_url="/campaign-statistics/"]` for the promoter workflow.
13. Publish `[crowdfunding_manage_rewards]` on a promoter-only page for reward management.
14. Publish `[crowdfunding_manage_fulfillment]` on an authenticated page for campaign-scoped delivery operations.
15. Add `[crowdfunding_fulfillment_audit]` to the same page or another authenticated page when immutable fulfillment history should be visible.
16. Publish `[crowdfunding_contribution_dashboard]` on an authenticated account page so signed-in users can review their own account-linked contributions.
17. Publish `[crowdfunding_campaign_statistics]` on an authenticated promoter page to show aggregate Core statistics, fulfillment workload and contribution trends for one authorized campaign.
18. Add `[crowdfunding_fulfillment_health]` to an authenticated promoter/admin page when aggregate entitlement-versus-fulfillment reconciliation health should be monitored.
19. Publish `[crowdfunding_promoter_portfolio]` on an authenticated promoter page for an executive aggregate view across every campaign owned by the current account.
20. Administrators with `moderate_cfwp_campaigns` can use **Crowdfunding → Moderation** to review submitted/in-review campaigns and record Core decisions.
21. When an owned campaign is rejected, the promoter can edit it on `[crowdfunding_edit_campaign]` and use the explicit **Resubmit campaign for review** action after saving any required changes.

== Campaign moderation ==

Version 0.6.0 introduces an independent Core moderation authority stored as `_cfwp_moderation_status`. Its states are `submitted`, `in_review`, `approved` and `rejected`. This state does not replace WordPress `post_status`: WordPress remains responsible for editorial publication and visibility.

New frontend submissions therefore keep `post_status = pending` and independently receive Core moderation state `submitted`. Moderation never automatically publishes, unpublishes or changes campaign visibility in this foundation.

The initial state graph is: no moderation state → `submitted`; `submitted` → `in_review`, `approved` or `rejected`; `in_review` → `approved` or `rejected`; `rejected` → `submitted`; `approved` is terminal in the current foundation.

The dedicated `moderate_cfwp_campaigns` capability belongs to administrators. Promoters are explicitly denied that capability even though they may edit their own draft/pending campaigns and manage fulfillment through separate authorities.

Version 0.6.1 adds a separate compatibility backfill for campaigns that predate the moderation authority and still have no `_cfwp_moderation_status`. It processes at most 50 campaigns per request by default, is filterable with a hard maximum of 200, and records completion only when no eligible legacy row remains.

Legacy WordPress `pending` campaigns are classified as Core `submitted`. Legacy WordPress `publish` campaigns are grandfathered as Core `approved` so already-public campaigns remain compatible with the new moderation model. This grandfathered `approved` value is a compatibility classification, not evidence that Crowdfunding WordPress historically recorded an approval decision.

Draft, private, trash and other editorial states are not inferred into moderation. Any campaign that already has moderation metadata—including invalid metadata requiring administrator review—is skipped rather than overwritten. The bootstrap uses unique meta insertion and rechecks concurrent writes, while leaving WordPress `post_status` untouched.

Version 0.6.2 adds `Campaign_Moderation_Repository` as the single transactional authority for administrator decisions. It accepts only `in_review`, `approved` or `rejected` as moderator targets and requires the `moderate_cfwp_campaigns` capability plus a stable source/reference idempotency key. `rejected → submitted` remains a promoter resubmission concern rather than an administrator decision.

Every successful decision updates the current moderation meta and inserts one immutable row into `cfwp_campaign_moderation_events` inside the same transaction. Events record campaign ID, moderator WordPress user ID, previous state, target state, source, stable source reference and UTC timestamp. Repeated calls with the same source/reference resolve idempotently only when they refer to the same campaign and target state.

The decision repository fails closed on missing, duplicated or invalid moderation metadata, locks the campaign/state rows while deciding, and exposes chronological event reads ordered by event ID. The ledger begins with actual decisions recorded from 0.6.2 onward: submission/bootstrap compatibility state is not retroactively rewritten into fake decision events. No moderation decision can publish, unpublish or otherwise mutate WordPress editorial `post_status`.

Version 0.6.3 adds the native WordPress **Crowdfunding → Moderation** operations UI. The queue is capability-gated and contains only campaigns whose current Core state is `submitted` or `in_review`, with pagination so pending work is not hidden when volume grows.

Opening a queue item shows current Core moderation state, separate WordPress editorial state, an explicit link to the campaign editor, the administrator decisions currently allowed by `Campaign_Moderation::can_decide_transition()`, and the immutable decision history from the 0.6.2 ledger. The editorial modified timestamp is labelled only as content activity and is never presented as a historical submission time.

Every decision form is nonce-protected, requires `moderate_cfwp_campaigns`, and carries a fresh UUID. The UI passes `admin_ui` plus that UUID-derived stable reference to `Campaign_Moderation_Repository::transition()`; it never writes moderation meta, event rows or WordPress post status directly. Double submission of the same rendered form therefore resolves through the repository idempotency contract rather than creating a second decision.

Approving in this UI changes only Core moderation state. Publishing/unpublishing remains a separate WordPress editorial action.

Version 0.6.4 adds the promoter-owned rejection and resubmission flow. A rejected campaign remains WordPress `pending`, so its owner can continue to edit it without any new editorial capability. Saving content does not alter the Core rejection state.

The editor shows the current Core moderation state separately and, only for an owned `pending + rejected` campaign, exposes a second nonce-protected **Resubmit campaign for review** form. The resubmission handler rechecks authentication, `edit_cfwp_campaigns`, campaign ownership, `edit_post`, editorial `pending` state and current Core `rejected` state before calling `Campaign_Moderation::mark_submitted()`.

Promoter resubmission never requires or grants `moderate_cfwp_campaigns`, never calls the administrator decision repository and never writes moderation meta or WordPress editorial status directly. Previous immutable administrator rejection events remain intact; the resubmission itself is a promoter lifecycle transition rather than a fabricated moderator-decision event.

The promoter dashboard now labels WordPress editorial status and Core moderation status separately. `submitted`, `in_review`, `rejected` and `approved` therefore produce distinct guidance instead of treating every WordPress `pending` campaign as merely awaiting review.

Version 0.6.5 adds a moderation-aware campaign editing lock. For users without `moderate_cfwp_campaigns`, campaign content is locked while Core moderation is `submitted`, `in_review` or `approved`. `rejected` remains editable so the owner can make corrections before explicitly resubmitting, and campaigns with no moderation state retain their existing draft/pending editing rules.

The lock is enforced through WordPress `map_meta_cap` for `edit_post` and is also rechecked by the frontend editor before rendering or accepting a POST. This protects the reviewed campaign snapshot across frontend, REST and stale-form paths without modifying `post_status`, moderation state or publication authority. Administrators with moderator authority are exempt from this promoter-facing lock and retain their separate WordPress editorial permissions.

Version 0.6.6 closes the moderation workflow boundary around WordPress publication and destructive deletion. Once an existing campaign has entered Core moderation, it may newly enter editorial `publish`, `future` or `private` only when exactly one valid moderation value exists and that value is `approved`. Invalid or duplicated moderation metadata therefore fails closed for publication.

This is an eligibility gate, not publication authority: an approval never calls a WordPress publication API and still requires the separate WordPress user capability and editorial action. Campaigns with no moderation metadata retain their existing compatibility, and already-published legacy content is never silently demoted by the gate.

The same rule is enforced through `wp_insert_post_data` for normal/programmatic WordPress writes and through an explicit REST conflict for API updates. Classic-editor attempts remain in their previous draft/pending state and surface an administrator notice. Non-moderators are also denied `delete_post` while the existing moderation editing lock is active; `rejected` campaigns remain outside that lock so owners can correct or remove them. This closes the 0.6.x moderation and campaign-workflow milestone without changing the state graph, moderator decision repository, WooCommerce or any crowdfunding financial/reward authority.

== Contributions and adjustments ==

Each contribution stores only the Core crowdfunding facts: campaign ID, optional WordPress user ID, source, source reference, exact decimal amount, ISO currency, status and timestamps. Payment/customer details remain with WooCommerce and the selected payment gateway.

The pair `source + source_reference` is unique and acts as an idempotency key. Original contribution amounts are immutable. Refunds are separate `cfwp_contribution_adjustments` records with their own idempotency key and `applied` / `reversed` lifecycle.

An applied refund can never exceed the contribution amount still available to refund. `net_amount_for_contribution()` derives the current contribution value as original amount minus applied refund adjustments. Public progress uses confirmed contributions net of applied refunds.

== Contribution dashboard ==

Version 0.5.0 adds `[crowdfunding_contribution_dashboard]`, an authenticated read-only account surface. It always scopes contribution reads to `get_current_user_id()` and does not accept a browser-supplied user ID.

The dashboard shows the associated campaign, contribution date and Core status, original amount, applied refund total, current net amount and any reward entitlement recorded in the Core reward-claims ledger. Pagination is local to the dashboard and does not change account identity.

The dashboard does not read WooCommerce orders, billing/shipping data or payment-gateway customer details. Technical `source` and `source_reference` identifiers are not exposed. Contributions recorded with `user_id = 0` remain guest contributions and are not heuristically attached to a signed-in account.

== Campaign statistics ==

Version 0.5.1 adds `[crowdfunding_campaign_statistics]`, an authenticated aggregate surface for campaign owners and administrators with cross-campaign authority.

The statistics surface shows authoritative net raised amount, funding goal, funded percentage, total contribution count, contribution counts by Core status and reward-claim counts by active/reversed state. Net funding reuses `Contribution_Adjustment_Repository::net_confirmed_total_for_campaign()` rather than introducing a second financial calculation.

Campaign selection can come from the shortcode `id` or `cfwp_campaign_id`, but access is always revalidated from the campaign record on the server. Promoters can view only campaigns they own; administrators may view other campaigns through `edit_others_cfwp_campaigns`.

The statistics query is aggregate and read-only. It never returns supporter rows, user IDs, emails, billing/shipping details, payment references or WooCommerce customer/order data.

Version 0.5.2 adds bounded contribution trends to the same statistics surface. Promoters can select a date range and group aggregate activity by day, week or month. The normalized range is capped at 366 days and invalid bucket values fall back to `day`.

Each trend bucket shows contributions created in that period, how many of those contributions are currently confirmed, and their current net confirmed value after current applied refund adjustments. The net value is attributed to contribution `created_at`; it is not presented as a reconstructed historical balance because Core does not yet keep an immutable event ledger for every contribution status transition.

`updated_at` is therefore not treated as evidence of when a contribution historically became confirmed, refunded or cancelled. Trend filters are read-only selectors and campaign authorization is completed before the aggregate query runs.

Version 0.5.3 adds aggregate reward-fulfillment statistics to the same authorized campaign surface. Counts come only from `cfwp_reward_fulfillments`, joined through reward claim and reward ownership to the campaign; WooCommerce order status is never used as fulfillment authority.

The surface shows `pending`, `processing`, `fulfilled` and `cancelled` counts. Pending delivery is `pending + processing`. The completion rate is `fulfilled / (pending + processing + fulfilled)` and deliberately excludes `cancelled` fulfillment from the denominator because cancelled entitlement no longer represents active delivery workload. These metrics are read-only and do not affect entitlement, reward availability or fulfillment lifecycle state.

Version 0.5.4 adds `[crowdfunding_fulfillment_health]`, a separate read-only aggregate diagnostic surface using the same campaign-owner/admin authority boundary. It compares current Core claim status with current Core fulfillment state and does not repair records automatically.

Health counts four disjoint conditions: active claims with no fulfillment, active claims whose fulfillment remains cancelled, reversed claims whose fulfillment is still pending/processing, and reversed claims whose fulfillment was already fulfilled. The first three are lifecycle drift; the last is a manual-review exception because fulfilled delivery is terminal and must not be undone automatically. No claim IDs, fulfillment IDs or supporter PII are returned.

Version 0.5.5 adds `[crowdfunding_promoter_portfolio]`, an authenticated current-account executive summary across all owned draft, pending and published campaigns. It accepts no account selector: portfolio identity comes exclusively from `get_current_user_id()`.

Portfolio totals reuse `Campaign_Statistics_Query` and `Fulfillment_Reconciliation_Health_Query` for each owned campaign. It reports campaign states, contributions, active entitlements, pending delivery, reconciliation-health issues and campaigns needing attention. Net raised values remain refund-adjusted Core totals and are grouped by campaign currency; unlike currencies are never added together.

== Rewards ==

Rewards are generic Core records, not WooCommerce products. They belong to exactly one crowdfunding campaign and have a stable ID, title, description, minimum contribution amount, optional claim limit, enabled state and explicit sort order.

A reward inherits the campaign currency. `claim_limit = 0` means unlimited. The configured limit is definition authority; actual usage and remaining finite capacity are derived from active reward claims, not a mutable stock field.

`[crowdfunding_manage_rewards]` allows authenticated promoters to create, edit, sort and activate/deactivate rewards only on their own `draft` or `pending` campaigns. `[crowdfunding_rewards]` renders enabled rewards for published campaigns and shows the derived remaining count for finite rewards.

== Reward claims ==

The versioned `cfwp_reward_claims` ledger links a stable reward ID to a stable contribution ID. Claims use `active` and `reversed` states and have their own `source + source_reference` idempotency authority.

A new claim requires an enabled reward and a `confirmed` contribution from the same campaign, in the campaign currency, whose current net amount meets the reward minimum. Finite limits are enforced transactionally. `remaining_for_reward()` returns `null` for unlimited rewards and otherwise derives `max(0, claim_limit - active claims)`.

Claim reversal preserves history. Controlled reactivation revalidates confirmed status, campaign, currency, enabled state, net minimum and finite capacity before returning the same historical claim to `active`.

== Reward availability ==

Finite reward availability is visible without introducing a second stock authority. Public reward cards and the contribution selector both call `Reward_Claim_Repository::remaining_for_reward()`.

Finite rewards show their current derived remaining count. Zero means unavailable and disables selection in the contribution form. Unlimited rewards return `null`, remain selectable and do not show a numeric availability count.

== Reward fulfillment ==

Reward claims and fulfillment remain separate authorities. A claim answers whether the supporter currently has the entitlement; a fulfillment record answers the operational delivery state.

Each claim can have at most one row in `cfwp_reward_fulfillments`. The current state is one of `pending`, `processing`, `fulfilled` or `cancelled`. Every real creation/transition is recorded in the immutable `cfwp_reward_fulfillment_events` ledger with `source + source_reference` idempotency.

A newly created active claim idempotently gets one `pending` fulfillment. Reversing a claim automatically moves `pending` or `processing` fulfillment to `cancelled`. Reactivating that claim returns the same cancelled fulfillment to `pending`; it never creates a second delivery record.

Repeated reversal/reactivation cycles use a fresh event-sequence-based source reference so an old transition cannot masquerade as a later successful transition. `fulfilled` remains terminal: if entitlement is reversed after delivery, the plugin does not automatically undo delivery and instead emits `cfwp_reward_fulfillment_sync_error` for manual review.

Version 0.4.9 adds a bounded historical reconciliation pass for active claims that were created before the fulfillment lifecycle bridge existed. The reconciler selects only active claims with no fulfillment, processes them through the same idempotent `ensure_for_claim()` authority, and records completion only when no missing active claim remains. Failed claims are left untouched for retry and emit `cfwp_reward_fulfillment_backfill_error`.

Backfill runs after the fulfillment schema installer, processes at most 50 claims per request by default (filterable and hard-capped at 200), never mutates claim rows and has no WooCommerce dependency.

Version 0.4.10 adds a dedicated `manage_cfwp_fulfillments` capability and `[crowdfunding_manage_fulfillment]`. Promoters may operate fulfillment only for campaigns they own; administrators can supervise other campaigns through their existing cross-campaign authority. This capability does not grant promoters permission to edit published campaign content.

The management UI derives campaign ownership from fulfillment → claim → reward on the server. A browser-supplied fulfillment ID is only a selector. Manual operations are deliberately restricted to `pending → processing`, `pending → fulfilled` and `processing → fulfilled`. `cancelled` remains controlled by entitlement lifecycle and `fulfilled` remains terminal.

Fulfillment management calls `Reward_Fulfillment_Repository::transition()` and therefore preserves immutable transition events. The UI and its read-only query layer do not integrate with WooCommerce and store no shipping, billing, tracking or customer PII.

Version 0.4.11 adds `[crowdfunding_fulfillment_audit]`, a separate read-only timeline for authorized campaign fulfillment history. It uses the same owner/admin campaign boundary as fulfillment management but registers no write action.

The audit timeline reads immutable events in event-ID order and shows the human-readable transition, event time, origin, stable source reference and event ID. It does not create a second audit store, cannot change fulfillment or entitlement state and has no WooCommerce or customer-PII dependency.

== Reward selection and WooCommerce bridge ==

`[crowdfunding_contribute]` can include an optional reward selection. Only enabled rewards for the current campaign are offered. Finite rewards show derived availability and zero-capacity rewards are disabled.

Reward selection is not a reservation. The server revalidates reward ownership, enabled state, minimum contribution and current finite capacity before checkout and during WooCommerce cart validation.

The selected reward is transported on the crowdfunding cart/order line as `_cfwp_reward_id`. That order-item marker is evidence, not claim or fulfillment authority.

The separate `WooCommerce_Reward_Adapter` creates a reward claim only after the contribution status is `confirmed`. The generic fulfillment components listen to or inspect Core claim state only; they do not read WooCommerce orders.

== Funding progress ==

Public progress uses `Contribution_Adjustment_Repository::net_confirmed_total_for_campaign()`. It never reads a manually editable raised amount or WooCommerce order total as authority.

The textual percentage can exceed 100% when a campaign overfunds. The visual progress bar is capped at 100% while preserving the real percentage in text and accessible progress semantics.

== WooCommerce refunds ==

WooCommerce refund lifecycle events are consumed through CRUD objects. Line-attributed partial refunds follow `_refunded_item_id` back to the original crowdfunding line. Ambiguous partial refunds fail closed rather than being distributed heuristically. Deleted WooCommerce refunds reverse their crowdfunding adjustments instead of deleting audit history.

Adjustment creation and reversal events feed reward entitlement reconciliation. WooCommerce refund objects do not directly edit claim or fulfillment rows.

== WooCommerce checkout ==

A published campaign can render `[crowdfunding_contribute]`. The server validates nonce, campaign status, deadline, minimum amount, campaign/store currency and optional reward selection, then adds a marked crowdfunding line to the WooCommerce cart and redirects to checkout.

The checkout uses a hidden virtual WooCommerce product as a transport line only. Contribution lines are quantity-locked, tax-free and excluded from coupons. Checkout does not write contribution, reward-claim or fulfillment ledgers directly.

== Promoters ==

The `cfwp_promoter` role can create and edit its own draft or pending campaigns only while Core moderation does not lock the campaign snapshot. Promoters cannot moderate or publish campaigns, edit published campaigns or manage campaigns owned by other users.

For non-moderators, `submitted`, `in_review` and `approved` campaign content is locked through the normal WordPress `edit_post` capability path. The same locked states cannot be deleted by a promoter. A promoter whose own `pending` campaign is Core `rejected` can still save editorial changes, delete it if desired, or explicitly resubmit that campaign to `submitted`. This promoter path does not grant `moderate_cfwp_campaigns`, cannot approve/reject campaigns and does not change WordPress publication state.

Administrators retain the dedicated `moderate_cfwp_campaigns` authority as well as publication and cross-campaign fulfillment/statistics supervision. For campaigns that have entered moderation, WordPress publication remains a separate administrator action but Core `approved` is a prerequisite.

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
Displays the WooCommerce-backed contribution form for a published, open campaign. Optional `id` selects the campaign. Enabled rewards can be selected optionally and are revalidated server-side.

= [crowdfunding_contribution_dashboard] =
Displays the authenticated current user's account-linked contributions, net refund-adjusted amount and Core reward entitlement. Optional `per_page` accepts 1–24. Account identity always comes from the current WordPress session.

= [crowdfunding_campaign_statistics] =
Displays aggregate contribution, reward-entitlement and fulfillment statistics plus bounded day/week/month contribution trends for one authorized campaign. Optional `id` selects the campaign; otherwise it uses `cfwp_campaign_id`. Promoters can view only owned campaigns and no supporter-level rows are returned.

= [crowdfunding_fulfillment_health] =
Displays aggregate read-only entitlement-versus-fulfillment reconciliation health for one authorized campaign. Optional `id` selects the campaign; otherwise it uses `cfwp_campaign_id`. It reports lifecycle drift and terminal post-delivery reversal exceptions without exposing supporter or record-level identities.

= [crowdfunding_promoter_portfolio] =
Displays aggregate statistics across every draft, pending and published campaign owned by the current signed-in account. It accepts no user/account selector and reports net raised separately by campaign currency.

= [crowdfunding_promoter_register] =
Displays promoter registration when WordPress user registration is enabled.

= [crowdfunding_submit_campaign] =
Displays the authenticated promoter campaign submission form. New submissions remain WordPress `pending` and independently enter Core moderation as `submitted`.

= [crowdfunding_edit_campaign] =
Displays the editor for one campaign owned by the current user. Draft/pending editorial state is necessary but not sufficient: for non-moderators, Core `submitted`, `in_review` and `approved` are locked. An owned `pending + rejected` campaign remains editable and exposes a separate explicit resubmission form after editorial changes are saved.

= [crowdfunding_manage_rewards] =
Displays authenticated reward management for one owned draft/pending campaign.

= [crowdfunding_manage_fulfillment] =
Displays authenticated fulfillment operations for one authorized campaign. Promoters are restricted to campaigns they own; administrators may supervise other campaigns. The UI can advance only pending/processing delivery state and cannot manually cancel or reactivate entitlement-owned state.

= [crowdfunding_fulfillment_audit] =
Displays the immutable chronological fulfillment event history for one authorized campaign. Optional `id` selects the campaign; otherwise it uses `cfwp_campaign_id`. The timeline is read-only and exposes event origin, stable source reference and event ID.

= [crowdfunding_promoter_dashboard] =
Displays the current user's draft, pending and published campaigns with WordPress editorial status and Core moderation status shown separately. Optional `per_page`, `edit_url`, `rewards_url`, `fulfillment_url` and `statistics_url` attributes. The campaign edit link is hidden while the moderation editing lock is active.

== Frequently Asked Questions ==

= Is campaign moderation the same as WordPress post status? =

No. Core moderation is separate from WordPress editorial state. A campaign can be WordPress `pending` while Core moderation is `submitted`, `in_review`, `approved` or `rejected`. Moderation does not publish or change visibility by itself.

= How are campaigns created before moderation existed handled? =

Version 0.6.1 performs a bounded compatibility backfill only for campaigns with no moderation metadata. Legacy `pending` maps to `submitted`; legacy `publish` is grandfathered as `approved`. Draft/private/trash campaigns are not inferred, and any existing moderation metadata is left untouched. Grandfathered `approved` is compatibility state, not proof of a historical review event.

= Are moderation decisions audited? =

Yes. From version 0.6.2, administrator transitions to `in_review`, `approved` or `rejected` are committed together with an immutable Core decision event containing the moderator user ID, previous/target states, source/reference and timestamp. Legacy backfill classifications and simple promoter submissions are not retroactively represented as moderator decisions.

= Where do administrators moderate campaigns? =

From version 0.6.3, administrators with `moderate_cfwp_campaigns` use **Crowdfunding → Moderation**. The queue contains current `submitted` and `in_review` campaigns, and each campaign detail shows repository-governed actions plus immutable decision history.

= Can a promoter resubmit a rejected campaign? =

Yes. From version 0.6.4, the owner of a WordPress `pending` campaign whose Core moderation state is `rejected` can edit it and then use the explicit resubmission form on `[crowdfunding_edit_campaign]`. The handler reauthorizes ownership and edit capability, then calls the normal `mark_submitted()` Core transition. It never grants moderator authority or changes `post_status`.

= When can a promoter edit a campaign that is in moderation? =

From version 0.6.5, non-moderators cannot edit campaign content while Core moderation is `submitted`, `in_review` or `approved`. The reviewed snapshot is protected through WordPress `edit_post` capability mapping and frontend POST reauthorization. `rejected` remains editable so the owner can correct it before explicitly resubmitting. This lock does not publish, unpublish or change moderation state.

= Does resubmitting erase the rejection history? =

No. The immutable administrator decision event that recorded the rejection remains in the moderation ledger. Resubmission changes the current Core state back to `submitted`; it is not recorded as a fake administrator decision.

= Does approving a campaign publish it? =

No. Core moderation and WordPress editorial publication remain separate. An `approved` moderation state never calls WordPress publish/unpublish APIs or changes `post_status` by itself.

= Can a campaign that entered moderation be published before approval? =

No. From version 0.6.6, an existing campaign with Core moderation metadata may newly enter WordPress `publish`, `future` or `private` only when exactly one valid moderation state exists and it is `approved`. Invalid or duplicated moderation metadata also blocks publication. Campaigns that never entered moderation retain their existing editorial compatibility, and already-published legacy campaigns are not silently demoted.

= Can the contribution dashboard show another user's contributions? =

No. The shortcode derives account identity exclusively from the signed-in WordPress user. It does not accept a user ID parameter from the browser.

= What happens to guest contributions? =

A contribution with `user_id = 0` remains a guest contribution. Version 0.5.0 does not guess account ownership from email, order or billing data.

= Are dashboard amounts read from WooCommerce orders? =

No. Original amount, applied refunds and net amount are read from the Crowdfunding WordPress contribution and adjustment ledgers. Reward entitlement is read from Core reward claims.

= Can campaign statistics identify supporters? =

No. Campaign statistics return aggregate counts and Core totals only. They do not return supporter rows, WordPress user IDs, emails, billing/shipping fields or WooCommerce customer/order data.

= Do campaign trend values reconstruct the amount raised on a past date? =

No. Version 0.5.2 groups contributions by their creation period and shows their current confirmed/refund-adjusted net value. Crowdfunding WordPress does not yet keep a complete immutable contribution-status event ledger, so `updated_at` is not used to invent historical confirmation/refund timing.

= How is the fulfillment completion rate calculated? =

Version 0.5.3 counts Core fulfillment records only. Pending delivery is `pending + processing`. Completion rate is `fulfilled / (pending + processing + fulfilled)`. Cancelled fulfillment is shown separately but excluded from the denominator because it is no longer active delivery workload.

= What does fulfillment reconciliation health detect? =

Version 0.5.4 checks aggregate Core consistency only. Lifecycle drift includes active claims missing fulfillment, active claims whose fulfillment is still cancelled, and reversed claims whose fulfillment is still pending or processing. A reversed claim whose fulfillment is already fulfilled is shown separately for manual review because delivery cannot be safely undone automatically.

= Does fulfillment health repair records? =

No. `[crowdfunding_fulfillment_health]` is intentionally read-only. It does not call fulfillment transitions, backfill or claim reconciliation and never uses WooCommerce order status as authority.

= Can promoter portfolio statistics be opened for another account? =

No. `[crowdfunding_promoter_portfolio]` derives its owner exclusively from `get_current_user_id()` and accepts no browser-supplied user/account selector. Administrators see their own portfolio on this surface rather than using it to impersonate another promoter.

= Why is portfolio net raised split by currency? =

Campaigns can use different currencies. Version 0.5.5 keeps each authoritative refund-adjusted campaign total in its campaign currency and groups matching currencies only; EUR, USD or other currencies are never added together as if they were comparable.

= Are rewards WooCommerce products? =

No. Reward definitions, claims and fulfillment belong to the generic crowdfunding Core. WooCommerce transports payment/order lifecycle evidence.

= Is a reward claim the same as fulfillment? =

No. A claim is entitlement. Fulfillment is the independent operational delivery lifecycle for that entitlement.

= Can a promoter cancel or reactivate fulfillment manually? =

No. `cancelled` and the return from `cancelled` to `pending` are controlled by the reward-claim lifecycle. The operational UI can only move active entitlement forward toward delivery.

= Can fulfillment management edit a published campaign? =

No. `manage_cfwp_fulfillments` is a separate capability. Promoters still cannot edit published campaign content.

= Can the fulfillment audit timeline change delivery state? =

No. `[crowdfunding_fulfillment_audit]` is read-only. It reads the immutable event ledger after campaign authorization and registers no write action.

= What happens when entitlement is reversed? =

A `pending` or `processing` fulfillment is automatically cancelled. If delivery was already marked `fulfilled`, the plugin never undoes delivery automatically and emits a synchronization error for manual review.

= What happens to active claims created before fulfillment existed? =

Version 0.4.9 reconciles them in bounded batches. Only active claims without a fulfillment are selected, and each passes through the normal idempotent fulfillment repository. The backfill is marked complete only after none remain.

= Where does the public availability number come from? =

For finite rewards it is derived at render time as `claim_limit - active claims` through `Reward_Claim_Repository::remaining_for_reward()`. Fulfillment state does not affect reward availability.

= Is the plugin HPOS compatible? =

Yes. WooCommerce order/refund/reward bridges use WooCommerce CRUD objects instead of direct order-table or post-table access.

= Is this plugin tied to CulturalFunding? =

No. Crowdfunding WordPress is a generic product. CulturalFunding is a reference implementation, not a dependency.

== Changelog ==

= 0.6.6 =
* Added publication eligibility for campaigns that have entered Core moderation: only a single valid `approved` state can newly enter WordPress `publish`, `future` or `private`.
* Invalid or duplicated moderation metadata now fails closed for new publication attempts.
* Preserved editorial compatibility for campaigns with no moderation metadata and never silently demotes already-published legacy content.
* Added an explicit REST conflict for blocked publication attempts and a classic-editor notice while preserving the previous draft/pending state.
* Added moderation-aware `delete_post` protection so non-moderators cannot remove `submitted`, `in_review` or `approved` campaign snapshots.
* Kept `rejected` campaigns editable/removable so owners can correct, abandon or explicitly resubmit them.
* Kept approval as eligibility only: Core moderation never invokes WordPress publish/schedule/private APIs.
* Closed the 0.6.x moderation and campaign-workflow milestone without changing WooCommerce, contribution, reward or fulfillment authorities.

= 0.6.5 =
* Added a moderation-aware campaign content editing lock for non-moderators while Core state is `submitted`, `in_review` or `approved`.
* Added global `edit_post` capability enforcement so REST, wp-admin/editor paths and stale frontend forms cannot bypass the lock.
* Kept `rejected` campaigns editable so owners can correct them before explicit resubmission.
* Added frontend render and POST lock reauthorization plus a specific locked-state error.
* Hid the promoter dashboard edit link while the campaign snapshot is locked and added state-specific lock guidance.
* Kept administrators with `moderate_cfwp_campaigns` exempt from the promoter-facing lock while preserving WordPress editorial capability checks.
* Kept the lock independent from moderation transitions, WordPress publication, rewards, WooCommerce, contributions and fulfillment.

= 0.6.4 =
* Added explicit promoter resubmission for owned WordPress `pending` campaigns whose Core moderation state is `rejected`.
* Added nonce, authentication, `edit_cfwp_campaigns`, ownership, `edit_post`, editorial-state and current-moderation-state reauthorization before resubmission.
* Reused `Campaign_Moderation::mark_submitted()` as the only state transition authority for `rejected → submitted`.
* Kept saving campaign content separate from resubmission so editing never silently changes moderation state.
* Added separate editorial and Core moderation labels plus state-specific guidance to the promoter dashboard.
* Added current moderation state and a dedicated resubmission form to the campaign editor.
* Preserved previous immutable administrator rejection events without fabricating a promoter action as a moderator-decision event.
* Kept promoter resubmission independent from `moderate_cfwp_campaigns`, administrator decision authority, WordPress publication and WooCommerce.

= 0.6.3 =
* Added native **Crowdfunding → Moderation** administrator operations UI.
* Added a paginated queue scoped to current Core `submitted` and `in_review` campaigns.
* Added campaign moderation detail with separate Core moderation and WordPress editorial state.
* Added repository-governed Start review, Approve and Reject actions based on `can_decide_transition()`.
* Added immutable decision-history presentation from the 0.6.2 moderation event ledger.
* Added nonce/capability checks plus per-form UUID source references for idempotent decision requests.
* Kept the UI free of direct moderation-meta, event-table or WordPress `post_status` writes.
* Approving continues to affect Core moderation only and never publishes/unpublishes campaign content.

= 0.6.2 =
* Added transactional administrator moderation decision authority for `in_review`, `approved` and `rejected` transitions.
* Added versioned immutable `cfwp_campaign_moderation_events` ledger with campaign, moderator user, from/to state, source/reference and timestamp.
* Added source/reference idempotency and conflict detection for repeated moderation decision requests.
* Added campaign/state row locking and atomic state-plus-event commits with rollback on failure.
* Added chronological decision-event reads without introducing a moderation UI in this cut.
* Kept `rejected → submitted` owned by promoter resubmission rather than moderator decision authority.
* Kept moderation decisions completely separate from WordPress publication, WooCommerce, contribution, reward and fulfillment authorities.
* Legacy backfill classifications and promoter submissions are not retroactively fabricated as moderator decision events.

= 0.6.1 =
* Added a versioned, bounded and retry-safe moderation compatibility backfill for pre-0.6.0 campaigns with no moderation metadata.
* Legacy WordPress `pending` campaigns map to Core `submitted` without changing editorial state.
* Legacy WordPress `publish` campaigns are grandfathered as Core `approved` for compatibility; this is not represented as evidence of a historical review decision.
* Draft, private, trash and other editorial states are not inferred into moderation.
* Existing valid or invalid moderation metadata is never overwritten by the backfill.
* Added unique insert-only legacy bootstrap authority with concurrent-write rechecks.
* Added completion/error hooks and a dedicated regression guard; WooCommerce, contribution, reward and fulfillment authorities remain untouched.

= 0.6.0 =
* Added independent Core campaign moderation status separate from WordPress editorial `post_status`.
* Added `submitted`, `in_review`, `approved` and `rejected` moderation states and an explicit transition graph.
* Added dedicated `moderate_cfwp_campaigns` capability for administrators and explicitly denied it to promoters.
* New frontend campaign submissions remain WordPress `pending` while entering Core moderation as `submitted`.
* Moderation state registration is not writable through REST and does not publish, unpublish or change campaign visibility.
* Existing campaigns are intentionally not backfilled or reclassified from historical WordPress post status in this cut.
* Kept moderation independent from WooCommerce, contributions, rewards and fulfillment.

= 0.5.5 =
* Added `[crowdfunding_promoter_portfolio]` as a current-account aggregate portfolio statistics surface.
* Added campaign totals and draft, pending and published portfolio counts.
* Added portfolio contribution totals, active reward entitlements and pending-delivery workload from existing Core campaign statistics authority.
* Added aggregate reconciliation-health issues, repairable drift, manual-review exceptions and campaigns needing attention.
* Added net raised totals grouped by campaign currency, with unlike currencies never cross-summed.
* Kept portfolio identity bound to `get_current_user_id()` with no browser-supplied account selector.
* Kept portfolio output aggregate, WooCommerce-independent and free of supporter rows, technical references and customer PII.
* Closed the 0.5.x dashboards/statistics milestone.

= 0.5.4 =
* Added `[crowdfunding_fulfillment_health]` as a campaign-scoped read-only reconciliation-health surface.
* Added aggregate detection of active claims missing fulfillment and active claims with cancelled fulfillment.
* Added aggregate detection of reversed claims still pending/processing and reversed claims already fulfilled.
* Separates lifecycle drift from terminal post-delivery reversal exceptions that require manual review.
* Reuses campaign owner/admin authorization and returns no supporter, claim or fulfillment identities.
* Kept health diagnostics SELECT-only, WooCommerce-independent and unable to mutate claims or fulfillment.

= 0.5.3 =
* Added aggregate campaign fulfillment statistics to `[crowdfunding_campaign_statistics]`.
* Added pending, processing, fulfilled and cancelled Core fulfillment counts.
* Added pending-delivery workload as `pending + processing`.
* Added completion rate as `fulfilled / (pending + processing + fulfilled)`, excluding cancelled fulfillment from active delivery workload.
* Kept fulfillment statistics read-only, campaign-scoped, WooCommerce-independent and free of supporter PII.
* Kept fulfillment statistics observational only: they do not change entitlement, availability or fulfillment lifecycle state.

= 0.5.2 =
* Added bounded campaign contribution trends to `[crowdfunding_campaign_statistics]`.
* Added read-only date range filters with a maximum normalized span of 366 days.
* Added allowlisted day, week and month aggregation buckets.
* Added contributions-created, currently-confirmed and current net confirmed/refund-adjusted values per creation period.
* Added an accessible tabular trend presentation with CSS-only proportional net-value bars.
* Explicitly avoids treating `updated_at` as historical state-transition evidence or presenting current facts as reconstructed historical balances.
* Kept trend queries aggregate, SELECT-only, WooCommerce-independent and free of supporter PII.

= 0.5.1 =
* Added `[crowdfunding_campaign_statistics]` for authenticated campaign-owner aggregate statistics.
* Added authoritative net raised amount, funding goal and funded percentage without duplicating financial authority.
* Added contribution counts by pending, confirmed, refunded and cancelled Core states.
* Added active/reversed reward-claim aggregate counts scoped through reward ownership by campaign.
* Added optional `statistics_url` links to the promoter dashboard.
* Kept the statistics query SELECT-only, WooCommerce-independent and free of supporter-level rows or PII.

= 0.5.0 =
* Added `[crowdfunding_contribution_dashboard]` for authenticated current-user contribution history.
* Added a SELECT-only dashboard query scoped by Core `user_id`.
* Added original, refunded and net amounts derived from contribution and applied-adjustment ledgers.
* Added Core reward entitlement summaries without reading WooCommerce orders.
* Added current-user-only pagination and published-campaign links.
* Guest contributions remain unattached when `user_id = 0`; no identity guessing from billing or order data.
* Kept technical source references, WooCommerce customer data and dashboard write actions out of the account surface.

= 0.4.11 =
* Added `[crowdfunding_fulfillment_audit]` as a campaign-scoped read-only fulfillment timeline.
* Added immutable event reads to `Reward_Fulfillment_Query`, ordered chronologically by event ID.
* Timeline shows transition, timestamp, event origin, stable source reference and event ID.
* Reused the existing `manage_cfwp_fulfillments` owner/admin authority without adding campaign-edit permissions.
* Kept audit separate from write actions, repository transitions, WooCommerce and customer/shipping PII.

= 0.4.10 =
* Added dedicated `manage_cfwp_fulfillments` capability for promoters and administrators.
* Added `[crowdfunding_manage_fulfillment]` campaign-scoped operational delivery management.
* Added read-only fulfillment → claim → reward campaign authority queries.
* Promoters remain restricted to campaigns they own; administrators can supervise other campaigns.
* Manual operations are restricted to pending/processing forward transitions; cancelled remains entitlement-owned and fulfilled remains terminal.
* Added optional `fulfillment_url` to the promoter dashboard for published campaigns.
* Kept fulfillment management independent from WooCommerce and free of shipping/billing/tracking/customer PII.

= 0.4.9 =
* Added bounded, idempotent backfill for historical active reward claims missing fulfillment.
* Backfill runs after fulfillment schema installation and uses the normal `ensure_for_claim()` authority.
* Missing claims are processed in configurable batches with a hard maximum of 200 per request.
* Failed claims remain untouched for retry and emit a dedicated backfill error hook.
* Backfill completion is recorded only after no active claim remains without a fulfillment.
* Kept reconciliation independent from WooCommerce, claim mutation, supporter PII and destructive history changes.

= 0.4.8 =
* Added automatic Core claim-to-fulfillment lifecycle synchronization.
* Active claim creation idempotently creates one pending fulfillment.
* Claim reversal cancels pending/processing fulfillment while preserving immutable transition history.
* Claim reactivation restores cancelled fulfillment to pending without creating a duplicate row.
* Added event-sequence-based transition references so repeated reversal/reactivation cycles stay idempotent.
* Kept fulfilled delivery terminal and surfaced post-delivery entitlement reversal for manual review instead of automatically undoing delivery.
* Kept the lifecycle bridge independent from WooCommerce, availability and supporter PII.

= 0.4.7 =
* Added versioned `cfwp_reward_fulfillments` Core state, one fulfillment per reward claim.
* Added `pending`, `processing`, `fulfilled` and `cancelled` fulfillment states.
* Added immutable `cfwp_reward_fulfillment_events` transition history with source/reference idempotency.
* Added active-claim validation and row-locked fulfillment transitions.
* Kept fulfillment independent from WooCommerce, reward availability and customer/shipping PII.

= 0.4.6 =
* Added public finite reward availability derived exclusively from active Core claims.
* Added remaining-count output to public reward cards and contribution selector.
* Unlimited rewards remain counter-free and zero-capacity finite rewards remain unavailable.

= 0.4.5 =
* Added net-per-contribution reward eligibility using applied refund adjustments.
* Added automatic reward-claim reversal and safe reactivation after partial refund changes.

= 0.4.4 =
* Added optional reward selection, checkout validation and WooCommerce-to-claim bridge.

= 0.4.3 =
* Added the versioned `cfwp_reward_claims` Core ledger.

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