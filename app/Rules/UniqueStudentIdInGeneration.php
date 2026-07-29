<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueStudentIdInGeneration implements ValidationRule
{
    public function __construct(
        private readonly ?string $generation,
        private readonly ?int $ignoreUserId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query = User::query()
            ->where('role', 'student')
            ->where('student_id', $value)
            ->where('generation', $this->generation);

        if ($this->ignoreUserId !== null) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $generationLabel = $this->generation ?: 'this generation';

            $fail("Student ID \"{$value}\" is already used by another student in {$generationLabel}.");
        }
    }
}
