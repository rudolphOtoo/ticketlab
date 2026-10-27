<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventFormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

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

    /**
     * Validation rules for a buyer's answer. Organiser-supplied rules are appended to,
     * never replace, the type constraints derived from the input type.
     *
     * @return array<int, mixed>
     */
    public function validationRules(): array
    {
        $rules = match ($this->input_type) {
            'email' => ['email'],
            'number' => ['numeric'],
            default => [],
        };

        $options = is_array($this->options) ? array_values(array_filter($this->options)) : [];

        if (in_array($this->input_type, ['select', 'radio'], true) && $options !== []) {
            // Rule::in quotes each value, so options containing commas stay intact.
            $rules[] = Rule::in($options);
        }

        if ($this->is_required) {
            $rules[] = 'required';
        }

        foreach ((array) $this->validation_rules as $rule) {
            if (is_string($rule) && !in_array($rule, $rules, true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }
}
