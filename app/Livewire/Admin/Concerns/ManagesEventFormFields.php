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
        $this->field_is_required = false;
        $this->field_sort_order = 0;
        $this->isFieldModalOpen = true;
    }

    public function editField(int $id): void
    {
        $this->resetValidation();
        $field = $this->findScopedField($id);

        if ($field === null) {
            return;
        }

        $this->editingFieldId = $field->id;
        $this->field_label = $field->label;
        $this->field_key = $field->field_key;
        $this->field_input_type = $field->input_type;
        $this->field_placeholder = $field->placeholder ?? '';
        $this->field_options_raw = is_array($field->options) ? implode(', ', $field->options) : '';
        $this->field_is_required = (bool) $field->is_required;
        $this->field_sort_order = (int) $field->sort_order;
        $this->isFieldModalOpen = true;
    }

    public function updatedFieldLabel(string $label): void
    {
        if ($this->editingFieldId === null && $this->field_key === '') {
            $this->field_key = str_replace('-', '_', Str::slug($label));
        }
    }

    public function saveField(): void
    {
        if (trim($this->field_key) === '') {
            $this->field_key = str_replace('-', '_', Str::slug($this->field_label));
        } else {
            $this->field_key = str_replace('-', '_', Str::slug($this->field_key));
        }

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
            $options = array_values(array_filter(array_map('trim', explode(',', $this->field_options_raw))));
        }

        if ($this->editingFieldId !== null) {
            $field = $this->findScopedField($this->editingFieldId);

            if ($field !== null) {
                $field->update([
                    'field_key' => Str::snake($this->field_key),
                    'label' => $this->field_label,
                    'input_type' => $this->field_input_type,
                    'placeholder' => $this->field_placeholder,
                    'options' => $options,
                    'is_required' => $this->field_is_required,
                    'sort_order' => $this->field_sort_order,
                ]);

                $this->flashMessage = "Questionnaire field '{$field->label}' updated.";
            }
        } else {
            EventFormField::create([
                'organization_id' => $this->organizationId,
                'event_id' => $event->id,
                'field_key' => Str::snake($this->field_key),
                'label' => $this->field_label,
                'input_type' => $this->field_input_type,
                'placeholder' => $this->field_placeholder,
                'options' => $options,
                'is_required' => $this->field_is_required,
                'sort_order' => $this->field_sort_order,
                'is_active' => true,
            ]);

            $this->flashMessage = "Custom field '{$this->field_label}' added to checkout form.";
        }

        $this->isFieldModalOpen = false;
        $this->editingFieldId = null;
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
