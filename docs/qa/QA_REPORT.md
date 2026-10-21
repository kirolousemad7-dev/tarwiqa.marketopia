# QA report

This pass exercised Laravel 13.31.0 on PHP 8.4.24. It did not treat a page load, a visible button, or the previous test count as proof that a function works.

## 1. Test environment

- PHPUnit is forced by `phpunit.xml` to `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and an empty `DB_URL`. Cache, mail, and session are `array`. Queue is `sync`.
- `DatabaseIsolationTest` passed before write tests: the configured database was `:memory:` and was not `database/database.sqlite`.
- Disposable browser database: `storage/framework/qa-testing.sqlite`.
- The connection was printed before `migrate` and `db:seed`. It matched that file, and only then were migrations and the local seeder run.
- QA application: `http://127.0.0.1:8001`.
- Manual-review application: `http://127.0.0.1:8000`. It was down at the start of the browser check and was started again with the normal application configuration. No form was submitted to it.
- Tooling: `php artisan test`, `php artisan about`, the router (`189` registered routes), and the Cursor browser against port 8001.

## 2. Coverage

The matrix is `docs/qa/QA_MATRIX.csv`. The original 54 rows are still there. Three rows were added after them: QA-401, QA-403, and QA-404.

Original 54:

| Status | Count |
| --- | ---: |
| PASS | 47 |
| FAIL | 2 |
| BLOCKED_PROVIDER | 0 |
| BLOCKED_REQUIREMENT | 1 |
| BLOCKED_ENVIRONMENT | 0 |
| UNVERIFIED | 2 |
| NOT_APPLICABLE | 2 |
| Total | 54 |

All 57 rows, including the three added cases:

| Status | Count |
| --- | ---: |
| PASS | 49 |
| FAIL | 3 |
| BLOCKED_PROVIDER | 0 |
| BLOCKED_REQUIREMENT | 1 |
| BLOCKED_ENVIRONMENT | 0 |
| UNVERIFIED | 2 |
| NOT_APPLICABLE | 2 |
| Total | 57 |

QA-101 and QA-102 moved from BLOCKED_PROVIDER to FAIL. No push or WhatsApp provider contract exists beyond `PUSH_PROVIDER_KEY`, `WHATSAPP_TOKEN`, and `WHATSAPP_FROM`. QA-103 is UNVERIFIED for a real inbox. The array transport was tested separately. QA-321 is BLOCKED_REQUIREMENT. QA-401 is a new FAIL for settings that are stored and never applied. QA-403 and QA-404 record the temporary-ban behavior and the impression definition.

These counts are the rows in that file. They are not a percentage of the product, and they are not the older 158-row audit.

## 3. Role verification

The browser session signed in on port 8001 and landed on `/overview` as Super Admin. The sidebar showed the super-admin links. Maids List and User List were opened and marked current.

Executed object checks:

- The QA customer deactivation did not change the same email's active row in the working database.
- Order creation rejected an address owned by a different customer.
- Draft maids were rejected on assignment and omitted from the active count and the order-form choices.
- A configured WhatsApp send was not marked sent.

The continuation signed in as the seeded supporter and then, after logout, as the seeded partner. Details are in section 11.

## 4. Functional verification

Submitted and checked after reload or in the database:

- Account deactivation from the QA browser. Customer id 4 stayed present, `status` became `inactive`, `ban_type` stayed null, and `deleted_at` stayed null. The customers page then showed Inactive Accounts 1 and Active Users 0.
- Export Logs returned activity columns and an `account.deactivate` event, not an order export.
- A complete admin order stored the payable total `55.00` from subtotal 100, wallet 30, deposit 10, and discount 5. The wallet balance moved from 80.00 to 50.00 with one purchase. A later notes-only edit kept the partner, address, maid, extra, and balance.
- Excessive wallet use, a foreign address, a draft maid, and a closed service date were rejected with no order row.
- API checkout on a closed date returned 422 and left the 500.00 balance untouched.
- Clearing a previously applied wallet amount returned that amount to the wallet.
- The overview on port 8001 showed Partner payable and Cleaning Services Orders 5. The cleaning count matches a seeded cleaning widget; the no-cleaning-widget case was proven separately and stayed at 0.

## 5. Bugs found and fixed

See `docs/qa/BUGS_FIXED.md`. Six defects were reproduced, given regression coverage, and fixed: order-log export, unsafe order saves, dashboard widget and payable labeling, draft maids in operational lists, raw spreadsheet formulas, and push/WhatsApp left queued without a sender.

The full suite after those fixes was 132 passed, 0 failed, 0 skipped, and 1168 assertions. The continuation count is in section 7.

## 6. Remaining issues

See the continuation section below. The earlier list is the state before this continuation.

## 7. Automated tests

The suite at the end of the first pass was 132 passed, 0 failed, 0 skipped, 1168 assertions.

The continuation suite, after the new flow test and the fixes below:

- Passed: 139
- Failed: 0
- Skipped: 0
- Assertions: 1381

`phpunit.xml` now forces the testing database, mailer, queue, and session values so a shell environment cannot point the suite at either SQLite file.

## 8. Browser verification

Application: `http://127.0.0.1:8001` only for anything that changed data. Port 8000 was left running and was not used.

| Page | Width | Result |
| --- | ---: | --- |
| `/login` then `/overview` | desktop | First pass. Sign-in ended on the overview URL |
| `/maids` | 1440 | First pass. QA Marker Maid, row about 65px. `docs/qa/screenshots/qa-maids-1440.png` |
| `/users/customer` | 1440 | First pass. Compact row. The menu was closed in that screenshot. `docs/qa/screenshots/qa-users-1440.png` |
| `/users/customer` | 1366 | Row height 64. The page does not overflow. The table scrolls. No failed resource responses |
| `/users/customer` | 1024 | Row height 64. Horizontal scroll stays in the table card. `docs/qa/screenshots/qa-users-1024.png` |
| `/users/customer` | 768 | Row height 64. The action panel text was read from the DOM. `docs/qa/screenshots/qa-users-menu-open-768.png` shows only a clipped Message chip |
| `/users/customer` | 390 | Row height 64. View, Edit, and the more button stay in the row. `docs/qa/screenshots/qa-users-actions-390.png` |
| `/supporter` and its links | desktop | Signed in as the seeded supporter. Overview, orders, maids, profile, and security opened. Admin overview returned Access denied |
| `/partner` and its links | desktop | Signed in as the seeded partner after the supporter logged out. Overview, orders, maids, messages, and profile opened. Supporter orders returned Access denied |

The full open customer menu was not captured. QA-323 stays unverified.

## 9. Data safety

- `.env` was not edited.
- Git was not initialized, staged, committed, or pushed.
- The continuation restarted only port 8001. Before it accepted requests, the process environment printed `storage/framework/qa-testing.sqlite`, an empty database URL, database session, database queue, database cache, and the log mailer.
- PHPUnit still uses `:memory:`.
- After the browser writes, a read-only comparison showed the working file still at 765952 bytes with mtime 2026-10-20 13:08:20 UTC. Users 4, maids 5, orders 5, conversations 0, ads 3, supporter sessions 3, sessions 1. MD-1001 is still Mona Adel. `orders.customer@tarwiqa.test` is still active.
- The QA file changed. It has one supporter session from this sign-in, the marker maid name, and the inactive QA customer. Those writes are disposable.
- Historical field-level preservation cannot be fully proven from the available baseline. No full before-image of every working column exists. This continuation did not change the sampled working fields above, and it does not explain the earlier timestamp change.
- No real customer message was sent and no live payment was captured.

## 10. External dependencies

- Push: FAIL, implementation missing. `config/services.php` has a generic key and no provider client. A configured key is stored as `not_implemented`.
- WhatsApp: FAIL, implementation missing. The config has a token and a from address and no provider client. A configured token is stored as `not_implemented`.
- Email: the array transport accepted the recipient, subject, and body. A transport exception is stored as `failed` and is not presented as sent. Live inbox delivery is unverified. The working mailer remains `log`.
- Provider acceptance and recipient delivery are separate. The success flash says the mail transport accepted the message and that inbox delivery was not confirmed.
- Paymob webhook handling is covered by `PaymobWebhookTest` in the passing suite. No live payment was processed.
- Banner impressions are a POST to `/api/v1/banners/{ad}/impression`. GET `/api/v1/banners` no longer increments the counter. An admin page view does not call that endpoint.

## 11. Continuation results

The original 26 unverified cases:

| Case | Result |
| --- | --- |
| QA-024 | PASS |
| QA-301 | PASS |
| QA-302 | PASS |
| QA-303 | PASS |
| QA-304 | PASS |
| QA-305 | PASS |
| QA-306 | PASS |
| QA-307 | PASS |
| QA-308 | PASS |
| QA-309 | PASS |
| QA-310 | PASS |
| QA-311 | PASS |
| QA-312 | PASS |
| QA-313 | PASS |
| QA-314 | PASS |
| QA-315 | PASS |
| QA-316 | PASS |
| QA-317 | PASS |
| QA-318 | PASS |
| QA-319 | PASS |
| QA-320 | PASS |
| QA-321 | BLOCKED_REQUIREMENT |
| QA-322 | PASS |
| QA-323 | UNVERIFIED |
| QA-324 | PASS |
| QA-325 | PASS |

Supporter and partner browser sessions were sequential in one browser profile: the previous account logged out before the next one signed in.

The seeded supporter has no governorates, so the browser orders and maids pages showed the empty assignment message. Allowed and disallowed records were proven with Cairo and Giza fixtures in `RemainingQaFlowTest`. The seeded database has one partner, so every maid is assigned to that partner. A second partner's order and thread were rejected in the HTTP test.

QA-308 did not click a calendar day cell. The next-month page and the closed-date rejection were requested. QA-314 displays the stored `conversion_count`. It does not calculate conversions from orders. QA-317 did not submit the one-time supporter password button. QA-319 stored the pdf on the fake disk and did not request a public file URL. Inbox rules save `routing_note` and do not route messages.

Maid profit remains the sum of this month's completed order totals minus the full salary of each maid attached to those orders. The missing decisions are whether a maid's full monthly salary belongs to one order, how wallet, deposit, and discount should be treated, and whether the result is profit. The arithmetic test is not an approved profit definition.

Temporary ban has no expiry column. A suspended temporary ban blocks login. Restore clears `ban_type` and leaves the same user id. Expiry is manual.

`order_limit`, `welcome_message`, and `manual_review_above` are stored on the user and are not read by order creation. That is QA-401, FAIL.

FUNCTIONAL QA NOT COMPLETE

Unresolved local cases: QA-101 and QA-102 have no delivery adapter. QA-321 has no agreed maid-profit formula. QA-323 does not have a screenshot of the full open action menu. QA-103 live inbox delivery was not verified. QA-401 settings do not affect orders.
