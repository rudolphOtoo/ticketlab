{{--
    Signed direct upload to Cloudinary for one image field of a Livewire component that uses
    App\Livewire\Concerns\UploadsImages. The file goes from the browser to Cloudinary; Livewire only receives the
    checked result into the component's #[Locked] $field property.
--}}
@props([
    'field',
    'id' => null,
    'label' => 'Upload image',
])

@php $inputId = $id ?? 'upload-' . $field; @endphp

<div x-data="imageUpload(@js($field))" {{ $attributes->class('space-y-1.5') }} data-image-upload="{{ $field }}">
    <label for="{{ $inputId }}" class="sr-only">{{ $label }}</label>
    <input
        id="{{ $inputId }}"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        @change="upload($event)"
        :disabled="busy"
        class="block w-full text-xs disabled:opacity-60"
    />
    <p class="text-[11px] text-[var(--text-tertiary)]">JPG, PNG or WebP, up to 5 MB.</p>

    <div x-show="busy" x-cloak class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--bg-subtle)]" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full bg-[var(--color-primary)] transition-[width]" :style="`width: ${progress}%`"></div>
    </div>

    <p x-show="error" x-text="error" x-cloak role="alert" class="text-xs text-rose-500"></p>
    @error($field)
        <p role="alert" class="text-xs text-rose-500">{{ $message }}</p>
    @enderror
</div>
