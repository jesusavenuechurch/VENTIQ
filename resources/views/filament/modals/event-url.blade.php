<div class="p-4">
    <div class="space-y-4">
        <!-- Event Info -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $event->name }}
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Public Event Page
                    </p>
                </div>
            </div>
        </div>

        <!-- URL Display -->
        <div class="space-y-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Public URL
            </label>
            <div class="flex gap-2">
                <input 
                    type="text" 
                    value="{{ $url }}" 
                    readonly
                    id="event-url-input"
                    class="flex-1 px-3 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                />
                <button 
                    type="button"
                    onclick="copyEventUrl(event)"
                    class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    Copy
                </button>
            </div>
        </div>

        <!-- Preview & Share Actions -->
        <div class="flex flex-wrap gap-2 pt-2">
            <a 
                href="{{ $url }}" 
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 dark:bg-blue-900 hover:bg-blue-200 dark:hover:bg-blue-800 text-blue-700 dark:text-blue-200 rounded-lg text-sm font-medium transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Preview Event Page
            </a>

            <button 
                onclick="shareToSocial('facebook')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Share on Facebook
            </button>

            <button 
                onclick="shareToSocial('twitter')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-medium transition-colors"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                </svg>
                Share on Twitter
            </button>
        </div>

        <!-- QR Code -->
        <div class="border-t pt-4">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                QR Code for Event Page
            </span>
            <div class="bg-white p-4 rounded-lg text-center mt-2">
                <img src="data:image/png;base64,{{ $qrBase64 }}" alt="QR code for {{ $event->name }}" class="mx-auto" width="200" height="200">
                <p class="text-xs text-gray-600 mt-2">Scan to view event</p>
            </div>
        </div>

        <!-- Info Note -->
        <div class="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                Share this link on social media, email, or your website to promote your event and allow people to purchase tickets directly.
            </p>
        </div>
    </div>
</div>

<script>
    function copyEventUrl(event) {
        const input = document.getElementById('event-url-input');
        const button = event.currentTarget;
        
        navigator.clipboard.writeText(input.value).then(() => {
            const originalHTML = button.innerHTML;
            
            button.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Copied!
            `;
            
            button.classList.remove('bg-primary-600', 'hover:bg-primary-700');
            button.classList.add('bg-green-600', 'hover:bg-green-700');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-600', 'hover:bg-green-700');
                button.classList.add('bg-primary-600', 'hover:bg-primary-700');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            input.select();
            try {
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            } catch (e) {
                alert('Please manually copy the link.');
            }
        });
    }

    function shareToSocial(platform) {
        const url = document.getElementById('event-url-input').value;
        const text = '{{ $event->name }}';
        
        let shareUrl;
        if (platform === 'facebook') {
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
        } else if (platform === 'twitter') {
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
        }
        
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
</script>