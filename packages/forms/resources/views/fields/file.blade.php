@php($filesPath = 'files.'.$field->name)
<div class="lynx-form-file" x-data="{ progress: 0, uploading: false }"
     x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
    <input id="{{ $id }}" type="file" class="lynx-form-input" wire:model="{{ $filesPath }}" @if ($field->maxFiles > 1) multiple @endif
           @if ($field->extensions !== []) accept="{{ implode(',', array_map(fn ($e) => '.'.$e, $field->extensions)) }}" @endif>
    <p class="lynx-form-help">{{ __('lynx-forms::forms.files_hint', ['count' => $field->maxFiles, 'size' => \Illuminate\Support\Number::fileSize($field->maxKb * 1024), 'types' => strtoupper(implode(', ', $field->extensions))]) }}</p>
    <div x-show="uploading" x-cloak class="lynx-form-progress" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
        <span :style="'width:' + progress + '%'"></span>
    </div>
    @foreach ((array) ($files[$field->name] ?? []) as $file)
        @if (is_object($file) && method_exists($file, 'getClientOriginalName'))
            <p class="lynx-form-file-name">{{ $file->getClientOriginalName() }}</p>
        @endif
    @endforeach
</div>
