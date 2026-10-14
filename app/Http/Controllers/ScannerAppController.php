<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The VENTIQ Scanner APK, for signed-in organizers, their teams and super admins. */
class ScannerAppController extends Controller
{
    public function download(Request $request, ?AppRelease $release = null)
    {
        // A specific build is for super admins (Filament); everyone else gets the current one.
        if ($release && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
        $release ??= AppRelease::current();
        abort_unless($release?->hasApk(), 404);

        return Storage::disk(AppRelease::DISK)->download($release->apk_path, $release->downloadName(), [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
