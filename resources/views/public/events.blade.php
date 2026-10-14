<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - {{ $organization->name }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    @if($organization->logo)
                        <img src="{{ Storage::url($organization->logo) }}" alt="{{ $organization->name }}" class="h-16 w-auto">
                    @endif
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ $organization->name }}</h1>
                        @if($organization->tagline)
                            <p class="text-gray-600 mt-1">{{ $organization->tagline }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Upcoming Events</h2>
            <p class="text-gray-600">Browse and register for our upcoming events</p>
        </div>

        @if($events->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($events as $event)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <!-- Event Image -->
                        @if($event->banner_image)
                            <div class="h-48 overflow-hidden">
                                <img src="{{ \App\Support\Thumb::url($event->banner_image, 480) }}" alt="{{ $event->name }}" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="h-48 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-white text-6xl opacity-50"></i>
                            </div>
                        @endif

                        <!-- Event Content -->
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $event->name }}</h3>
                            
                            @if($event->tagline)
                                <p class="text-gray-600 text-sm mb-4">{{ $event->tagline }}</p>
                            @endif

                            <!-- Event Details -->
                            <div class="space-y-2 mb-4">
                                @if($event->event_date)
                                    <div class="flex items-center text-sm text-gray-700">
                                        <i class="fas fa-calendar text-blue-600 w-5"></i>
                                        <span class="ml-2">{{ $event->event_date->format('M j, Y') }}</span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-700">
                                        <i class="fas fa-clock text-blue-600 w-5"></i>
                                        <span class="ml-2">{{ $event->event_date->format('g:i A') }}</span>
                                    </div>
                                @endif

                                @if($event->venue)
                                    <div class="flex items-center text-sm text-gray-700">
                                        <i class="fas fa-map-marker-alt text-red-600 w-5"></i>
                                        <span class="ml-2">{{ $event->venue }}</span>
                                    </div>
                                @endif

                                <!-- Ticket Price Range -->
                                @php
                                    $minPrice = $event->tiers->min('price');
                                    $maxPrice = $event->tiers->max('price');
                                @endphp
                                @if($event->tiers->count() > 0)
                                    <div class="flex items-center text-sm text-gray-700">
                                        <i class="fas fa-ticket-alt text-green-600 w-5"></i>
                                        <span class="ml-2">
                                            @if($minPrice == 0)
                                                Free
                                            @elseif($minPrice == $maxPrice)
                                                {{ number_format($minPrice) }} LSL
                                            @else
                                                {{ number_format($minPrice) }} - {{ number_format($maxPrice) }} LSL
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Description Preview -->
                            @if($event->description)
                                <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                                    {{ Str::limit($event->description, 120) }}
                                </p>
                            @endif

                            <!-- View Event Button -->
                            <a 
                                href="{{ route('public.event', ['orgSlug' => $organization->slug, 'eventSlug' => $event->slug]) }}" 
                                class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors"
                            >
                                View Event & Register
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- No Events -->
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-calendar-times text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Upcoming Events</h3>
                <p class="text-gray-600">Check back soon for new events!</p>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p>&copy; {{ date('Y') }} {{ $organization->name }}. All rights reserved.</p>
                @if($organization->contact_email)
                    <p class="mt-2 text-gray-400">
                        Contact: <a href="mailto:{{ $organization->contact_email }}" class="text-blue-400 hover:text-blue-300">{{ $organization->contact_email }}</a>
                    </p>
                @endif
            </div>
        </div>
    </footer>
@include('partials.cookie-notice')
</body>
</html>