import StatusBadge from '@/Components/Admin/StatusBadge';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface FraudFlag {
    id: number;
    rule: string;
    status: string;
    details: string | null;
    user: { id: number; full_name: string; email: string } | null;
    transaction: { id: number; reference: string; amount: string; status: string } | null;
    created_at: string;
}

interface Props {
    flags: { data: FraudFlag[]; total: number };
    filters: { status?: string; rule?: string };
}

const STATUSES = ['open', 'reviewed', 'dismissed'];
const RULES = ['velocity', 'unusual_amount'];

export default function Index({ flags, filters }: Props) {
    const updateFilter = (key: string, value: string) =>
        router.get(route('admin.fraud.index'), { ...filters, [key]: value || undefined }, { preserveState: true });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Fraud Flags</h2>}>
            <Head title="Fraud Flags" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3 rounded-xl bg-white p-4 shadow-card ring-1 ring-gray-900/5">
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => updateFilter('status', e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                    >
                        <option value="">All statuses</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.rule ?? ''}
                        onChange={(e) => updateFilter('rule', e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                    >
                        <option value="">All rules</option>
                        {RULES.map((r) => (
                            <option key={r} value={r}>
                                {r.replace(/_/g, ' ')}
                            </option>
                        ))}
                    </select>
                    <span className="ml-auto text-sm text-gray-500">{flags.total} total</span>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Rule</th>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Transaction</th>
                                <th className="px-6 py-3">Details</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Flagged</th>
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {flags.data.map((flag) => (
                                <tr key={flag.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 capitalize">{flag.rule.replace(/_/g, ' ')}</td>
                                    <td className="px-6 py-3">
                                        {flag.user ? (
                                            <Link href={route('admin.users.show', flag.user.id)} className="text-brand-700 hover:underline">
                                                {flag.user.full_name}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-6 py-3">
                                        {flag.transaction ? (
                                            <Link
                                                href={route('admin.transactions.show', flag.transaction.id)}
                                                className="font-mono text-xs text-brand-700 hover:underline"
                                            >
                                                {flag.transaction.reference}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="max-w-md px-6 py-3 text-xs text-gray-600">{flag.details}</td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={flag.status} />
                                    </td>
                                    <td className="px-6 py-3 text-xs text-gray-500">{new Date(flag.created_at).toLocaleString()}</td>
                                    <td className="px-6 py-3">
                                        {flag.status === 'open' && (
                                            <div className="flex gap-2">
                                                <PrimaryButton
                                                    className="!px-2 !py-1 !text-[10px]"
                                                    onClick={() => router.post(route('admin.fraud.review', flag.id))}
                                                >
                                                    Reviewed
                                                </PrimaryButton>
                                                <DangerButton
                                                    className="!px-2 !py-1 !text-[10px]"
                                                    onClick={() => router.post(route('admin.fraud.dismiss', flag.id))}
                                                >
                                                    Dismiss
                                                </DangerButton>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {flags.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-6 py-6 text-center text-gray-400">
                                        No fraud flags match this filter.
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
