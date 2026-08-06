import StatusBadge from '@/Components/Admin/StatusBadge';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Message {
    id: number;
    body: string;
    is_staff_reply: boolean;
    author: { full_name: string } | null;
    created_at: string;
}

interface Ticket {
    id: number;
    subject: string;
    status: string;
    priority: string;
    transaction_id: number | null;
    user: { full_name: string; email: string; phone_number: string };
    messages: Message[];
}

const STATUSES = ['open', 'pending', 'resolved', 'closed'];

export default function Show({ ticket }: { ticket: Ticket }) {
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.support.reply', ticket.id), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-800">{ticket.subject}</h2>
                    <StatusBadge status={ticket.status} />
                </div>
            }
        >
            <Head title={ticket.subject} />

            <div className="mx-auto grid max-w-5xl grid-cols-1 gap-6 py-8 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div className="space-y-4 lg:col-span-2">
                    <div className="space-y-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-gray-900/5">
                        {ticket.messages.map((m) => (
                            <div
                                key={m.id}
                                className={`rounded-lg p-4 text-sm ${
                                    m.is_staff_reply ? 'bg-brand-50 text-brand-900' : 'bg-gray-50 text-gray-800'
                                }`}
                            >
                                <div className="mb-1 flex justify-between text-xs text-gray-500">
                                    <span className="font-medium">
                                        {m.author?.full_name ?? 'Unknown'} {m.is_staff_reply && '(staff)'}
                                    </span>
                                    <span>{new Date(m.created_at).toLocaleString()}</span>
                                </div>
                                <p className="whitespace-pre-wrap">{m.body}</p>
                            </div>
                        ))}
                    </div>

                    <form onSubmit={submit} className="rounded-xl bg-white p-6 shadow-card ring-1 ring-gray-900/5">
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={4}
                            placeholder="Write a reply…"
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                        />
                        <div className="mt-3 flex justify-end">
                            <PrimaryButton disabled={processing}>Send reply</PrimaryButton>
                        </div>
                    </form>
                </div>

                <div className="space-y-6">
                    <div className="rounded-xl bg-white p-6 shadow-card ring-1 ring-gray-900/5">
                        <h3 className="mb-3 font-medium text-gray-800">Requester</h3>
                        <p className="text-sm text-gray-800">{ticket.user.full_name}</p>
                        <p className="text-sm text-gray-500">{ticket.user.email}</p>
                        <p className="text-sm text-gray-500">{ticket.user.phone_number}</p>
                        {ticket.transaction_id && (
                            <Link
                                href={route('admin.transactions.show', ticket.transaction_id)}
                                className="mt-3 inline-block text-sm text-brand-700 hover:underline"
                            >
                                View linked transaction →
                            </Link>
                        )}
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-card ring-1 ring-gray-900/5">
                        <h3 className="mb-3 font-medium text-gray-800">Status</h3>
                        <select
                            value={ticket.status}
                            onChange={(e) => router.patch(route('admin.support.update-status', ticket.id), { status: e.target.value })}
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                        >
                            {STATUSES.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
