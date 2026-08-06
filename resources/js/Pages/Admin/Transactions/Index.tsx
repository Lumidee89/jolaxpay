import StatusBadge from '@/Components/Admin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface TransactionRow {
    id: number;
    reference: string;
    status: string;
    service_type: string;
    amount: string;
    currency: string;
    created_at: string;
    user: { full_name: string; email: string } | null;
    meter: { label: string; disco: { name: string } | null } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    transactions: Paginated<TransactionRow>;
    discos: { id: number; name: string }[];
    filters: { status?: string; service_type?: string; disco_id?: string; search?: string };
}

const STATUSES = [
    'fee_disclosed', 'payment_initiated', 'payment_received', 'payment_confirmed',
    'generating_token', 'token_generated', 'delivered', 'outcome_confirmed', 'failed',
];

export default function Index({ transactions, discos, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (key: string, value: string) => {
        router.get(route('admin.transactions.index'), { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilter('search', search);
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Transactions</h2>}>
            <Head title="Transactions" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <form onSubmit={submitSearch} className="flex gap-2">
                        <input
                            type="text"
                            placeholder="Search reference, name, email…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-800 focus:ring-red-800"
                        />
                        <button type="submit" className="rounded-md bg-gray-800 px-3 py-2 text-sm text-white">
                            Search
                        </button>
                    </form>

                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter('status', e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-800 focus:ring-red-800"
                    >
                        <option value="">All statuses</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {s.replace(/_/g, ' ')}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.disco_id ?? ''}
                        onChange={(e) => applyFilter('disco_id', e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-800 focus:ring-red-800"
                    >
                        <option value="">All DisCos</option>
                        {discos.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>

                    <span className="ml-auto text-sm text-gray-500">{transactions.total} total</span>
                </div>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Reference</th>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Meter / DisCo</th>
                                <th className="px-6 py-3">Amount</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {transactions.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <Link
                                            href={route('admin.transactions.show', t.id)}
                                            className="font-mono text-xs text-red-800 hover:underline"
                                        >
                                            {t.reference.slice(0, 8)}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-3">
                                        <div>{t.user?.full_name ?? '—'}</div>
                                        <div className="text-xs text-gray-400">{t.user?.email}</div>
                                    </td>
                                    <td className="px-6 py-3">
                                        {t.meter ? `${t.meter.label} · ${t.meter.disco?.name ?? '—'}` : '—'}
                                    </td>
                                    <td className="px-6 py-3">
                                        {t.currency} {Number(t.amount).toLocaleString()}
                                    </td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={t.status} />
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(t.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {transactions.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No transactions match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>

                    {transactions.last_page > 1 && (
                        <div className="flex justify-center gap-1 border-t border-gray-100 px-6 py-3">
                            {transactions.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    className={`rounded px-3 py-1 text-sm ${
                                        link.active ? 'bg-red-800 text-white' : 'text-gray-600 hover:bg-gray-100'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
