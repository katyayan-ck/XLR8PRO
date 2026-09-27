@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
@include('admin.utils.platform.approvals.admin._nav')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-5">
            <form method="POST" action="{{ route('utils.approvals.admin.import.run') }}" enctype="multipart/form-data" class="card">
                @csrf
                <div class="card-header"><h3 class="card-title mb-0">Power sheet</h3></div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        One row per level. Rows with the same topic, scope and validity form one rule. Applying replaces
                        <strong>all</strong> rules of each topic in the sheet; a topic with any bad row is skipped whole.
                        Open requests keep their snapshot.
                    </p>
                    <a href="{{ route('utils.approvals.admin.import.template') }}" class="small"><i class="la la-download"></i> Download the template</a>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="form-control mt-3">
                    <div class="mt-3">
                        <label class="form-check"><input type="radio" name="mode" value="dry" class="form-check-input" checked> <span class="form-check-label">Dry run (nothing is written)</span></label>
                        <label class="form-check"><input type="radio" name="mode" value="apply" class="form-check-input"> <span class="form-check-label">Apply (purge and replace per topic)</span></label>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Run</button></div>
            </form>
        </div>
        <div class="col-lg-7">
            @if ($result)
                <div class="card">
                    <div class="card-header"><h3 class="card-title mb-0">{{ $result['apply'] ? 'Apply' : 'Dry run' }} result</h3></div>
                    <div class="card-body">
                        @if (! $result['ok'])
                            <div class="alert alert-danger">{{ $result['message'] }}</div>
                        @endif
                        <dl class="row small mb-0">
                            <dt class="col-4">Topics {{ $result['apply'] && ($result['applied'] ?? false) ? 'replaced' : 'ready' }}</dt><dd class="col-8">{{ implode(', ', $result['topics'] ?? []) ?: '—' }}</dd>
                            <dt class="col-4">Rules / levels</dt><dd class="col-8">{{ $result['rules'] ?? 0 }} / {{ $result['levels'] ?? 0 }}</dd>
                            <dt class="col-4">Skipped topics</dt><dd class="col-8">{{ implode(', ', $result['skipped_topics'] ?? []) ?: '—' }}</dd>
                            @if ($result['batch'] ?? null) <dt class="col-4">Batch</dt><dd class="col-8"><code>{{ $result['batch'] }}</code></dd> @endif
                        </dl>
                        @if (! empty($result['errors']))
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <strong class="text-danger">{{ count($result['errors']) }} problem(s)</strong>
                                <a href="{{ route('utils.approvals.admin.import.errors') }}" class="btn btn-sm btn-outline-danger"><i class="la la-file-excel"></i> Error sheet</a>
                            </div>
                            <table class="table table-sm mt-2">
                                @foreach (array_slice($result['errors'], 0, 50) as $e)
                                    <tr><td>Row {{ $e['row'] }}</td><td><code>{{ $e['topic'] }}</code></td><td>{{ $e['message'] }}</td></tr>
                                @endforeach
                            </table>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
