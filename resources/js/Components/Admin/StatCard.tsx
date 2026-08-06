export default function StatCard({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: string | number;
    tone?: 'default' | 'good' | 'bad' | 'warn';
}) {
    const toneClass = {
        default: 'text-gray-900',
        good: 'text-green-700',
        bad: 'text-red-700',
        warn: 'text-amber-700',
    }[tone];

    const accentClass = {
        default: 'bg-brand-600',
        good: 'bg-green-500',
        bad: 'bg-red-500',
        warn: 'bg-amber-500',
    }[tone];

    return (
        <div className="relative overflow-hidden rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5 transition hover:shadow-md">
            <div className={`absolute inset-x-0 top-0 h-0.5 ${accentClass}`} />
            <div className="text-sm text-gray-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${toneClass}`}>{value}</div>
        </div>
    );
}
