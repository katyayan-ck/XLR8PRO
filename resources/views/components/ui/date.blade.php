@props(['name', 'value' => null, 'time' => false, 'id' => null, 'min' => null, 'max' => null, 'required' => false, 'placeholder' => null])
{{--
    Site-format date / date-time picker (.ai/rules/ui.md, DEC-066). Shows `display.date_format` (+ time);
    submits ISO: Y-m-d, or Y-m-d H:i with :time="true". Enhanced by public/js/xl-ui.js (flatpickr).
    <x-ui.date name="deadline" :value="$task->deadline" time />
--}}
@php
    $iso = null;
    if ($value) {
        try {
            $iso = \Illuminate\Support\Carbon::parse($value)->format($time ? 'Y-m-d H:i' : 'Y-m-d');
        } catch (\Throwable) {
            $iso = null;
        }
    }
    $id ??= 'xl-'.str_replace(['[', ']', '.'], '-', $name);
@endphp
<input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ old(str_replace(['[', ']'], ['.', ''], $name), $iso) }}"
       data-xl-date="{{ $time ? 'datetime' : 'date' }}" autocomplete="off"
       @if ($min) data-min="{{ $min }}" @endif @if ($max) data-max="{{ $max }}" @endif
       @if ($placeholder) placeholder="{{ $placeholder }}" @endif
       @required($required)
       {{ $attributes->merge(['class' => 'form-control']) }}>
