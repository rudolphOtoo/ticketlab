{{--
    A stored image (Cloudinary, pasted link or older path) as an <img>. Cloudinary images get f_auto,q_auto copies
    at the preset's widths in a srcset, so each screen downloads the size it needs; others render as they are.
    Renders nothing when there is no image to show.
--}}
@props([
    'value',
    'preset' => 'hero',
    'alt' => '',
    'sizes' => null,
    'eager' => false,
])

@php
    $imagePreset = \App\Services\Media\ImagePreset::fromLegacy($preset);
    $src = \App\Support\MediaUrl::resolve($value, $imagePreset);
    $srcset = \App\Support\MediaUrl::srcset($value, $imagePreset);
@endphp

@if ($src !== null)
    <img
        src="{{ $src }}"
        @if ($srcset !== null)
            srcset="{{ $srcset }}"
            sizes="{{ $sizes ?? $imagePreset->sizes() }}"
        @endif
        alt="{{ $alt }}"
        @if ($eager) fetchpriority="high" @else loading="lazy" @endif
        decoding="async"
        {{ $attributes }}
    />
@endif
