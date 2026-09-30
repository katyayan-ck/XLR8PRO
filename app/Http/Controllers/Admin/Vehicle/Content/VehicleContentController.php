<?php

namespace App\Http\Controllers\Admin\Vehicle\Content;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleTrim;
use App\Services\Vehicle\Content\VehicleContentService;
use App\Services\Vehicle\Content\VehicleContentWorkbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vehicles → Vehicle content (DEC-092 Phase 2): per model — images, PDF brochure, category-wise specifications, its trims;
 * per trim (variant code) — features and the gallery (images bound to the trim or to one colour). VEH_CONT_VIEW to see,
 * VEH_CONT_EDIT to change. All work is in VehicleContentService.
 */
class VehicleContentController extends Controller
{
    public function __construct(private readonly VehicleContentService $content) {}

    public function index(): View
    {
        $this->allow('VEH_CONT_VIEW');

        return view('admin.vehicle.content.index', ['title' => 'Vehicle content', 'segments' => $this->content->overview()]);
    }

    public function model(string $code): View
    {
        $this->allow('VEH_CONT_VIEW');
        $model = $this->findModel($code);

        return view('admin.vehicle.content.model', ['title' => $model->name, 'model' => $model, 'sheet' => $this->content->modelSheet($model),
            'canEdit' => backpack_user()->can('VEH_CONT_EDIT'), 'maxKb' => $this->maxKb()]);
    }

    public function saveSpecs(Request $request, string $code): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['specs' => 'array', 'specs.*' => 'nullable|string|max:500']);
        $changed = $this->content->saveModelSpecs($this->findModel($code), (array) ($data['specs'] ?? []));
        Alert::success(__('vehicle.flash.content_saved', ['count' => $changed]))->flash();

        return redirect()->route('vehicle.content.model', [$code, 'tab' => 'specs']);
    }

    public function addSpecItem(Request $request, string $code): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['category' => 'required|string|max:100', 'name' => 'required|string|max:150', 'unit' => 'nullable|string|max:30']);
        $this->content->addSpecItem($data['category'], $data['name'], $data['unit'] ?? null);
        Alert::success(__('vehicle.flash.item_added', ['name' => $data['name']]))->flash();

        return redirect()->route('vehicle.content.model', [$code, 'tab' => 'specs']);
    }

    public function uploadImages(Request $request, string $code): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['images' => 'required|array|max:20', 'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:'.$this->maxKb()]);
        $count = $this->content->addModelImages($this->findModel($code), $data['images']);
        Alert::success(__('vehicle.flash.images_added', ['count' => $count]))->flash();

        return redirect()->route('vehicle.content.model', [$code, 'tab' => 'media']);
    }

    public function uploadBrochure(Request $request, string $code): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['brochure' => 'required|file|mimes:pdf|max:'.$this->maxKb()]);
        $this->content->setBrochure($this->findModel($code), $data['brochure']);
        Alert::success(__('vehicle.flash.brochure_saved'))->flash();

        return redirect()->route('vehicle.content.model', [$code, 'tab' => 'media']);
    }

    public function removeModelMedia(string $code, int $media): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $result = $this->content->removeModelMedia($this->findModel($code), $media);
        $result->ok ? Alert::success(__('vehicle.flash.file_removed'))->flash() : Alert::error($result->message)->flash();

        return redirect()->route('vehicle.content.model', [$code, 'tab' => 'media']);
    }

    public function trim(string $variantCode): View
    {
        $this->allow('VEH_CONT_VIEW');
        $trim = $this->findTrim($variantCode);

        return view('admin.vehicle.content.trim', ['trim' => $trim, 'sheet' => $sheet = $this->content->trimSheet($trim), 'title' => $sheet['name'],
            'canEdit' => backpack_user()->can('VEH_CONT_EDIT'), 'maxKb' => $this->maxKb()]);
    }

    public function saveFeatures(Request $request, string $variantCode): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['features' => 'array', 'features.*' => 'nullable|string|max:255']);
        $changed = $this->content->saveTrimFeatures($this->findTrim($variantCode), (array) ($data['features'] ?? []));
        Alert::success(__('vehicle.flash.content_saved', ['count' => $changed]))->flash();

        return redirect()->route('vehicle.content.trim', [$variantCode, 'tab' => 'features']);
    }

    public function addFeatureItem(Request $request, string $variantCode): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['feature_group' => 'required|string|max:100', 'name' => 'required|string|max:255']);
        $this->content->addFeatureItem($data['feature_group'], $data['name']);
        Alert::success(__('vehicle.flash.item_added', ['name' => $data['name']]))->flash();

        return redirect()->route('vehicle.content.trim', [$variantCode, 'tab' => 'features']);
    }

    public function uploadGallery(Request $request, string $variantCode): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['level' => 'required|string|max:50', 'images' => 'required|array|max:20',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:'.$this->maxKb()]);
        $result = $this->content->addGalleryImages($this->findTrim($variantCode), $data['level'], $data['images']);
        $result->ok ? Alert::success(__('vehicle.flash.images_added', ['count' => $result->get('added')]))->flash() : Alert::error($result->message)->flash();

        return redirect()->route('vehicle.content.trim', [$variantCode, 'tab' => 'gallery']);
    }

    public function removeGallery(string $variantCode, int $media): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $result = $this->content->removeGalleryImage($this->findTrim($variantCode), $media);
        $result->ok ? Alert::success(__('vehicle.flash.file_removed'))->flash() : Alert::error($result->message)->flash();

        return redirect()->route('vehicle.content.trim', [$variantCode, 'tab' => 'gallery']);
    }

    /** Specifications (`specs`) or features (`features`) workbook of every active model (DEC-092 Phase 3). */
    public function export(string $kind, VehicleContentWorkbookService $workbooks): BinaryFileResponse
    {
        $this->allow('VEH_CONT_VIEW');
        abort_unless(in_array($kind, ['specs', 'features'], true), 404);
        $path = storage_path('app/vehicle-'.$kind.'-'.Str::random(8).'.xlsx');
        $kind === 'specs' ? $workbooks->exportSpecs($path) : $workbooks->exportFeatures($path);

        return response()->download($path, 'vehicle-'.($kind === 'specs' ? 'specifications' : 'features').'-'.now()->format('Ymd-Hi').'.xlsx')->deleteFileAfterSend();
    }

    /** Import our workbook or the OEM sample format; the match report is shown on the page. */
    public function import(Request $request, VehicleContentWorkbookService $workbooks): RedirectResponse
    {
        $this->allow('VEH_CONT_EDIT');
        $data = $request->validate(['kind' => 'required|in:specs,features', 'file' => 'required|file|mimes:xlsx,xls|max:'.$this->maxKb()]);
        $path = $data['file']->getRealPath();
        $report = DB::transaction(fn () => $data['kind'] === 'specs' ? $workbooks->importSpecs($path) : $workbooks->importFeatures($path));
        Alert::success(__('vehicle.flash.content_imported', ['count' => $report['values']]))->flash();

        return redirect()->route('vehicle.content.index')->with('content_import', ['kind' => $data['kind']] + $report);
    }

    private function allow(string $permission): void
    {
        if (! backpack_user()->can($permission)) {
            abort(403);
        }
    }

    private function findModel(string $code): VehicleModel
    {
        return VehicleModel::query()->where('code', strtoupper($code))->firstOrFail();
    }

    private function findTrim(string $variantCode): VehicleTrim
    {
        return $this->content->trim($variantCode) ?? abort(404);
    }

    private function maxKb(): int
    {
        return (int) setting('docs.max_upload_kb', 10240);
    }
}
