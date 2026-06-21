<div class="p-6 rounded-2xl shadow-sm border transition-all 
            bg-white border-gray-200 
            dark:bg-gray-900 dark:border-gray-800 dark:shadow-none">
    
    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex-1 text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-3 mb-2">
                <div class="p-2 bg-primary-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white uppercase tracking-tight">
                    VENTI<span class="text-[#F07F22]">Q</span> Mobile Scanner
                </h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Scan tickets at your event using any Android phone. Real-time attendance tracking on the go.
            </p>
        </div>

        <div class="flex-shrink-0 w-full md:w-auto">
            <a href="{{ asset('apk/ventiq-scanner.apk') }}" 
               download
               class="inline-flex items-center justify-center gap-2 w-full px-6 py-3 font-bold text-white transition-all rounded-xl shadow-md bg-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Download App
            </a>
        </div>
    </div>
</div>