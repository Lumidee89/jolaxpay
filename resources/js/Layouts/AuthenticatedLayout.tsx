import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

/** JolaxPay Admin shell — the only web client here is staff (TRD §2.2, §7). */
const NAV_ITEMS: Array<{ label: string; route: string; permission?: string }> = [
    { label: 'Dashboard', route: 'dashboard' },
    { label: 'Analytics', route: 'admin.analytics.index' },
    { label: 'Transactions', route: 'admin.transactions.index' },
    { label: 'Providers', route: 'admin.providers.index', permission: 'manage-providers' },
    { label: 'Support', route: 'admin.support.index', permission: 'manage-support' },
    { label: 'FAQ', route: 'admin.faq.index', permission: 'manage-support' },
    { label: 'Referrals', route: 'admin.referrals.index', permission: 'manage-referrals' },
    { label: 'Fraud', route: 'admin.fraud.index', permission: 'manage-fraud' },
    { label: 'Users', route: 'admin.users.index', permission: 'manage-users' },
    { label: 'Reconciliation', route: 'admin.reconciliation.index', permission: 'view-reconciliation' },
    { label: 'Wallet Activity', route: 'admin.wallet-activity.index', permission: 'view-reconciliation' },
];

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, flash } = usePage<PageProps>().props;
    const user = auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    const visibleItems = NAV_ITEMS.filter(
        (item) => !item.permission || user.permissions.includes(item.permission),
    );

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="h-1 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800" />

            <nav className="border-b border-gray-100 bg-white/95 backdrop-blur">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center gap-2.5">
                                <Link href={route('dashboard')} className="flex items-center gap-2.5">
                                    <ApplicationLogo className="block h-8 w-auto" />
                                    <span className="hidden text-base font-semibold tracking-tight text-gray-900 sm:block">
                                        JolaxPay
                                    </span>
                                </Link>
                            </div>

                            <div className="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                                {visibleItems.map((item) => (
                                    <NavLink
                                        key={item.route}
                                        href={route(item.route)}
                                        active={route().current(item.route) || route().current(item.route + '.*')}
                                    >
                                        {item.label}
                                    </NavLink>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {user.full_name}
                                                <span className="ms-2 rounded bg-brand-50 px-1.5 py-0.5 text-xs font-medium text-brand-700">
                                                    {user.roles[0] ?? 'staff'}
                                                </span>

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown((previousState) => !previousState)
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
                    <div className="space-y-1 pb-3 pt-2">
                        {visibleItems.map((item) => (
                            <ResponsiveNavLink
                                key={item.route}
                                href={route(item.route)}
                                active={route().current(item.route)}
                            >
                                {item.label}
                            </ResponsiveNavLink>
                        ))}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">{user.full_name}</div>
                            <div className="text-sm font-medium text-gray-500">{user.email}</div>
                        </div>

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                            <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            {(flash?.success || flash?.error) && (
                <div className="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>
                    )}
                    {flash.error && (
                        <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>
                    )}
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
