{{-- resources/views/admin/vehicle-accessory/import.blade.php --}}
@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">Import Vehicle Accessories</div>
        <div class="card-body">
            <form method="POST" action="{{ route('vehicle-accessory.import.process') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Excel File</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button class="btn btn-primary">Import</button>
                <a href="{{ route('vehicle-accessory.template') }}" class="btn btn-secondary">Download Template</a>
                <a href="{{ route('vehicle-accessory.import.history') }}" class="btn btn-outline-dark">Import History</a>
            </form>
        </div>
    </div>
</div>
@endsection