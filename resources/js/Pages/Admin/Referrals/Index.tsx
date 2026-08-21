import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Leader = { position: number; agent_id: number; merchant_id: string; name: string; total_referrals: number; active_referrals: number; referral_transactions: number; referral_earnings: number; direct_earnings: number };
type Rule = { id: number; name: string; earning_type: string; service_type?: string; calculation_type: string; value: string; is_active: boolean };
type Settings = { leaderboard_enabled: boolean; ranking_metric: string; active_min_transactions: number; visible_positions: number; ranking_period: string; promotional_message?: string; milestones: Array<{ threshold: number; name: string }> };
type Props = { leaderboard: Leader[]; dateRange: { from: string; to: string; label: string }; filters: Record<string, string>; settings: Settings; rules: Rule[]; campaigns: Array<{ id: number; name: string; starts_at: string; ends_at: string; is_active: boolean }>; rewards: Array<{ id: number; status: string; reward?: string; rewarded_at?: string; agent?: { full_name: string } }> };

const money = (value: number) => `₦${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

export default function Index({ leaderboard, dateRange, filters, settings, rules, campaigns, rewards }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const rule = useForm({ name: '', earning_type: 'referral', service_type: '', calculation_type: 'percentage', value: '', jolaxpay_margin: '', minimum_commission: '', maximum_commission: '', starts_at: '', ends_at: '', is_active: true });
    const reward = useForm({ agent_ids: [] as number[], status: 'planned', period_key: `${dateRange.from}:${dateRange.to}`, reward: '', rewarded_at: '', internal_note: '', notify: true });
    const campaign = useForm({ name: '', starts_at: '', ends_at: '', ranking_metric: 'active', is_active: false, promotional_message: '', reward_details: {} });
    const configuration = useForm({ ...settings, milestones: settings.milestones ?? [] });

    const filter = (key: string, value: string) => router.get(route('admin.referrals.index'), { ...filters, [key]: value || undefined }, { preserveState: true });
    const submitReward = () => router.post(route('admin.referrals.rewards.store'), { ...reward.data, agent_ids: selected });

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Agent Referral Performance</h2>}>
            <Head title="Agent Referral Performance" />
            <div className="mx-auto max-w-7xl space-y-6 py-8 sm:px-6 lg:px-8">
                <section className="rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5">
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-sm">Period<select className="mt-1 block rounded-md border-gray-300" value={filters.period ?? 'month'} onChange={e => filter('period', e.target.value)}><option value="today">Today</option><option value="week">This week</option><option value="month">This month</option><option value="previous_month">Previous month</option><option value="custom">Custom</option></select></label>
                        <label className="text-sm">Rank by<select className="mt-1 block rounded-md border-gray-300" value={filters.metric ?? 'active'} onChange={e => filter('metric', e.target.value)}><option value="active">Active referrals</option><option value="total">Total referrals</option></select></label>
                        {filters.period === 'custom' && <><input type="date" value={filters.from ?? ''} onChange={e => filter('from', e.target.value)} className="rounded-md border-gray-300"/><input type="date" value={filters.to ?? ''} onChange={e => filter('to', e.target.value)} className="rounded-md border-gray-300"/></>}
                        <a className="ml-auto rounded-md bg-gray-900 px-4 py-2 text-sm text-white" href={route('admin.referrals.export', filters)}>Export CSV</a>
                    </div>
                    <p className="mt-3 text-sm text-gray-500">{dateRange.label}: {dateRange.from} – {dateRange.to}</p>
                </section>

                <section className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <div className="flex items-center border-b p-5"><h3 className="font-semibold">Referral leaderboard</h3><span className="ml-auto text-sm text-gray-500">{leaderboard.length} Agents</span></div>
                    <div className="overflow-x-auto"><table className="min-w-full divide-y text-sm"><thead className="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th className="p-3"></th><th>Rank</th><th>Agent</th><th>Total</th><th>Active</th><th>Transactions</th><th>Referral earnings</th><th>Direct earnings</th></tr></thead><tbody className="divide-y">{leaderboard.map(row => <tr key={row.agent_id}><td className="p-3"><input type="checkbox" checked={selected.includes(row.agent_id)} onChange={() => setSelected(v => v.includes(row.agent_id) ? v.filter(id => id !== row.agent_id) : [...v, row.agent_id])}/></td><td>#{row.position}</td><td className="py-3"><div className="font-medium">{row.name}</div><div className="text-xs text-gray-500">{row.merchant_id}</div></td><td>{row.total_referrals}</td><td>{row.active_referrals}</td><td>{row.referral_transactions}</td><td>{money(row.referral_earnings)}</td><td>{money(row.direct_earnings)}</td></tr>)}</tbody></table></div>
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <form onSubmit={e => { e.preventDefault(); submitReward(); }} className="space-y-3 rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5">
                        <h3 className="font-semibold">Reward selected Agents ({selected.length})</h3>
                        <select value={reward.data.status} onChange={e => reward.setData('status', e.target.value)} className="w-full rounded-md border-gray-300"><option value="planned">Internal reward planned</option><option value="rewarded">Mark as rewarded</option></select>
                        <textarea placeholder="Reward given" value={reward.data.reward} onChange={e => reward.setData('reward', e.target.value)} className="w-full rounded-md border-gray-300"/>
                        {reward.data.status === 'rewarded' && <label className="block text-sm">Date issued<input type="datetime-local" value={reward.data.rewarded_at} onChange={e => reward.setData('rewarded_at', e.target.value)} className="mt-1 w-full rounded-md border-gray-300"/></label>}
                        <textarea placeholder="Internal note" value={reward.data.internal_note} onChange={e => reward.setData('internal_note', e.target.value)} className="w-full rounded-md border-gray-300"/>
                        <label className="flex gap-2 text-sm"><input type="checkbox" checked={reward.data.notify} onChange={e => reward.setData('notify', e.target.checked)}/>Send in-app notification</label>
                        <PrimaryButton disabled={!selected.length || reward.processing}>Save reward</PrimaryButton>
                    </form>

                    <form onSubmit={e => { e.preventDefault(); rule.post(route('admin.referrals.rules.store'), { onSuccess: () => rule.reset() }); }} className="space-y-3 rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5">
                        <h3 className="font-semibold">Add commission rule</h3>
                        <input placeholder="Rule name" value={rule.data.name} onChange={e => rule.setData('name', e.target.value)} className="w-full rounded-md border-gray-300"/>
                        <div className="grid grid-cols-2 gap-2"><select value={rule.data.earning_type} onChange={e => rule.setData('earning_type', e.target.value)} className="rounded-md border-gray-300"><option value="direct">Direct Agent sale</option><option value="referral">Referral transaction</option></select><select value={rule.data.service_type} onChange={e => rule.setData('service_type', e.target.value)} className="rounded-md border-gray-300"><option value="">All services</option>{['electricity','airtime','data','cable_tv','education'].map(v => <option key={v}>{v}</option>)}</select></div>
                        <div className="grid grid-cols-2 gap-2"><select value={rule.data.calculation_type} onChange={e => rule.setData('calculation_type', e.target.value)} className="rounded-md border-gray-300"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select><input type="number" step="0.0001" placeholder="Value" value={rule.data.value} onChange={e => rule.setData('value', e.target.value)} className="rounded-md border-gray-300"/></div>
                        <PrimaryButton disabled={rule.processing}>Create rule</PrimaryButton>
                    </form>
                </section>

                <section className="rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5"><h3 className="mb-3 font-semibold">Commission rules</h3><div className="space-y-2">{rules.map(r => <div key={r.id} className="flex items-center rounded-lg border p-3 text-sm"><div><b>{r.name}</b><div className="text-gray-500">{r.earning_type} · {r.service_type || 'all services'} · {r.value}{r.calculation_type === 'percentage' ? '%' : ' fixed'}</div></div><button className="ml-auto text-brand-700" onClick={() => router.patch(route('admin.referrals.rules.update', r.id), { is_active: !r.is_active })}>{r.is_active ? 'Deactivate' : 'Activate'}</button></div>)}</div></section>

                <form onSubmit={e => { e.preventDefault(); configuration.patch(route('admin.referrals.settings.update')); }} className="space-y-3 rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5"><h3 className="font-semibold">Leaderboard and qualification controls</h3><div className="grid gap-3 md:grid-cols-4"><label className="text-sm">Active transactions<input type="number" min="1" value={configuration.data.active_min_transactions} onChange={e => configuration.setData('active_min_transactions', Number(e.target.value))} className="mt-1 w-full rounded-md border-gray-300"/></label><label className="text-sm">Visible positions<input type="number" min="1" value={configuration.data.visible_positions} onChange={e => configuration.setData('visible_positions', Number(e.target.value))} className="mt-1 w-full rounded-md border-gray-300"/></label><label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={configuration.data.leaderboard_enabled} onChange={e => configuration.setData('leaderboard_enabled', e.target.checked)}/>Leaderboard active</label></div><div><h4 className="mb-2 text-sm font-medium">Achievement milestones</h4><div className="grid gap-2 md:grid-cols-2">{configuration.data.milestones.map((milestone, index) => <div key={index} className="grid grid-cols-[100px_1fr] gap-2"><input type="number" min="1" value={milestone.threshold} onChange={e => configuration.setData('milestones', configuration.data.milestones.map((item, i) => i === index ? { ...item, threshold: Number(e.target.value) } : item))} className="rounded-md border-gray-300"/><input value={milestone.name} onChange={e => configuration.setData('milestones', configuration.data.milestones.map((item, i) => i === index ? { ...item, name: e.target.value } : item))} className="rounded-md border-gray-300"/></div>)}</div></div><textarea placeholder="Promotional message" value={configuration.data.promotional_message ?? ''} onChange={e => configuration.setData('promotional_message', e.target.value)} className="w-full rounded-md border-gray-300"/><PrimaryButton>Save controls</PrimaryButton></form>

                <form onSubmit={e => { e.preventDefault(); campaign.post(route('admin.referrals.campaigns.store'), { onSuccess: () => campaign.reset() }); }} className="space-y-3 rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5"><h3 className="font-semibold">Create referral competition</h3><div className="grid gap-3 md:grid-cols-2"><input placeholder="Campaign name" value={campaign.data.name} onChange={e => campaign.setData('name', e.target.value)} className="rounded-md border-gray-300"/><select value={campaign.data.ranking_metric} onChange={e => campaign.setData('ranking_metric', e.target.value)} className="rounded-md border-gray-300"><option value="active">Active referrals</option><option value="total">Total registrations</option></select><label className="text-sm">Starts<input type="datetime-local" value={campaign.data.starts_at} onChange={e => campaign.setData('starts_at', e.target.value)} className="mt-1 w-full rounded-md border-gray-300"/></label><label className="text-sm">Ends<input type="datetime-local" value={campaign.data.ends_at} onChange={e => campaign.setData('ends_at', e.target.value)} className="mt-1 w-full rounded-md border-gray-300"/></label></div><textarea placeholder="Campaign message" value={campaign.data.promotional_message} onChange={e => campaign.setData('promotional_message', e.target.value)} className="w-full rounded-md border-gray-300"/><label className="flex gap-2 text-sm"><input type="checkbox" checked={campaign.data.is_active} onChange={e => campaign.setData('is_active', e.target.checked)}/>Activate immediately</label><PrimaryButton disabled={campaign.processing}>Create competition</PrimaryButton></form>

                <section className="grid gap-6 lg:grid-cols-2"><div className="rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5"><h3 className="mb-3 font-semibold">Campaign history</h3>{campaigns.map(c => <p key={c.id} className="border-b py-2 text-sm"><b>{c.name}</b> · {c.is_active ? 'Active' : 'Inactive'}<br/><span className="text-gray-500">{c.starts_at} – {c.ends_at}</span></p>)}</div><div className="rounded-xl bg-white p-5 shadow-card ring-1 ring-gray-900/5"><h3 className="mb-3 font-semibold">Reward history</h3>{rewards.map(r => <p key={r.id} className="border-b py-2 text-sm"><b>{r.agent?.full_name}</b> · {r.status}<br/><span className="text-gray-500">{r.reward || 'No reward description'}</span></p>)}</div></section>
            </div>
        </AuthenticatedLayout>
    );
}
