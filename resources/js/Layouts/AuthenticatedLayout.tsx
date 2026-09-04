import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

type IconName = 'grid' | 'chart' | 'swap' | 'pulse' | 'chat' | 'book' | 'gift' | 'shield' | 'users' | 'reconcile' | 'wallet';
type NavItem = { label: string; route: string; icon: IconName; permission?: string };
const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', route: 'dashboard', icon: 'grid' },
    { label: 'Analytics', route: 'admin.analytics.index', icon: 'chart' },
    { label: 'Transactions', route: 'admin.transactions.index', icon: 'swap' },
    { label: 'Providers', route: 'admin.providers.index', icon: 'pulse', permission: 'manage-providers' },
    { label: 'Support', route: 'admin.support.index', icon: 'chat', permission: 'manage-support' },
    { label: 'Knowledge base', route: 'admin.faq.index', icon: 'book', permission: 'manage-support' },
    { label: 'Announcements', route: 'admin.announcements.index', icon: 'chat', permission: 'manage-support' },
    { label: 'Agent referrals', route: 'admin.referrals.index', icon: 'gift', permission: 'manage-referrals' },
    { label: 'Fraud monitoring', route: 'admin.fraud.index', icon: 'shield', permission: 'manage-fraud' },
    { label: 'Users', route: 'admin.users.index', icon: 'users', permission: 'manage-users' },
    { label: 'Reconciliation', route: 'admin.reconciliation.index', icon: 'reconcile', permission: 'view-reconciliation' },
    { label: 'Wallet activity', route: 'admin.wallet-activity.index', icon: 'wallet', permission: 'view-reconciliation' },
];

function Icon({ name, className = 'h-5 w-5' }: { name: IconName; className?: string }) {
    const paths: Record<IconName, ReactNode> = {
        grid: <><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></>,
        chart: <><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/></>,
        swap: <><path d="m7 7 3-3 3 3"/><path d="M10 4v12"/><path d="m17 17-3 3-3-3"/><path d="M14 20V8"/></>,
        pulse: <path d="M3 12h4l2-7 4 14 2-7h6"/>,
        chat: <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>,
        book: <><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></>,
        gift: <><rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 12h18M7.5 8C5 8 5 4 7.5 4 10 4 12 8 12 8s2-4 4.5-4S19 8 16.5 8"/></>,
        shield: <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></>,
        users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
        reconcile: <><path d="M20 7h-9M14 3l-4 4 4 4M4 17h9M10 13l4 4-4 4"/></>,
        wallet: <><path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6"/><path d="M16 15h2"/></>,
    };
    return <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{paths[name]}</svg>;
}

export default function Authenticated({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const visibleItems = NAV_ITEMS.filter((item) => !item.permission || user.permissions.includes(item.permission));
    const initials = user.full_name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
    const sidebar = <div className="flex h-full flex-col px-4 py-5">
        <Link href={route('dashboard')} className="flex h-12 items-center gap-3 px-2"><ApplicationLogo className="h-9 w-auto"/><div><div className="text-lg font-bold tracking-tight text-gray-950">JolaxPay</div><div className="-mt-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-600">Admin console</div></div></Link>
        <div className="mt-8 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400">Workspace</div>
        <nav className="mt-3 space-y-1">{visibleItems.map((item) => { const active = route().current(item.route) || route().current(item.route + '.*'); return <Link key={item.route} href={route(item.route)} onClick={() => setSidebarOpen(false)} className={`group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${active ? 'bg-brand-700 text-white shadow-lg shadow-brand-800/15' : 'text-gray-500 hover:bg-brand-50 hover:text-brand-800'}`}><Icon name={item.icon}/><span>{item.label}</span>{active && <span className="absolute -left-4 h-6 w-1 rounded-r-full bg-brand-400"/>}</Link>; })}</nav>
        <div className="mt-auto rounded-2xl bg-gradient-to-br from-brand-800 to-brand-900 p-4 text-white shadow-xl shadow-brand-900/10"><div className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10"><Icon name="shield" className="h-5 w-5"/></div><div className="mt-3 text-sm font-semibold">Secure operations</div><div className="mt-1 text-xs leading-5 text-brand-100/80">Monitor providers, payments and customer requests in one place.</div></div>
    </div>;
    return <div className="min-h-screen bg-[#eeecec] p-0 lg:p-4 xl:p-6">
        {sidebarOpen && <button aria-label="Close navigation" className="fixed inset-0 z-40 bg-gray-950/40 lg:hidden" onClick={() => setSidebarOpen(false)}/>}<aside className={`fixed inset-y-0 left-0 z-50 w-64 transform bg-white transition lg:hidden ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>{sidebar}</aside>
        <div className="mx-auto min-h-[calc(100vh-3rem)] max-w-[1600px] overflow-hidden bg-[#f8f7f7] shadow-2xl shadow-gray-900/10 lg:grid lg:grid-cols-[248px_1fr] lg:rounded-[28px] lg:ring-1 lg:ring-white/80">
            <aside className="hidden border-r border-gray-100 bg-white lg:block">{sidebar}</aside><div className="min-w-0">
                <header className="flex h-[78px] items-center gap-3 border-b border-gray-100 bg-white px-4 sm:px-6"><button onClick={() => setSidebarOpen(true)} className="rounded-xl border border-gray-200 p-2.5 text-gray-600 lg:hidden"><svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><div className="hidden max-w-md flex-1 items-center gap-3 rounded-xl bg-[#f7f5f5] px-4 py-3 text-gray-400 sm:flex"><svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span className="text-sm">Search transactions, users, references...</span><kbd className="ml-auto rounded-md border border-gray-200 bg-white px-2 py-0.5 text-[10px] text-gray-400">⌘ K</kbd></div><div className="ml-auto flex items-center gap-2"><Link href={route('admin.support.index')} className="relative flex h-10 w-10 items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-500 shadow-sm hover:text-brand-700"><Icon name="chat" className="h-4 w-4"/></Link><Dropdown><Dropdown.Trigger><button className="flex items-center gap-3 rounded-xl px-2 py-1.5 text-left hover:bg-gray-50"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-sm font-bold text-brand-800">{initials}</span><span className="hidden sm:block"><span className="block text-sm font-semibold text-gray-900">{user.full_name}</span><span className="block max-w-36 truncate text-xs text-gray-400">{user.email}</span></span><svg className="hidden h-4 w-4 text-gray-400 sm:block" viewBox="0 0 20 20" fill="currentColor"><path d="m5 7 5 5 5-5"/></svg></button></Dropdown.Trigger><Dropdown.Content align="right"><Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link><Dropdown.Link href={route('logout')} method="post" as="button">Log out</Dropdown.Link></Dropdown.Content></Dropdown></div></header>
                {header && <div className="border-b border-gray-100 bg-white px-5 py-4 sm:px-8">{header}</div>}{(flash?.success || flash?.error) && <div className="px-5 pt-5 sm:px-8">{flash.success && <div className="rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}{flash.error && <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}</div>}<main>{children}</main>
            </div>
        </div>
    </div>;
}
