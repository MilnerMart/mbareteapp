<?php

namespace App\Http\Requests;

use App\Models\CalendarNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * note_on solo al crear: el dia lo define el dia elegido en el calendario y despues no se cambia.
     * remind_days: con cuantos dias de anticipacion avisar en la campanita (2, 1 y/o 0 = el mismo dia); vacio es solo una anotacion.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note_on' => $this->isMethod('post') ? ['required', 'date_format:Y-m-d'] : ['exclude'],
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'remind_days' => ['nullable', 'array'],
            'remind_days.*' => ['integer', 'distinct', Rule::in(CalendarNote::remindDayList)],
        ];
    }
}
