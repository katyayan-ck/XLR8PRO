@props(['name', 'options' => [], 'selected' => [], 'multiple' => false, 'placeholder' => null, 'required' => false, 'id' => null, 'source' => null])
{{--
    Select2 select (.ai/rules/ui.md, DEC-066): searchable, clearable, chips for multiple. Never a list box.
    <x-ui.select name="assignees[]" :options="$team" :selected="$ids" multiple placeholder="Pick people" />
    `source` = URL returning Select2 JSON ({results: [{id, text}]}) for large lists.
--}}
@php
    $id ??= 'xl-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $selected = collect(old(str_replace('[]', '', $name), (array) $selected))->map(fn ($v) => (string) $v)->all();
@endphp
<select name="{{ $name }}" id="{{ $id }}" data-xl="select2" @if ($multiple) multiple @endif @required($required)
        @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif @if ($source) data-xl-source="{{ $source }}" @endif
        {{ $attributes->merge(['class' => 'form-select']) }}>
    @unless ($multiple)
        <option value=""></option>
    @endunless
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected(in_array((string) $value, $selected, true))>{{ $label }}</option>
    @endforeach
</select>
