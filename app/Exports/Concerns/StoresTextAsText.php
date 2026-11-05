<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Store every string as literal text. PhpSpreadsheet's default binder turns a string starting with "=" into a
 * live formula, and attendee names and answers are typed by the public, so that would be formula injection.
 */
trait StoresTextAsText
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return new DefaultValueBinder()->bindValue($cell, $value);
    }
}
