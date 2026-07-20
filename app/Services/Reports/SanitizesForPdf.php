<?php

namespace App\Services\Reports;

trait SanitizesForPdf
{
        /**
     * Recursively sanitize all strings in an array for PDF rendering.
     * DomPDF requires clean UTF-8 — strips or replaces anything that isn't.
     */
    protected function sanitizeForPdf(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                // Convert to UTF-8, replacing invalid sequences
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                // Strip any remaining non-printable characters except newlines/tabs
                $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/u', '', $value);
                // Trim the result
                $value = trim($value);
            }
        });
 
        return $data;
    }

    protected function sanitizeString(string $value): string
    {   
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/u', '', $value);
        return trim($value);
    }

    protected function sanitizeModelForPdf(\Illuminate\Database\Eloquent\Model $model): array
    {
        return $this->sanitizeForPdf($model->toArray());
    }
}
