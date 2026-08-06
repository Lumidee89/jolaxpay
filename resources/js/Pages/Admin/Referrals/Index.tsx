import StatusBadge from '@/Components/Admin/StatusBadge';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

interface Referral {
    id: number;
    code: string;
    status: string;
    reward_type: string | null;
    reward_value: string | null;
    referrer: { full_name: string; email: string } | null;
    referred_user: { full_name: string; email: string } | null;
    created_at: string;
}

interface Props {
    referrals: { data: Referral[]; total: number };
    filters: { status?: string };
}

const STATUSES = ['pending', 'qualified', 'rewarded', 'flagged'];

export default function Index({ referrals, filters }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Referrals</h2>}>
            <Head title="Referrals" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3 rounded-xl bg-white p-4 shadow-card ring-1 ring-gray-900/5">
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) =>
                            router.get(route('admin.referrals.index'), { status: e.target.value || undefined }, { preserveState: true })
                        }
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                    >
                        <option value="">All statuses</option>
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>
                    <span className="ml-auto text-sm text-gray-500">{referrals.total} total</span>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Code</th>
                                <th className="px-6 py-3">Referrer</th>
                                <th className="px-6 py-3">Referred</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Reward</th>
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {referrals.data.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 font-mono text-xs">{r.code}</td>
                                    <td className="px-6 py-3">{r.referrer?.full_name ?? '—'}</td>
                                    <td className="px-6 py-3">{r.referred_user?.full_name ?? 'Not yet joined'}</td>
                                    <td className="px-6 py-3">
                                        <StatusBadge status={r.status} />
                                    </td>
                                    <td className="px-6 py-3">
                                        {r.reward_value ? `${r.reward_type}: ${r.reward_value}` : '—'}
                                    </td>
                                    <td className="px-6 py-3">
                                        {r.status === 'qualified' && (
                                            <div className="flex gap-2">
                                                <PrimaryButton
                                                    className="!px-2 !py-1 !text-[10px]"
                                                    onClick={() => router.post(route('admin.referrals.approve', r.id))}
                                                >
                                                    Approve
                                                </PrimaryButton>
                                                <DangerButton
                                                    className="!px-2 !py-1 !text-[10px]"
                                                    onClick={() => router.post(route('admin.referrals.flag', r.id))}
                                                >
                                                    Flag
                                                </DangerButton>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {referrals.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No referrals match this filter.
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
