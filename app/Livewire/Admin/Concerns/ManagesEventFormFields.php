<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\EventFormField;
use Illuminate\Support\Str;

trait ManagesEventFormFields
{
    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isFieldModalOpen = false;

    public ?int $editingFieldId = null;

    public string $field_label = '';

    public string $field_key = '';

    public string $field_input_type = 'text';

    public string $field_placeholder = '';

    public string $field_options_raw = '';

    /** @var list<string> */
    public array $field_options = [];

    public string $newOptionInput = '';

    public bool $field_is_required = false;

    public int $field_sort_order = 0;

    public function openCreateFieldModal(): void
    {
        $this->resetValidation();
        $this->editingFieldId = null;
        $this->field_label = '';
        $this->field_key = '';
        $this->field_input_type = 'text';
        $this->field_placeholder = '';
        $this->field_options_raw = '';
        $this->field_options = [];
        $this->newOptionInput = '';
        $this->field_is_required = false;
        $this->field_sort_order = 0;
        $this->isFieldModalOpen = true;
    }

    public function addOption(): void
    {
        $trimmed = trim($this->newOptionInput);

        if ($trimmed !== '' && !in_array($trimmed, $this->field_options, true)) {
            $this->field_options[] = $trimmed;
            $this->newOptionInput = '';
        }
    }

    public function removeOption(int $index): void
    {
        if (array_key_exists($index, $this->field_options)) {
            $this->field_options = array_values(array_filter(
                $this->field_options,
                static fn(int $k): bool => $k !== $index,
                ARRAY_FILTER_USE_KEY,
            ));
        }
    }

    public function editField(int $id): void
    {
        $this->resetValidation();
        $field = $this->findScopedField($id);

        if ($field === null) {
            return;
        }

        $this->fill([
            'editingFieldId' => $field->id,
            'field_label' => $field->label,
            'field_key' => $field->field_key,
            'field_input_type' => $field->input_type,
            'field_placeholder' => $field->placeholder ?? '',
            'field_options_raw' => is_array($field->options) ? implode(', ', $field->options) : '',
            'field_options' => is_array($field->options) ? array_values($field->options) : [],
            'newOptionInput' => '',
            'field_is_required' => $field->is_required,
            'field_sort_order' => $field->sort_order,
            'isFieldModalOpen' => true,
        ]);
    }

    public function updatedFieldLabel(string $label): void
    {
        if ($this->editingFieldId === null && $this->field_key === '') {
            $this->field_key = str_replace('-', '_', Str::slug($label));
        }
    }

    public function saveField(): void
    {
        $keySource = trim($this->field_key) === '' ? $this->field_label : $this->field_key;
        $this->field_key = str_replace('-', '_', Str::slug($keySource));

        $this->validate([
            'field_label' => ['required', 'string', 'max:100'],
            'field_key' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_]+$/'],
            'field_input_type' => ['required', 'string', 'in:text,select,radio,checkbox,textarea'],
            'field_placeholder' => ['nullable', 'string', 'max:120'],
            'field_options_raw' => ['nullable', 'string', 'max:500'],
            'field_is_required' => ['boolean'],
            'field_sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            return;
        }

        $options = null;

        if (in_array($this->field_input_type, ['select', 'radio', 'checkbox'], true)) {
            $options = !empty($this->field_options)
                ? $this->field_options
                : array_values(array_filter(array_map('trim', explode(',', $this->field_options_raw))));
        }

        $this->flashMessage = $this->persistField($event->id, $options) ?? $this->flashMessage;

        $this->isFieldModalOpen = false;
        $this->editingFieldId = null;
    }

    /**
     * Create or update the questionnaire field behind the modal and return the confirmation to flash.
     *
     * @param list<string>|null $options
     */
    private function persistField(int $eventId, ?array $options): ?string
    {
        $attributes = [
            'field_key' => Str::snake($this->field_key),
            'label' => $this->field_label,
            'input_type' => $this->field_input_type,
            'placeholder' => $this->field_placeholder,
            'options' => $options,
            'is_required' => $this->field_is_required,
            'sort_order' => $this->field_sort_order,
        ];

        if ($this->editingFieldId === null) {
            EventFormField::create([
                'organization_id' => $this->organizationId,
                'event_id' => $eventId,
                ...$attributes,
                'is_active' => true,
            ]);

            return "Custom field '{$this->field_label}' added to checkout form.";
        }

        $field = $this->findScopedField($this->editingFieldId);

        if ($field === null) {
            return null;
        }

        $field->update($attributes);

        return "Questionnaire field '{$field->label}' updated.";
    }

    public function deleteField(int $id): void
    {
        $field = $this->findScopedField($id);

        if ($field !== null) {
            $label = $field->label;
            $field->delete();
            $this->flashMessage = "Field '{$label}' was deleted.";
        }
    }

    protected function findScopedField(int $id): ?EventFormField
    {
        if ($this->organizationId === null) {
            return null;
        }

        return EventFormField::query()->where('organization_id', $this->organizationId)->whereKey($id)->first();
    }
}
