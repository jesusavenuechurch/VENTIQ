// Alpine for the standalone pages (sign in, sign-in link) that don't run
// Livewire. Pages using layouts.app get Alpine from Livewire instead; never
// load both on one page.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
