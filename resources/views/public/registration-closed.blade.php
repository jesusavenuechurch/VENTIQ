<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Closed - {{ $event->name }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center space-x-4">
                @if($organization->logo)
                    <img src="{{ Storage::url($organization->logo) }}" alt="{{ $organization->name }}" class="h-12 w-auto">
                @endif
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $organization->name }}</h1>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <!-- Icon -->
            <div class="mb-6">
                <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-lock text-red-600 text-5xl"></i>
                </div>
            </div>

            <!-- Title -->
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Registration Closed</h2>
            
            <p class="text-lg text-gray-600 mb-8">
                {{ $closedReason ?? 'Sorry, registration for this event is no longer available.' }}
            </p>

            <!-- Event Info -->
            <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left max-w-md mx-auto">
                <h3 class="font-bold text-gray-900 mb-3">{{ $event->name }}</h3>
                


                @if($event->event_date)
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-calendar text-blue-600 mr-2"></i>
                        Event date: {{ $event->event_date->format('F j, Y \a\t g:i A') }}
                    </p>
                @endif
            </div>

            <!-- Contact Info -->
            @if($organization->contact_email)
                <div class="border-t pt-8">
                    <p class="text-gray-700 mb-4">
                        Have questions or need assistance?
                    </p>
                    <a href="mailto:{{ $organization->contact_email }}" 
                       class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-semibold">
                        <i class="fas fa-envelope"></i>
                        Contact Us
                    </a>
                </div>
            @endif

            <!-- Back Button -->
            <div class="mt-8">
                <a href="{{ route('events.browse') }}" 
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    View Other Events
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; {{ date('Y') }} {{ $organization->name }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
@include('partials.cookie-notice')
</body>
</html>