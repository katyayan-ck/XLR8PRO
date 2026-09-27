@props(['name' => 'file', 'multiple' => false, 'accept' => null, 'url' => null, 'fields' => [], 'reload' => false, 'maxKb' => null, 'id' => null])
{{--
    Drop-zone uploader (.ai/rules/ui.md, DEC-066): drag & drop / browse, previews, remove before upload,
    type + size checks from Settings (docs.allowed_mimes, docs.max_upload_kb).
    - inside a normal form: <x-ui.upload name="file" />  (files post with the form)
    - AJAX, per-file progress + errors: <x-ui.upload :url="route('utils.docs.store')" :fields="['ref_type' => 'TASK', 'ref_id' => $id]" multiple reload />
--}}
@php $id ??= 'xl-'.str_replace(['[', ']'], ['-', ''], $name); @endphp
<input type="file" name="{{ $name }}" id="{{ $id }}" @if ($multiple) multiple @endif
       @if ($accept) accept="{{ $accept }}" @endif
       @if ($maxKb) data-max-kb="{{ $maxKb }}" @endif
       @if ($url) data-xl-upload-url="{{ $url }}" data-xl-upload-fields="{{ json_encode((object) $fields) }}" @endif
       @if ($reload) data-xl-reload @endif
       {{ $attributes->merge(['class' => 'form-control']) }}>
