<?php

namespace App\Http\Controllers\Admin\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Dev-only UI kit (DEC-067): static reference screens showing how forms, lists, elements, a CRM dashboard and a chat
 * look with Tabler + the shared UI layer. Nothing here reads or writes data. Hidden (404) unless
 * config('platform.dev_ui_kit') is on.
 */
class UiKitController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> page key => [title, Line Awesome icon] */
    public const PAGES = [
        'index' => ['Overview', 'la-swatchbook'],
        'forms' => ['Forms', 'la-edit'],
        'lists' => ['Lists & tables', 'la-table'],
        'elements' => ['Elements', 'la-shapes'],
        'dashboard' => ['CRM dashboard', 'la-chart-area'],
        'chat' => ['Chat', 'la-comments'],
        'pages' => ['Pages', 'la-copy'],
    ];

    public function show(string $page = 'index'): View
    {
        abort_unless(config('platform.dev_ui_kit'), 404);

        return view('admin.dev.ui.'.$page, [
            'title' => 'UI kit · '.self::PAGES[$page][0],
            'pages' => self::PAGES,
            'page' => $page,
        ]);
    }
}
