<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EnquiryMail;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a dedicated-site contact form submission to the organisation's inbox.
 */
class SendEnquiryEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param array{name: string, email: string, subject: string, message: string} $enquiry
     */
    public function __construct(
        public int $organizationId,
        public array $enquiry,
    ) {}

    public function handle(): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        Mail::to($organization->contactEmail())->send(new EnquiryMail($this->enquiry, $organization->name));
    }
}
