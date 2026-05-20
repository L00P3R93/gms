# TODO — Known GameApi Gaps

Items below are blocked on the upstream wallet/Game API. The admin panel
degrades gracefully (error-aware empty states) until these are resolved.

## Missing API endpoints

- **Player Withdrawals** — there is no global `/withdrawals` listing endpoint;
  it returns 404. Withdrawals are only reachable per-customer via
  `getCustomerTransactions()`. `PlayerWithdrawalsPage` shows an empty state
  until a global endpoint exists.

- **Purchases** — there is no global `/purchases` listing endpoint.
  `listPurchases()` returns a limited payload only. Per-customer data is
  available via `getCustomerPurchases()`.

- **Robot Results** — the wallet API does not track robot game history; there
  is no robot results endpoint. `ListRobotResults` renders a static empty
  state and the stats widget shows zeros.

## Follow-ups

- When any GameApi list endpoint starts returning Laravel pagination metadata
  (`data` + `current_page`/`per_page`/`total`), forward `search` and
  `sortColumn` to it as query params instead of relying on the in-memory
  fallback in `App\Support\ApiTablePaginator`.
