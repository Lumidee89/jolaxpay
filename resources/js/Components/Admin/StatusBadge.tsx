const COLORS: Record<string, string> = {
    fee_disclosed: 'bg-gray-100 text-gray-700',
    payment_initiated: 'bg-blue-100 text-blue-700',
    payment_received: 'bg-blue-100 text-blue-700',
    payment_confirmed: 'bg-violet-100 text-violet-700',
    generating_token: 'bg-amber-100 text-amber-800',
    token_generated: 'bg-amber-100 text-amber-800',
    delivered: 'bg-teal-100 text-teal-800',
    outcome_confirmed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    open: 'bg-blue-100 text-blue-700',
    reviewed: 'bg-green-100 text-green-800',
    dismissed: 'bg-gray-100 text-gray-600',
    pending: 'bg-amber-100 text-amber-800',
    success: 'bg-green-100 text-green-800',
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-600',
    healthy: 'bg-green-100 text-green-800',
    degraded: 'bg-amber-100 text-amber-800',
    down: 'bg-red-100 text-red-800',
    unknown: 'bg-gray-100 text-gray-600',
};

export default function StatusBadge({ status }: { status: string }) {
    const classes = COLORS[status] ?? 'bg-gray-100 text-gray-700';

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`}>
            {status.replace(/_/g, ' ')}
        </span>
    );
}
