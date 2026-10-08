<?php

// Only the settings VENTIQ changes; Livewire's defaults cover the rest.
return [

    // The thin bar at the top while a wire:navigate page loads (organizer tabs).
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#F07F22',
    ],

    // Uploads go through a temporary file first. The scanner app's APK
    // (uploaded in Filament) is far over the 12 MB default, so allow 200 MB
    // and longer for it to arrive. The web server and PHP must allow it
    // too: client_max_body_size, upload_max_filesize, post_max_size.
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:204800'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'svg', 'wav', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga', 'webp', 'wma'],
        'max_upload_time' => 15,
        'cleanup' => true,
    ],

];
