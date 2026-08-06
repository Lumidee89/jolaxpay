import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface UserRow {
    id: number;
    full_name: string;
    email: string;
    phone_number: string;
    is_active: boolean;
    is_diaspora: boolean;
    created_at: string;
}

interface Props {
    users: { data: UserRow[]; total: number };
    filters: { search?: string };
}

export default function Index({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { search: search || undefined }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Users</h2>}>
            <Head title="Users" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <form onSubmit={submit} className="flex items-center gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-100">
                    <input
                        type="text"
                        placeholder="Search name, email, phone…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-800 focus:ring-red-800"
                    />
                    <button type="submit" className="rounded-md bg-gray-800 px-3 py-2 text-sm text-white">
                        Search
                    </button>
                    <span className="ml-auto text-sm text-gray-500">{users.total} total</span>
                </form>

                <div className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Name</th>
                                <th className="px-6 py-3">Email</th>
                                <th className="px-6 py-3">Phone</th>
                                <th className="px-6 py-3">Diaspora</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Joined</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {users.data.map((u) => (
                                <tr key={u.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3">
                                        <Link href={route('admin.users.show', u.id)} className="text-red-800 hover:underline">
                                            {u.full_name}
                                        </Link>
                                    </td>
                                    <td className="px-6 py-3">{u.email}</td>
                                    <td className="px-6 py-3">{u.phone_number}</td>
                                    <td className="px-6 py-3">{u.is_diaspora ? 'Yes' : 'No'}</td>
                                    <td className="px-6 py-3">
                                        <span className={u.is_active ? 'text-green-700' : 'text-red-700'}>
                                            {u.is_active ? 'Active' : 'Deactivated'}
                                        </span>
                                    </td>
                                    <td className="px-6 py-3 text-gray-500">{new Date(u.created_at).toLocaleDateString()}</td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-6 py-6 text-center text-gray-400">
                                        No users match this search.
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
