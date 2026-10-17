<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\PersonName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => PersonName::normalize($this->input('name')),
            'last_name' => PersonName::normalize($this->input('last_name')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60', new PersonName('nombre')],
            'last_name' => ['required', 'string', 'min:2', 'max:60', new PersonName('apellido')],
            'age' => ['required', 'integer', 'min:12', 'max:100'],
            'height' => ['required', 'integer', 'min:100', 'max:230'],
            'weight' => ['required', 'integer', 'min:25', 'max:250'],
            'goal' => ['nullable', Rule::in(User::goalSlugList)],
            'gender' => ['nullable', Rule::in(User::genderSlugList)],
            'rest_days' => ['nullable', 'array', 'max:'.User::maxRestDays],
            'rest_days.*' => ['integer', 'distinct', Rule::in(User::weekDayList)],
        ];
    }

    public function messages(): array
    {
        return [
            'rest_days.max' => 'Deja al menos un dia de entrenamiento en la semana',
        ];
    }
}
