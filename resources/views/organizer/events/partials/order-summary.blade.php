{{-- The order: worked out live from the numbers above (form's x-data `order`). --}}
<div class="p-5 rounded-2xl bg-white border border-gray-100" x-show="order.people > 0">
    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">If you sell all of them</p>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <dt class="text-[11px] font-bold text-gray-500">Expected attendance</dt>
            <dd class="text-lg font-black text-[#1D4069]" x-text="order.people.toLocaleString('en-US') + (order.people === 1 ? ' person' : ' people')"></dd>
        </div>
        <div x-show="mode === 'paid'">
            <dt class="text-[11px] font-bold text-gray-500">Ticket sales</dt>
            <dd class="text-lg font-black text-[#1D4069]" x-text="money(order.sales)"></dd>
        </div>
        <div>
            <dt class="text-[11px] font-bold text-gray-500">VENTIQ fees</dt>
            <dd class="text-lg font-black text-[#1D4069]" x-text="fees.sponsored ? 'Sponsored' : money(order.fees)"></dd>
        </div>
        <div x-show="mode === 'paid'">
            <dt class="text-[11px] font-bold text-gray-500">You keep</dt>
            <dd class="text-lg font-black text-mint-ink" x-text="money(order.keep)"></dd>
        </div>
    </dl>
    <p class="mt-3 text-[12px] font-medium text-gray-500">
        Fees are charged on the tickets actually issued, so you pay for what you sell. We'll let you know if a ticket type sells out, and you can allow more tickets any time.
    </p>
</div>
