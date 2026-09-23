{{-- Shared address field set. $address is null when adding a new one, in which case
     $types is restricted to unused address_type slots for this person. --}}
<div class="row">
    <div class="col-md-3 mb-2">
        <label class="small">Type</label>
        <select name="address_type" class="form-control form-select form-select-sm" {{ $address ? 'disabled' : '' }}>
            @foreach ($types as $type)
                <option value="{{ $type }}" {{ $address?->address_type === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>
        @if ($address)
            {{-- disabled selects don't submit — resend the locked value --}}
            <input type="hidden" name="address_type" value="{{ $address->address_type }}">
        @endif
    </div>
    <div class="col-md-4 mb-2">
        <label class="small">Address Line 1</label>
        <input type="text" name="address_line_1" class="form-control form-control-sm" value="{{ $address->address_line_1 ?? '' }}">
    </div>
    <div class="col-md-5 mb-2">
        <label class="small">Address Line 2</label>
        <input type="text" name="address_line_2" class="form-control form-control-sm" value="{{ $address->address_line_2 ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">Landmark</label>
        <input type="text" name="landmark" class="form-control form-control-sm" value="{{ $address->landmark ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">City</label>
        <input type="text" name="city" class="form-control form-control-sm" value="{{ $address->city ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">District</label>
        <input type="text" name="district" class="form-control form-control-sm" value="{{ $address->district ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">Taluka</label>
        <input type="text" name="taluka" class="form-control form-control-sm" value="{{ $address->taluka ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">State</label>
        <input type="text" name="state" class="form-control form-control-sm" value="{{ $address->state ?? '' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">Country</label>
        <input type="text" name="country" class="form-control form-control-sm" value="{{ $address->country ?? 'India' }}">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">Pincode</label>
        <input type="text" name="pincode" class="form-control form-control-sm" value="{{ $address->pincode ?? '' }}" maxlength="6">
    </div>
</div>
