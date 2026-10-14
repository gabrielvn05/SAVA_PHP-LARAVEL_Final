<?php

namespace App\Http\Requests;

use App\Support\Carreras;
use App\Support\CedulaEcuador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PerfilUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $cedulaActual = trim((string) ($this->user()?->cedula ?? ''));
        $cedula = $cedulaActual !== ''
            ? $cedulaActual
            : CedulaEcuador::normalizeDigits((string) $this->input('cedula', ''));

        $this->merge([
            'cedula' => $cedula,
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
                'regex:/^\d{10}$/',
                Rule::unique('users', 'cedula')->ignore($this->user()->id),
            ],
            'carrera' => ['required', 'string', Rule::in($carreras)],
            'celular' => ['required', 'string', 'regex:/^\d{9,15}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cedula = (string) $this->input('cedula', '');
            if ($cedula === '' || strlen($cedula) !== 10) {
                return;
            }

            $user = $this->user();
            if ($user && trim((string) $user->cedula) !== '' && $user->cedula === $cedula) {
                return;
            }

            if (! CedulaEcuador::isValid($cedula)) {
                $validator->errors()->add('cedula', 'La cédula no es válida. Verifica que sea tu número de identidad ecuatoriano.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cedula.required' => 'Indica tu cédula de identidad.',
            'cedula.regex' => 'La cédula ecuatoriana debe tener exactamente 10 dígitos.',
            'cedula.unique' => 'Esa cédula ya está registrada en otra cuenta.',
            'carrera.required' => 'Selecciona tu carrera.',
            'carrera.in' => 'Selecciona una carrera válida.',
            'celular.required' => 'Indica tu número celular.',
            'celular.regex' => 'El celular debe tener entre 9 y 15 dígitos.',
        ];
    }
}
