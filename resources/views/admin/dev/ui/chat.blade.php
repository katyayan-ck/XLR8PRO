@extends('admin.dev.ui._layout')

@section('kit')
@php
    $threads = [
        ['Rahul Sharma', 'RS', 'bg-blue-lt', 'Can we do Saturday 11am for delivery?', '10:42', 2, true],
        ['Anita Verma', 'AV', 'bg-green-lt', 'Sent the Aadhaar copy 👍', '09:15', 0, true],
        ['Mohd. Irfan', 'MI', 'bg-purple-lt', 'What is the final on-road for Harrier?', 'Yesterday', 1, false],
        ['Sneha Joshi', 'SJ', 'bg-orange-lt', 'Thanks, loan is approved.', 'Yesterday', 0, false],
        ['Vikram Rathore', 'VR', 'bg-red-lt', 'Please call after 5pm', 'Mon', 0, false],
        ['Pooja Nair', 'PN', 'bg-azure-lt', 'Photos of the car look great!', 'Sun', 0, false],
    ];
    $messages = [
        ['them', 'Rahul Sharma', 'Hi, is the Nexon in Pristine White available for delivery this week?', '10:21'],
        ['me', 'Priya Mehta', 'Hello Rahul! Yes — the car has arrived at the Jaipur yard and PDI is done.', '10:24'],
        ['me', 'Priya Mehta', 'Sharing the final on-road price and the delivery checklist.', '10:24'],
        ['doc', 'Priya Mehta', 'Quote_Q-JPR-0142.pdf', '10:25'],
        ['them', 'Rahul Sharma', 'Perfect. Can we do Saturday 11am for delivery?', '10:42'],
    ];
@endphp
<div class="card">
    <div class="xl-chat">
        {{-- Conversation list --}}
        <div class="xl-chat-list">
            <div class="card-header">
                <div class="input-icon w-100">
                    <span class="input-icon-addon"><i class="la la-search"></i></span>
                    <input type="search" class="form-control" placeholder="Search conversations" aria-label="Search conversations">
                </div>
            </div>
            <div class="px-3 py-2 border-bottom">
                <div class="nav nav-segmented w-100" role="tablist">
                    <a href="#" class="nav-link active flex-fill text-center">Mine <span class="badge bg-primary text-primary-fg ms-1">3</span></a>
                    <a href="#" class="nav-link flex-fill text-center">Queue</a>
                    <a href="#" class="nav-link flex-fill text-center">Done</a>
                </div>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($threads as $i => [$name, $ini, $bg, $last, $when, $unread, $online])
                    <a href="#" class="list-group-item list-group-item-action {{ $i === 0 ? 'active' : '' }}" @if($i === 0) aria-current="true" @endif>
                        <div class="row align-items-center g-2">
                            <div class="col-auto"><span class="avatar {{ $bg }} xl-avatar">{{ $ini }}@if($online)<span class="badge bg-success"></span>@endif</span></div>
                            <div class="col text-truncate">
                                <div class="d-flex"><span class="fw-medium text-truncate">{{ $name }}</span><span class="ms-auto small text-secondary ps-2">{{ $when }}</span></div>
                                <div class="d-flex align-items-center">
                                    <span class="text-secondary small text-truncate">{{ $last }}</span>
                                    @if ($unread) <span class="badge bg-primary text-primary-fg ms-auto">{{ $unread }}</span> @endif
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Conversation --}}
        <div class="xl-chat-main">
            <div class="card-header">
                <div class="d-flex align-items-center w-100 gap-2">
                    <span class="avatar bg-blue-lt xl-avatar">RS<span class="badge bg-success"></span></span>
                    <div class="text-truncate">
                        <div class="fw-medium">Rahul Sharma</div>
                        <div class="small text-secondary text-truncate">+91 98XXXXXX12 · XENQ-4201 · Nexon Creative +</div>
                    </div>
                    <div class="ms-auto d-flex gap-1">
                        <span class="badge bg-green-lt d-none d-md-inline-flex align-items-center me-1"><i class="la la-clock me-1"></i>Window open 21 h</span>
                        <button type="button" class="btn btn-icon btn-ghost-secondary" aria-label="Call"><i class="la la-phone"></i></button>
                        <button type="button" class="btn btn-icon btn-ghost-secondary" aria-label="Link to record"><i class="la la-link"></i></button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-icon btn-ghost-secondary" data-bs-toggle="dropdown" aria-label="More"><i class="la la-ellipsis-v"></i></button>
                            <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="#">Assign…</a><a class="dropdown-item" href="#">Mark done</a><a class="dropdown-item" href="#">Send template</a></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="xl-chat-body" id="kit-chat-body">
                <div class="chat-bubbles">
                    <div class="hr-text my-2">Today</div>
                    @foreach ($messages as [$who, $author, $text, $time])
                        <div class="chat-item {{ $who === 'them' ? '' : 'chat-item-me' }}">
                            <div class="row align-items-end g-2 {{ $who === 'them' ? '' : 'justify-content-end' }}">
                                @if ($who === 'them') <div class="col-auto"><span class="avatar avatar-sm bg-blue-lt">RS</span></div> @endif
                                <div class="col col-lg-8">
                                    <div class="chat-bubble {{ $who === 'them' ? '' : 'chat-bubble-me' }}">
                                        <div class="chat-bubble-title">
                                            <div class="row"><div class="col chat-bubble-author">{{ $author }}</div><div class="col-auto chat-bubble-date">{{ $time }} @if($who !== 'them')<i class="la la-check-double ms-1"></i>@endif</div></div>
                                        </div>
                                        <div class="chat-bubble-body">
                                            @if ($who === 'doc')
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="avatar avatar-sm bg-red-lt"><i class="la la-file-pdf"></i></span>
                                                    <div class="text-truncate"><div class="fw-medium text-truncate">{{ $text }}</div><div class="small opacity-75">PDF · 212 KB</div></div>
                                                    <a href="#" class="btn btn-icon btn-sm ms-auto" aria-label="Download"><i class="la la-download"></i></a>
                                                </div>
                                            @else
                                                <p class="mb-0">{{ $text }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($who !== 'them') <div class="col-auto"><span class="avatar avatar-sm bg-purple-lt">PM</span></div> @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="chat-item">
                        <div class="row align-items-end g-2">
                            <div class="col-auto"><span class="avatar avatar-sm bg-blue-lt">RS</span></div>
                            <div class="col-auto"><div class="chat-bubble py-2"><span class="xl-typing" aria-label="typing"><span></span><span></span><span></span></span></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="xl-chat-composer">
                <form class="d-flex align-items-end gap-2" id="kit-chat-form">
                    <button type="button" class="btn btn-icon btn-ghost-secondary" aria-label="Attach"><i class="la la-paperclip"></i></button>
                    <button type="button" class="btn btn-icon btn-ghost-secondary d-none d-sm-inline-flex" aria-label="Template"><i class="la la-file-alt"></i></button>
                    <label class="visually-hidden" for="kit-chat-input">Message</label>
                    <textarea class="form-control" id="kit-chat-input" rows="1" data-bs-toggle="autosize" placeholder="Type a message…"></textarea>
                    <button type="submit" class="btn btn-primary btn-icon" aria-label="Send"><i class="la la-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
/* Static demo: appends the typed message as an outgoing bubble (nothing is sent). */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('kit-chat-form'), input = document.getElementById('kit-chat-input'), body = document.getElementById('kit-chat-body');
    if (!form) { return; }
    body.scrollTop = body.scrollHeight;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = input.value.trim();
        if (!text) { return; }
        var item = document.createElement('div');
        item.className = 'chat-item chat-item-me';
        item.innerHTML = '<div class="row align-items-end g-2 justify-content-end"><div class="col col-lg-8"><div class="chat-bubble chat-bubble-me">'
            + '<div class="chat-bubble-title"><div class="row"><div class="col chat-bubble-author">You</div><div class="col-auto chat-bubble-date">now</div></div></div>'
            + '<div class="chat-bubble-body"><p class="mb-0"></p></div></div></div><div class="col-auto"><span class="avatar avatar-sm bg-purple-lt">PM</span></div></div>';
        item.querySelector('p').textContent = text;
        var typing = body.querySelector('.chat-bubbles > .chat-item:last-child');
        body.querySelector('.chat-bubbles').insertBefore(item, typing);
        input.value = '';
        body.scrollTop = body.scrollHeight;
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
    });
});
</script>
@endpush
