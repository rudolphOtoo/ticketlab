<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFormField extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'field_key',
        'label',
        'input_type',
        'field_type',
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

    public function getFieldTypeAttribute(): ?string
    {
        return $this->attributes['input_type'] ?? null;
    }

    public function setFieldTypeAttribute(?string $value): void
    {
        $this->attributes['input_type'] = $value;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
