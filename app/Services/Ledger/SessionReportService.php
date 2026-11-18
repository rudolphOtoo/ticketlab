<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\Event;
use App\Models\SessionLedger;
use App\Support\PrivateFiles;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
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
        // Private disk, namespaced per organisation: ledger PDFs are financial records and never get a public URL.
        $fileName = sprintf('orgs/%d/ledgers/session_%d_%s.pdf', $ledger->organization_id, $ledger->id, $timestamp);

        $pdf = Pdf::loadView('reports.session-ledger', [
            'ledger' => $ledger,
            'event' => $event,
            'organization' => $organization,
            'capacityTotal' => $capacityTotal,
            'zoneBreakdown' => $zoneBreakdown,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $pdfContent = $pdf->output();
        PrivateFiles::put($fileName, $pdfContent, 'application/pdf');

        $ledger->update([
            'snapshot_pdf_path' => $fileName,
        ]);

        return $fileName;
    }

    /**
     * The ledger PDF for an already authorised caller: a short-lived signed link on cloud storage, or a streamed
     * download from a local disk. Regenerated if the stored copy is missing.
     */
    public function downloadReport(SessionLedger $ledger): StreamedResponse|RedirectResponse
    {
        $path = $ledger->snapshot_pdf_path;

        if (!PrivateFiles::exists($path)) {
            $path = $this->generateReport($ledger);
        }

        $downloadName = sprintf('Session_Ledger_%s.pdf', str_replace(' ', '_', $ledger->session_name));
        $signed = PrivateFiles::temporaryDownloadUrl((string) $path, $downloadName);

        return $signed !== null
            ? redirect()->away($signed)
            : Storage::disk(PrivateFiles::disk())->download((string) $path, $downloadName);
    }
}
