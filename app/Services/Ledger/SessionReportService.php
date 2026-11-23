<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Event;
use App\Models\SessionLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Session ledger PDFs, which are financial records. Nothing is stored as a file: locking a session saves the figures
 * the PDF shows on the ledger row, and each download draws the PDF from them and streams it straight to the
 * (already authorised) admin, like the CSV exports. There is no private storage to secure or clean up.
 */
class SessionReportService
{
    /**
     * Freeze what the PDF shows. Called once, inside the locking transaction.
     *
     * @param list<array{name: string, code: string, admitted: int}> $zoneBreakdown
     */
    public function snapshot(
        SessionLedger $ledger,
        ?Event $event = null,
        int $capacityTotal = 0,
        array $zoneBreakdown = [],
    ): void {
        $ledger->update([
            'snapshot' => [
                'event_id' => $event?->id,
                'event_title' => $event?->title,
                'event_venue' => $event?->venue,
                'capacity_total' => $capacityTotal,
                'zone_breakdown' => $zoneBreakdown,
            ],
        ]);
    }

    /**
     * The ledger PDF for a caller who has already passed the scope check. Ledgers locked before snapshots existed
     * show their frozen totals without a zone breakdown.
     */
    public function download(SessionLedger $ledger): StreamedResponse
    {
        $snapshot = $ledger->snapshot ?? [];

        $pdf = Pdf::loadView('reports.session-ledger', [
            'ledger' => $ledger,
            'venue' => $snapshot['event_venue'] ?? null,
            'capacityTotal' => (int) ($snapshot['capacity_total'] ?? 0),
            'zoneBreakdown' => $snapshot['zone_breakdown'] ?? [],
        ])->setPaper('a4', 'portrait');

        $fileName = sprintf('Session_Ledger_%s.pdf', preg_replace('/[^A-Za-z0-9_-]+/', '_', $ledger->session_name));

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $fileName,
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }
}
