<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\Help\HelpService;
use App\Services\Platform\Help\HelpUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F1 help (DEC-094, W16b): the pane's article for a screen, help search, the Help centre and one article as a page.
 * Every signed-in user may use help; each article follows its own `permissions`, and `::: can` sections are filtered
 * per user by HelpService.
 */
class HelpController extends Controller
{
    public function __construct(private readonly HelpService $help, private readonly HelpUsageService $usage) {}

    /** Help centre: the articles the user may open, by module; screen coverage for settings managers. */
    public function index(): View
    {
        $user = backpack_user();
        $articles = collect($this->help->articles())->filter(fn (array $a) => $this->help->canOpen($a, $user))
            ->groupBy(fn (array $a) => $a['module'] ?? 'General')->sortKeys();

        return view('admin.utils.platform.help.index', [
            'title' => __('utils.help.centre'),
            'articles' => $articles,
            'coverage' => $user->can('UTL_SETTINGS_MANAGE') ? $this->help->coverage() : null,
            'usage' => $user->can('UTL_SETTINGS_MANAGE') ? $this->usage->report(30) : null,   // W16f
        ]);
    }

    /** The pane content for a route name: `{ok, missing, key, title, html, updated, tour, url}`. */
    public function pane(Request $request): JsonResponse
    {
        $route = (string) $request->validate(['route' => 'required|string|max:190'])['route'];
        $article = $this->help->forRoute($route, backpack_user());
        $this->usage->record($article === null ? 'MISSING' : 'OPEN', $article['key'] ?? $route, backpack_user()->id);   // W16f
        if ($article === null) {
            return response()->json(['ok' => true, 'missing' => true, 'title' => __('utils.help.title'), 'message' => __('utils.help.missing')]);
        }

        return response()->json([
            'ok' => true,
            'missing' => false,
            'key' => $article['key'],
            'title' => $article['title'],
            'html' => $this->help->render($article, backpack_user()),
            'updated' => $article['updated'] ? __('utils.help.updated', ['date' => site_date($article['updated'])]) : null,
            'tour' => $article['tour'],
            'url' => route('utils.help.show', ['key' => $article['key']]),
        ]);
    }

    /** Search the articles the user may open: `{ok, results: [{key, title, snippet, url}]}`. */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->validate(['q' => 'required|string|min:2|max:100'])['q'];
        $results = array_map(fn (array $r) => $r + ['url' => route('utils.help.show', ['key' => $r['key']])], $this->help->search($query, backpack_user()));
        $this->usage->record($results === [] ? 'SEARCH_EMPTY' : 'SEARCH', $query, backpack_user()->id);   // W16f

        return response()->json(['ok' => true, 'results' => $results]);
    }

    /** The browser reports a finished tour (W16f): `{event: TOUR_DONE, key}` — the only client-sent usage event. */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate(['event' => 'required|in:TOUR_DONE', 'key' => 'required|string|max:190']);
        $this->usage->record($data['event'], $data['key'], backpack_user()->id);

        return response()->json(['ok' => true]);
    }

    /** One article as a full page (404 when missing or not allowed). */
    public function show(string $key): View
    {
        $article = $this->help->find($key, backpack_user());
        abort_if($article === null, 404);

        return view('admin.utils.platform.help.show', [
            'title' => $article['title'],
            'article' => $article,
            'html' => $this->help->render($article, backpack_user()),
        ]);
    }
}
