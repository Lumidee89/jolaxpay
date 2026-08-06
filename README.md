# JolaxPay Backend

Laravel backend for JolaxPay: a stateless JSON API for the React Native mobile
app, and an Inertia + React (TSX) admin panel for staff — one codebase, one
database, one business-logic layer. See the companion docs in the repo root
one level up (`01-JolaxPay-PRD.md` … `04-JolaxPay-Implementation-Plan.md`)
for the full product/technical spec this implements.

This repo is the Phase 1 MVP scaffold per the Implementation Plan: the full
domain layer, database schema, mobile API, and a working (if visually
minimal) admin panel, all wired together and tested end-to-end. Live payment
processor / DisCo vending integrations are intentionally **mocked** — see
[Mocked vs. real](#mocked-vs-real) below.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Mobile API | `/api/v1/*`, Sanctum personal-access tokens |
| Admin | `/admin/*`, Inertia + React 18 + TypeScript, session guard |
| Roles/permissions | `spatie/laravel-permission` (`super_admin`, `ops`, `support`) |
| Database | PostgreSQL in staging/production; SQLite locally (zero-config) |
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

Everything for **electricity vending** and **domestic/international
payments** ships behind an interface with a `mock` driver active by default
(`VENDING_ELECTRICITY_DRIVER`, `PAYMENTS_DOMESTIC_DRIVER`,
`PAYMENTS_INTERNATIONAL_DRIVER` in `.env`) — this is intentional (Implementation
Plan §2: "a stub/mock provider for the rest, for parallel frontend
development"), not a placeholder that got forgotten:

- `App\Domain\Vending\Providers\MockElectricityProvider` — generates a
  realistic-looking token; set `transaction.meta.simulate_failure = true` to
  test the retry → refund path.
- `App\Domain\Payments\Providers\MockPaymentProcessor` — always succeeds
  unless `transaction.meta.simulate_payment_failure = true`.
- Notification channels (`SMS_DRIVER`, `WHATSAPP_DRIVER`) default to `log` —
  writes to `storage/logs/laravel.log` instead of calling a real gateway.

Swap a driver by adding a new class implementing the relevant `Contracts\*`
interface and a `match` arm in `VendingManager`/`PaymentManager`.

## Not yet wired up

- **Diaspora Mode multi-currency capture** — the `transactions.fx_rate` /
  `amount_ngn` columns and international payment routing exist; no real
  international processor is integrated.
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
