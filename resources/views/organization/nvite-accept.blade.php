<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ $invite->organization->name }} | VENTIQ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>[x-cloak]{display:none!important} body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <span class="inline-block px-3 py-1 rounded-full bg-blue-50 text-[#1D4069] text-[10px] font-black uppercase tracking-widest mb-4">
                You've been invited
            </span>
            <h1 class="text-3xl font-black tracking-tighter text-[#1D4069] uppercase">
                VENTI<span class="text-[#F07F22]">Q.</span>
            </h1>
            <p class="text-[12px] font-bold text-gray-500 mt-3">
                Join <span class="text-[#1D4069]">{{ $invite->organization->name }}</span>'s workspace
            </p>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-2xl border border-white p-8 md:p-10"
             x-data="{
                loading: false,
                name: '',
                password: '',
                passwordConfirmation: '',
             }">

            @if($errors->any())
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-bold uppercase tracking-widest border border-red-100">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('organization.invite.submit', $invite->token) }}"
                  @submit="loading = true" class="space-y-4">
                @csrf

                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Email</label>
                    <input type="email" value="{{ $invite->email }}" disabled
                        class="w-full bg-gray-100 border-none rounded-2xl px-5 py-4 font-bold text-gray-400 outline-none">
                </div>

                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Your Name</label>
                    <input type="text" name="name" x-model="name" required
                        class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                </div>

                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Password</label>
                    <input type="password" name="password" x-model="password" required
                        class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                </div>

                <div>
                    <label class="text-[9px] font-black text-gray-400 uppercase ml-1 mb-1 block">Confirm Password</label>
                    <input type="password" name="password_confirmation" x-model="passwordConfirmation" required
                        class="w-full bg-gray-50 border-none rounded-2xl px-5 py-4 font-bold outline-none focus:ring-2 focus:ring-[#F07F22]/20">
                </div>

                <button type="submit" :disabled="loading"
                    class="w-full py-5 rounded-2xl bg-[#1D4069] text-white font-black text-[10px] uppercase tracking-[0.3em] shadow-lg flex items-center justify-center mt-2">
                    <span x-show="!loading">Join {{ $invite->organization->name }} 🚀</span>
                    <i x-show="loading" class="fas fa-spinner animate-spin"></i>
                </button>
            </form>
        </div>
    </div>

</body>
</html>