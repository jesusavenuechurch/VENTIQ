<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * Ticket QR images and PDF passes used to sit in public storage at
 * addresses built from the ticket's number, so anyone could fetch any
 * attendee's. They move to private storage (served only through the
 * ticket's link) and the public copies are deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public  = Storage::disk('public');
        $private = Storage::disk('local');

        DB::table('tickets')->where(fn ($q) => $q->whereNotNull('qr_code_path')->orWhereNotNull('avatar_path'))
            ->select(['id', 'qr_code_path', 'avatar_path'])->orderBy('id')
            ->chunkById(500, function ($tickets) use ($public, $private) {
                foreach ($tickets as $t) {
                    $update = [];
                    foreach (['qr_code_path' => 'qr', 'avatar_path' => 'passes'] as $column => $folder) {
                        $path = $t->{$column};
                        if (!$path || str_starts_with($path, 'ticket-files/')) {
                            continue;
                        }

                        $new = 'ticket-files/' . $folder . '/' . basename(dirname($path)) . '/' . basename($path);
                        if ($public->exists($path)) {
                            $private->put($new, $public->get($path));
                            $public->delete($path);
                            $update[$column] = $new;
                        } else {
                            // Gone already: made again the next time it's needed.
                            $update[$column] = null;
                        }
                    }
                    if ($update) {
                        DB::table('tickets')->where('id', $t->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        // Left private: the files are made again on demand wherever needed.
    }
};
