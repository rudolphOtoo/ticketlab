<?php

declare(strict_types=1);

use App\Models\Attendee;

it('extracts answers from nested label and value format', function () {
    $attendee = new Attendee([
        'custom_fields' => [
            't_shirt' => ['label' => 'T-Shirt Size', 'value' => 'XL'],
            'newsletter' => ['label' => 'Subscribe', 'value' => false],
            'notes' => ['label' => 'Special Notes', 'value' => ''],
        ],
    ]);

    expect($attendee->answerFor('t_shirt'))->toBe('XL')
        ->and($attendee->answerFor('newsletter'))->toBe(false)
        ->and($attendee->answerFor('notes'))->toBe('');
});

it('extracts answers from legacy flat format', function () {
    $attendee = new Attendee([
        'custom_fields' => [
            't_shirt' => 'Large',
            'seat_count' => 0,
        ],
    ]);

    expect($attendee->answerFor('t_shirt'))->toBe('Large')
        ->and($attendee->answerFor('seat_count'))->toBe(0);
});

it('returns null when field is not present or custom_fields is empty or not an array', function () {
    $attendeeEmpty = new Attendee(['custom_fields' => []]);
    $attendeeNull = new Attendee(['custom_fields' => null]);

    expect($attendeeEmpty->answerFor('missing'))->toBeNull()
        ->and($attendeeNull->answerFor('missing'))->toBeNull();
});

it('returns explicit null when nested value is null', function () {
    $attendee = new Attendee([
        'custom_fields' => [
            'optional_field' => ['label' => 'Optional', 'value' => null],
        ],
    ]);

    expect($attendee->answerFor('optional_field'))->toBeNull();
});


