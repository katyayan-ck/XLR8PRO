<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Comms\CommTemplate;
use App\Models\Comms\CommTemplateVersion;
use App\Services\Platform\Templates\TemplateService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Template admin (FRS TPL-09): list / filter, draft editor, preview, version diff, submit /
 * approve / activate, JSON export / import. View = UTL_TPL_VIEW, edit = UTL_TPL_EDIT,
 * approve directly / activate = UTL_TPL_ACTIVATE.
 */
class TemplateAdminController extends Controller
{
    public function __construct(private readonly TemplateService $templates) {}

    public function index(Request $request): View
    {
        $this->gate('UTL_TPL_VIEW');
        $filters = $request->only(['channel', 'category', 'status', 'q']);
        $rows = CommTemplate::query()->with(['versions' => fn ($q) => $q->select('id', 'template_id', 'version', 'status', 'usage_count', 'last_used_at')])
            ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->whereHas('versions', fn ($v) => $v->where('status', $s)))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")))
            ->orderBy('code')->orderBy('channel')->paginate(30)->withQueryString();

        return view('admin.utils.platform.templates.index', ['title' => 'Message templates', 'rows' => $rows, 'filters' => $filters]);
    }

    public function edit(Request $request, ?int $id = null): View
    {
        $this->gate('UTL_TPL_VIEW');
        $template = $id ? CommTemplate::query()->with('versions')->findOrFail($id) : null;
        $versionId = $request->integer('version') ?: $template?->versions->firstWhere('status', 'DRAFT')?->id ?? $template?->versions->first()?->id;
        $version = $versionId ? CommTemplateVersion::query()->with('template')->find($versionId) : null;
        $preview = $version ? $this->templates->renderVersion($version, [], true) : null;
        $diff = null;
        if ($template && $request->integer('compare') && $version) {
            $diff = $this->templates->diff($request->integer('compare'), $version->id);
        }

        return view('admin.utils.platform.templates.edit', [
            'title' => $template ? "{$template->code} ({$template->channel})" : 'New template',
            'template' => $template, 'version' => $version, 'preview' => $preview, 'diff' => $diff,
            'canEdit' => backpack_user()->can('UTL_TPL_EDIT'), 'canActivate' => backpack_user()->can('UTL_TPL_ACTIVATE'),
        ]);
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $this->gate('UTL_TPL_EDIT');
        $data = $request->validate([
            'code' => 'required|string|max:120', 'channel' => 'required|string', 'locale' => 'nullable|string|max:10',
            'category' => 'required|string', 'name' => 'required|string|max:150', 'description' => 'nullable|string|max:2000',
            'subject' => 'nullable|string|max:250', 'body_html' => 'nullable|string|max:100000', 'body_text' => 'nullable|string|max:5000',
            'variables_json' => 'nullable|string|max:10000', 'sample_json' => 'nullable|string|max:10000', 'wa_components_json' => 'nullable|string|max:10000',
            'provider_template_id' => 'nullable|string|max:100', 'dlt_entity_id' => 'nullable|string|max:50', 'dlt_header' => 'nullable|string|max:20',
        ]);
        foreach (['variables_json' => 'variables', 'sample_json' => 'sample_vars', 'wa_components_json' => 'wa_components'] as $in => $out) {
            if (($data[$in] ?? '') !== '') {
                $decoded = json_decode((string) $data[$in], true);
                if (! is_array($decoded)) {
                    Alert::error(ucfirst(str_replace('_', ' ', $out)).' must be valid JSON.')->flash();

                    return back()->withInput();
                }
                $data[$out] = $decoded;
            }
            unset($data[$in]);
        }
        $result = $this->templates->saveDraft($data['code'], $data, backpack_user()->id);
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back()->withInput();
        }
        Alert::success(__('utils.flash.draft_v_saved', ['version' => $result->get('version')]))->flash();

        return redirect()->route('utils.templates.edit', ['id' => $result->get('template_id'), 'version' => $result->get('version_id')]);
    }

    public function submit(int $versionId): RedirectResponse
    {
        $this->gate('UTL_TPL_EDIT');

        return $this->respond($this->templates->submit($versionId, backpack_user()->id), 'Submitted for approval.');
    }

    public function approve(int $versionId): RedirectResponse
    {
        $this->gate('UTL_TPL_ACTIVATE');

        return $this->respond($this->templates->approveDirect($versionId, backpack_user()->id), 'Version approved.');
    }

    public function activate(int $versionId): RedirectResponse
    {
        $this->gate('UTL_TPL_ACTIVATE');

        return $this->respond($this->templates->activate($versionId, backpack_user()->id), 'Version activated.');
    }

    /** Live preview with a variable bag (TPL-10). */
    public function preview(Request $request, int $versionId): JsonResponse
    {
        $this->gate('UTL_TPL_VIEW');
        $vars = json_decode((string) $request->input('vars', '{}'), true);
        $result = $this->templates->renderVersion(CommTemplateVersion::query()->with('template')->findOrFail($versionId), is_array($vars) ? $vars : [], true);

        return response()->json($result->toArray());
    }

    public function export(): Response
    {
        $this->gate('UTL_TPL_VIEW');
        $json = json_encode($this->templates->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="templates-'.now()->format('Ymd-Hi').'.json"',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->gate('UTL_TPL_EDIT');
        $request->validate(['file' => 'required|file|max:2048']);
        $items = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($items)) {
            Alert::error(__('utils.flash.file_not_template_export'))->flash();

            return back();
        }
        $result = $this->templates->import($items, backpack_user()->id);
        Alert::success(__('utils.flash.drafts_imported', ['count' => $result->get('drafts')]).($result->get('errors') ? ' '.__('utils.flash.import_errors', ['errors' => implode('; ', $result->get('errors'))]) : ''))->flash();

        return back();
    }

    private function respond(Result $result, string $success): RedirectResponse
    {
        $result->ok ? Alert::success($success)->flash() : Alert::error($result->message)->flash();

        return back();
    }

    private function gate(string $permission): void
    {
        if (! backpack_user()->can($permission)) {
            abort(403);
        }
    }
}
