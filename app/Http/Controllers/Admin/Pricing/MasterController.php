<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\ImportPricingMasterJob;
use App\Models\Vehicle\Pricing\MasterImport;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Support\PricingMaster\MasterDefinition;
use App\Support\PricingMaster\MasterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The shared pricing-master screens under Admin → Pricing (DEC-083): AG Grid list (rows by AJAX), create / edit / remove
 * through the definition (entity services, WEF versioning), .xlsx export, queued import with progress. Writes are refused
 * while a Pricing Process is open (it owns the prices until it completes or is discarded).
 */
class MasterController extends Controller
{
    public function __construct(private readonly PricingSessionService $sessions) {}

    public function index(string $master): View
    {
        $definition = $this->authorise($master, 'VIEW');

        return view('admin.pricing.masters.index', [
            'title' => $definition->label(),
            'master' => $definition,
            'canManage' => backpack_user()->can($definition->permission().'_MANAGE'),
            'locked' => $this->sessions->gate() !== null,
            'imports' => MasterImport::query()->where('master', $definition->key())->latest('id')->limit(5)->get(),
        ]);
    }

    public function rows(Request $request, string $master): JsonResponse
    {
        $definition = $this->authorise($master, 'VIEW');
        $rows = [];
        $definition->query($request->boolean('history'))->orderByDesc($definition->query()->getModel()->getKeyName())
            ->chunk(1000, function ($chunk) use (&$rows, $definition) {
                foreach ($chunk as $model) {
                    $rows[] = $definition->row($model);
                }
            });

        return response()->json(['rows' => $rows]);
    }

    public function create(string $master): View|RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');

        return $this->lockedRedirect($definition) ?? view('admin.pricing.masters.form', ['title' => 'New · '.$definition->label(), 'master' => $definition, 'row' => null]);
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');
        if ($locked = $this->lockedRedirect($definition)) {
            return $locked;
        }
        $definition->save($this->input($request, $definition));

        return redirect()->route('pricing.masters.index', $definition->key())->with('success', __('pricing.flash.master_saved', ['label' => $definition->label()]).$this->recalcNote($definition));
    }

    public function edit(string $master, int $id): View|RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');

        return $this->lockedRedirect($definition) ?? view('admin.pricing.masters.form', [
            'title' => 'Edit · '.$definition->label(), 'master' => $definition, 'row' => $definition->query()->findOrFail($id),
        ]);
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');
        if ($locked = $this->lockedRedirect($definition)) {
            return $locked;
        }
        $definition->save($this->input($request, $definition), $definition->query()->findOrFail($id));

        return redirect()->route('pricing.masters.index', $definition->key())->with('success', __('pricing.flash.master_updated', ['label' => $definition->label()]).$this->recalcNote($definition));
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');
        if ($locked = $this->lockedRedirect($definition)) {
            return $locked;
        }
        $definition->remove($definition->query()->findOrFail($id));

        return redirect()->route('pricing.masters.index', $definition->key())->with('success', __('pricing.flash.master_row_removed', ['label' => $definition->label()]).$this->recalcNote($definition));
    }

    public function export(string $master): BinaryFileResponse
    {
        $definition = $this->authorise($master, 'VIEW');
        $path = tempnam(sys_get_temp_dir(), 'prm').'.xlsx';
        $definition->export($path);

        return response()->download($path, str_replace(' ', '-', $definition->label()).'-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, string $master): RedirectResponse
    {
        $definition = $this->authorise($master, 'MANAGE');
        if ($locked = $this->lockedRedirect($definition)) {
            return $locked;
        }
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'wef_date' => [$definition->hasWef() ? 'required' : 'nullable', 'date'],
        ]);
        $file = $data['file'];
        $import = MasterImport::query()->create([
            'master' => $definition->key(), 'status' => 'queued', 'file_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'path' => $file->store('pricing/masters', 'local'), 'wef_date' => $data['wef_date'] ?? null,
            'message' => 'Queued…', 'created_by' => backpack_user()->id,
        ]);
        ImportPricingMasterJob::dispatch($import->id);

        return redirect()->route('pricing.masters.index', $definition->key())->with('success', __('pricing.flash.import_queued_progress_shows_below'));
    }

    public function importStatus(string $master, int $id): JsonResponse
    {
        $definition = $this->authorise($master, 'VIEW');
        $import = MasterImport::query()->where('master', $definition->key())->findOrFail($id);

        return response()->json(['id' => $import->id, 'status' => $import->status, 'message' => $import->message,
            'result' => array_diff_key((array) $import->result, ['issues' => 1]), 'issues' => array_slice((array) ($import->result['issues'] ?? []), 0, 50)]);
    }

    private function authorise(string $master, string $activity): MasterDefinition
    {
        try {
            $definition = MasterRegistry::get($master);
        } catch (\InvalidArgumentException) {
            abort(404);
        }
        if (! backpack_user()->can($definition->permission().'_'.$activity)) {
            abort(403, 'You do not have permission for '.$definition->label().'.');
        }

        return $definition;
    }

    private function lockedRedirect(MasterDefinition $definition): ?RedirectResponse
    {
        if ($definition->recalculates() && $this->sessions->gate()) {
            return redirect()->route('pricing.masters.index', $definition->key())
                ->with('warning', __('pricing.flash.pricing_process_open_pricing_masters_are'));
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function input(Request $request, MasterDefinition $definition): array
    {
        $input = [];
        foreach ($definition->formFields() as $field) {
            $spec = $definition->input($field);
            if ($spec['type'] === 'bool') {
                $input[$field] = $request->boolean($field);
            } elseif ($request->has($field)) {
                $value = $request->input($field);
                $input[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        if (array_filter($input, fn ($v) => $v !== null && $v !== '') === []) {
            throw ValidationException::withMessages(['form' => 'Fill in at least one field.']);
        }

        return array_map(fn ($v) => $v === '' ? null : $v, $input);
    }

    private function recalcNote(MasterDefinition $definition): string
    {
        return $definition->recalculates() ? ' '.__('pricing.flash.master_recalc_note') : '';
    }
}
