import StatusBadge from '@/Components/Admin/StatusBadge';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';

interface StatusHistoryEntry {
    from_status: string | null;
    to_status: string;
    note: string | null;
    created_at: string;
    caused_by_user: { full_name: string } | null;
}

interface LedgerEntry {
    id: number;
    type: string;
    reason: string;
    amount: string;
    balance_after: string;
    created_at: string;
}

interface TransactionDetail {
    id: number;
    reference: string;
    status: string;
    service_type: string;
    amount: string;
    fee: string;
    currency: string;
    payment_method: string | null;
    token: string | null;
    delivery_destination: string;
    delivery_channel: string | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    recipient_email: string | null;
    outcome_confirmed: boolean | null;
    outcome_reason: string | null;
    vend_attempts: number;
    delivery_attempts: number;
    refunded_to_wallet: boolean;
    user: { id: number; full_name: string; email: string; phone_number: string };
    meter: { label: string; meter_number: string; disco: { name: string } | null } | null;
    status_history: StatusHistoryEntry[];
    ledger_entries: LedgerEntry[];
    created_at: string;
}

export default function Show({ transaction }: { transaction: TransactionDetail }) {
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.user.permissions.includes('manage-transactions');
    const isTerminal = transaction.status === 'outcome_confirmed' || transaction.status === 'failed';

    const retry = () => {
        if (confirm('Retry this transaction from its current stage?')) {
            router.post(route('admin.transactions.retry', transaction.id));
        }
    };

    const refund = () => {
        if (confirm('Refund the full amount to the customer wallet?')) {
            router.post(route('admin.transactions.refund', transaction.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">
                        Transaction <span className="font-mono text-base text-gray-500">{transaction.reference}</span>
                    </h2>
                    <StatusBadge status={transaction.status} />
                </div>
            }
        >
            <Head title={`Transaction ${transaction.reference}`} />

            <div className="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
                {canManage && !isTerminal && (
                    <div className="flex gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-100">
                        <SecondaryButton onClick={retry}>Manual retry</SecondaryButton>
                        <DangerButton onClick={refund} disabled={transaction.refunded_to_wallet}>
                            {transaction.refunded_to_wallet ? 'Already refunded' : 'Refund to wallet'}
                        </DangerButton>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Section title="Purchase">
                            <Row label="Service" value={transaction.service_type.replace(/_/g, ' ')} />
                            <Row label="Amount" value={`${transaction.currency} ${Number(transaction.amount).toLocaleString()}`} />
                            <Row label="Fee" value={`${transaction.currency} ${Number(transaction.fee).toLocaleString()}`} />
                            <Row label="Payment method" value={transaction.payment_method ?? '—'} />
                            <Row label="Meter" value={transaction.meter ? `${transaction.meter.label} (${transaction.meter.meter_number})` : '—'} />
                            <Row label="DisCo" value={transaction.meter?.disco?.name ?? '—'} />
                            <Row label="Token" value={transaction.token ?? '—'} mono />
                            <Row label="Vend attempts" value={String(transaction.vend_attempts)} />
                            <Row label="Delivery attempts" value={String(transaction.delivery_attempts)} />
                        </Section>

                        <Section title="Delivery">
                            <Row label="Destination" value={transaction.delivery_destination.replace(/_/g, ' ')} />
                            <Row label="Channel" value={transaction.delivery_channel ?? '—'} />
                            <Row label="Recipient" value={transaction.recipient_name ?? '—'} />
                            <Row label="Recipient phone" value={transaction.recipient_phone ?? '—'} />
                            <Row label="Recipient email" value={transaction.recipient_email ?? '—'} />
                            <Row
                                label="Outcome confirmed"
                                value={
                                    transaction.outcome_confirmed === null
                                        ? 'Awaiting confirmation'
                                        : transaction.outcome_confirmed
                                          ? 'Yes'
                                          : `No — ${transaction.outcome_reason ?? 'no reason given'}`
                                }
                            />
                        </Section>

                        <Section title="State history">
                            <div className="space-y-3">
                                {transaction.status_history.map((h, i) => (
                                    <div key={i} className="flex items-start gap-3 text-sm">
                                        <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-800" />
                                        <div>
                                            <div className="font-medium text-gray-800">
                                                {h.from_status ? `${h.from_status} → ${h.to_status}` : h.to_status}
                                            </div>
                                            {h.note && <div className="text-gray-500">{h.note}</div>}
                                            <div className="text-xs text-gray-400">
                                                {new Date(h.created_at).toLocaleString()}
                                                {h.caused_by_user && ` · ${h.caused_by_user.full_name}`}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Section>
                    </div>

                    <div className="space-y-6">
                        <Section title="Buyer">
                            <Row label="Name" value={transaction.user.full_name} />
                            <Row label="Email" value={transaction.user.email} />
                            <Row label="Phone" value={transaction.user.phone_number} />
                        </Section>

                        <Section title="Ledger entries">
                            {transaction.ledger_entries.length === 0 && (
                                <p className="text-sm text-gray-400">No ledger entries for this transaction.</p>
                            )}
                            <div className="space-y-2">
                                {transaction.ledger_entries.map((entry) => (
                                    <div key={entry.id} className="flex justify-between text-sm">
                                        <span className="text-gray-600">
                                            {entry.type} · {entry.reason.replace(/_/g, ' ')}
                                        </span>
                                        <span className={entry.type === 'credit' ? 'text-green-700' : 'text-red-700'}>
                                            {entry.type === 'credit' ? '+' : '-'}
                                            {Number(entry.amount).toLocaleString()}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 className="mb-4 font-medium text-gray-800">{title}</h3>
            {children}
        </div>
    );
}

function Row({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex justify-between border-b border-gray-50 py-2 text-sm last:border-0">
            <span className="text-gray-500">{label}</span>
            <span className={mono ? 'font-mono text-gray-800' : 'text-gray-800'}>{value}</span>
        </div>
    );
}
