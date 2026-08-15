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
use App\Livewire\Assist\ChatPage;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionSegmentController;
use App\Http\Controllers\PublicSessionCheckinController;
use App\Http\Controllers\SessionParticipantController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationInviteAcceptController;    
use App\Http\Controllers\ProgrammeController;
use App\Http\Controllers\PayLesothoController;
use App\Http\Controllers\SessionPlanController;
use App\Http\Controllers\CertificateController;

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


Route::get('/', [PublicEventController::class, 'index'])->name('home');

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

    // Payment screen (Screen 2) — online (PayLesotho) default, manual methods behind a toggle
    Route::get('/payment/{ticketId}', [RegistrationController::class, 'payment'])
        ->name('registration.payment');

    Route::post('/payment/{ticketId}/manual', [RegistrationController::class, 'submitManualPayment'])
        ->name('registration.payment.manual');
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

// ── PRIMARY GATEWAY: PayLesotho ──────────────────────────────────
Route::prefix('payment/paylesotho')->name('paylesotho.')->group(function () {
    Route::post('/ticket/initiate', [PayLesothoController::class, 'initiateTicketPayment'])
        ->name('ticket.initiate');
    Route::get('/status/{session}', [PayLesothoController::class, 'status'])
        ->name('status');
    Route::post('/callback/{method}', [PayLesothoController::class, 'callback'])
        ->name('callback');
});

Route::middleware(['auth'])->prefix('payment/paylesotho')->name('paylesotho.')->group(function () {
    Route::post('/package/initiate', [PayLesothoController::class, 'initiatePackagePayment'])
        ->name('package.initiate'); // kept only until packages are fully removed — see §5

    Route::post('/session-package/initiate', [PayLesothoController::class, 'initiateSessionPackagePayment'])
        ->name('session-package.initiate');
});

// ── FALLBACK GATEWAY: MoPay (kept, not removed) ──────────────────
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

Route::middleware(['auth'])->get('/assist/{conversation?}', ChatPage::class)->name('assist');
Route::get('/events/search', [PublicEventController::class, 'search'])->name('events.search');
Route::get('/events/upcoming', [PublicEventController::class, 'upcoming'])->name('events.upcoming');
Route::get('/events/discover', [PublicEventController::class, 'discover'])->name('events.discover');


Route::middleware(['auth'])->prefix('sessions')->name('sessions.')->group(function () {
    Route::get('/', [SessionController::class, 'index'])->name('index');
    Route::get('/create', [SessionController::class, 'create'])->name('create');
    Route::post('/', [SessionController::class, 'store'])->name('store');

    // MUST be above '/{session}' — otherwise Laravel tries to bind
    // "reports" as a Session ID and 404s before this line is ever reached.
    Route::get('/reports', [SessionController::class, 'reports'])->name('reports');

    Route::get('/{session}', [SessionController::class, 'show'])->name('show');
    Route::post('/{session}/start', [SessionController::class, 'start'])->name('start');
    Route::post('/{session}/segments', [SessionSegmentController::class, 'store'])->name('segments.store');
    Route::post('/{session}/segments/{segment}/log', [SessionSegmentController::class, 'log'])->name('segments.log');
    Route::post('/{session}/segments/{segment}/finish', [SessionSegmentController::class, 'finish'])->name('segments.finish');
    Route::post('/{session}/segments/{segment}/tag', [SessionSegmentController::class, 'tag'])->name('segments.tag');
    Route::get('/{session}/report', [SessionController::class, 'report'])->name('report');
    Route::post('/{session}/report/review', [SessionController::class, 'markReviewed'])->name('report.review');
    Route::patch('/{session}/report', [SessionController::class, 'updateReport'])->name('report.update');
    Route::get('/{session}/report/pdf', [SessionController::class, 'reportPdf'])->name('report.pdf');
    Route::get('/{session}/report/status', [SessionController::class, 'reportStatus'])->name('report.status');
    Route::post('/{session}/report/generate', [SessionController::class, 'generateReport'])->name('report.generate');
    Route::get('/{session}/checkin', [SessionParticipantController::class, 'index'])->name('checkin');
    Route::post('/{session}/checkin', [SessionParticipantController::class, 'store'])->name('checkin.store');
    Route::get('/{session}/checkin-qr.png', [SessionController::class, 'checkinQr'])->name('checkin.qr');
    Route::get('/{session}/checkin-pass', [SessionController::class, 'checkinPass'])->name('checkin.pass');
    Route::get('/{session}/checkin-pass.pdf', [SessionController::class, 'checkinPassPdf'])->name('checkin.pass.pdf');
    Route::get('/{session}/participants/count', [SessionController::class, 'participantsCount'])->name('participants.count');
    Route::post('/{session}/segments/{segment}/pause', [SessionSegmentController::class, 'pause'])->name('segments.pause');
    Route::post('/{session}/segments/{segment}/resume', [SessionSegmentController::class, 'resume'])->name('segments.resume');
    Route::patch('/{session}/checkin/{participant}', [SessionParticipantController::class, 'update'])->name('checkin.update');
    Route::get('/{session}/checkin/pdf', [SessionParticipantController::class, 'exportPdf'])->name('checkin.pdf');
    Route::get('/{session}/participants/{participant}/card', [SessionParticipantController::class, 'card'])->name('checkin.card');
    Route::get('/{session}/participants/{participant}/card.pdf', [SessionParticipantController::class, 'cardPdf'])->name('checkin.card.pdf');
});

Route::get('/join', [PublicSessionCheckinController::class, 'join'])->name('public.session-join');
Route::get('/checkin/{token}', [PublicSessionCheckinController::class, 'show'])->name('public.session-checkin.form');
Route::post('/checkin/{token}', [PublicSessionCheckinController::class, 'store'])->name('public.session-checkin.submit');

Route::middleware(['auth'])->prefix('organization')->name('organization.')->group(function () {
    Route::get('/members', [OrganizationMemberController::class, 'index'])->name('members');
    Route::post('/invite', [OrganizationMemberController::class, 'invite'])->name('invite.store');
    Route::delete('/invite/{invite}', [OrganizationMemberController::class, 'revoke'])->name('invite.revoke');
    Route::get('/session-plan/payment', [SessionPlanController::class, 'showPayment'])->name('session-plan.payment');
});
 
// Public — no auth, the invite token itself is the credential
Route::get('/invite/{token}', [OrganizationInviteAcceptController::class, 'show'])->name('organization.invite.show');
Route::post('/invite/{token}', [OrganizationInviteAcceptController::class, 'submit'])->name('organization.invite.submit');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'store'])->name('login.submit');
Route::post('/logout', [App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('logout');

Route::middleware(['auth'])->prefix('programmes')->name('programmes.')->group(function () {
    Route::get('/', [ProgrammeController::class, 'index'])->name('index');
    Route::get('/create', [ProgrammeController::class, 'create'])->name('create');
    Route::post('/', [ProgrammeController::class, 'store'])->name('store');
    Route::get('/{programme}', [ProgrammeController::class, 'show'])->name('show');
    Route::post('/{programme}/certificates', [ProgrammeController::class, 'issueCertificates'])->name('certificates.issue');
    Route::post('/{programme}/report/generate', [ProgrammeController::class, 'generateReport'])->name('report.generate');
    Route::get('/{programme}/report', [ProgrammeController::class, 'report'])->name('report');
    Route::get('/{programme}/report/status', [ProgrammeController::class, 'reportStatus'])->name('report.status');
    Route::get('/{programme}/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
});

// Public — the token itself is the credential, same pattern as the session
// check-in and organization invite links above. The lookup form comes
// first so a plain "/certify" doesn't get swallowed by the {token} route.
Route::get('/certify', [CertificateController::class, 'lookup'])->name('certificates.lookup');
Route::get('/certify/{token}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/certify/{token}/download', [CertificateController::class, 'downloadPublic'])->name('certificates.download.public');