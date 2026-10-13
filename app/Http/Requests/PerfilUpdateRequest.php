<?php

namespace App\Http\Requests;

use App\Support\Carreras;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cedula' => preg_replace('/\D+/', '', (string) $this->input('cedula', '')) ?? '',
            'celular' => preg_replace('/\D+/', '', (string) $this->input('celular', '')) ?? '',
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $carreras = array_column(Carreras::OPCIONES, 'value');

        return [
            'cedula' => [
                'required',
                'string',
                'regex:/^\d{10,13}$/',
                Rule::unique('users', 'cedula')->ignore($this->user()->id),
            ],
            'carrera' => ['required', 'string', Rule::in($carreras)],
            'celular' => ['required', 'string', 'regex:/^\d{9,15}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cedula.required' => 'Indica tu cédula de identidad.',
            'cedula.regex' => 'La cédula debe tener entre 10 y 13 dígitos.',
            'cedula.unique' => 'Esa cédula ya está registrada en otra cuenta.',
            'carrera.required' => 'Selecciona tu carrera.',
            'carrera.in' => 'Selecciona una carrera válida.',
            'celular.required' => 'Indica tu número celular.',
            'celular.regex' => 'El celular debe tener entre 9 y 15 dígitos.',
        ];
    }
}
