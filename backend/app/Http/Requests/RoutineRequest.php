<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'integer', 'min:1', 'max:7'],
            'rest_minutes' => ['required', 'numeric', 'min:0', 'max:30'],
            // al editar se envian todos los ejercicios en el orden final; los que no vienen se quitan
            'exercises' => ['sometimes', 'array', 'max:100'],
            'exercises.*.exercise_id' => ['required', 'integer', 'distinct'],
            'exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'exercises.*.reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            'exercises.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'exercises.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
