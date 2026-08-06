import StatCard from '@/Components/Admin/StatCard';
import StatusBadge from '@/Components/Admin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface RecentTransaction {
    id: number;
    reference: string;
    user: { full_name: string } | null;
    status: string;
    amount: string;
    currency: string;
    created_at: string;
}

interface Props {
    stats: {
        transactions_today: number;
        completed_today: number;
        failed_today: number;
        in_flight_today: number;
        success_rate: number | null;
        open_tickets: number;
        stuck_transactions: number;
        degraded_providers: number;
    };
    recentTransactions: RecentTransaction[];
}

export default function Dashboard({ stats, recentTransactions }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Dashboard</h2>}>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <StatCard label="Transactions today" value={stats.transactions_today} />
                    <StatCard label="Completed today" value={stats.completed_today} tone="good" />
                    <StatCard label="Failed today" value={stats.failed_today} tone={stats.failed_today > 0 ? 'bad' : 'default'} />
                    <StatCard label="In flight" value={stats.in_flight_today} />
                    <StatCard
                        label="Success rate"
                        value={stats.success_rate !== null ? `${stats.success_rate}%` : '—'}
                        tone="good"
                    />
                    <StatCard label="Open tickets" value={stats.open_tickets} tone={stats.open_tickets > 0 ? 'warn' : 'default'} />
                    <StatCard
                        label="Stuck transactions"
                        value={stats.stuck_transactions}
                        tone={stats.stuck_transactions > 0 ? 'bad' : 'good'}
                    />
                    <StatCard
                        label="Degraded providers"
                        value={stats.degraded_providers}
                        tone={stats.degraded_providers > 0 ? 'warn' : 'good'}
                    />
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 className="font-medium text-gray-800">Recent transactions</h3>
                        <Link href={route('admin.transactions.index')} className="text-sm text-brand-700 hover:underline">
                            View all
                        </Link>
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Reference</th>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Amount</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {recentTransactions.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <Link
                                            href={route('admin.transactions.show', t.id)}
                                            className="font-mono text-xs text-brand-700 hover:underline"
                                        >
                                            {t.reference.slice(0, 8)}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-3">{t.user?.full_name ?? '—'}</td>
                                    <td className="px-6 py-3">
                                        {t.currency} {Number(t.amount).toLocaleString()}
                                    </td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={t.status} />
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(t.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {recentTransactions.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-6 text-center text-gray-400">
                                        No transactions yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
