<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | payment_mode arrived in June 2026 with a default of 'free', so events made
 | before then that sell priced tickets are still marked free. The organizer
 | edit form then hides their ticket types (it only offers "How many people
 | are you expecting?"), so organizers can't change their numbers.
 | Registration already decides by each ticket's price, so this only fixes
 | the label: any event with a priced ticket type is paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->where('payment_mode', 'free')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('event_tiers')
                ->whereColumn('event_tiers.event_id', 'events.id')
                ->where('event_tiers.price', '>', 0))
            ->update(['payment_mode' => 'paid']);
    }

    public function down(): void
    {
        // Not reversible: which events were mislabelled isn't recorded.
    }
};
