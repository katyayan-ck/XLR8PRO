@extends(backpack_view('blank'))

@section('header')
    <h2>
        <i class="la la-undo text-danger"></i> Requested Refunds
    </h2>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-danger text-black">
                <h3 class="card-title">Bookings with Refund Requested</h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Booking No</th>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th>Amount</th>
                            <th>Booking Date</th>
                            <th>Requested On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gridData as $row)
                            <tr>
                                <td>{{ $row['booking_no'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['mobile'] }}</td>
                                <td>{{ $row['amount'] }}</td>
                                <td>{{ $row['booking_date'] }}</td>
                                <td>{{ $row['requested_date'] }}</td>
                                <td>{!! $row['status'] !!}</td>
                                <td>{!! $row['action'] !!}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No refund requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection