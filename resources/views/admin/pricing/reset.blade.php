@extends(backpack_view('blank'))

@section('title', $title)

{{-- Pricing reset (DEC-082): dry preview + typed confirmation; the POST runs only in a local environment. --}}
@section('content')
<div class="container-xl">
    <h2 class="mb-3">{{ $title }}</h2>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="mb-2">Dry preview — nothing has been deleted.</p>
            <ul class="small mb-3">
                <li><strong>Deletes:</strong> vehicle variants, and orphan models, created on or after the cutoff.</li>
                <li><strong>Flushes:</strong> pricing sessions, profile, OEM prices and their history, flags, drafts, affected rows, snapshots, holds, CSD prices and queued jobs.</li>
                <li><strong>Keeps:</strong> sheet headers, add-ons, discounts, dealer charges, RTO, insurance, TCS and synonyms.</li>
            </ul>

            @if (! $allowed)
                <div class="alert alert-warning mb-0" role="alert">The reset runs only in a local environment.</div>
            @else
                <form method="POST" action="{{ route('pricing.reset.run') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-12 col-sm-4">
                        <label class="form-label required" for="rs-after">Cutoff date</label>
                        <x-ui.date name="after" id="rs-after" :value="$after" required />
                    </div>
                    <div class="col-12 col-sm-4">
                        <label class="form-label required" for="rs-confirm">Type {{ $confirmation }} to confirm</label>
                        <input type="text" class="form-control" name="confirmation" id="rs-confirm" autocomplete="off" required>
                    </div>
                    <div class="col-12 col-sm-4">
                        <button type="submit" class="btn btn-danger"><i class="la la-trash me-1"></i>Run pricing reset</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
