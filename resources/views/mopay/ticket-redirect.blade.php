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
        <p class="text-sm font-bold text-gray-600 uppercase tracking-widest">Securing your ticket...</p>
        <p class="text-xs text-gray-400">You'll be redirected to complete payment</p>
    </div>

    <form id="mopay-form" method="POST" action="{{ $postUrl }}">
        @csrf
        <input type="hidden" name="ticket_id" value="{{ $ticketId }}">
    </form>

    <script>
        document.getElementById('mopay-form').submit();
    </script>
</body>
</html>