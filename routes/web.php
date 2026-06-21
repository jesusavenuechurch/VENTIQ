<?php
use Illuminate\Support\Facades\Route;
use App\Models\Partner;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\PartnerVerificationController;
use App\Http\Controllers\TicketDownloadController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\EventsBrowseController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\WhatsAppController;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use App\Http\Controllers\AgentRegistrationController;
use App\Http\Controllers\AgentApplicationController;
use App\Http\Controllers\ContactInquiryController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Models\Organization;
use App\Http\Controllers\MopayController;

Route::post('/contact', [ContactInquiryController::class, 'store'])->name('contact.store');

Route::get('/sitemap.xml', function () {
    $sitemap = Sitemap::create()
        ->add(Url::create('/')
            ->setLastModificationDate(now())
            ->setPriority(1.0)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
        ->add(Url::create('/pricing')
            ->setLastModificationDate(now())
            ->setPriority(0.8)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
        ->add(Url::create('/events')
            ->setLastModificationDate(now())
            ->setPriority(0.9)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
    
    // Add all public events dynamically (without is_published check)
    try {
        $organizations = \App\Models\Organization::with('events')->get();
        
        foreach ($organizations as $org) {
            if ($org->events) {
                foreach ($org->events as $event) {
                    $sitemap->add(
                        Url::create("/org/{$org->slug}/event/{$event->slug}")
                            ->setLastModificationDate($event->updated_at ?? now())
                            ->setPriority(0.9)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    );
                }
            }
        }
    } catch (\Exception $e) {
        // Log error but still return sitemap with base URLs
        \Log::error('Sitemap generation error: ' . $e->getMessage());
    }
    
    return $sitemap->toResponse(request());
})->name('sitemap');


Route::get('/', function () {
    $activeOrgs = Organization::where('is_active', true)->count();
    return view('welcome', compact('activeOrgs'));
});

Route::view('/about', 'public.about')->name('about');
// 1. The handler for the email link (Fixes your 'verification.verify' error)
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/admin'); 
})->middleware(['auth', 'signed'])->name('verification.verify');

// 2. The page users see if they try to access /admin without verifying first
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Redirect the standard login route to your Filament login page
Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::get('/events', [PublicEventController::class, 'browseAll'])
    ->name('events.browse');

// Ticket Routes
Route::get('/ticket/{qr_code}', [TicketDownloadController::class, 'show'])->name('ticket.download');
Route::post('/ticket/{qr_code}/update-preference', [TicketDownloadController::class, 'updatePreference'])->name('ticket.update-preference');
Route::get('/ticket/{qr_code}/download', [TicketDownloadController::class, 'download'])->name('ticket.avatar.download');

// Organization & Event Routes
Route::prefix('org/{orgSlug}')->group(function () {
    // List all public events for an organization
    Route::get('/events', [PublicEventController::class, 'listEvents'])
        ->name('public.events');
    
    // View specific event
    Route::get('/event/{eventSlug}', [PublicEventController::class, 'show'])
        ->name('public.event');
});

// Optional: Short URL format
Route::get('/e/{orgSlug}/{eventSlug}', [PublicEventController::class, 'show'])
    ->name('event.short');

// Registration Routes
Route::prefix('register/{orgSlug}/{eventSlug}')->group(function () {
    // Show registration form
    Route::get('/', [RegistrationController::class, 'showForm'])
        ->name('registration.form');
    
    // Submit registration
    Route::post('/', [RegistrationController::class, 'register'])
        ->name('registration.submit');
    
    // Confirmation page
    Route::get('/confirmation/{ticketId}', [RegistrationController::class, 'confirmation'])
        ->name('registration.confirmation');
});

// Registration Error Page
Route::get('/{orgSlug}/{eventSlug}/register/error', function ($orgSlug, $eventSlug) {
    $organization = \App\Models\Organization::where('slug', $orgSlug)->firstOrFail();
    $event = \App\Models\Event::where('slug', $eventSlug)
        ->where('organization_id', $organization->id)
        ->firstOrFail();

    $error = session('error', 'Registration failed. Please try again.');
    $retryUrl = route('registration.form', ['orgSlug' => $orgSlug, 'eventSlug' => $eventSlug]); // ✅ Fixed

    return view('public.registration-error', compact('organization', 'event', 'error', 'retryUrl'));
})->name('registration.error');

// Installment payment routes
Route::prefix('installment')->name('installment.')->group(function () {
    Route::get('/search', [InstallmentController::class, 'search'])->name('search');
    Route::post('/find', [InstallmentController::class, 'find'])->name('find');
    Route::get('/{ticket}', [InstallmentController::class, 'show'])->name('show');
    Route::post('/{ticket}/pay', [InstallmentController::class, 'pay'])->name('pay');
});

Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook'])
    ->name('whatsapp.webhook');

Route::get('/pricing', function () {
    return view('public.pricing');
})->name('pricing');


Route::get('/access', function () {
    return view('public.org-admin');
})->name('pricing');

// Direct organization registration (NO agent token)
Route::get('/org/register', [AgentRegistrationController::class, 'showForm'])
    ->name('org.register.direct');

Route::post('/org/register', [AgentRegistrationController::class, 'submit'])
    ->name('org.register.submit');

// Agent referral registration (WITH agent token)
Route::get('/org/register/{token}', [AgentRegistrationController::class, 'showForm'])
    ->name('agent.registration.form');

Route::post('/org/register/{token}', [AgentRegistrationController::class, 'submit'])
    ->name('agent.registration.submit');

// Success page (shared by both)
Route::get('/org/registration-success', [AgentRegistrationController::class, 'success'])
    ->name('agent.registration.success');

Route::get('/become-agent', [AgentApplicationController::class, 'showForm'])->name('agent.apply');
Route::post('/become-agent', [AgentApplicationController::class, 'submit'])->name('agent.submit');
Route::get('/reset-password/{token}', [AgentApplicationController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [AgentApplicationController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get(
        '/organizational-records/{organizationalRecord}/pdf',
        [\App\Http\Controllers\OrganizationalRecordPdfController::class, 'download']
    )->name('organizational-records.pdf');
});

Route::get('/reports/revenue/{event}', function (App\Models\Event $event) {
    return (new App\Services\Reports\RevenueReportService(
        $event->load(['organization', 'tiers', 'tickets.payments', 'tickets.tier'])
    ))->downloadPdf();
})->middleware(['auth'])->name('reports.revenue');

Route::get('/reports/attendance/{event}', function (App\Models\Event $event) {
    return (new App\Services\Reports\AttendanceReportService(
        $event->load(['organization', 'tiers', 'tickets.client', 'tickets.tier', 'tickets.workshopDetail'])
    ))->downloadPdf();
})->middleware(['auth'])->name('reports.attendance');

Route::get('/reports/registration-summary/{event}', function (App\Models\Event $event) {
    return (new App\Services\Reports\RegistrationSummaryService(
        $event->load(['organization', 'tiers', 'tickets.tier'])
    ))->downloadPdf();
})->middleware(['auth'])->name('reports.registration-summary');

// Replace the existing mopay routes with these names
Route::middleware(['auth'])->prefix('payment')->name('online-payment.')->group(function () {
    Route::get('/package/initiate', [MopayController::class, 'initiatePackagePayment'])
        ->name('package.initiate');
});

Route::get('/payment/ticket/initiate', [MopayController::class, 'initiateTicketPayment'])
    ->name('online-payment.ticket.initiate');
        
Route::get('/payment/package/callback', [MopayController::class, 'packageCallback'])
    ->name('online-payment.package.callback');

Route::get('/payment/ticket/callback', [MopayController::class, 'ticketCallback'])
    ->name('online-payment.ticket.callback');