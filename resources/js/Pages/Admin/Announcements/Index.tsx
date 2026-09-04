import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Announcement = { id: number; title: string; body: string; is_published: boolean };
type Page = { data: Announcement[]; prev_page_url: string | null; next_page_url: string | null; current_page: number };

function Editor({ announcement, close }: { announcement?: Announcement; close: () => void }) {
    const { data, setData, errors, processing, post, patch } = useForm({
        title: announcement?.title ?? '', body: announcement?.body ?? '', is_published: announcement?.is_published ?? true,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: close, preserveScroll: true };
        if (announcement) patch(route('admin.announcements.update', announcement.id), options);
        else post(route('admin.announcements.store'), options);
    };
    return <form onSubmit={submit} className="mb-6 space-y-4 rounded-2xl border border-brand-100 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold">{announcement ? 'Edit announcement' : 'New announcement'}</h2>
        <label className="block text-sm font-medium">Title<input required maxLength={160} value={data.title} onChange={e => setData('title', e.target.value)} className="mt-2 block w-full rounded-xl border-gray-200 focus:border-brand-600 focus:ring-brand-600" />{errors.title && <span className="text-red-600">{errors.title}</span>}</label>
        <label className="block text-sm font-medium">Message<textarea required rows={6} maxLength={5000} value={data.body} onChange={e => setData('body', e.target.value)} className="mt-2 block w-full rounded-xl border-gray-200 focus:border-brand-600 focus:ring-brand-600" />{errors.body && <span className="text-red-600">{errors.body}</span>}</label>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.is_published} onChange={e => setData('is_published', e.target.checked)} className="rounded text-brand-700 focus:ring-brand-600" />Publish on mobile Home (uncheck to save a draft)</label>
        {errors.is_published && <p className="text-sm text-red-600">{errors.is_published}</p>}
        <div className="flex gap-3"><button disabled={processing} className="rounded-xl bg-brand-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Saving…' : 'Save announcement'}</button><button type="button" onClick={close} disabled={processing} className="rounded-xl border px-5 py-3 text-sm">Cancel</button></div>
    </form>;
}

export default function Index({ announcements }: { announcements: Page }) {
    const [editing, setEditing] = useState<Announcement | 'new' | null>(null);
    return <AuthenticatedLayout><Head title="Announcements" /><div className="p-5 sm:p-8">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h1 className="text-2xl font-semibold">Announcements</h1><p className="mt-1 text-sm text-gray-500">Keep customers informed with updates on their Home screen.</p></div><button onClick={() => setEditing('new')} className="rounded-xl bg-brand-700 px-5 py-3 text-sm font-semibold text-white">New announcement</button></div>
        {editing && <Editor key={editing === 'new' ? 'new' : editing.id} announcement={editing === 'new' ? undefined : editing} close={() => setEditing(null)} />}
        <div className="space-y-4">{announcements.data.map(item => <article key={item.id} className="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100"><div className="flex flex-wrap items-start justify-between gap-3"><h2 className="text-lg font-semibold">{item.title}</h2><span className={`rounded-full px-3 py-1 text-xs font-medium ${item.is_published ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{item.is_published ? 'Published' : 'Draft'}</span></div><p className="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-gray-600">{item.body}</p><div className="mt-4 flex gap-4 text-sm font-semibold"><button onClick={() => setEditing(item)} className="text-brand-700">Edit / change visibility</button><button className="text-red-600" onClick={() => { if (window.confirm('Delete this announcement? It will be removed from mobile Home.')) router.delete(route('admin.announcements.destroy', item.id), { preserveScroll: true }); }}>Delete</button></div></article>)}</div>
        {!announcements.data.length && <div className="rounded-2xl bg-white p-12 text-center text-gray-500">No announcements yet. Create your first update above.</div>}
        <div className="mt-6 flex items-center gap-4 text-sm">{announcements.prev_page_url && <Link href={announcements.prev_page_url}>Previous</Link>}<span>Page {announcements.current_page}</span>{announcements.next_page_url && <Link href={announcements.next_page_url}>Next</Link>}</div>
    </div></AuthenticatedLayout>;
}
