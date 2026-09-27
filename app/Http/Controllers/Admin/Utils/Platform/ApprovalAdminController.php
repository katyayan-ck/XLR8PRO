<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Exports\Platform\RowsExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Designation;
use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalTopic;
use App\Services\OrgService;
use App\Services\Platform\Approval\ApprovalService;
use App\Services\Platform\Approval\Entities\ApprovalRuleService;
use App\Services\Platform\Approval\Entities\ApprovalTopicService;
use App\Services\Platform\Approval\PowerSheetImportService;
use App\Services\Platform\Approval\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Approval master data (FRS §8.4): topic tree, rules with levels, power-sheet import and
 * simulation. Everything here needs UTL_APPR_ADMIN; writes go through the entity services.
 */
class ApprovalAdminController extends Controller
{
    public function __construct(
        private readonly TopicService $topics,
        private readonly ApprovalTopicService $topicWriter,
        private readonly ApprovalRuleService $ruleWriter,
    ) {}

    public function topics(Request $request): View
    {
        $this->gate();
        $editing = $request->query('edit') ? ApprovalTopic::query()->find($request->query('edit')) : null;

        return view('admin.utils.platform.approvals.admin.topics', [
            'title' => 'Approval topics',
            'tree' => $this->topics->tree(),
            'options' => $this->topics->options(),
            'editing' => $editing,
        ]);
    }

    public function saveTopic(Request $request): RedirectResponse
    {
        $this->gate();
        $input = $request->only(['code', 'parent_id', 'title', 'item_key', 'mode', 'value_type', 'is_mandatory', 'is_active', 'description']);
        $input['is_mandatory'] = $request->boolean('is_mandatory');
        $input['is_active'] = $request->boolean('is_active');
        $id = $request->integer('id');
        $id ? $this->topicWriter->update(ApprovalTopic::query()->findOrFail($id), $input) : $this->topicWriter->create($input);
        Alert::success('Topic saved.')->flash();

        return redirect()->route('utils.approvals.admin.topics');
    }

    public function rules(Request $request): View
    {
        $this->gate();
        $topicId = $request->integer('topic') ?: null;

        return view('admin.utils.platform.approvals.admin.rules', [
            'title' => 'Approval rules',
            'topicId' => $topicId,
            'options' => $this->topics->options(),
            'rules' => ApprovalRule::query()->with(['topic', 'levels'])->when($topicId, fn ($q) => $q->where('topic_id', $topicId))
                ->orderBy('topic_id')->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function editRule(Request $request, ?int $id = null): View
    {
        $this->gate();

        return view('admin.utils.platform.approvals.admin.rule-form', [
            'title' => $id ? "Edit rule #{$id}" : 'New rule',
            'rule' => $id ? ApprovalRule::query()->with('levels')->findOrFail($id) : null,
            'topicId' => $request->integer('topic') ?: null,
            'options' => $this->topics->options(),
            'designations' => Designation::query()->orderBy('name')->pluck('name', 'code')->all(),
        ]);
    }

    public function saveRule(Request $request, ?int $id = null): RedirectResponse
    {
        $this->gate();
        $input = $request->only(array_merge(['topic_id', 'valid_from', 'valid_to', 'note'], array_map(fn ($d) => $d.'_code', ApprovalRule::SCOPES)));
        $input['is_active'] = $request->boolean('is_active');
        $input['levels'] = array_values(array_filter((array) $request->input('levels', []), fn ($l) => is_array($l) && trim((string) ($l['level_no'] ?? '')) !== ''));
        $rule = $id ? $this->ruleWriter->update(ApprovalRule::query()->findOrFail($id), $input) : $this->ruleWriter->create($input);
        Alert::success("Rule #{$rule->id} saved.")->flash();

        return redirect()->route('utils.approvals.admin.rules', ['topic' => $rule->topic_id]);
    }

    public function deleteRule(int $id): RedirectResponse
    {
        $this->gate();
        $rule = ApprovalRule::query()->findOrFail($id);
        $this->ruleWriter->update($rule, ['is_active' => false]);
        $rule->delete();
        Alert::success("Rule #{$id} removed (open requests keep their snapshot).")->flash();

        return back();
    }

    public function importForm(): View
    {
        $this->gate();

        return view('admin.utils.platform.approvals.admin.import', ['title' => 'Import power sheet', 'result' => session('powersheet_result')]);
    }

    public function import(Request $request, PowerSheetImportService $importer): RedirectResponse
    {
        $this->gate();
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $apply = $request->input('mode') === 'apply';
        $result = $importer->import($request->file('file')->getRealPath(), $apply);
        $data = $result->data + ['ok' => $result->ok, 'message' => $result->message, 'apply' => $apply];
        session(['powersheet_errors' => $data['errors'] ?? []]);
        $result->ok ? Alert::success($apply ? ($data['applied'] ? 'Power sheet applied.' : 'Nothing applied — fix the errors.') : 'Dry run complete — nothing written.')->flash() : Alert::error($result->message)->flash();

        return redirect()->route('utils.approvals.admin.import')->with('powersheet_result', $data);
    }

    public function importErrors(): BinaryFileResponse
    {
        $this->gate();
        $rows = array_map(fn ($e) => [$e['row'], $e['topic'], $e['message']], (array) session('powersheet_errors', []));

        return Excel::download(new RowsExport(['Row', 'Topic', 'Problem'], $rows, 'Errors'), 'power-sheet-errors.xlsx');
    }

    public function template(): BinaryFileResponse
    {
        $this->gate();
        $example = [['DISCOUNT.EXTRA', 1, 'SC', 'AMOUNT', 2000, 0, 3000, '', '', '', '', '', '', '', '', 'NEXON', '', '', 'RETAIL']];

        return Excel::download(new RowsExport(PowerSheetImportService::TEMPLATE_HEADINGS, $example, 'Power sheet'), 'power-sheet-template.xlsx');
    }

    public function simulate(Request $request, ApprovalService $approvals): View
    {
        $this->gate();
        $input = $request->validate([
            'user_id' => 'nullable|integer',
            'item' => 'nullable|string|max:100',
            'ask' => 'nullable|numeric|min:0',
            'scope' => 'nullable|array',
            'scope.*' => 'nullable|string|max:50',
        ]);
        $preview = ! empty($input['item']) ? $approvals->preview((int) ($input['user_id'] ?? backpack_user()->id), $input['item'], array_filter($input['scope'] ?? []), $input['ask'] ?? 0) : null;

        return view('admin.utils.platform.approvals.admin.simulate', [
            'title' => 'Approval simulation',
            'input' => $input,
            'preview' => $preview,
            'items' => ApprovalTopic::query()->whereNotNull('item_key')->orderBy('code')->pluck('code', 'item_key')->all(),
            'team' => OrgService::teamOptions(),
        ]);
    }

    private function gate(): void
    {
        if (! backpack_user()->can('UTL_APPR_ADMIN')) {
            abort(403);
        }
    }
}
