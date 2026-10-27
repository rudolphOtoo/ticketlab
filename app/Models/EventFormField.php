<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventFormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFormField extends Model
{
    use BelongsToOrganization;
    /** @use HasFactory<EventFormFieldFactory> */
    use HasFactory;

    protected static function newFactory(): EventFormFieldFactory
    {
        return EventFormFieldFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'event_id',
        'field_key',
        'label',
        'input_type',
        'placeholder',
        'options',
        'validation_rules',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
