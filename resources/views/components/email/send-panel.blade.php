@props(['template', 'to' => '', 'ref' => null, 'vars' => [], 'attach' => [], 'title' => 'Send email'])
{{-- Ops "send this again" panel (FRS EML-10) — posts to the same Email::send API. --}}
@php $refType = $ref instanceof \Illuminate\Database\Eloquent\Model ? app(\App\Services\Platform\Chat\ChatService::class)->refType($ref) : null; @endphp
@can('UTL_COMM_SEND')
    <form method="POST" action="{{ route('utils.comms.email.send') }}" class="card">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">
        @foreach ($attach as $docId) <input type="hidden" name="attach[]" value="{{ $docId }}"> @endforeach
        @if ($refType) <input type="hidden" name="ref_type" value="{{ $refType }}"><input type="hidden" name="ref_id" value="{{ $ref->getKey() }}"> @endif
        <div class="card-header"><h3 class="card-title mb-0"><i class="la la-envelope me-1"></i>{{ $title }} <span class="small text-muted">{{ $template }}</span></h3></div>
        <div class="card-body row g-2">
            <div class="col-12"><input type="text" name="to" value="{{ $to }}" required class="form-control form-control-sm" placeholder="To (person codes or emails)"></div>
            <div class="col-md-6"><input type="text" name="cc" class="form-control form-control-sm" placeholder="Cc"></div>
            <div class="col-md-6"><input type="text" name="bcc" class="form-control form-control-sm" placeholder="Bcc"></div>
            <div class="col-md-4"><input type="text" name="from" class="form-control form-control-sm" placeholder="From alias (default)"></div>
            <div class="col-md-8"><input type="text" name="vars_json" value="{{ $vars ? json_encode($vars) : '' }}" class="form-control form-control-sm font-monospace" placeholder='{"cust_name":"…"}'></div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-sm btn-primary">Send</button></div>
    </form>
@endcan
