import StatCard from '@/Components/Admin/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface Props {
    activation: { total_users: number; activated_users: number; rate: number | null };
    electricityFlow: {
        total: number;
        completed: number;
        failed: number;
        completion_rate: number | null;
        avg_time_to_completion_minutes: number | null;
    };
    aiInsightEngagement: { shown: number; clicked: number; click_through_rate: number | null };
    referralConversion: { linked: number; rewarded: number; rate: number | null };
    ticketsByCategory: { category: string; total: number }[];
}

export default function Index({ activation, electricityFlow, aiInsightEngagement, referralConversion, ticketsByCategory }: Props) {
    const ticketTotal = ticketsByCategory.reduce((sum, row) => sum + row.total, 0);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Product Analytics</h2>}>
            <Head title="Analytics" />

            <div className="mx-auto max-w-7xl space-y-8 py-8 sm:px-6 lg:px-8">
                <section>
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Activation</h3>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <StatCard label="Total users" value={activation.total_users} />
                        <StatCard label="Activated users" value={activation.activated_users} tone="good" />
                        <StatCard label="Activation rate" value={activation.rate !== null ? `${activation.rate}%` : '—'} tone="good" />
                    </div>
                </section>

                <section>
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Electricity Payment Flow</h3>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <StatCard label="Total purchases" value={electricityFlow.total} />
                        <StatCard
                            label="Completion rate"
                            value={electricityFlow.completion_rate !== null ? `${electricityFlow.completion_rate}%` : '—'}
                            tone="good"
                        />
                        <StatCard label="Failed" value={electricityFlow.failed} tone={electricityFlow.failed > 0 ? 'bad' : 'default'} />
                        <StatCard
                            label="Avg. time to completion"
                            value={
                                electricityFlow.avg_time_to_completion_minutes !== null
                                    ? `${electricityFlow.avg_time_to_completion_minutes} min`
                                    : '—'
                            }
                        />
                    </div>
                </section>

                <section>
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        AI Insight Engagement (Home card)
                    </h3>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <StatCard label="Shown" value={aiInsightEngagement.shown} />
                        <StatCard label="Clicked" value={aiInsightEngagement.clicked} />
                        <StatCard
                            label="Click-through rate"
                            value={aiInsightEngagement.click_through_rate !== null ? `${aiInsightEngagement.click_through_rate}%` : '—'}
                            tone="good"
                        />
                    </div>
                </section>

                <section>
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Referral Conversion</h3>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <StatCard label="Codes linked" value={referralConversion.linked} />
                        <StatCard label="Rewarded" value={referralConversion.rewarded} tone="good" />
                        <StatCard
                            label="Conversion rate"
                            value={referralConversion.rate !== null ? `${referralConversion.rate}%` : '—'}
                            tone="good"
                        />
                    </div>
                </section>

                <section>
                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Support Tickets by Category</h3>
                    <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                        <table className="min-w-full divide-y divide-gray-100 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th className="px-6 py-3">Category</th>
                                    <th className="px-6 py-3">Tickets</th>
                                    <th className="px-6 py-3">Share</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {ticketsByCategory.map((row) => (
                                    <tr key={row.category} className="hover:bg-gray-50">
                                        <td className="px-6 py-3 capitalize">{row.category}</td>
                                        <td className="px-6 py-3">{row.total}</td>
                                        <td className="px-6 py-3 text-gray-500">
                                            {ticketTotal > 0 ? `${Math.round((row.total / ticketTotal) * 100)}%` : '—'}
                                        </td>
                                    </tr>
                                ))}
                                {ticketsByCategory.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-6 text-center text-gray-400">
                                            No support tickets yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
