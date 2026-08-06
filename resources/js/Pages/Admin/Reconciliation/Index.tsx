import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface DailyRow {
    date: string;
    transaction_count: number;
    gross_amount: string;
}

interface LedgerTotal {
    type: string;
    reason: string;
    total: string;
}

interface Props {
    daily: DailyRow[];
    ledgerTotals: LedgerTotal[];
    range: { from: string; to: string };
}

export default function Index({ daily, ledgerTotals, range }: Props) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Reconciliation</h2>}>
            <Head title="Reconciliation" />

            <div className="mx-auto max-w-5xl space-y-6 py-8 sm:px-6 lg:px-8">
                <p className="text-sm text-gray-500">
                    {range.from} – {range.to}. Ledger vs. completed-transaction totals — a starting point for
                    processor/DisCo settlement-file reconciliation (Phase 2).
                </p>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <div className="border-b border-gray-100 px-6 py-4 font-medium text-gray-800">
                        Completed transactions by day
                    </div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Date</th>
                                <th className="px-6 py-3">Transactions</th>
                                <th className="px-6 py-3">Gross amount</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {daily.map((row) => (
                                <tr key={row.date}>
                                    <td className="px-6 py-3">{row.date}</td>
                                    <td className="px-6 py-3">{row.transaction_count}</td>
                                    <td className="px-6 py-3">₦{Number(row.gross_amount).toLocaleString()}</td>
                                </tr>
                            ))}
                            {daily.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-6 py-6 text-center text-gray-400">
                                        No completed transactions in this range.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <div className="border-b border-gray-100 px-6 py-4 font-medium text-gray-800">Ledger totals</div>
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Type</th>
                                <th className="px-6 py-3">Reason</th>
                                <th className="px-6 py-3">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {ledgerTotals.map((row, i) => (
                                <tr key={i}>
                                    <td className="px-6 py-3 capitalize">{row.type}</td>
                                    <td className="px-6 py-3 capitalize">{row.reason.replace(/_/g, ' ')}</td>
                                    <td className="px-6 py-3">₦{Number(row.total).toLocaleString()}</td>
                                </tr>
                            ))}
                            {ledgerTotals.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-6 py-6 text-center text-gray-400">
                                        No ledger activity in this range.
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
