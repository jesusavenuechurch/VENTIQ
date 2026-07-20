<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\Api\TicketScanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\Api\VoucherScanController;
use App\Http\Controllers\Api\WorkshopController;


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
    Route::post('/scanner/voucher/lookup', [VoucherScanController::class, 'lookup']);
    Route::post('/scanner/voucher/checkin', [VoucherScanController::class, 'checkin']);
    Route::get('/workshop/ticket/{code}', [WorkshopController::class, 'ticketDetails']);
    Route::post('/workshop/ticket/{ticketId}/sign', [WorkshopController::class, 'saveSignature']);
    Route::patch('/workshop/ticket/{ticketId}/details', [WorkshopController::class, 'updateDetails']);
    Route::post('/workshop/ticket/{ticketId}/signature-status', [WorkshopController::class, 'updateSignatureStatus']);
    Route::get('/workshop/event/{eventId}/summary', [WorkshopController::class, 'eventSummary']);
});

// WhatsApp webhook
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook'])
    ->name('whatsapp.webhook');