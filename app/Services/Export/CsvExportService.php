<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Services/Export/CsvExportService.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Services\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * @param string[] $headers
     * @param iterable<array<int, mixed>> $rows
     */
    public function stream(array $headers, iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 — Excel sur Windows n'interprète pas les accents sans lui.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
