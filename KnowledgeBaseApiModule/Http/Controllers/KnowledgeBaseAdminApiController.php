<?php

namespace Modules\KnowledgeBaseApiModule\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Modules\KnowledgeBase\Entities\KbArticle;
use Modules\KnowledgeBase\Entities\KbArticleKbCategory;
use Modules\KnowledgeBase\Entities\KbCategory;

class KnowledgeBaseAdminApiController extends Controller
{
    private function getLocales(Mailbox $mailbox): array
    {
        $locales = \Kb::getLocales($mailbox);
        if (empty($locales)) {
            $locales = [\Kb::defaultLocale($mailbox)];
        }
        return $locales;
    }

    private function applyTranslatables($entity, Request $request, array $fields, Mailbox $mailbox): void
    {
        $defaultLocale = \Kb::defaultLocale($mailbox);
        $requestLocale = $request->input('locale');

        foreach ($fields as $field) {
            $i18nField = $field.'_i18n';
            if ($request->has($i18nField) && is_array($request->input($i18nField))) {
                foreach ($request->input($i18nField) as $locale => $value) {
                    $entity->setAttributeInLocale($field, (string)$value, (string)$locale);
                }
            } elseif ($request->has($field)) {
                $locale = $requestLocale ?: $defaultLocale;
                $entity->setAttributeInLocale($field, (string)$request->input($field), (string)$locale);
            }
        }
    }

    private function categoryToArray(KbCategory $category, array $locales): array
    {
        $translations = [];
        foreach (KbCategory::$translatable_fields as $field) {
            $translations[$field] = [];
            foreach ($locales as $locale) {
                $translations[$field][$locale] = $category->getAttributeInLocale($field, $locale);
            }
        }
        return [
            'id' => $category->id,
            'mailbox_id' => $category->mailbox_id,
            'parent_id' => $category->kb_category_id,
            'visibility' => $category->visibility,
            'expand' => (bool)$category->expand,
            'sort_order' => $category->sort_order,
            'articles_order' => $category->articles_order,
            'translations' => $translations,
        ];
    }

    private function articleToArray(KbArticle $article, array $locales): array
    {
        $translations = [];
        foreach (KbArticle::$translatable_fields as $field) {
            $translations[$field] = [];
            foreach ($locales as $locale) {
                $translations[$field][$locale] = $article->getAttributeInLocale($field, $locale);
            }
        }
        return [
            'id' => $article->id,
            'mailbox_id' => $article->mailbox_id,
            'status' => $article->status,
            'status_name' => $article->status == KbArticle::STATUS_PUBLISHED ? 'published' : 'draft',
            'sort_order' => $article->sort_order,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
            'category_ids' => $article->categories->pluck('id')->all(),
            'translations' => $translations,
        ];
    }

    public function categoriesFull(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $locales = $this->getLocales($mailbox);
        $categories = KbCategory::where('mailbox_id', $mailbox->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Response::json([
            'mailbox_id' => $mailbox->id,
            'mailbox_name' => $mailbox->name,
            'default_locale' => \Kb::defaultLocale($mailbox),
            'locales' => $locales,
            'categories' => $categories->map(fn($c) => $this->categoryToArray($c, $locales))->values()->all(),
        ]);
    }

    public function categoryShow(Request $request, $categoryId)
    {
        $category = KbCategory::findOrFail($categoryId);
        $mailbox = Mailbox::findOrFail($category->mailbox_id);
        $locales = $this->getLocales($mailbox);
        return Response::json($this->categoryToArray($category, $locales));
    }

    public function categoryCreate(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $category = new KbCategory();
        $category->mailbox_id = $mailbox->id;
        return $this->saveCategory($category, $request, $mailbox, 201);
    }

    public function categoryUpdate(Request $request, $categoryId)
    {
        $category = KbCategory::findOrFail($categoryId);
        $mailbox = Mailbox::findOrFail($category->mailbox_id);
        return $this->saveCategory($category, $request, $mailbox, 200);
    }

    private function saveCategory(KbCategory $category, Request $request, Mailbox $mailbox, int $successCode)
    {
        if ($request->has('parent_id')) {
            $parentId = $request->input('parent_id');
            if ($parentId) {
                $parent = KbCategory::where('id', $parentId)->where('mailbox_id', $mailbox->id)->first();
                if (!$parent) {
                    return Response::json(['error' => 'parent_id not found in this mailbox'], 422);
                }
                if ($category->id && $parent->id == $category->id) {
                    return Response::json(['error' => 'category cannot be its own parent'], 422);
                }
                $category->kb_category_id = $parent->id;
            } else {
                $category->kb_category_id = null;
            }
        }
        if ($request->has('visibility')) {
            $vis = (int)$request->input('visibility');
            if (!in_array($vis, [KbCategory::VISIBILITY_PUBLIC, KbCategory::VISIBILITY_PRIVATE], true)) {
                return Response::json(['error' => 'visibility must be 1 (public) or 2 (private)'], 422);
            }
            $category->visibility = $vis;
        }
        if ($request->has('expand')) {
            $category->expand = (bool)$request->input('expand');
        }
        if ($request->has('articles_order')) {
            $category->articles_order = (int)$request->input('articles_order');
        }
        if ($request->has('sort_order')) {
            $category->sort_order = (int)$request->input('sort_order');
        }

        $this->applyTranslatables($category, $request, KbCategory::$translatable_fields, $mailbox);

        try {
            $category->save();
        } catch (\Exception $e) {
            return Response::json(['error' => 'save failed', 'details' => $e->getMessage()], 422);
        }

        // Bust the static cache so subsequent reads in the same request see the new state.
        KbCategory::$categories_cached = null;
        KbCategory::$articles_counts = null;
        KbCategory::$article_to_category_cached = null;

        return Response::json($this->categoryToArray($category, $this->getLocales($mailbox)), $successCode);
    }

    public function categoryDelete($categoryId)
    {
        $category = KbCategory::findOrFail($categoryId);
        $id = $category->id;
        KbArticleKbCategory::where('kb_category_id', $id)->delete();
        KbCategory::where('kb_category_id', $id)->update(['kb_category_id' => null]);
        $category->delete();

        KbCategory::$categories_cached = null;
        KbCategory::$articles_counts = null;
        KbCategory::$article_to_category_cached = null;

        return Response::json(['deleted' => true, 'id' => $id]);
    }

    public function categoriesReorder(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $items = $request->input('categories');
        if (!is_array($items) || empty($items)) {
            return Response::json(['error' => 'categories[] array required, each item with id and sort_order/parent_id'], 422);
        }
        $updated = 0;
        foreach ($items as $item) {
            if (empty($item['id'])) {
                continue;
            }
            $category = KbCategory::where('id', $item['id'])->where('mailbox_id', $mailbox->id)->first();
            if (!$category) {
                continue;
            }
            $dirty = false;
            if (isset($item['sort_order'])) {
                $category->sort_order = (int)$item['sort_order'];
                $dirty = true;
            }
            if (array_key_exists('parent_id', $item)) {
                $category->kb_category_id = $item['parent_id'] ? (int)$item['parent_id'] : null;
                $dirty = true;
            }
            if ($dirty) {
                $category->save();
                $updated++;
            }
        }
        KbCategory::$categories_cached = null;
        return Response::json(['updated' => $updated]);
    }

    public function articleShow(Request $request, $articleId)
    {
        $article = KbArticle::findOrFail($articleId);
        $mailbox = Mailbox::findOrFail($article->mailbox_id);
        $locales = $this->getLocales($mailbox);
        return Response::json([
            'default_locale' => \Kb::defaultLocale($mailbox),
            'locales' => $locales,
            'article' => $this->articleToArray($article, $locales),
        ]);
    }

    public function articlesList(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $locales = $this->getLocales($mailbox);

        $query = KbArticle::where('mailbox_id', $mailbox->id);

        if ($request->filled('category_id')) {
            $catId = (int)$request->input('category_id');
            $articleIds = KbArticleKbCategory::where('kb_category_id', $catId)->pluck('kb_article_id');
            $query->whereIn('id', $articleIds);
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'draft' || (int)$status === KbArticle::STATUS_DRAFT) {
                $query->where('status', KbArticle::STATUS_DRAFT);
            } elseif ($status === 'published' || (int)$status === KbArticle::STATUS_PUBLISHED) {
                $query->where('status', KbArticle::STATUS_PUBLISHED);
            }
        }

        $articles = $query->orderBy('sort_order')->orderBy('id')->get();
        return Response::json([
            'mailbox_id' => $mailbox->id,
            'default_locale' => \Kb::defaultLocale($mailbox),
            'locales' => $locales,
            'articles' => $articles->map(fn($a) => $this->articleToArray($a, $locales))->values()->all(),
        ]);
    }

    public function articleCreate(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $article = new KbArticle();
        $article->mailbox_id = $mailbox->id;
        return $this->saveArticle($article, $request, $mailbox, 201);
    }

    public function articleUpdate(Request $request, $articleId)
    {
        $article = KbArticle::findOrFail($articleId);
        $mailbox = Mailbox::findOrFail($article->mailbox_id);
        return $this->saveArticle($article, $request, $mailbox, 200);
    }

    private function saveArticle(KbArticle $article, Request $request, Mailbox $mailbox, int $successCode)
    {
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'draft' || (int)$status === KbArticle::STATUS_DRAFT) {
                $article->status = KbArticle::STATUS_DRAFT;
            } elseif ($status === 'published' || (int)$status === KbArticle::STATUS_PUBLISHED) {
                $article->status = KbArticle::STATUS_PUBLISHED;
            }
        }
        if ($request->has('sort_order')) {
            $article->sort_order = (int)$request->input('sort_order');
        }

        $this->applyTranslatables($article, $request, KbArticle::$translatable_fields, $mailbox);

        // Auto-slug from title in the default locale if slug is empty there.
        $defaultLocale = \Kb::defaultLocale($mailbox);
        $existingSlug = $article->getAttributeInLocale('slug', $defaultLocale);
        if (!$existingSlug) {
            $title = $article->getAttributeInLocale('title', $defaultLocale);
            if ($title) {
                $article->setAttributeInLocale('slug', \Kb::slugify($title), $defaultLocale);
            }
        }

        // Sanitize text per-locale (mirrors KnowledgeBaseController@articleSave).
        $allowedTags = \Kb::getAllowedTags();
        foreach ($this->getLocales($mailbox) as $locale) {
            $text = $article->getAttributeInLocale('text', $locale);
            if ($text !== '' && $text !== null) {
                $article->setAttributeInLocale('text', \Helper::stripDangerousTags($text, $allowedTags), $locale);
            }
        }

        try {
            $article->save();
        } catch (\Exception $e) {
            return Response::json(['error' => 'save failed', 'details' => $e->getMessage()], 422);
        }

        if ($request->has('category_ids')) {
            $ids = (array)$request->input('category_ids');
            $valid = KbCategory::whereIn('id', $ids)
                ->where('mailbox_id', $mailbox->id)
                ->pluck('id')
                ->all();
            $article->categories()->sync($valid);
        }
        $article->load('categories');

        return Response::json($this->articleToArray($article, $this->getLocales($mailbox)), $successCode);
    }

    public function articleDelete($articleId)
    {
        $article = KbArticle::findOrFail($articleId);
        $id = $article->id;
        KbArticleKbCategory::where('kb_article_id', $id)->delete();
        $article->delete();
        return Response::json(['deleted' => true, 'id' => $id]);
    }

    public function articleSetCategories(Request $request, $articleId)
    {
        $article = KbArticle::findOrFail($articleId);
        $ids = (array)$request->input('category_ids', []);
        $valid = KbCategory::whereIn('id', $ids)
            ->where('mailbox_id', $article->mailbox_id)
            ->pluck('id')
            ->all();
        $article->categories()->sync($valid);
        return Response::json(['article_id' => $article->id, 'category_ids' => $valid]);
    }

    public function attachmentUpload(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail($mailboxId);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,svg,pdf|max:10240',
        ]);
        if ($validator->fails()) {
            return Response::json(['error' => 'invalid file', 'details' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $stem = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeStem = \Str::slug($stem) ?: 'file';
        $filename = uniqid().'-'.$safeStem.'.'.$ext;
        $dir = 'uploads/knowledgebase/'.$mailbox->id.'/'.date('Y/m');
        $path = $file->storeAs($dir, $filename, 'public');

        return Response::json([
            'url' => asset(\Storage::disk('public')->url($path)),
            'path' => $path,
            'size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
        ], 201);
    }
}
