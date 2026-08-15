@extends('layouts.app')
@section('title', 'New Programme | VENTIQ')
@section('content')
<div class="max-w-xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-black text-[#1D4069] uppercase tracking-tight mb-1">New Programme</h1>
    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-8">Just the shell — you'll add sessions once this exists</p>

    <form method="POST" action="{{ route('programmes.store') }}" class="space-y-6">
        @csrf
        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Programme Name</label>
            <input type="text" name="name" required placeholder="e.g. Customer Service Training"
                class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Start Date</label>
                <input type="date" name="start_date" required value="{{ now()->toDateString() }}" x-ref="startDate"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
            </div>
            <div>
                <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">End Date</label>
                <input type="date" name="end_date" value="{{ now()->toDateString() }}"
                    class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                <p class="text-[9px] text-gray-400 font-bold mt-1 ml-1">Same as start date for a single-day programme</p>
            </div>
        </div>

        <div>
            <label class="text-[10px] font-black text-[#1D4069] uppercase tracking-wider ml-1 mb-1 block">Venue (optional)</label>
            <input type="text" name="venue" placeholder="e.g. Maseru Convention Center"
                class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
        </div>

        <label class="flex items-center justify-between px-5 py-4 bg-gray-50 rounded-2xl cursor-pointer">
            <div>
                <p class="text-[11px] font-black text-[#1D4069] uppercase tracking-wide">Offer Certificates</p>
                <p class="text-[9px] text-gray-400 font-bold mt-0.5">You'll issue them manually once you're ready — nothing goes out automatically.</p>
            </div>
            <input type="checkbox" name="certificates_enabled" value="1" class="w-5 h-5 accent-[#1D4069]">
        </label>

        @if($errors->any())
            <div class="p-4 bg-rose-50 text-rose-600 rounded-2xl text-[11px] font-bold">
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <button type="submit" class="w-full py-5 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-xl transition-all">
            Create Programme
        </button>
    </form>
</div>
@endsection