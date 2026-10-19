<?php
// app/Services/Payments/Contracts/PaymentDriver.php
namespace App\Services\Payments\Contracts;

use App\Models\PaymentSession;
use App\Services\Payments\DTOs\PaymentInitiationData;
use App\Services\Payments\DTOs\PaymentInitiationResult;
use App\Services\Payments\DTOs\PaymentStatusResult;
use Illuminate\Http\Request;

interface PaymentDriver
{
    public function initiate(PaymentInitiationData $data): PaymentInitiationResult;
    public function handleCallback(Request $request, PaymentSession $session): PaymentStatusResult;
}