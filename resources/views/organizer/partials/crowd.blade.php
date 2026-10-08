{{-- A little group of faces for empty and happy states. --}}
<div class="flex justify-center -space-x-3 mb-4" aria-hidden="true">
    @foreach($seeds as $i => $seed)
        <x-avatar :seed="$seed" size="{{ $i === 1 ? 'w-16 h-16 relative z-10 -mt-2' : 'w-12 h-12' }}" class="ring-4 ring-white" />
    @endforeach
</div>
