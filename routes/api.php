<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\PartnerVerificationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\Api\TicketScanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\WhatsAppController;

// Partner routes
Route::get('/partner/download', [PartnerVerificationController::class, 'downloadPartners']);
Route::get('/partner/verify/{id}', [PartnerVerificationController::class, 'verifyPartner']);
Route::get('/partner/sync-status', [PartnerVerificationController::class, 'syncStatus']);

// Check-in routes
Route::post('/checkin/bulk', [CheckInController::class, 'bulkCheckIn']);
Route::post('/checkin', [CheckInController::class, 'checkIn']);
Route::get('/checkins', [CheckInController::class, 'index']);
Route::get('/checkins/stats', [CheckInController::class, 'stats']);
Route::get('/checkins/status/{partnerId}', [CheckInController::class, 'checkStatus']);

// ===== MOBILE APP AUTHENTICATION (NO AUTH REQUIRED) =====
Route::post('/auth/login', [AuthController::class, 'login']);

// ===== MOBILE APP PROTECTED ROUTES =====
Route::middleware('auth:sanctum')->group(function () {
    // Auth endpoints
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Scanner endpoints
    Route::get('/scanner/events', [TicketScanController::class, 'getEvents']);
    Route::get('/scanner/tickets/{eventId}', [TicketScanController::class, 'downloadTickets']);
    Route::get('/scanner/verify/{qrCode}', [TicketScanController::class, 'verifyTicket']);
    Route::post('/scanner/checkin/bulk', [TicketScanController::class, 'bulkCheckIn']);
    Route::get('/scanner/stats/{eventId}', [TicketScanController::class, 'getStats']);
    Route::get('/scanner/sync-status', [TicketScanController::class, 'syncStatus']);
});

// WhatsApp webhook
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook'])
    ->name('whatsapp.webhook');

// WhatsApp webhook verification (Meta calls this once when you click "Verify and Save")
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verifyWebhook'])
    ->name('whatsapp.webhook.verify');