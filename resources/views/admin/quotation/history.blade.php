@extends(backpack_view('blank'))

@section('content')

<div class="card">

    <div class="card-header">

        <h3 class="mb-0">
            Quotation History
        </h3>

    </div>

    <div class="card-body">

        <table class="table table-bordered table-striped">

            <thead class="table-light">

                <tr>

                    <th width="6%">Rev</th>

                    <th width="10%">Action</th>

                    <th width="10%">Status</th>

                    <th width="10%">On Road</th>

                    <th width="10%">User</th>

                    <th width="15%">Date</th>

                    <th width="39%">Changes</th>

                </tr>

            </thead>

            <tbody>

                @foreach($actions as $row)

                <tr>

                    <td>
                        <strong>{{ $row->revision }}</strong>
                    </td>

                    <td>
                        {{ ucfirst($row->action) }}
                    </td>

                    <td>
                        {{ ucfirst($row->status) }}
                    </td>

                    <td>

                        ₹ {{ number_format($row->onroad,2) }}

                    </td>

                    <td>

                        {{ optional($row->actionBy)->display_name }}

                    </td>

                    <td>

                        {{ $row->created_at->format('d M Y h:i A') }}

                    </td>

                    <td>

                        @if(count($row->changes))

                        <table class="table table-sm table-bordered mb-0">

                            <thead>

                                <tr>

                                    <th>Field</th>

                                    <th>Old Value</th>

                                    <th>New Value</th>

                                </tr>

                            </thead>

                            <tbody>

                                @foreach($row->changes as $change)

                                <tr>

                                    <td>

                                        <strong>

                                            {{ $change['field'] }}

                                        </strong>

                                    </td>

                                    <td class="text-danger">

                                        {{ is_array($change['old']) ? implode(', ', $change['old']) : ($change['old'] ?:
                                        '-') }}

                                    </td>

                                    <td class="text-success">

                                        {{ is_array($change['new']) ? implode(', ', $change['new']) : ($change['new'] ?:
                                        '-') }}

                                    </td>

                                </tr>

                                @endforeach

                            </tbody>

                        </table>

                        @else

                        <span class="text-muted">

                            No Changes

                        </span>

                        @endif

                    </td>

                </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

@endsection