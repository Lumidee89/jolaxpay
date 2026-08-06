# JolaxPay Backend

Laravel backend for JolaxPay: a stateless JSON API for the React Native mobile
app, and an Inertia + React (TSX) admin panel for staff — one codebase, one
database, one business-logic layer. See the companion docs in the repo root
one level up (`01-JolaxPay-PRD.md` … `04-JolaxPay-Implementation-Plan.md`)
for the full product/technical spec this implements.

This repo is the Phase 1 MVP scaffold per the Implementation Plan: the full
domain layer, database schema, mobile API, and a working admin panel, all
wired together and tested end-to-end. Electricity vending is a real
integration (VTpass — see [Electricity vending](#electricity-vending-vtpass)
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
- `GET/POST /v1/meters`, `PATCH /v1/meters/{id}/favorite`
- `POST /v1/transactions` — **requires an `Idempotency-Key` header** (TRD §8); a
  repeated key on the same route replays the original response instead of
  double-charging.
- `GET /v1/transactions/{id}/status` — polling fallback for the
  `private-transaction.{id}` broadcast channel
- `POST /v1/transactions/{id}/outcome`, `GET /v1/transactions/{id}/receipt` (PDF)
- `GET /v1/wallet`, `POST /v1/wallet/fund`
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
  per-biller docs.
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
├── Vending/        VendingManager + VendingProviderContract (per DisCo/telecom)
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
(`VENDING_ELECTRICITY_DRIVER`, `PAYMENTS_DOMESTIC_DRIVER`,
`PAYMENTS_INTERNATIONAL_DRIVER` in `.env`) — this is intentional
(Implementation Plan §2: "a stub/mock provider for the rest, for parallel
frontend development"), not a placeholder that got forgotten:

- **Electricity vending is real** — `VENDING_ELECTRICITY_DRIVER=vtpass` uses
  `VtpassElectricityProvider` against VTpass's actual sandbox/live API. See
  [Electricity vending](#electricity-vending-vtpass) above. `mock`
  (`MockElectricityProvider`) is still the default and remains useful for
  frontend work and CI — set `transaction.meta.simulate_failure = true` to
  test the retry → refund path without touching VTpass at all.
- **Payments are still mocked** — `App\Domain\Payments\Providers\MockPaymentProcessor`
  always succeeds unless `transaction.meta.simulate_payment_failure = true`.
  No card/bank-transfer/USSD processor is integrated yet.
- Notification channels (`SMS_DRIVER`, `WHATSAPP_DRIVER`) default to `log` —
  writes to `storage/logs/laravel.log` instead of calling a real gateway.

Swap a driver by adding a new class implementing the relevant `Contracts\*`
interface and a `match` arm in `VendingManager`/`PaymentManager`.

## Not yet wired up

- **A payment processor** (Flutterwave/Paystack for domestic, a
  Diaspora-Mode-capable processor for international) — still mocked, see above.
- **Diaspora Mode multi-currency capture** — the `transactions.fx_rate` /
  `amount_ngn` columns and international payment routing exist; no real
  international processor is integrated.
- **A VTpass webhook receiver + reconciliation job** for transactions that
  stay "pending" through every bounded retry — see the note in
  [Electricity vending](#electricity-vending-vtpass).
- **WhatsApp Business API** — falls back to the `log` driver.
- **PHPStan/Larastan static analysis** — TRD §10 calls for it in CI; not yet
  added to `composer.json`.
- **The mobile app itself** (`jolaxpay-mobile`, Expo/React Native) — this repo
  is the backend only, per the Implementation Plan's two-repo recommendation.

## Testing

```bash
php artisan test
```

30 Pest tests covering: ledger correctness (credit/debit/insufficient-funds/
idempotent-refund/concurrent-debit), the transaction state machine (valid
and invalid transitions, terminal-state protection), the full mobile
purchase pipeline end-to-end (card and wallet payment, insufficient funds,
auto-refund on simulated vend failure, idempotency-key replay, access
control), and admin RBAC (guest redirect, role-based page access).

`phpunit.xml` sets `QUEUE_CONNECTION=sync` and `BROADCAST_CONNECTION=null`
for the test environment, so the full async pipeline runs inline within each
test — no queue worker or Reverb server needed to run the suite.
