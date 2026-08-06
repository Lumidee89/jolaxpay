import StatusBadge from '@/Components/Admin/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

interface Provider {
    id: number;
    name: string;
    region: string | null;
    service_type: string;
    health_status: string;
    health_checked_at: string | null;
    meters_count: number;
    transactions_count: number;
    successful_transactions_count: number;
}

interface BillerProvider {
    id: number;
    name: string;
    service_type: string;
    health_status: string;
    health_checked_at: string | null;
    beneficiaries_count: number;
    transactions_count: number;
    successful_transactions_count: number;
}

const HEALTH_OPTIONS = ['healthy', 'degraded', 'down', 'unknown'];

export default function Health({ providers, billers }: { providers: Provider[]; billers: BillerProvider[] }) {
    const updateHealth = (disco: Provider, status: string) => {
        router.patch(route('admin.providers.update-health', disco.id), { health_status: status });
    };

    const updateBillerHealth = (biller: BillerProvider, status: string) => {
        router.patch(route('admin.billers.update-health', biller.id), { health_status: status });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Provider Health</h2>}>
            <Head title="Provider Health" />

            <div className="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="border-b border-gray-100 px-6 py-4 font-medium text-gray-800">Electricity (DisCos)</div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Provider</th>
                                <th className="px-6 py-3">Region</th>
                                <th className="px-6 py-3">Meters</th>
                                <th className="px-6 py-3">Success rate</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Set status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {providers.map((p) => {
                                const rate = p.transactions_count > 0
                                    ? Math.round((p.successful_transactions_count / p.transactions_count) * 100)
                                    : null;

                                return (
                                    <tr key={p.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-3 font-medium text-gray-800">{p.name}</td>
                                        <td className="px-6 py-3 text-gray-500">{p.region ?? '—'}</td>
                                        <td className="px-6 py-3">{p.meters_count}</td>
                                        <td className="px-6 py-3">{rate !== null ? `${rate}%` : '—'}</td>
                                        <td className="px-6 py-3">
                                            <StatusBadge status={p.health_status} />
                                        </td>
                                        <td className="px-6 py-3">
                                            <select
                                                value={p.health_status}
                                                onChange={(e) => updateHealth(p, e.target.value)}
                                                className="rounded-md border-gray-300 text-xs shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                            >
                                                {HEALTH_OPTIONS.map((opt) => (
                                                    <option key={opt} value={opt}>
                                                        {opt}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                );
                            })}
                            {providers.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No providers seeded yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="border-b border-gray-100 px-6 py-4 font-medium text-gray-800">
                        Airtime, data, cable TV &amp; education (Billers)
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Biller</th>
                                <th className="px-6 py-3">Service</th>
                                <th className="px-6 py-3">Beneficiaries</th>
                                <th className="px-6 py-3">Success rate</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Set status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {billers.map((b) => {
                                const rate = b.transactions_count > 0
                                    ? Math.round((b.successful_transactions_count / b.transactions_count) * 100)
                                    : null;

                                return (
                                    <tr key={b.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-3 font-medium text-gray-800">{b.name}</td>
                                        <td className="px-6 py-3 text-gray-500 capitalize">{b.service_type.replace(/_/g, ' ')}</td>
                                        <td className="px-6 py-3">{b.beneficiaries_count}</td>
                                        <td className="px-6 py-3">{rate !== null ? `${rate}%` : '—'}</td>
                                        <td className="px-6 py-3">
                                            <StatusBadge status={b.health_status} />
                                        </td>
                                        <td className="px-6 py-3">
                                            <select
                                                value={b.health_status}
                                                onChange={(e) => updateBillerHealth(b, e.target.value)}
                                                className="rounded-md border-gray-300 text-xs shadow-sm focus:border-brand-600 focus:ring-brand-600"
                                            >
                                                {HEALTH_OPTIONS.map((opt) => (
                                                    <option key={opt} value={opt}>
                                                        {opt}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                    </tr>
                                );
                            })}
                            {billers.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No billers seeded yet.
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
