<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProgressSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scoring' => ['required', 'array'],
            'scoring.pointsPerDay' => ['required', 'integer', 'min:0', 'max:1000'],
            'scoring.pointsPerStreakDay' => ['required', 'integer', 'min:0', 'max:1000'],
            'scoring.pointsPerMinute' => ['required', 'integer', 'min:0', 'max:100'],
            'scoring.kgPerPoint' => ['required', 'integer', 'min:1', 'max:100000'],
            'scoring.maxPointsPerDay' => ['required', 'integer', 'min:1', 'max:100000'],
            'ranks' => ['required', 'array'],
            'ranks.*.minPoints' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
