<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'order_id',
        'organization_id',
        'subdivision_id',
        'ticket_type_id',
        'name',
        'email',
        'phone',
        'qr_code_hash',
        'checked_in_at',
        'checked_in_by',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'custom_fields' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }
}
