{{-- Where an organization's payout goes, with a warning when it's missing or just changed. --}}
@if($org?->hasPayoutDetails())
    <p class="text-[11px] font-bold text-[#1D4069] mt-1"><i class="fas fa-money-bill-transfer mr-1 text-gray-400"></i>{{ $org->payoutSummary() }}</p>
    @if($org->payoutRecentlyChanged())
        <p class="text-[11px] font-black text-rose-600 mt-0.5"><i class="fas fa-triangle-exclamation mr-1"></i>Changed {{ $org->payout_updated_at->diffForHumans() }}: confirm with the organizer by phone ({{ $org->phone }}) before paying.</p>
    @endif
@else
    <p class="text-[11px] font-black text-action-ink mt-1"><i class="fas fa-circle-exclamation mr-1"></i>No payout account on file: ask them to add one in Settings.</p>
@endif
