<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\AttendeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $organization_id
 * @property int|null $subdivision_id
 * @property int $ticket_type_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $qr_code_hash
 * @property CarbonImmutable|null $checked_in_at
 * @property int|null $checked_in_by
 * @property array<string, mixed>|null $custom_fields
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Order $order
 * @property-read Subdivision|null $subdivision
 * @property-read TicketType $ticketType
 * @property-read User|null $checkedInByUser
 */
class Attendee extends Model
{
    use BelongsToOrganization;
    /** @use HasFactory<AttendeeFactory> */
    use HasFactory;

    protected static function newFactory(): AttendeeFactory
    {
        return AttendeeFactory::new();
    }

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

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Subdivision, $this>
     */
    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * The buyer's answer to one registration question, or null when it was left unanswered.
     *
     * Online checkout stores answers labelled ({key: {label, value}}); older and manually created passes store
     * the bare value ({key: value}). Both shapes are read here so callers never see the wrapper.
     */
    public function answerFor(string $fieldKey): mixed
    {
        $answer = is_array($this->custom_fields) ? $this->custom_fields[$fieldKey] ?? null : null;

        return is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
    }
}
