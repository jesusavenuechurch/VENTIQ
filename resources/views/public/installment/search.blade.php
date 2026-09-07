<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Installment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="h-full flex flex-col bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm flex-shrink-0">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <h1 class="text-xl font-bold text-gray-900">Pay Installment</h1>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="bg-white rounded-lg shadow-md p-8">
                <!-- Icon -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-search text-blue-600 text-2xl"></i>
                    </div>
                </div>

                <!-- Title -->
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">
                    Find Your Ticket
                </h2>
                <p class="text-gray-600 text-center mb-8">
                    Enter your details to make an installment payment
                </p>

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

                <!-- Search Form -->
                <form method="POST" action="{{ route('installment.find') }}">
                    @csrf

                    <div class="space-y-6">
                        <!-- Phone Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 py-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-700 font-medium">
                                    +266
                                </span>
                                <input type="tel" 
                                       name="phone" 
                                       value="{{ old('phone') }}"
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="5949 4756"
                                       required>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                Phone number used during registration
                            </p>
                        </div>

                        <!-- Ticket Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Ticket Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   name="ticket_number" 
                                   value="{{ old('ticket_number') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., TKT-6-00001"
                                   required>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                Found in your registration confirmation
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>
                            Find My Ticket
                        </button>
                    </div>
                </form>

                <!-- Help Text -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <p class="text-sm text-gray-600 text-center mb-3">
                        <i class="fas fa-question-circle text-gray-500 mr-1"></i>
                        Don't have your ticket number?
                    </p>
                    <p class="text-xs text-gray-500 text-center">
                        Check your email or WhatsApp for your registration confirmation
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white flex-shrink-0">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; {{ date('Y') }} Event Ticketing. All rights reserved.</p>
            </div>
        </footer>
@include('partials.cookie-notice')
</body>
</html>