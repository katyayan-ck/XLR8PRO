@extends(backpack_view('blank'))

@section('title', $title)

@section('content')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-8">
            <x-approval.panel :request="$approval" />
        </div>
        <div class="col-lg-4">
            <x-chat.thread :model="$approval" title="Timeline" />
        </div>
    </div>
</div>
@endsection
