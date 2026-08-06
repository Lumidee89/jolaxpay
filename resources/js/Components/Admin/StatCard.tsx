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

    return (
        <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <div className="text-sm text-gray-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${toneClass}`}>{value}</div>
        </div>
    );
}
