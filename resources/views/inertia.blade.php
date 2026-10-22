<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($noindex ?? false)<meta name="robots" content="noindex">@endif
    {{-- Link previews (WhatsApp, Facebook) read the HTML the server sends,
         not what React renders, so a page's preview is set here from its
         `meta` prop. --}}
    @php($meta = $page['props']['meta'] ?? [])
    <title inertia>{{ $meta['title'] ?? 'VENTIQ | Event Operations & Access Management' }}</title>
    <meta name="description" content="{{ $meta['description'] ?? 'The modern gateway for workshops, events, and seamless registrations in Lesotho.' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->full() }}">
    <meta property="og:title" content="{{ $meta['title'] ?? 'VENTIQ' }}">
    <meta property="og:description" content="{{ $meta['description'] ?? 'Events in Lesotho, simply connected.' }}">
    <meta property="og:image" content="{{ $meta['image'] ?? asset('images/meta.jpeg') }}">
    <meta name="twitter:card" content="summary_large_image">
    @routes
    @viteReactRefresh
    @vite(['resources/css/inertia.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    {{-- The site's loading screen (and, on a browser's first visit, the
         Lumela greeting): on full page loads of pages inside the site
         layout, not attendee pages. Moving between React pages never
         shows it again. --}}
    @php($withLoader = !\Illuminate\Support\Str::startsWith($page['component'], ['Tickets/', 'Events/', 'Public/', 'Sessions/Show', 'Sessions/CheckinPass']))
    @if($withLoader)
        @include('partials.page-loader')
    @endif
    @inertia
</body>
</html>
