@extends(backpack_view('blank'))

@section('title', 'View Receipt')

@push('after_styles')
    <style>
        .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); }
        .section-title { font-size: 16px; font-weight: 500; color: #1f4b78; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
        .form-control:disabled, .form-control[readonly] { background-color: #f8f9fa; opacity: 1; border: 1px dashed #ced4da; font-weight: 500;}
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0 text-black">View Receipt: {{ $receipt->type_number }}</h4>
                        <a href="{{ backpack_url('accounts/receipt') }}" class="btn btn-secondary btn-sm">
                            <i class="la la-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            {{-- RECEIPT DETAILS --}}
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Receipt No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->type_number }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Receipt Date</label>
                                <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($receipt->date)->format('d-m-Y') }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Issued For Location</label>
                                <input type="text" class="form-control" value="{{ $receipt->location }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">On A/c Of</label>
                                <input type="text" class="form-control" value="{{ $onAccountOfOptions[$receipt->account_of] ?? $receipt->account_of }}" readonly>
                            </div>
                        </div>

                        <div class="row mt-3">
                            {{-- TRANSACTION DETAILS --}}
                            <div class="col-md-12">
                                <div class="section-title">Transaction & Vehicle Details</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Xceler8 Enq No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->enq_id ? 'XENQ-'.$receipt->enq_id : 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Booking No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->bid ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">VOTF No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->otf_no ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Invoice No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->inv_no ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Registration No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->vh_rgn_no ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Chassis No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->chassis_no ?? 'N/A' }}" readonly>
                            </div>
                        </div>

                        <div class="row mt-3">
                            {{-- CUSTOMER DETAILS --}}
                            <div class="col-md-12">
                                <div class="section-title">Customer Details</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Customer Name</label>
                                <input type="text" class="form-control" value="{{ $receipt->customer_name ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Care Of</label>
                                @php
                                    $careOfMap = [1 => 'Son of', 2 => 'Daughter of', 3 => 'Married to', 4 => 'Guardian Name'];
                                    $careOfPrefix = isset($careOfMap[$receipt->care_of_type]) ? $careOfMap[$receipt->care_of_type] . ' ' : '';
                                @endphp
                                <input type="text" class="form-control" value="{{ $receipt->care_of ? $careOfPrefix . $receipt->care_of : 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Mobile No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->mobile ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Alt Mobile No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->alternate_mobile ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label text-muted">Address</label>
                                <textarea class="form-control" rows="2" readonly>{{ $receipt->address ?? 'N/A' }}</textarea>
                            </div>
                        </div>

                        <div class="row mt-3">
                            {{-- PAYMENT DETAILS --}}
                            <div class="col-md-12">
                                <div class="section-title">Payment Details</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Mode of Payment</label>
                                <input type="text" class="form-control" value="{{ $paymentModeOptions[$receipt->mode] ?? $receipt->mode }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Amount (In Figures)</label>
                                <input type="text" class="form-control" id="amount_val" value="{{ number_format($receipt->amount, 2) }}" readonly>
                                <input type="hidden" id="raw_amount" value="{{ $receipt->amount }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Amount (In Words)</label>
                                <input type="text" class="form-control fw-bold" id="amount_in_words" readonly>
                            </div>
                            
                            @if(in_array(strtoupper($paymentModeOptions[$receipt->mode] ?? ''), ['CHEQUE', 'RTGS', 'NEFT', 'BANK TRANSFER', 'DEMAND DRAFT']))
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Instrument / Ref No.</label>
                                <input type="text" class="form-control" value="{{ $receipt->instrument_no ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Transaction Date</label>
                                <input type="text" class="form-control" value="{{ $receipt->trans_date ? \Carbon\Carbon::parse($receipt->trans_date)->format('d-m-Y') : 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Bank Name</label>
                                <input type="text" class="form-control" value="{{ $receipt->bank ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">Transaction / UTR ID</label>
                                <input type="text" class="form-control" value="{{ $receipt->trans_no ?? 'N/A' }}" readonly>
                            </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
            const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

            function inWords (num) {
                if ((num = num.toString()).length > 9) return 'overflow';
                let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
                if (!n) return; let str = '';
                str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
                str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
                str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
                str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
                str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) + 'Only' : 'Only';
                return str;
            }

            let rawAmount = Math.floor(document.getElementById('raw_amount').value);
            if(rawAmount > 0) {
                document.getElementById('amount_in_words').value = 'Rupees ' + inWords(rawAmount);
            }
        });
    </script>
@endpush