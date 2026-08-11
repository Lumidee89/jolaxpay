import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface FaqArticle {
    id: number;
    category: string;
    question: string;
    answer: string;
    sort_order: number;
    is_published: boolean;
}

interface Props {
    articles: { data: FaqArticle[]; total: number };
    categories: string[];
    filters: { category?: string };
}

export default function Index({ articles, categories, filters }: Props) {
    const [editing, setEditing] = useState<FaqArticle | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Knowledge Base / FAQ</h2>}>
            <Head title="FAQ" />

            <div className="mx-auto max-w-7xl space-y-4 py-8 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3 rounded-xl bg-white p-4 shadow-card ring-1 ring-gray-900/5">
                    <select
                        value={filters.category ?? ''}
                        onChange={(e) =>
                            router.get(route('admin.faq.index'), { category: e.target.value || undefined }, { preserveState: true })
                        }
                        className="rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>
                    <span className="text-sm text-gray-500">{articles.total} total</span>
                    <PrimaryButton className="ml-auto" onClick={() => setCreating(true)}>
                        Add Article
                    </PrimaryButton>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-gray-900/5">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-6 py-3">Category</th>
                                <th className="px-6 py-3">Question</th>
                                <th className="px-6 py-3">Published</th>
                                <th className="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {articles.data.map((article) => (
                                <tr key={article.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-3 text-gray-500">{article.category}</td>
                                    <td className="px-6 py-3 font-medium text-gray-800">{article.question}</td>
                                    <td className="px-6 py-3">
                                        <input
                                            type="checkbox"
                                            checked={article.is_published}
                                            onChange={(e) =>
                                                router.patch(
                                                    route('admin.faq.update', article.id),
                                                    { ...article, is_published: e.target.checked },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-6 py-3">
                                        <div className="flex gap-2">
                                            <SecondaryButton className="!px-2 !py-1 !text-[10px]" onClick={() => setEditing(article)}>
                                                Edit
                                            </SecondaryButton>
                                            <DangerButton
                                                className="!px-2 !py-1 !text-[10px]"
                                                onClick={() => {
                                                    if (confirm('Delete this article?')) {
                                                        router.delete(route('admin.faq.destroy', article.id));
                                                    }
                                                }}
                                            >
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {articles.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-6 py-6 text-center text-gray-400">
                                        No articles yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {creating && (
                <ArticleModal show={creating} onClose={() => setCreating(false)} title="Add Article" />
            )}
            {editing && (
                <ArticleModal
                    key={editing.id}
                    show={!!editing}
                    onClose={() => setEditing(null)}
                    title="Edit Article"
                    article={editing}
                />
            )}
        </AuthenticatedLayout>
    );
}

function ArticleModal({
    show,
    onClose,
    title,
    article,
}: {
    show: boolean;
    onClose: () => void;
    title: string;
    article?: FaqArticle;
}) {
    const { data, setData, post, patch, processing, reset, errors } = useForm({
        category: article?.category ?? '',
        question: article?.question ?? '',
        answer: article?.answer ?? '',
        sort_order: article?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const onSuccess = () => {
            reset();
            onClose();
        };
        if (article) {
            patch(route('admin.faq.update', article.id), { onSuccess });
        } else {
            post(route('admin.faq.store'), { onSuccess });
        }
    };

    return (
        <Modal show={show} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4 p-6">
                <h3 className="text-lg font-medium text-gray-900">{title}</h3>
                <div>
                    <InputLabel htmlFor="category" value="Category" />
                    <TextInput
                        id="category"
                        className="mt-1 block w-full"
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                    />
                    {errors.category && <p className="mt-1 text-sm text-red-600">{errors.category}</p>}
                </div>
                <div>
                    <InputLabel htmlFor="question" value="Question" />
                    <TextInput
                        id="question"
                        className="mt-1 block w-full"
                        value={data.question}
                        onChange={(e) => setData('question', e.target.value)}
                    />
                    {errors.question && <p className="mt-1 text-sm text-red-600">{errors.question}</p>}
                </div>
                <div>
                    <InputLabel htmlFor="answer" value="Answer" />
                    <textarea
                        id="answer"
                        rows={5}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600"
                        value={data.answer}
                        onChange={(e) => setData('answer', e.target.value)}
                    />
                    {errors.answer && <p className="mt-1 text-sm text-red-600">{errors.answer}</p>}
                </div>
                <div className="flex justify-end gap-3">
                    <SecondaryButton type="button" onClick={onClose}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
