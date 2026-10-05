<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - {{ $ticket->ticket_number }}</title>
    @vite('resources/css/app.css')
</head>
<body class="h-full flex flex-col bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm flex-shrink-0">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center space-x-4">
                @if($ticket->event->organization->logo)
                    <img src="{{ Storage::url($ticket->event->organization->logo) }}" 
                         alt="{{ $ticket->event->organization->name }}" 
                         class="h-12 w-auto">
                @endif
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $ticket->event->organization->name }}</h1>
                    <p class="text-sm text-gray-600">{{ $ticket->event->name }}</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Success Message -->
            @if (session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ session('success') }}
                </div>
            @endif

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Ticket Info & Payment History -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Ticket Details Card -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Ticket Details</h2>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">Ticket Number</p>
                                <p class="font-semibold text-gray-900">{{ $ticket->ticket_number }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Name</p>
                                <p class="font-semibold text-gray-900">{{ $ticket->client->full_name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Event</p>
                                <p class="font-semibold text-gray-900">{{ $ticket->event->name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Tier</p>
                                <p class="font-semibold text-gray-900">{{ $ticket->tier->tier_name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Progress Card -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Status</h2>
                        
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">
                                    {{ config('constants.currency.symbol') }} {{ number_format($ticket->amount_paid, 2) }} 
                                    of 
                                    {{ config('constants.currency.symbol') }} {{ number_format($ticket->amount, 2) }}
                                </span>
                                <span class="text-sm font-medium text-blue-600">
                                    {{ number_format($ticket->payment_progress, 1) }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-blue-600 h-3 rounded-full transition-all" 
                                     style="width: {{ $ticket->payment_progress }}%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="bg-green-50 rounded-lg p-3">
                                <p class="text-xs text-gray-600 mb-1">Paid</p>
                                <p class="text-lg font-bold text-green-600">
                                    {{ config('constants.currency.symbol') }} {{ number_format($ticket->amount_paid, 2) }}
                                </p>
                            </div>
                            <div class="bg-orange-50 rounded-lg p-3">
                                <p class="text-xs text-gray-600 mb-1">Remaining</p>
                                <p class="text-lg font-bold text-orange-600">
                                    {{ config('constants.currency.symbol') }} {{ number_format($ticket->remaining_amount, 2) }}
                                </p>
                            </div>
                            <div class="bg-blue-50 rounded-lg p-3">
                                <p class="text-xs text-gray-600 mb-1">Total</p>
                                <p class="text-lg font-bold text-blue-600">
                                    {{ config('constants.currency.symbol') }} {{ number_format($ticket->amount, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment History Card -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Payment History</h2>
                        
                        @if($ticket->payments->isEmpty())
                            <p class="text-gray-600 text-center py-4">No payments recorded yet</p>
                        @else
                            <div class="space-y-3">
                                @foreach($ticket->payments as $payment)
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center
                                                @if($payment->status === 'approved') bg-green-100
                                                @elseif($payment->status === 'pending') bg-yellow-100
                                                @else bg-red-100 @endif">
                                                <i class="fas 
                                                    @if($payment->status === 'approved') fa-check text-green-600
                                                    @elseif($payment->status === 'pending') fa-clock text-yellow-600
                                                    @else fa-times text-red-600 @endif"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900">
                                                    {{ config('constants.currency.symbol') }} {{ number_format($payment->amount, 2) }}
                                                </p>
                                                <p class="text-xs text-gray-600">
                                                    {{ $payment->payment_method_label }} 
                                                    @if($payment->payment_reference)
                                                        • {{ $payment->payment_reference }}
                                                    @endif
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ $payment->created_at->format('M j, Y @ g:i A') }}
                                                </p>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                            @if($payment->status === 'approved') bg-green-100 text-green-800
                                            @elseif($payment->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right Column: Make Payment Form -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Make Payment</h2>
                        
                        @if($ticket->remaining_amount > 0)
                            <form method="POST" action="{{ route('installment.pay', $ticket->id) }}">
                                @csrf

                                <div class="space-y-4">
                                    <!-- Amount -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Amount <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-3 text-gray-600">
                                                {{ config('constants.currency.symbol') }}
                                            </span>
                                            <input type="number" 
                                                   name="amount" 
                                                   step="0.01"
                                                   min="0.01"
                                                   max="{{ $ticket->remaining_amount }}"
                                                   value="{{ old('amount', $ticket->remaining_amount) }}"
                                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                   required>
                                        </div>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Maximum: {{ config('constants.currency.symbol') }} {{ number_format($ticket->remaining_amount, 2) }}
                                        </p>
                                    </div>

                                    <!-- Payment Method -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Payment Method <span class="text-red-500">*</span>
                                        </label>
                                        <select name="payment_method_id" 
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                required>
                                            <option value="">Select method</option>
                                            @foreach($paymentMethods as $method)
                                                @php
                                                    $config = config('constants.payment_methods.' . $method->payment_method, []);
                                                    $label = is_array($config) ? $config['label'] : $config;
                                                @endphp
                                                <option value="{{ $method->id }}" {{ old('payment_method_id') == $method->id ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Payment Reference -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Payment Reference
                                        </label>
                                        <input type="text" 
                                               name="payment_reference" 
                                               value="{{ old('payment_reference') }}"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="e.g., MPESA-ABC123">
                                    </div>

                                    <!-- Submit Button -->
                                    <button type="submit" 
                                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                                        Submit Payment
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="text-center py-6">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-check-circle text-green-600 text-3xl"></i>
                                </div>
                                <p class="text-lg font-semibold text-green-900 mb-1">Fully Paid!</p>
                                <p class="text-sm text-gray-600">No remaining balance</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white flex-shrink-0">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; {{ date('Y') }} {{ $ticket->event->organization->name }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
@include('partials.cookie-notice')
</body>
</html>