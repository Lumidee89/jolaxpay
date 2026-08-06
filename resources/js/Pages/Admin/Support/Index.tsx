import StatusBadge from '@/Components/Admin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Ticket {
    id: number;
    subject: string;
    status: string;
    priority: string;
    transaction_id: number | null;
    user: { full_name: string; email: string } | null;
    assignee: { full_name: string } | null;
    created_at: string;
}

interface Props {
    tickets: { data: Ticket[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: { status?: string };
}

const STATUSES = ['open', 'pending', 'resolved', 'closed'];

export default function Index({ tickets, filters }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Support Tickets</h2>}>
            <Head title="Support" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(route('admin.support.index'), { status: e.target.value || undefined }, { preserveState: true })
                        }
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-800 focus:ring-red-800"
                    >
                        <option value="">All statuses</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                    <span className="ml-auto text-sm text-gray-500">{tickets.total} total</span>
                </div>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Subject</th>
                                <th className="px-6 py-3">User</th>
                                <th className="px-6 py-3">Priority</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Assignee</th>
                                <th className="px-6 py-3">Opened</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {tickets.data.map((t) => (
                                <tr key={t.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <Link href={route('admin.support.show', t.id)} className="text-red-800 hover:underline">
                                            {t.subject}
                                        </Link>
                                        {t.transaction_id && (
                                            <span className="ml-2 text-xs text-gray-400">#{t.transaction_id}</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-3">{t.user?.full_name ?? '—'}</td>
                                    <td className="px-6 py-3 capitalize">{t.priority}</td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={t.status} />
                                    </td>
                                    <td className="px-6 py-3">{t.assignee?.full_name ?? 'Unassigned'}</td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(t.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No tickets match this filter.
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
