{{-- Shared banking field set. $bank is null when adding a new one, in which case
     $types is restricted to unused account_type slots for this person. --}}
<div class="row">
    <div class="col-md-3 mb-2">
        <label class="small">Account Type</label>
        <select name="account_type" class="form-control form-select form-select-sm" {{ $bank ? 'disabled' : '' }}>
            @foreach ($types as $type)
                <option value="{{ $type }}" {{ $bank?->account_type === $type ? 'selected' : '' }}>{{ $type }}</option>
            @endforeach
        </select>
        @if ($bank)
            <input type="hidden" name="account_type" value="{{ $bank->account_type }}">
        @endif
    </div>
    <div class="col-md-4 mb-2">
        <label class="small">Bank Name</label>
        <input type="text" name="bank_name" class="form-control form-control-sm" value="{{ $bank->bank_name ?? '' }}">
    </div>
    <div class="col-md-5 mb-2">
        <label class="small">Branch Name</label>
        <input type="text" name="branch_name" class="form-control form-control-sm" value="{{ $bank->branch_name ?? '' }}">
    </div>
    <div class="col-md-4 mb-2">
        <label class="small">Account Number</label>
        <input type="text" name="account_number" class="form-control form-control-sm" value="{{ $bank->account_number ?? '' }}">
    </div>
    <div class="col-md-4 mb-2">
        <label class="small">Account Holder Name</label>
        <input type="text" name="account_holder_name" class="form-control form-control-sm" value="{{ $bank->account_holder_name ?? '' }}">
    </div>
    <div class="col-md-4 mb-2">
        <label class="small">Account Nature</label>
        <select name="account_nature" class="form-control form-select form-select-sm">
            @foreach (\App\Models\Admin\PersonBankingDetail::ACCOUNT_NATURES as $nature)
                <option value="{{ $nature }}" {{ ($bank->account_nature ?? 'Savings') === $nature ? 'selected' : '' }}>{{ $nature }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">IFSC Code</label>
        <input type="text" name="ifsc_code" class="form-control form-control-sm" value="{{ $bank->ifsc_code ?? '' }}" maxlength="11">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small">MICR Code</label>
        <input type="text" name="micr_code" class="form-control form-control-sm" value="{{ $bank->micr_code ?? '' }}">
    </div>
</div>
