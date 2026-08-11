import StatusBadge from '@/Components/Admin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface FundingRow {
    id: number;
    reference: string;
    amount: string;
    currency: string;
    status: string;
    created_at: string;
    user: { full_name: string; email: string } | null;
}

interface WithdrawalRow {
    id: number;
    amount: string;
    currency: string;
    bank_name: string | null;
    account_number: string;
    account_name: string | null;
    status: string;
    failure_reason: string | null;
    created_at: string;
    user: { full_name: string; email: string } | null;
}

interface TransferRow {
    id: number;
    amount: string;
    currency: string;
    created_at: string;
    recipient_name: string | null;
    meta: { counterparty_wallet_address?: string; note?: string } | null;
    wallet: { user: { full_name: string; email: string } | null } | null;
}

interface Props {
    fundings: Paginated<FundingRow>;
    withdrawals: Paginated<WithdrawalRow>;
    transfers: Paginated<TransferRow>;
    filters: { funding_status?: string; withdrawal_status?: string };
}

const money = (amount: string, currency: string) => `${currency} ${Number(amount).toLocaleString()}`;

function Pagination({ data }: { data: Paginated<unknown> }) {
    if (data.last_page <= 1) return null;

    return (
        <div className="flex justify-center gap-1 border-t border-gray-100 px-6 py-3">
            {data.links.map((link, i) => (
                <Link
                    key={i}
                    href={link.url ?? '#'}
                    preserveState
                    className={`rounded px-3 py-1 text-sm ${
                        link.active ? 'bg-brand-700 text-white' : 'text-gray-600 hover:bg-gray-100'
                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export default function Index({ fundings, withdrawals, transfers, filters }: Props) {
    const applyFilter = (key: string, value: string) => {
        router.get(route('admin.wallet-activity.index'), { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Wallet Activity</h2>}>
            <Head title="Wallet Activity" />

            <div className="mx-auto max-w-7xl space-y-8 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-gray-500">
                    Money moving in and out of customer wallets outside the Payment Flow itself: card-funded
                    top-ups, bank withdrawals, and wallet-to-wallet transfers by address.
                </p>

                {/* Fundings */}
                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span className="font-medium text-gray-800">Wallet fundings</span>
                        <select
                            value={filters.funding_status ?? ''}
                            onChange={(e) => applyFilter('funding_status', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                        <span className="ml-auto text-sm text-gray-500">{fundings.total} total</span>
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Amount</th>
                                <th className="px-6 py-3">Reference</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {fundings.data.map((f) => (
                                <tr key={f.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <div>{f.user?.full_name ?? '—'}</div>
                                        <div className="text-xs text-gray-400">{f.user?.email}</div>
                                    </td>
                                    <td className="px-6 py-3">{money(f.amount, f.currency)}</td>
                                    <td className="px-6 py-3 font-mono text-xs text-gray-500">{f.reference}</td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={f.status} />
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(f.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {fundings.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-6 text-center text-gray-400">
                                        No wallet fundings match this filter.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination data={fundings} />
                </div>

                {/* Withdrawals */}
                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span className="font-medium text-gray-800">Withdrawals</span>
                        <select
                            value={filters.withdrawal_status ?? ''}
                            onChange={(e) => applyFilter('withdrawal_status', e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                        >
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                        <span className="ml-auto text-sm text-gray-500">{withdrawals.total} total</span>
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Amount</th>
                                <th className="px-6 py-3">Bank account</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {withdrawals.data.map((w) => (
                                <tr key={w.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <div>{w.user?.full_name ?? '—'}</div>
                                        <div className="text-xs text-gray-400">{w.user?.email}</div>
                                    </td>
                                    <td className="px-6 py-3">{money(w.amount, w.currency)}</td>
                                    <td className="px-6 py-3">
                                        <div>{w.account_name ?? '—'}</div>
                                        <div className="text-xs text-gray-400">
                                            {w.bank_name ?? '—'} · {w.account_number}
                                        </div>
                                    </td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={w.status} />
                                        {w.status === 'failed' && w.failure_reason && (
                                            <div className="mt-1 max-w-xs text-xs text-red-600">{w.failure_reason}</div>
                                        )}
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(w.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {withdrawals.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-6 text-center text-gray-400">
                                        No withdrawals match this filter.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination data={withdrawals} />
                </div>

                {/* Transfers */}
                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4">
                        <span className="font-medium text-gray-800">Wallet-to-wallet transfers</span>
                        <span className="ml-auto text-sm text-gray-500">{transfers.total} total</span>
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">From</th>
                                <th className="px-6 py-3">To</th>
                                <th className="px-6 py-3">Amount</th>
                                <th className="px-6 py-3">Note</th>
                                <th className="px-6 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {transfers.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <div>{t.wallet?.user?.full_name ?? '—'}</div>
                                        <div className="text-xs text-gray-400">{t.wallet?.user?.email}</div>
                                    </td>
                                    <td className="px-6 py-3">
                                        <div>{t.recipient_name ?? '—'}</div>
                                        <div className="font-mono text-xs text-gray-400">
                                            {t.meta?.counterparty_wallet_address ?? '—'}
                                        </div>
                                    </td>
                                    <td className="px-6 py-3">{money(t.amount, t.currency)}</td>
                                    <td className="px-6 py-3 text-gray-500">{t.meta?.note ?? '—'}</td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(t.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {transfers.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-6 text-center text-gray-400">
                                        No wallet-to-wallet transfers yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    <Pagination data={transfers} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
