<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Knowledge base content management (PRD §22, manage-support permission). */
class FaqArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $articles = FaqArticle::query()
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Faq/Index', [
            'articles' => $articles,
            'categories' => FaqArticle::query()->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['category']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        FaqArticle::create([...$data, 'sort_order' => $data['sort_order'] ?? 0, 'is_published' => true]);

        return back()->with('success', 'Article added.');
    }

    public function update(Request $request, FaqArticle $faqArticle): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $faqArticle->update($data);

        return back()->with('success', 'Article updated.');
    }

    public function destroy(FaqArticle $faqArticle): RedirectResponse
    {
        $faqArticle->delete();

        return back()->with('success', 'Article deleted.');
    }
}
