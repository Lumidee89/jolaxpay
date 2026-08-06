import StatusBadge from '@/Components/Admin/StatusBadge';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Wallet {
    id: number;
    balance: string;
    currency: string;
}

interface Transaction {
    id: number;
    reference: string;
    status: string;
    amount: string;
    currency: string;
    created_at: string;
}

interface Session {
    id: number;
    device_name: string;
    last_used_at: string | null;
}

interface TargetUser {
    id: number;
    full_name: string;
    email: string;
    phone_number: string;
    is_active: boolean;
    is_diaspora: boolean;
    country_code: string;
    created_at: string;
    wallets: Wallet[];
    transactions: Transaction[];
}

export default function Show({ targetUser, sessions }: { targetUser: TargetUser; sessions: Session[] }) {
    const resetPassword = () => {
        if (confirm(`Issue a temporary password for ${targetUser.full_name}?`)) {
            router.post(route('admin.users.reset-password', targetUser.id));
        }
    };

    const toggleActive = () => {
        const verb = targetUser.is_active ? 'deactivate' : 'reactivate';
        if (confirm(`Are you sure you want to ${verb} this account?`)) {
            router.post(route('admin.users.toggle-active', targetUser.id));
        }
    };

    const revokeSession = (sessionId: number) => {
        router.delete(route('admin.users.revoke-session', [targetUser.id, sessionId]));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">{targetUser.full_name}</h2>
                    <span className={targetUser.is_active ? 'text-sm text-green-700' : 'text-sm text-red-700'}>
                        {targetUser.is_active ? 'Active' : 'Deactivated'}
                    </span>
                </div>
            }
        >
            <Head title={targetUser.full_name} />

            <div className="mx-auto grid max-w-6xl grid-cols-1 gap-6 py-8 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div className="space-y-6 lg:col-span-2">
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h3 className="mb-4 font-medium text-gray-800">Recent transactions</h3>
                        <div className="divide-y divide-gray-50">
                            {targetUser.transactions.map((t) => (
                                <div key={t.id} className="flex items-center justify-between py-2 text-sm">
                                    <Link href={route('admin.transactions.show', t.id)} className="font-mono text-xs text-red-800 hover:underline">
                                        {t.reference.slice(0, 8)}
                                    </Link>
                                    <span>
                                        {t.currency} {Number(t.amount).toLocaleString()}
                                    </span>
                                    <StatusBadge status={t.status} />
                                    <span className="text-gray-400">{new Date(t.created_at).toLocaleDateString()}</span>
                                </div>
                            ))}
                            {targetUser.transactions.length === 0 && (
                                <p className="py-4 text-sm text-gray-400">No transactions yet.</p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h3 className="mb-4 font-medium text-gray-800">Active device sessions</h3>
                        <div className="divide-y divide-gray-50">
                            {sessions.map((s) => (
                                <div key={s.id} className="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <div className="text-gray-800">{s.device_name}</div>
                                        <div className="text-xs text-gray-400">
                                            {s.last_used_at ? `Last used ${new Date(s.last_used_at).toLocaleString()}` : 'Never used'}
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => revokeSession(s.id)}
                                        className="text-xs text-red-700 hover:underline"
                                    >
                                        Revoke
                                    </button>
                                </div>
                            ))}
                            {sessions.length === 0 && <p className="py-4 text-sm text-gray-400">No active sessions.</p>}
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h3 className="mb-3 font-medium text-gray-800">Details</h3>
                        <p className="text-sm text-gray-800">{targetUser.email}</p>
                        <p className="text-sm text-gray-500">{targetUser.phone_number}</p>
                        <p className="mt-2 text-xs text-gray-400">
                            {targetUser.country_code} {targetUser.is_diaspora && '· Diaspora'}
                        </p>
                        <p className="mt-1 text-xs text-gray-400">
                            Joined {new Date(targetUser.created_at).toLocaleDateString()}
                        </p>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h3 className="mb-3 font-medium text-gray-800">Wallets</h3>
                        {targetUser.wallets.map((w) => (
                            <div key={w.id} className="flex justify-between text-sm">
                                <span className="text-gray-500">{w.currency}</span>
                                <span className="font-medium text-gray-800">{Number(w.balance).toLocaleString()}</span>
                            </div>
                        ))}
                    </div>

                    <div className="space-y-2 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                        <h3 className="mb-1 font-medium text-gray-800">Actions</h3>
                        <SecondaryButton onClick={resetPassword} className="w-full justify-center">
                            Reset password
                        </SecondaryButton>
                        <DangerButton onClick={toggleActive} className="w-full justify-center">
                            {targetUser.is_active ? 'Deactivate (fraud flag)' : 'Reactivate account'}
                        </DangerButton>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
