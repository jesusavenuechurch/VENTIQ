<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Error - {{ $organization->name ?? 'Event Registration' }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center space-x-4">
                @if(isset($organization) && $organization->logo)
                    <img src="{{ Storage::url($organization->logo) }}" alt="{{ $organization->name }}" class="h-12 w-auto">
                @endif
                <div>
                    <h1 class="text-xl font-bold text-gray-900">{{ $organization->name ?? 'Event Registration' }}</h1>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8 text-center">
            <!-- Error Icon -->
            <div class="flex justify-center mb-6">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-4xl"></i>
                </div>
            </div>

            <!-- Error Title -->
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                Registration Failed
            </h2>

            <!-- Error Message -->
            <div class="bg-red-50 border-2 border-red-200 rounded-lg p-6 mb-8 text-left">
                <p class="text-lg font-semibold text-red-900 mb-2">
                    <i class="fas fa-times-circle mr-2"></i>
                    What went wrong:
                </p>
                <p class="text-red-800">
                    {{ $error ?? 'An unexpected error occurred while processing your registration. Please try again.' }}
                </p>
            </div>

            <!-- Common Issues -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8 text-left">
                <p class="font-semibold text-blue-900 mb-3">
                    <i class="fas fa-lightbulb mr-2"></i>
                    Common issues:
                </p>
                <ul class="space-y-2 text-sm text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mr-2 mt-0.5"></i>
                        <span>Make sure all required fields are filled correctly</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mr-2 mt-0.5"></i>
                        <span>Check that your email address is valid</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mr-2 mt-0.5"></i>
                        <span>Ensure your phone number is 8 digits (without +266)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mr-2 mt-0.5"></i>
                        <span>Verify you selected a payment method (for paid tickets)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-blue-600 mr-2 mt-0.5"></i>
                        <span>Check that the ticket tier is still available</span>
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ $retryUrl ?? url()->previous() }}" 
                   class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                    <i class="fas fa-redo mr-2"></i>
                    Try Again
                </a>
                
                @if(isset($organization) && isset($event))
                <a href="{{ route('public.event', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}" 
                   class="inline-flex items-center justify-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Event
                </a>
                @endif
            </div>

            <!-- Contact Support -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-gray-600 mb-4">
                    <i class="fas fa-question-circle text-gray-500 mr-2"></i>
                    Still having trouble?
                </p>
                @if(isset($organization))
                <div class="flex flex-col sm:flex-row gap-3 justify-center items-center text-sm">
                    @if($organization->phone)
                    <a href="tel:{{ $organization->phone }}" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-phone mr-1"></i>
                        {{ $organization->phone }}
                    </a>
                    @endif
                    
                    @if($organization->contact_email)
                    <a href="mailto:{{ $organization->contact_email }}" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-envelope mr-1"></i>
                        {{ $organization->contact_email }}
                    </a>
                    @endif
                </div>
                @else
                <p class="text-sm text-gray-600">
                    Contact the event organizer for assistance
                </p>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; {{ date('Y') }} {{ $organization->name ?? 'Event Registration' }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
@include('partials.cookie-notice')
</body>
</html>