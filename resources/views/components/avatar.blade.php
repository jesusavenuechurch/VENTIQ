{{-- A person's illustrated face. seed: something stable (email, phone). --}}
@props(['seed' => null, 'size' => 'w-9 h-9'])
<img src="{{ \App\Support\Avatar::url($seed) }}" alt="" aria-hidden="true" loading="lazy"
     {{ $attributes->merge(['class' => "{$size} shrink-0 rounded-full bg-slate-100"]) }}>
