<?php

namespace App\Services\Platform\Help;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * F1 help engine (DEC-094, W16b; FRS help-and-support §3). Articles are Markdown files in `config('platform.help.path')`
 * (default `resources/help/`), versioned with the code: `{module}/{process}/{screen}.md` plus `_module.md` /
 * `_process.md` overviews. Front matter (YAML between `---` lines):
 *
 *   title: Booking — edit
 *   routes: [sales.booking.edit, sales.booking.update]   # the screens it serves
 *   route_prefix: sales.booking                          # overviews: fallback for every route under it
 *   module: SLS
 *   process: BKNG
 *   permissions: [SLS_BKNG_VIEW]                         # who may open it (any of); empty = everyone
 *   updated: 2026-10-01
 *   tour: [{ element: '[data-xl-tour=customer]', title: Customer, text: … }]   # W16c
 *
 * Body sections between `::: can CODE[, CODE2]` and `:::` show only to holders of one of those permissions. Rendered
 * with CommonMark (raw HTML escaped) and cached per article + the permissions it checks.
 *
 * Example:
 *
 *   $article = $help->forRoute('sales.booking.edit', $user);   // null → no help yet
 *   $html = $help->render($article, $user);
 */
class HelpService
{
    private const CAN_BLOCK = '/^:::\s*can\s+([A-Za-z0-9_,\s]+?)\s*\R(.*?)\R:::\s*$/ms';

    /**
     * Every article, keyed by its path without `.md` (`sales/booking/edit`).
     *
     * @return array<string, array{key: string, title: string, routes: list<string>, route_prefix: ?string, module: ?string, process: ?string, permissions: list<string>, updated: ?string, tour: list<array<string, mixed>>, body: string, mtime: int}>
     */
    public function articles(): array
    {
        $path = $this->path();
        if (! is_dir($path)) {
            return [];
        }
        $files = collect(File::allFiles($path))->filter(fn ($f) => $f->getExtension() === 'md' && strtolower($f->getFilename()) !== 'readme.md');
        $signature = md5($files->map(fn ($f) => $f->getRelativePathname().':'.$f->getMTime())->implode('|'));

        return Cache::remember("help.index.{$signature}", now()->addDay(), function () use ($files) {
            $articles = [];
            foreach ($files as $file) {
                $key = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -3));
                $article = $this->parse($key, (string) file_get_contents($file->getPathname()), (int) $file->getMTime());
                if ($article !== null) {
                    $articles[$key] = $article;
                }
            }
            ksort($articles);

            return $articles;
        });
    }

    /**
     * The article for a route the user may open: one that lists the route, else the overview with the longest matching
     * `route_prefix`, else null (the miss is logged so writers see the gap).
     *
     * @return array<string, mixed>|null
     */
    public function forRoute(string $route, User $user): ?array
    {
        $articles = array_filter($this->articles(), fn (array $a) => $this->canOpen($a, $user));
        foreach ($articles as $article) {
            if (in_array($route, $article['routes'], true)) {
                return $article;
            }
        }
        $best = null;
        foreach ($articles as $article) {
            $prefix = $article['route_prefix'];
            if ($prefix !== null && ($route === $prefix || str_starts_with($route, $prefix.'.'))
                && ($best === null || strlen($prefix) > strlen((string) $best['route_prefix']))) {
                $best = $article;
            }
        }
        if ($best === null) {
            Log::info('Help article missing', ['route' => $route]);
        }

        return $best;
    }

    /** @return array<string, mixed>|null the article by key when the user may open it */
    public function find(string $key, User $user): ?array
    {
        $article = $this->articles()[$key] ?? null;

        return $article !== null && $this->canOpen($article, $user) ? $article : null;
    }

    /** @param  array<string, mixed>  $article */
    public function canOpen(array $article, User $user): bool
    {
        return $article['permissions'] === [] || collect($article['permissions'])->contains(fn (string $code) => $user->can($code));
    }

    /**
     * The article's HTML for this user: `::: can` sections the user lacks are removed. Cached per article version and
     * the user's answers for the permissions the article checks.
     *
     * @param  array<string, mixed>  $article
     */
    public function render(array $article, User $user): string
    {
        preg_match_all(self::CAN_BLOCK, $article['body'], $m);
        $codes = collect($m[1])->flatMap(fn (string $list) => $this->codes($list))->unique()->sort()->values();
        $allowed = $codes->filter(fn (string $code) => $user->can($code))->values()->all();
        $cacheKey = 'help.html.'.md5($article['key'].'|'.$article['mtime'].'|'.implode(',', $allowed));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($article, $allowed) {
            $markdown = (string) preg_replace_callback(self::CAN_BLOCK, fn (array $block) => array_intersect($this->codes($block[1]), $allowed) !== [] ? $block[2] : '', $article['body']);

            return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        });
    }

    /**
     * Articles the user may open whose title, headings or text contain the query (title hits first).
     *
     * @return list<array{key: string, title: string, snippet: string}>
     */
    public function search(string $query, User $user, int $limit = 20): array
    {
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 2) {
            return [];
        }
        $hits = [];
        foreach ($this->articles() as $article) {
            if (! $this->canOpen($article, $user)) {
                continue;
            }
            $text = (string) preg_replace(self::CAN_BLOCK, '', $article['body']);   // never search hidden sections
            $title = mb_strtolower($article['title']);
            $score = (str_contains($title, $query) ? 3 : 0)
                + (preg_match('/^#+ .*'.preg_quote($query, '/').'/mi', $text) ? 2 : 0)
                + (str_contains(mb_strtolower($text), $query) ? 1 : 0);
            if ($score > 0) {
                $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags(Str::markdown($text))));
                $at = mb_stripos($plain, $query);
                $hits[] = ['score' => $score, 'key' => $article['key'], 'title' => $article['title'],
                    'snippet' => Str::limit(mb_substr($plain, max(0, ($at === false ? 0 : $at) - 60)), 160)];
            }
        }
        usort($hits, fn ($a, $b) => [$b['score'], $a['title']] <=> [$a['score'], $b['title']]);

        return array_map(fn (array $h) => ['key' => $h['key'], 'title' => $h['title'], 'snippet' => $h['snippet']], array_slice($hits, 0, $limit));
    }

    /**
     * Admin screens (named GET routes under the admin prefix, JSON / AJAX endpoints excluded by name) with and without
     * an article of their own (overview fallbacks do not count).
     *
     * @return array{total: int, covered: int, missing: list<string>}
     */
    public function coverage(): array
    {
        $prefix = trim((string) config('backpack.base.route_prefix', 'admin'), '/').'/';
        $documented = collect($this->articles())->flatMap(fn (array $a) => $a['routes'])->unique()->flip();
        $screens = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->getName() !== null && str_starts_with($r->uri(), $prefix)
                && ! preg_match('/\.(data|search|list|ajax|export|download|widget|fetch|json|options|pane)$|\.(get|check|fetch)[-a-z]*$/', (string) $r->getName()))
            ->map(fn ($r) => (string) $r->getName())->unique()->sort()->values();
        $missing = $screens->reject(fn (string $name) => $documented->has($name))->values()->all();

        return ['total' => $screens->count(), 'covered' => $screens->count() - count($missing), 'missing' => $missing];
    }

    /** Directory holding the articles. */
    public function path(): string
    {
        return (string) config('platform.help.path', resource_path('help'));
    }

    /** @return array<string, mixed>|null */
    private function parse(string $key, string $raw, int $mtime): ?array
    {
        $meta = [];
        $body = $raw;
        if (preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $raw, $m)) {
            try {
                $meta = (array) (Yaml::parse($m[1]) ?? []);
            } catch (ParseException $e) {
                Log::warning('Help article front matter is invalid', ['article' => $key, 'error' => $e->getMessage()]);

                return null;
            }
            $body = $m[2];
        }
        $list = fn (mixed $v) => array_values(array_filter(array_map('strval', (array) ($v ?? [])), fn ($s) => $s !== ''));

        return [
            'key' => $key,
            'title' => (string) ($meta['title'] ?? Str::headline(basename($key))),
            'routes' => $list($meta['routes'] ?? []),
            'route_prefix' => isset($meta['route_prefix']) ? (string) $meta['route_prefix'] : null,
            'module' => isset($meta['module']) ? (string) $meta['module'] : null,
            'process' => isset($meta['process']) ? (string) $meta['process'] : null,
            'permissions' => $list($meta['permissions'] ?? []),
            'updated' => isset($meta['updated']) ? (is_int($meta['updated']) ? date('Y-m-d', $meta['updated']) : (string) $meta['updated']) : null,
            'tour' => array_values(array_filter((array) ($meta['tour'] ?? []), 'is_array')),
            'body' => $body,
            'mtime' => $mtime,
        ];
    }

    /** @return list<string> */
    private function codes(string $list): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $list) ?: [])));
    }
}
