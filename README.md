# JolaxPay Backend

Laravel backend for JolaxPay: a stateless JSON API for the React Native mobile
app, and an Inertia + React (TSX) admin panel for staff — one codebase, one
database, one business-logic layer. See the companion docs in the repo root
one level up (`01-JolaxPay-PRD.md` … `04-JolaxPay-Implementation-Plan.md`)
for the full product/technical spec this implements.

This repo is the Phase 1 MVP scaffold per the Implementation Plan: the full
domain layer, database schema, mobile API, and a working admin panel, all
wired together and tested end-to-end. Vending is a real integration — not
just electricity, but airtime, data, cable TV, and education too, all via
VTpass (see [Electricity vending](#electricity-vending-vtpass) and
[Airtime, data, cable TV & education vending](#airtime-data-cable-tv--education-vending-also-vtpass)
below); the payment processor is still mocked — see
[Mocked vs. real](#mocked-vs-real).

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Mobile API | `/api/v1/*`, Sanctum personal-access tokens |
| Admin | `/admin/*`, Inertia + React 18 + TypeScript, session guard |
| Roles/permissions | `spatie/laravel-permission` (`super_admin`, `ops`, `support`) |
| Database | PostgreSQL in staging/production; SQLite or MySQL locally |
| Cache/queues/sessions | Redis (via `predis`, no PHP extension required) |
| Realtime | Laravel Reverb, `private-transaction.{id}` channel |
| PDF receipts | `barryvdh/laravel-dompdf` |
| Background jobs | Laravel Queues (Horizon for monitoring) |

## First-time setup

```bash
composer install
npm install
cp .env.example .env   # already done if you're reading this from a fresh clone — verify values
php artisan key:generate
touch database/database.sqlite   # only if it doesn't already exist
php artisan migrate --seed
npm run build     # or `npm run dev` for hot-reload while working on the admin UI
```

`db:seed` runs three seeders in order:
1. **RolesAndPermissionsSeeder** — `super_admin`/`ops`/`support` roles, always safe to run.
2. **DiscoSeeder** — the ten major Nigerian DisCos, `health_status = healthy`.
3. **DemoDataSeeder** — *local only* (no-ops outside `APP_ENV=local`): a demo
   admin, a demo customer with a meter and two transactions (one completed,
   one failed-and-refunded), so the admin dashboard isn't empty on first run.

**Demo admin login:** `admin@jolaxpay.com` / `password` at `/admin/login`.

Redis must be running locally (`redis-server` or `brew services start redis`).

## Running it

You need four processes for the full purchase pipeline to work end-to-end
(realtime status pushes + async vending/notification jobs):

```bash
php artisan serve              # http://localhost:8000 — API + admin
php artisan queue:work         # processes ProcessTransactionPayment/ProcessVending/DeliverToken
php artisan reverb:start       # realtime broadcasting (private-transaction.{id})
npm run dev                    # Vite dev server for the admin UI (skip if you ran `npm run build`)
```

Without a queue worker running, `POST /v1/transactions` still returns `202`
immediately (Payment Initiated) but never progresses further — this is by
design (TRD §5: vending/payment calls are never inline in the request
cycle), not a bug.

## Mobile API (`/api/v1`)

Bearer-token auth via Sanctum. Full route list: `php artisan route:list --path=api`.
Highlights:

- `POST /v1/auth/register`, `/login` (new-device OTP challenge), `/logout`
- `GET/POST /v1/meters`, `PATCH /v1/meters/{id}/favorite` — electricity only
- `GET /v1/billers`, `POST /v1/billers/verify` — the airtime/data/cable_tv/
  education catalog (+ cached bundle prices) and their merchant-verify
- `GET/POST /v1/beneficiaries`, `PATCH /v1/beneficiaries/{id}/favorite` —
  saved airtime/data/cable_tv/education recipients (the non-electricity
  counterpart to `meters`)
- `POST /v1/transactions` — **requires an `Idempotency-Key` header** (TRD §8); a
  repeated key on the same route replays the original response instead of
  double-charging. Works for every `service_type` — electricity needs
  `meter_id`/`meter_group_id`, everything else needs `biller_id` (or a saved
  `beneficiary_id`) plus `biller_identifier`/`variation_code` as that biller
  requires.
- `GET /v1/transactions/{id}/status` — polling fallback for the
  `private-transaction.{id}` broadcast channel
- `POST /v1/transactions/{id}/outcome`, `GET /v1/transactions/{id}/receipt` (PDF)
- `POST /v1/transactions/{id}/repeat` — re-runs a past purchase's meter/
  biller/amount/recipient as a fresh transaction (payment_method optionally
  overridable), through the exact same `initiate()` path as a new purchase
- `GET /v1/wallet`, `POST /v1/wallet/fund`, `POST /v1/wallet/transfer` (by
  `wallet_address`), `GET /v1/wallet/fund/{reference}/status`
- `GET /v1/withdrawals`, `POST /v1/withdrawals`, `GET /v1/withdrawals/banks`,
  `POST /v1/withdrawals/resolve-account` — wallet → bank account payout
- `GET/POST /v1/scheduled-purchases`, `/v1/power-circle`, `/v1/meter-groups`, `/v1/referrals`
- `GET/POST /v1/support/tickets`
- `GET /v1/providers/status` (public, no auth)
- `POST /v1/meters/verify` — checks a meter number against the DisCo before
  it's saved or purchased against, returning the customer's name

## Electricity vending (VTpass)

`VENDING_ELECTRICITY_DRIVER=vtpass` in `.env` activates
`App\Domain\Vending\Providers\VtpassElectricityProvider`, a real integration
against [VTpass](https://vtpass.com/documentation/) — not a stub. Get your
keys from the VTpass dashboard's API Keys tab (sandbox and live are separate
accounts) and set `VTPASS_API_KEY`, `VTPASS_SECRET_KEY`, `VTPASS_PUBLIC_KEY`,
and `VTPASS_ENV` (`sandbox` by default). VTpass's own sandbox has a
pre-loaded wallet and behaves like the live API, so this is safe to point at
for genuine end-to-end testing — no mocking required once your keys are set.

A few things worth knowing before you flip the driver on:

- `Disco.api_provider_code` must hold VTpass's `serviceID` for that biller
  (`ikeja-electric`, `eko-electric`, …) — `DiscoSeeder` already seeds the
  correct value for all ten DisCos it creates, verified against VTpass's own
  per-biller docs. That seeder is only the initial bootstrap, though —
  `php artisan vtpass:sync-discos` (scheduled weekly) pulls VTpass's live
  `GET /services?identifier=electricity-bill` list and keeps `discos` in
  sync from there: existing rows get their `name` refreshed (curated
  `code`/`region` are left alone), new DisCos VTpass adds are created, and
  ones it drops are marked `is_active = false` (never deleted, since
  meters/transactions still reference them historically).
- VTpass's guidance is to **requery, not resubmit**, a "pending" transaction.
  The provider stores VTpass's own `request_id` on `transaction.meta` the
  first time it pays, and every subsequent retry from
  `TransactionService::processVending()`'s bounded retry loop requeries that
  same id instead of paying again.
- If every bounded retry still comes back "pending", the purchase is marked
  Failed and auto-refunded per the normal flow — but VTpass may still
  resolve it to "delivered" asynchronously afterward. Closing that gap for
  real needs a VTpass webhook receiver plus a reconciliation job (the
  Reconciliation admin page is the natural home for that check); it's not
  built yet, and is called out as a known limitation in the provider's
  docblock, not silently ignored.
- `POST /v1/meters/verify` (and `verifyMeter()` on the provider contract)
  wraps VTpass's `merchant-verify` — use it before saving a meter or
  initiating a purchase to catch a mistyped meter number and show the
  customer's name up front.

## Airtime, data, cable TV & education vending (also VTpass)

The same VTpass account covers airtime, data, cable TV, and education pins
(WAEC/JAMB) — `App\Domain\Vending\Providers\VtpassBillerProvider` is one
driver for all four, since VTpass's request shape only varies by a handful
of per-biller flags. Each service type has its own toggle so you can bring
them up independently: `VENDING_AIRTIME_DRIVER`, `VENDING_DATA_DRIVER`,
`VENDING_CABLE_TV_DRIVER`, `VENDING_EDUCATION_DRIVER` (all default `mock`;
set to `vtpass` to activate against the credentials already configured
above — no separate keys needed).

Unlike electricity (meter-anchored), these four are **biller-anchored**
(`App\Models\Biller`, seeded by `BillerSeeder` — verified against VTpass's
own per-product docs, same as `DiscoSeeder`):

- `Biller.api_provider_code` holds VTpass's `serviceID` (`mtn`, `mtn-data`,
  `dstv`, `waec`, `jamb`, …). `requires_billers_code` / `requires_variation` /
  `supports_verify` describe what that specific biller's `/pay` call needs —
  airtime needs neither; data/cable_tv need both (`billersCode` = subscriber
  phone or smartcard number, `variation_code` = bundle/bouquet); education
  needs a `variation_code` always and a `billersCode` only for JAMB (its
  Profile ID) — WAEC just needs the variation.
- `biller_variations` caches VTpass's `GET /service-variations` (bundle/
  bouquet/pin-type options + prices) so the mobile purchase form doesn't hit
  VTpass on every load. Refresh it with `php artisan vtpass:sync-variations`
  (scheduled daily in `routes/console.php` once a driver is live) — a
  variation VTpass stops returning for a biller is marked `is_active =
  false` rather than left behind, the same "retire, don't delete" pattern
  `vtpass:sync-discos` uses for discos.
- A saved `Beneficiary` (the non-electricity equivalent of a saved `Meter`)
  supplies its `biller_id`/`identifier` by default on a purchase, still
  overridable per-request.
- Same requery-not-resubmit and pending-past-every-retry caveats as
  electricity apply here too — see `VtpassBillerProvider`'s class docblock.

## Wallets, transfers & Paystack

Every wallet gets a `wallet_address` (e.g. `JLXA1B2C3D4E5`) assigned the
moment it's created (`LedgerService::walletFor()`) — how another JolaxPay
user sends it money, deliberately not the account's phone/email.

- `POST /v1/wallet/transfer` — wallet-to-wallet, by `wallet_address`. Purely
  internal ledger movement (`LedgerReason::TransferOut`/`::TransferIn`), no
  external processor involved. Both wallets are locked in a fixed id order
  (`LedgerService::transfer()`) so two simultaneous opposite-direction
  transfers can't deadlock each other.
- **Card payments (purchases + wallet funding) and bank withdrawals all go
  through Paystack** (`App\Domain\Payments\PaystackGateway`,
  https://paystack.com/docs/api/) once `PAYMENTS_DOMESTIC_DRIVER=paystack`
  — set `PAYSTACK_SECRET_KEY`/`PAYSTACK_PUBLIC_KEY`/`PAYSTACK_CALLBACK_URL`
  to activate; `mock` (the default) keeps everything synchronous and
  instant for local dev, same bootstrap pattern as VTpass.
- Unlike VTpass, Paystack's checkout is a **hosted-page redirect**, not a
  server-to-server call our code can get a result back from directly: the
  mobile app opens `paystack_authorization_url` (present on a transaction
  while it's `payment_initiated` and paid by `card`, or returned directly
  by `POST /v1/wallet/fund`) in a WebView, the customer pays on Paystack's
  page, and a `charge.success`/`charge.failed` webhook
  (`POST /v1/webhooks/paystack`, verified via the `x-paystack-signature`
  HMAC-SHA512 header — `PaystackGateway::verifyWebhookSignature()`) is what
  actually confirms it — see `PaystackWebhookController` and
  `TransactionService::initializePaystackCheckout()`/`processPayment()`.
- Withdrawals (`WithdrawalController`) resolve the destination account name
  via Paystack before ever moving money, debit the wallet immediately
  (held, same "debit now, reverse on failure" pattern as a purchase —
  `LedgerReason::Withdrawal`/`::WithdrawalReversal`), then hand off to
  Paystack Transfers. A transfer is *always* asynchronous — even Paystack's
  own docs note it comes back `pending` and resolves via
  `transfer.success`/`transfer.failed` within ~15 minutes, never
  synchronously — so `withdrawals.status` starts `pending` and the webhook
  is what finalizes it either way.

## Admin panel (`/admin`)

No public registration — staff accounts are provisioned via:

```bash
php artisan admin:create-user --role=super_admin
```

Pages: Dashboard, Transactions (list/detail with manual retry/refund),
Provider Health, Support Tickets, Referrals, Users, Reconciliation. Each
non-dashboard page is gated by its own permission (`manage-transactions`,
`manage-providers`, etc.) — see `database/seeders/RolesAndPermissionsSeeder.php`
for exactly what each of the three roles can do.

## Branding

The admin UI uses the JolaxPay mark (`public/images/logo.png`, sourced from
`../logo.png` at the repo root) as its logo and favicon, and a `brand` color
scale in `tailwind.config.js` sampled from the logo's own red gradient.
`brand-700` (`#8f0e23`) is the primary action color; functional colors
(success/warning/danger status badges) intentionally stay on Tailwind's
standard palette so a red "failed" badge never reads as a brand-colored
button. See `resources/js/Components/ApplicationLogo.tsx` and
`resources/js/Components/Admin/StatusBadge.tsx`.

## Architecture

```
app/Domain/
├── Identity/       OtpService (new-device login, password reset, high-value tx step-up)
├── Payments/       PaymentManager + PaymentProcessorContract (domestic/international)
├── Vending/        VendingManager — VendingProviderContract (electricity,
│                   per-DisCo) + BillerVendingProviderContract (airtime/
│                   data/cable_tv/education, per-Biller)
├── Wallet/         LedgerService — double-entry ledger, the highest-priority
│                   correctness surface in the app (see tests/Unit/Domain)
├── Notifications/  NotificationDispatcher (sms/email/whatsapp/in_app, logs every send)
├── Scheduling/      ScheduledPurchaseEvaluator (recurring purchases)
└── Transactions/   TransactionService (orchestrator) + TransactionStateMachine
                     (the only thing allowed to change a transaction's status)
```

The Payment Flow state machine (TRD §6) is enforced in code —
`TransactionStatus::canTransitionTo()` — and every transition is written to
`transaction_status_history` for the admin audit trail, then broadcast on
`private-transaction.{id}`. A broadcast failure (Reverb down, etc.) is caught
and logged, never allowed to break the purchase flow itself.

`App\Jobs\ProcessTransactionPayment → ProcessVending → DeliverToken` is the
queued pipeline: payment capture → vending → delivery, each step retried a
bounded number of times (`config/vending.php`) before falling back to an
automatic wallet refund.

## Mocked vs. real

Both vending and payments ship behind a driver interface
(`VENDING_ELECTRICITY_DRIVER`, `VENDING_AIRTIME_DRIVER`, `VENDING_DATA_DRIVER`,
`VENDING_CABLE_TV_DRIVER`, `VENDING_EDUCATION_DRIVER`, `PAYMENTS_DOMESTIC_DRIVER`,
`PAYMENTS_INTERNATIONAL_DRIVER` in `.env`) — this is intentional
(Implementation Plan §2: "a stub/mock provider for the rest, for parallel
frontend development"), not a placeholder that got forgotten:

- **All five vending categories are real** — electricity, airtime, data,
  cable TV, and education each have their own `VENDING_*_DRIVER=vtpass`
  toggle against VTpass's actual sandbox/live API. See
  [Electricity vending](#electricity-vending-vtpass) and
  [Airtime, data, cable TV & education vending](#airtime-data-cable-tv--education-vending-also-vtpass)
  above. `mock` (`MockElectricityProvider`/`MockBillerProvider`) is still the
  default per category and remains useful for frontend work and CI — set
  `transaction.meta.simulate_failure = true` to test the retry → refund path
  without touching VTpass at all.
- **Card payments are real via Paystack** — `PAYMENTS_DOMESTIC_DRIVER=paystack`
  activates hosted-checkout card payments for purchases, wallet funding, and
  bank withdrawals. See [Wallets, transfers & Paystack](#wallets-transfers--paystack)
  above. `mock` (`MockPaymentProcessor`) is still the default and remains
  useful for frontend work and CI — always succeeds unless
  `transaction.meta.simulate_payment_failure = true`. International
  (Diaspora Mode) payments are still mocked only — no live processor yet.
- Notification channels (`SMS_DRIVER`, `WHATSAPP_DRIVER`) default to `log` —
  writes to `storage/logs/laravel.log` instead of calling a real gateway.

Swap a driver by adding a new class implementing the relevant `Contracts\*`
interface and a `match` arm in `VendingManager`/`PaymentManager`.

## Not yet wired up

- **An international payment processor** (Diaspora Mode) — domestic card
  payments are real via Paystack (see above), but non-NGN currencies still
  route through the mocked international driver.
- **Diaspora Mode multi-currency capture** — the `transactions.fx_rate` /
  `amount_ngn` columns exist; no real international processor is integrated.
- **A withdrawal admin/ops view** — `withdrawals` has no dedicated admin
  page yet (unlike `transactions`/`reconciliation`); staff can currently
  only inspect one via `php artisan tinker` or a DB client.
- **A VTpass webhook receiver + reconciliation job** for transactions that
  stay "pending" through every bounded retry — see the note in
  [Electricity vending](#electricity-vending-vtpass) (applies to every
  VTpass-backed service, not just electricity).
- **WhatsApp Business API** — falls back to the `log` driver.
- **PHPStan/Larastan static analysis** — TRD §10 calls for it in CI; not yet
  added to `composer.json`.
- **The mobile app itself** (`jolaxpay-mobile`, Expo/React Native) — this repo
  is the backend only, per the Implementation Plan's two-repo recommendation.

## Testing

```bash
php artisan test
```

91 Pest tests covering: ledger correctness (credit/debit/insufficient-funds/
idempotent-refund/concurrent-debit/wallet-to-wallet transfer), the
transaction state machine (valid and invalid transitions, terminal-state
protection), the full mobile purchase pipeline end-to-end for both
electricity (card and wallet payment, insufficient funds, auto-refund on
simulated vend failure, idempotency-key replay, repeat-purchase, access
control) and the biller-anchored services (airtime, data with
variation_code validation, saved beneficiaries), VTpass response parsing
for every product family against VTpass's own documented example
responses, the full Paystack round trip (checkout initialize → webhook
confirm, for both purchases and wallet funding, success and failure) and
withdrawals (bank resolve, debit-then-transfer, webhook-driven
success/failure with automatic reversal), and admin RBAC (guest redirect,
role-based page access).

`phpunit.xml` sets `QUEUE_CONNECTION=sync` and `BROADCAST_CONNECTION=null`
for the test environment, so the full async pipeline runs inline within each
test — no queue worker or Reverb server needed to run the suite.
