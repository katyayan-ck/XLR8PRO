@props(['person' => null, 'ref' => null, 'label' => 'Call'])
{{--
    Click-to-call (FRS §16.6, TEL-02/07). With :person the number is resolved server-side from the
    person_code, so the browser never receives the customer's real number. Without it, the agent types one.
--}}
@php
    $personCode = $person instanceof \Illuminate\Database\Eloquent\Model ? $person->person_code : $person;
    $refType = $ref instanceof \Illuminate\Database\Eloquent\Model ? app(\App\Services\Platform\Chat\ChatService::class)->refType($ref) : null;
@endphp
@can('UTL_COMM_CALL')
    <form method="POST" action="{{ route('utils.calls.dial') }}" class="d-inline-flex gap-1 click-to-call">
        @csrf
        @if ($personCode)
            <input type="hidden" name="to" value="{{ $personCode }}">
        @else
            <input type="tel" name="to" required class="form-control form-control-sm" placeholder="Mobile">
        @endif
        @if ($refType)
            <input type="hidden" name="ref_type" value="{{ $refType }}">
            <input type="hidden" name="ref_id" value="{{ $ref->getKey() }}">
        @endif
        <button class="btn btn-sm btn-success" title="Your phone rings first, then the customer"><i class="la la-phone"></i> {{ $label }}</button>
    </form>
@endcan
