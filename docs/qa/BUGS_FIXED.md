# Bugs fixed in this pass

No user-delete behavior was restored. The working SQLite file was not migrated or reseeded.

## 1. Export Logs exported orders

Reproduction: `GET /users/{user}/logs.csv` streamed columns User, Order, Status, Total, Created from `orders`.

Root cause: `UserViewController::exportLogs` queried `$user->orders()` even though `audit_logs` exists.

Fix: account create, update, and bulk status write an `audit_logs` row for the same user. The export streams When, Event, Description, Actor, and IP address for that user. It does not substitute orders.

Regression: `FunctionalIntegrityTest::test_activity_log_export_is_not_an_order_export_and_neutralizes_formulas`.

Files: `app/Models/AuditLog.php`, `app/Http/Controllers/UserViewController.php`.

## 2. Admin orders accepted unsafe input and dropped omitted relationships

Reproduction:

- An address owned by another customer was stored.
- A draft maid id was accepted.
- Wallet, deposit, and discount could exceed the subtotal.
- A wallet amount was saved with no ledger debit, so the same balance could be reused.
- An edit that omitted partner, address, maids, or extras cleared them.
- A closed booking date was only stored on the calendar and was not checked on submit.

Root cause: `OrderDesk` validated existence only, always wrote `partner_id` from a missing key as null, and `syncRelations` replaced maids and extras whenever any one of those keys was present. `BookingSchedule` was not called from create, update, or API checkout.

Fix: reject another customer's address, inactive partners, draft or inactive maids, inactive extras, and totals that exceed the subtotal. Apply only the wallet difference through the existing wallet ledger, so an unchanged edit does not debit again. Sync a relation only when its key is present. Reject closed dates, closed-until periods, closed doors, and closed arrival hours on admin save and API checkout.

Regression: `FunctionalIntegrityTest` order-desk and API methods, and `VideoParityTest::test_admin_can_create_and_fully_edit_an_order` now funds the wallet and expects the debit and the later release.

Files: `app/Services/Admin/OrderDesk.php`, `app/Services/Admin/BookingSchedule.php`, `app/Services/OrderCheckoutService.php`, `tests/Feature/VideoParityTest.php`.

## 3. Dashboard counted an unrelated widget and presented a double-subtracted total as profit

Reproduction: with no widget whose title contains "clean", the overview used the first widget. Partner profit calculated `total - wallet_amount - discount_total` even though `total` is already `subtotal - wallet - deposit - discount`.

Root cause: `cleaningWidget()` fell back to the first service type, and `partnerProfit()` subtracted amounts that were already removed from `total`.

Fix: a missing cleaning widget returns 0 and the label "No cleaning widget". The partner card is "Partner payable" and sums completed `total`. The maid card is labeled "Maid revenue minus salaries" because it still subtracts full salaries rather than an allocated cost.

Regression: `FunctionalIntegrityTest::test_dashboard_does_not_count_an_unrelated_widget_or_call_payable_profit`.

Files: `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`.

## 4. Draft maids appeared in operational assignment data

Reproduction: the order form and the active-maid metric included `is_draft` rows when their status was active.

Root cause: those queries filtered status and not `is_draft`.

Fix: active operational queries and the assignment list require `is_draft` false.

Regression: `FunctionalIntegrityTest` draft-count and order-form assertions.

Files: `app/Http/Controllers/MaidViewController.php`, `app/Http/Controllers/AdminParityController.php`, `app/Http/Controllers/UserViewController.php`.

## 5. Spreadsheet formula characters were exported raw

Reproduction: a partner name beginning with `=` was written straight into CSV.

Root cause: export rows were passed to `fputcsv` without neutralization.

Fix: `spreadsheet_cell` prefixes values that start with `=`, `+`, `-`, `@`, tab, or carriage return. The export data rows use it.

Regression: the partner export assertion in `FunctionalIntegrityTest`, and the formula download assertions in `RemainingQaFlowTest`.

Files: `app/Support/helpers.php` and the export methods in `UserViewController`, `AdminOperationsController`, `AdminParityController`, and `OrderViewController`.

## 6. Configured push and WhatsApp were left queued with no sender

Reproduction: after the configuration check passed, push and WhatsApp stayed `queued` and no provider was called.

Root cause: `OutboundChannel` only calls a sender for email.

Fix: push and WhatsApp are stored as `not_implemented` and are not marked sent. Unconfigured channels still stop at `configuration_required` before this branch.

Regression: `FunctionalIntegrityTest::test_configured_whatsapp_is_not_marked_sent_without_a_provider_adapter`.

File: `app/Services/Admin/OutboundChannel.php`.

## 7. Customer profile tabs showed sample people and crashed without a city

Reproduction: the customer edit page rendered hardcoded log and message copy. An address with no city threw "Attempt to read property name on null" and returned 500.

Root cause: the logs and messages sections were static HTML, and the address cells used `->city->name` when `city` was null.

Fix: the edit page loads that user's audit log and messages. Empty states replace the sample copy. City names use a null-safe read.

Regression: `RemainingQaFlowTest::test_user_draft_wallet_profile_tabs_bulk_guard_and_temporary_ban`.

Files: `app/Http/Controllers/UserViewController.php`, `resources/views/users/partials/customer_role_form.blade.php`.

## 8. Listing banners counted as impressions

Reproduction: `GET /api/v1/banners` incremented `impressions` and wrote an ad event for every active ad, including an admin-unrelated fetch.

Root cause: the index action updated the counter while building the cached list.

Fix: the list is read-only. `POST /api/v1/banners/{ad}/impression` records one impression and one event for an active ad. Inactive ads return 404.

Regression: `RemainingQaFlowTest::test_inbox_reviews_shares_inquiries_ads_and_uploads`.

Files: `app/Http/Controllers/Api/V1/BannerController.php`, `routes/api.php`.

## 9. The locations page failed when no governorate existed

Reproduction: opening coverage with an empty governorate list returned 500.

Root cause: the area form called `route('admin.areas.store', $governorates->first())` when that value was null.

Fix: the form posts to the coverage page until a governorate exists.

Regression: the sidebar GET of `admin.coverage.index` in `RemainingQaFlowTest`.

File: `resources/views/coverage/index.blade.php`.

## 10. Finalizing a draft did not credit the opening balance

Reproduction: a draft saved with an opening balance had no wallet. Removing the draft flag and saving again left the balance null.

Root cause: the opening deposit ran only in `store`, and drafts skip that branch.

Fix: create and update share one ledger reference, `00000000-0000-4000-a000-` plus the user id. An inactive draft is not credited. The same amount on a later save does not credit again.

Regression: `RemainingQaFlowTest::test_user_draft_wallet_profile_tabs_bulk_guard_and_temporary_ban`.

File: `app/Http/Controllers/UserViewController.php`.

## 11. A rejected email stayed queued

Reproduction: when `Mail::raw` throws, the dispatch row remained `queued` and could be read as pending delivery.

Root cause: the send had no failure update.

Fix: a transport exception sets status `failed` and a generic error. The notify response shows that error. A successful array-transport send is described as accepted by the mail transport, with inbox delivery unconfirmed.

Regression: `RemainingQaFlowTest::test_email_uses_the_test_transport_and_push_whatsapp_are_not_sent`.

Files: `app/Services/Admin/OutboundChannel.php`, `app/Http/Controllers/AdminParityController.php`.

## 12. Saving a maid without an off day returned 500

Reproduction: a maid request that omitted `off_day` passed validation and then failed the NOT NULL column.

Root cause: validation allowed null while the column does not.

Fix: create, update, and the action modal require an off day from 1 to 7, which matches the form select.

Regression: `RemainingQaFlowTest` maid upload requests include `off_day` and the text file is still rejected.

Files: `app/Http/Requests/Maids/SaveRequest.php`, `app/Http/Controllers/MaidViewController.php`.
