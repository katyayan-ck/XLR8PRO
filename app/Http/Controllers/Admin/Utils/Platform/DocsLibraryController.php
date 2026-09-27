<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Docs\DocsService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Document library, cart and packs (FRS §4, DOC-05..09), plus uploads from `<x-docs.uploader>`
 * on any record screen. Every read goes through `DocsService::canView()`.
 */
class DocsLibraryController extends Controller
{
    public function __construct(private readonly DocsService $docs, private readonly ChatService $chat) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_DOCS_VIEW')) {
            abort(403);
        }
        $filters = $request->only(['path_entity', 'path_location', 'path_category', 'path_sub', 'path_item', 'fy', 'kind', 'q']);

        return view('admin.utils.platform.docs.index', [
            'title' => 'Document library',
            'filters' => $filters,
            'library' => $this->docs->library($filters, backpack_user()->id),
            'mine' => $this->docs->mine(backpack_user()->id),
            'cart' => $this->docs->cart(backpack_user()->id),
            'groups' => $this->docs->groups(backpack_user()->id),
        ]);
    }

    /** Upload a file or an information card, into the library (path fields) or onto a record (ref_type/ref_id). */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! backpack_user()->can('UTL_DOCS_UPLOAD')) {
            abort(403);
        }
        $data = $request->validate([
            'title' => 'nullable|string|max:250',
            'description' => 'nullable|string|max:2000',
            'info_body' => 'nullable|string|max:20000',
            'file' => 'nullable|file|required_without:info_body',
            'collection' => 'nullable|string|in:'.implode(',', config('platform.docs.collections')),
            'ref_type' => 'nullable|string|max:30',
            'ref_id' => 'nullable|integer|required_with:ref_type',
            'path_entity' => 'nullable|string|max:100',
            'path_location' => 'nullable|string|max:100',
            'path_category' => 'nullable|string|max:100',
            'path_sub' => 'nullable|string|max:100',
            'path_item' => 'nullable|string|max:100',
            'fy' => 'nullable|string|max:9',
            'expiry_date' => 'nullable|date',
        ]);

        $model = null;
        if (! empty($data['ref_type'])) {
            $model = $this->chat->resolve($data['ref_type'], (int) $data['ref_id']);
            abort_if($model === null, 404);
            abort_unless($this->chat->canView($model, backpack_user()->id), 403);
        }
        $meta = array_filter(array_intersect_key($data, array_flip(['title', 'description', 'info_body', 'path_entity', 'path_location', 'path_category', 'path_sub', 'path_item', 'fy', 'expiry_date'])), fn ($v) => $v !== null && $v !== '');

        $result = $request->hasFile('file')
            ? $this->docs->attach($model, $request->file('file'), $data['collection'] ?? 'docs', $meta)
            : $this->docs->card($meta, $model);

        return $this->respond($request, $result, 'Document saved.');
    }

    /** `?inline=1` shows the file in the browser (image / PDF previews, DEC-069); otherwise it downloads. */
    public function download(Request $request, int $id): BinaryFileResponse
    {
        abort_unless($this->docs->canView($id, backpack_user()->id), 403);
        $media = Document::query()->findOrFail($id)->media()->firstOrFail();

        if ($request->boolean('inline')) {
            return response()->file($media->getPath(), [
                'Content-Type' => $media->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            ]);
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $this->docs->delete($id), 'Document deleted.');
    }

    public function cartAdd(Request $request, int $id): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $this->docs->cartAdd(backpack_user()->id, $id), 'Added to cart.');
    }

    public function cartRemove(Request $request, int $id): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $this->docs->cartRemove(backpack_user()->id, $id), 'Removed from cart.');
    }

    public function cartSave(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'purpose' => 'nullable|string|max:250']);

        return $this->respond($request, $this->docs->cartSaveAs(backpack_user()->id, $data['name'], $data['purpose'] ?? null), 'Pack saved.');
    }

    public function groupRename(Request $request, int $groupId): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        return $this->respond($request, $this->docs->renameGroup(backpack_user()->id, $groupId, $data['name']), 'Pack renamed.');
    }

    public function groupDestroy(Request $request, int $groupId): JsonResponse|RedirectResponse
    {
        return $this->respond($request, $this->docs->deleteGroup(backpack_user()->id, $groupId), 'Pack deleted.');
    }

    public function groupZip(int $groupId): BinaryFileResponse|RedirectResponse
    {
        $result = $this->docs->zip(backpack_user()->id, $groupId);
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back();
        }

        return response()->download((string) $result->get('path'))->deleteFileAfterSend(true);
    }

    private function respond(Request $request, Result $result, string $success): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($result->toArray(), $result->ok ? 200 : 422);
        }
        $result->ok ? Alert::success($success)->flash() : Alert::error($result->message)->flash();

        return back();
    }
}
