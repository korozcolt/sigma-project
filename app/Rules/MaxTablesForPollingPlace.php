<?php

namespace App\Rules;

use App\Models\PollingPlace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxTablesForPollingPlace implements ValidationRule
{
    public function __construct(private readonly int $pollingPlaceId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $pollingPlace = PollingPlace::query()->select(['id', 'max_tables'])->find($this->pollingPlaceId);

        if (! $pollingPlace) {
            return;
        }

        if ((int) $value > (int) $pollingPlace->max_tables) {
            $fail('El número de mesa no puede ser mayor a '.$pollingPlace->max_tables.'.');
        }
    }
}

