<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Event;
use App\Models\SessionLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionReportService
{
    /**
     * Generate and store an immutable PDF audit snapshot for the given session ledger.
     *
     * @param array<int, array{name: string, code: string, admitted: int}> $zoneBreakdown
     */
    public function generateReport(
        SessionLedger $ledger,
        ?Event $event = null,
        int $capacityTotal = 0,
        array $zoneBreakdown = [],
    ): string {
        $organization = $ledger->organization;
        $timestamp = now()->format('Ymd_His');
        $fileName = sprintf('reports/session_%d_%s.pdf', $ledger->id, $timestamp);

        $pdf = Pdf::loadView('reports.session-ledger', [
            'ledger' => $ledger,
            'event' => $event,
            'organization' => $organization,
            'capacityTotal' => $capacityTotal,
            'zoneBreakdown' => $zoneBreakdown,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $pdfContent = $pdf->output();
        Storage::disk('local')->put($fileName, $pdfContent);

        $ledger->update([
            'snapshot_pdf_path' => $fileName,
        ]);

        return $fileName;
    }

    /**
     * Stream or download an existing session ledger PDF.
     */
    public function downloadReport(SessionLedger $ledger): StreamedResponse
    {
        $path = $ledger->snapshot_pdf_path;

        if (!$path || !Storage::disk('local')->exists($path)) {
            $path = $this->generateReport($ledger);
        }

        $downloadName = sprintf('Session_Ledger_%s.pdf', str_replace(' ', '_', $ledger->session_name));

        return Storage::disk('local')->download($path, $downloadName);
    }
}
