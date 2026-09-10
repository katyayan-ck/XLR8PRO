{{-- resources/views/admin/vehicle-accessory/export.blade.php --}}
@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">Export Vehicle Accessories</div>
        <div class="card-body">
            <form method="POST" action="{{ route('vehicle-accessory.export.process') }}">
                @csrf
                <button class="btn btn-success">Download Excel</button>
                <a href="{{ route('vehicle-accessory.export.history') }}" class="btn btn-outline-dark">Export History</a>
            </form>
        </div>
    </div>
</div>
@endsection