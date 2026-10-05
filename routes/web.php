<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketDownloadController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\EventsBrowseController;
use App\Http\Controllers\InstallmentController;
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
    return redirect()->to(\App\Support\IntentRedirect::resolve('host'));
})->middleware(['auth', 'signed'])->name('verification.verify');

// 2. The page users see until they verify (after signing up, or when a
//    page that needs a verified email sends them here)
Route::get('/email/verify', function (Illuminate\Http\Request $request) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->to(\App\Support\IntentRedirect::resolve('host'));
    }
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::post('/email/verification-notification', function (Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

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


Route::get('/pricing', function () {
    return view('public.pricing');
})->name('pricing');


Route::get('/access', function () {
    return view('public.org-admin');
})->name('access');

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

// Event PDF reports: the controller limits each to the event's own
// organization (or a super admin).
Route::middleware(['auth'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/revenue/{event}', [App\Http\Controllers\EventReportController::class, 'revenue'])->name('revenue');
    Route::get('/attendance/{event}', [App\Http\Controllers\EventReportController::class, 'attendance'])->name('attendance');
    Route::get('/attendance/{event}/excel', [App\Http\Controllers\EventReportController::class, 'attendanceExcel'])->name('attendance-excel');
    Route::get('/registration-summary/{event}', [App\Http\Controllers\EventReportController::class, 'registrationSummary'])->name('registration-summary');
});

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
    Route::delete('/{session}/segments/{segment}', [SessionSegmentController::class, 'destroy'])->name('segments.destroy');
    Route::post('/{session}/segments/{segment}/log', [SessionSegmentController::class, 'log'])->name('segments.log');
    Route::post('/{session}/segments/{segment}/log/undo', [SessionSegmentController::class, 'undoLog'])->name('segments.log.undo');
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
});

Route::get('/join', [PublicSessionCheckinController::class, 'join'])->name('public.session-join');
Route::get('/checkin/{token}', [PublicSessionCheckinController::class, 'show'])->name('public.session-checkin.form');
Route::post('/checkin/{token}', [PublicSessionCheckinController::class, 'store'])->name('public.session-checkin.submit');

Route::middleware(['auth'])->prefix('organization')->name('organization.')->group(function () {
    Route::get('/members', fn () => redirect()->route('organizer.team.index'))->name('members');
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
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.submit');
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/login/start', [App\Http\Controllers\Auth\EmailLoginController::class, 'start'])->name('login.start');
    Route::post('/login/code', [App\Http\Controllers\Auth\EmailLoginController::class, 'send'])->name('login.code.send');
    Route::post('/login/code/verify', [App\Http\Controllers\Auth\EmailLoginController::class, 'verify'])->name('login.code.verify');
    Route::get('/login/link/{id}/{token}', [App\Http\Controllers\Auth\EmailLoginController::class, 'showLink'])->whereNumber('id')->name('login.link.show');
    Route::post('/login/link/{id}/{token}', [App\Http\Controllers\Auth\EmailLoginController::class, 'useLink'])->whereNumber('id')->name('login.link.use');
});
Route::post('/logout', [App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('logout');

// ── Organizer area ───────────────────────────────────────────────
// Where org users land: their events and the payments waiting on them.
// Super admins reach it by picking an organization in Filament.
Route::middleware(['auth', 'verified', 'organizer', 'organizer.setup'])->prefix('organizer')->name('organizer.')->group(function () {
    Route::get('/setup', [App\Http\Controllers\Organizer\SetupController::class, 'show'])->name('setup');
    Route::post('/setup', [App\Http\Controllers\Organizer\SetupController::class, 'store'])->name('setup.store');
    Route::get('/', [App\Http\Controllers\Organizer\HomeController::class, 'index'])->name('home');
    Route::get('/payments', [App\Http\Controllers\Organizer\PaymentsController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}', [App\Http\Controllers\Organizer\PaymentsController::class, 'decide'])->name('payments.decide');
    Route::get('/events/create', [App\Http\Controllers\Organizer\EventsController::class, 'create'])->middleware('can:create_event')->name('events.create');
    Route::post('/events', [App\Http\Controllers\Organizer\EventsController::class, 'store'])->middleware('can:create_event')->name('events.store');
    Route::get('/events/{event}/edit', [App\Http\Controllers\Organizer\EventsController::class, 'edit'])->middleware('can:edit_event')->name('events.edit');
    Route::put('/events/{event}', [App\Http\Controllers\Organizer\EventsController::class, 'update'])->middleware('can:edit_event')->name('events.update');
    Route::get('/events/{event}/qr.svg', [App\Http\Controllers\Organizer\EventsController::class, 'qr'])->name('events.qr');
    Route::get('/events/{event}/attendees', [App\Http\Controllers\Organizer\AttendeesController::class, 'index'])->name('events.attendees');
    Route::post('/events/{event}/fee-sponsorship', [App\Http\Controllers\Organizer\AttendeesController::class, 'toggleFeeSponsorship'])->name('events.fee-sponsorship');

    Route::get('/payment-accounts', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'index'])->middleware('can:view_payment_method')->name('accounts.index');
    Route::post('/payment-accounts', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'store'])->middleware('can:create_payment_method')->name('accounts.store');
    Route::put('/payment-accounts/{account}', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'update'])->middleware('can:edit_payment_method')->name('accounts.update');
    Route::post('/payment-accounts/{account}/default', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'makeDefault'])->middleware('can:edit_payment_method')->name('accounts.default');
    Route::post('/payment-accounts/{account}/toggle', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'toggle'])->middleware('can:edit_payment_method')->name('accounts.toggle');
    Route::delete('/payment-accounts/{account}', [App\Http\Controllers\Organizer\PaymentAccountsController::class, 'destroy'])->middleware('can:delete_payment_method')->name('accounts.destroy');
    Route::get('/team', [App\Http\Controllers\Organizer\TeamController::class, 'index'])->name('team.index');
    Route::middleware('can:manage_staff')->group(function () {
        Route::post('/team/invites', [App\Http\Controllers\Organizer\TeamController::class, 'invite'])->name('team.invite');
        Route::delete('/team/invites/{invite}', [App\Http\Controllers\Organizer\TeamController::class, 'revokeInvite'])->name('team.invite.revoke');
        Route::put('/team/{member}/role', [App\Http\Controllers\Organizer\TeamController::class, 'changeRole'])->name('team.role');
        Route::delete('/team/{member}', [App\Http\Controllers\Organizer\TeamController::class, 'remove'])->name('team.remove');
    });
    Route::get('/events/{event}/day', [App\Http\Controllers\Organizer\EventDayController::class, 'show'])->name('events.day');
    Route::get('/events/{event}/comp', [App\Http\Controllers\Organizer\TicketsController::class, 'createComp'])->middleware('can:approve_payment')->name('events.comp.create');
    Route::post('/events/{event}/comp', [App\Http\Controllers\Organizer\TicketsController::class, 'storeComp'])->middleware('can:approve_payment')->name('events.comp.store');
    Route::post('/tickets/{ticket}/resend', [App\Http\Controllers\Organizer\TicketsController::class, 'resend'])->middleware('throttle:20,1')->name('tickets.resend');
    Route::post('/tickets/{ticket}/reinstate', [App\Http\Controllers\Organizer\AttendeesController::class, 'reinstate'])->name('tickets.reinstate');
    Route::post('/tickets/{ticket}/cancel', [App\Http\Controllers\Organizer\AttendeesController::class, 'cancel'])->name('tickets.cancel');
});

Route::middleware(['auth'])->prefix('organizer/act-as')->name('organizer.act-as.')->group(function () {
    Route::get('/{organization}', [App\Http\Controllers\Organizer\ActAsController::class, 'start'])->name('start');
    Route::post('/exit', [App\Http\Controllers\Organizer\ActAsController::class, 'stop'])->name('stop');
});

// The organizer's one-tap review link (email / WhatsApp). The signature
// is the credential, so no login; it expires after a week.
Route::middleware('signed')->group(function () {
    Route::get('/payment-review/{payment}', [App\Http\Controllers\PaymentReviewController::class, 'show'])->name('payment-review.show');
    Route::post('/payment-review/{payment}', [App\Http\Controllers\PaymentReviewController::class, 'decide'])->name('payment-review.decide');
});

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