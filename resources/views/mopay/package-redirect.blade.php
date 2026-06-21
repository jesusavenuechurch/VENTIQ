<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redirecting to payment...</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="text-center space-y-4">
        <div class="animate-spin rounded-full h-10 w-10 border-4 border-[#F07F22] border-t-transparent mx-auto"></div>
        <p class="text-sm font-bold text-gray-600 uppercase tracking-widest">Redirecting to payment...</p>
    </div>

    {{-- Auto-submit POST form to MoPay initiate endpoint --}}
    <form id="mopay-form" method="POST" action="{{ $postUrl }}">
        @csrf
        <input type="hidden" name="package_type"    value="{{ $packageType }}">
        <input type="hidden" name="organization_id" value="{{ $organizationId }}">
    </form>

    <script>
        document.getElementById('mopay-form').submit();
    </script>
</body>
</html>