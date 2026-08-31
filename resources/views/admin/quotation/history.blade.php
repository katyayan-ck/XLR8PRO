@extends(backpack_view('blank'))

@section('content')

<div class="card">

    <div class="card-header" style="display: block;">

        <h3 class="mb-1">
            Quotation History
        </h3>

        <div style="font-size: 12px; color: #666; margin-top: 5px;">

            <strong>Quotation No.:</strong> {{ $quotation->quotation_no }}

            &nbsp;&nbsp; | &nbsp;&nbsp;

            <strong>Customer Name:</strong> {{ $customerName ?? '-' }}

            &nbsp;&nbsp; | &nbsp;&nbsp;

            <strong>Model:</strong> {{ $modelName ?? '-' }}

        </div>

    </div>

    <div class="card-body">

        <table class="table table-bordered table-striped">

            <thead class="table-light">

                <tr>

                    <th width="6%">Version</th>

                    <th width="10%">Action</th>

                    <th width="10%">Status</th>

                    

                    <th width="10%">User</th>

                    <th width="15%">Date</th>
                    <th width="10%">On Road</th>
                    <th width="34%">Financier</th>
                    <th width="5%">View</th>

                </tr>

            </thead>

            <tbody>

                @foreach($actions as $row)

                <tr>

                    <td>
                        <strong>V{{ $row->version }}</strong>
                    </td>

                    <td>
                        {{ ucfirst($row->action) }}
                    </td>

                    <td>
                        {{ ucfirst($row->status) }}
                    </td>

                    

                    <td>

                        {{ optional($row->actionBy)->display_name }}

                    </td>

                    <td>

                        {{ $row->created_at->format('d M Y h:i A') }}

                    </td>
                    <td>

                        ₹ {{ number_format($row->onroad,2) }}

                    </td>

                    <td>
                        {{ $row->financier_display ?: '-' }}
                    </td>
                    <td class="text-center">
                        <a href="{{ backpack_url('quotation-form/' . $quotation->id . '/history/' . $row->version . '/pdf') }}"
                        target="_blank"
                        title="View PDF"
                        class="btn btn-sm btn-outline-danger">
                            <i class="la la-file-pdf"></i>
                        </a>
                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

@endsection