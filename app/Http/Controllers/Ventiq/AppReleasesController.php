<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Builds of the VENTIQ Scanner app. The current one is offered to
 * organizers: its APK to download, and its Play Store link once listed.
 */
class AppReleasesController extends Controller
{
    public function index()
    {
        return Inertia::render('Ventiq/AppReleases', [
            'releases' => AppRelease::latest('id')->get()->map(fn (AppRelease $r) => [
                'id'        => $r->id,
                'version'   => $r->version,
                'current'   => (bool) $r->is_current,
                'size'      => $r->sizeLabel(),
                'hasApk'    => $r->hasApk(),
                'playStore' => $r->play_store_url,
                'notes'     => $r->notes,
                'uploaded'  => $r->created_at?->format('j M Y'),
                'download'  => $r->hasApk() ? route('scanner-app.download.release', $r) : null,
            ]),
            'meta'     => ['title' => 'Scanner app | VENTIQ'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'version'        => ['required', 'string', 'max:30'],
            // An APK is a zip; browsers and phones label it either way.
            'apk'            => ['nullable', 'file', 'mimetypes:application/vnd.android.package-archive,application/zip,application/octet-stream,application/java-archive', 'max:204800'],
            'play_store_url' => ['nullable', 'url', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'is_current'     => ['boolean'],
        ]);
        if (!$request->hasFile('apk') && empty($data['play_store_url'])) {
            return back()->withErrors(['apk' => 'Upload the APK, or give the Play Store link (or both).']);
        }

        $path = $request->hasFile('apk')
            ? $request->file('apk')->storeAs('app-releases', 'ventiq-scanner-' . Str::slug($data['version']) . '-' . now()->timestamp . '.apk', AppRelease::DISK)
            : null;

        AppRelease::create([
            'version' => $data['version'], 'apk_path' => $path, 'play_store_url' => $data['play_store_url'] ?? null,
            'notes' => $data['notes'] ?? null, 'is_current' => (bool) ($data['is_current'] ?? true), 'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('status', "Version {$data['version']} is uploaded.");
    }

    public function update(Request $request, AppRelease $release)
    {
        $data = $request->validate([
            'play_store_url' => ['nullable', 'url', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'is_current'     => ['boolean'],
        ]);
        $release->update($data);

        return back()->with('status', "Version {$release->version} is saved." . ($release->is_current ? ' It is the one organizers get.' : ''));
    }

    public function destroy(AppRelease $release)
    {
        $release->delete();

        return back()->with('status', "Version {$release->version} has been deleted.");
    }
}
