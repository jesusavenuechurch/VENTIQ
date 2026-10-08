<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Sign in | VENTIQ</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center p-4">
    <h1 class="text-3xl font-black tracking-tighter text-[#1D4069] uppercase mb-8">VENTI<span class="text-[#F07F22]">Q.</span></h1>

    <div class="w-full max-w-md bg-white rounded-[2.5rem] shadow-2xl p-8 md:p-12 text-center">
        @if($login)
            {{-- A tap, not an automatic sign-in: mail scanners open links
                 before people do and would use this one up. --}}
            <p class="text-[15px] font-black text-[#1D4069]">Sign in as {{ $login->user->email }}?</p>
            <p class="text-[12px] text-gray-500 mt-2">You'll stay signed in on this device.</p>
            <form method="POST" action="{{ route('login.link.use', [$id, $token]) }}" class="mt-6">
                @csrf
                <button class="w-full py-5 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white font-black text-[10px] uppercase tracking-[0.3em]">Sign in</button>
            </form>
        @else
            <p class="text-[15px] font-black text-[#1D4069]">This sign-in link no longer works</p>
            <p class="text-[12px] text-gray-500 mt-2">Links work once, for 10 minutes. Ask for a new one from the sign-in page.</p>
            <a href="{{ route('login') }}" class="mt-6 inline-block w-full py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em]">Go to sign in</a>
        @endif
    </div>
</body>
</html>
