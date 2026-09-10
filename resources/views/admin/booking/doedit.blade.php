@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">

</section>
@endsection

@section('content')
<div class="row">

    <div class="card bg-light border-0 shadow-sm mb-4">
        <div class="card-header">
            <h2 class="mb-0">Invoice Details</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-control" value="{{ $booking->name ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Model / Variant</label>
                    <input type="text" class="form-control"
                        value="{{ $booking->model_code ?? 'N/A' }} / {{ $booking->variant_code ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Finance Mode</label>
                    <input type="text" class="form-control" value="{{ $booking->fin_mode ?? 'N/A' }}" readonly>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Financier</label>
                    <input type="text" class="form-control"
                        value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $booking->financier)['name'] ?? 'N/A' }}"
                        readonly>
                </div>
            </div>
        </div>
    </div>

    <form id="doForm" method="POST" action="{{ route('finance.do.update', $booking->id) }}"
        enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <input type="hidden" name="instrument_type" value="2">
        <input type="hidden" name="retail" value="1">
        <input type="hidden" name="bid" value="{{ $booking->id }}">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="mb-0">Finance Details (Delivery Order)</h2>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-sm-3">
                        <label class="form-label">Finance Mode</label>
                        <input type="text" class="form-control"
                            value="{{ $finance->fin_mode ?? $booking->fin_mode ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Loan Status</label>
                        <input type="text" class="form-control" value="{{ $finance->loan_status ?? 'N/A' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Financier Name</label>
                        <input type="text" class="form-control"
                            value="{{ collect($data['financiers'] ?? [])->firstWhere('id', $finance->financier ?? $booking->financier)['name'] ?? 'N/A' }}"
                            readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label">Case Status</label>
                        <input type="text" class="form-control"
                            value="{{ $finance->case_status == 2 ? 'In House Finance Done' : 'In-Process' }}" readonly>
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Instrument Type</label>
                        <input type="text" class="form-control" value="Delivery Order" readonly>
                    </div>



                    <div class="col-sm-3">
                        <label class="form-label">Instrument Proof</label>
                        <div class="mt-2">
                            @if($finance && $finance->getFirstMediaUrl('instrument_proof'))
                            @php
                            $existingUrl = $finance->getFirstMediaUrl('instrument_proof');
                            $fileName = basename($existingUrl);
                            @endphp
                            <span
                                class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 px-3 py-2"
                                style="cursor:pointer"
                                onclick="openInstrumentModal('{{ $existingUrl }}', '{{ $fileName }}')">
                                <i class="la la-paperclip"></i>
                                <span class="fw-medium small">{{ $fileName }}</span>
                            </span>
                            @else
                            <span class="text-muted small">No proof uploaded yet</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-sm-2">
                        <label class="form-label">Loan Amount</label>
                        <input type="text" class="form-control" value="{{ $finance->loan_amount ?? '0' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Margin Money</label>
                        <input type="text" class="form-control" value="{{ $finance->margin ?? '0' }}" readonly>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">File Charge</label>
                        <input type="text" class="form-control" value="{{ $finance->file_charge ?? '0' }}" readonly>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label text-black">Delivery Order Number <span
                                class="text-danger">*</span></label>
                        <input type="text" name="instrument_ref_no" class="form-control"
                            value="{{ old('instrument_ref_no', $finance->instrument_ref_no ?? '') }}" required
                            autofocus>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-success">
                    <i class="la la-save"></i> Save Delivery Order
                </button>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                    <i class="la la-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </form>

</div>

<div class="modal fade" id="instrumentProofModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="instrumentModalFileName"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <iframe id="instrumentModalPreview" style="width:100%; height:500px;" frameborder="0"></iframe>
            </div>
            <div class="modal-footer">
                <a id="instrumentModalDownload" class="btn btn-success" download>Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    window.openInstrumentModal = function(url, name) {
        document.getElementById('instrumentModalFileName').innerText = name;
        document.getElementById('instrumentModalPreview').src = url;
        document.getElementById('instrumentModalDownload').href = url;
        $('#instrumentProofModal').modal('show');
    };

    $('#instrumentProofModal').on('show.bs.modal', function () {
        $(this).appendTo('body');
    });


    $('#doForm').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true)
            .html('<i class="la la-spinner la-spin"></i> Saving...');
    });
</script>
@endpush