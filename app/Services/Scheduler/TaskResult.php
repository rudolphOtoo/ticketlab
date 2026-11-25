<?php

declare(strict_types=1);

namespace App\Services\Scheduler;

/**
 * What one task did: counts by outcome, and the errors it met (already logged with their context by the task).
 */
final class TaskResult
{
    /**
     * @param array<string, int> $counts
     * @param list<string> $errors
     */
    public function __construct(
        public array $counts = [],
        public array $errors = [],
    ) {}

    public function add(string $outcome, int $by = 1): void
    {
        $this->counts[$outcome] = ($this->counts[$outcome] ?? 0) + $by;
    }

    /** Records an item that failed: counted under "errors"; the message never holds secrets or tokens. */
    public function error(string $message): void
    {
        $this->errors[] = $message;
        $this->add('errors');
    }
}
