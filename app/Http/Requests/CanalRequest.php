<?php

namespace App\Http\Requests;

use App\PublicacionCanal;
use App\Services\Publicacion\Credenciales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validacion del alta y edicion de canales (REQ-CAN-01..03, CAN-10, CAN-12).
 *
 * Sin red: los formatos se validan con Credenciales. Un secreto vacio en
 * edicion significa conservar el anterior.
 */
class CanalRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $canal = $id ? PublicacionCanal::find($id) : null;

        $reglas = [
            'nombre' => [
                'required',
                'string',
                'max:60',
                Rule::unique('publicacion_canales', 'nombre')->ignore($id),
            ],
            'habilitado' => ['nullable', 'boolean'],
        ];

        if ($canal === null) {
            $reglas['tipo'] = [
                'required',
                Rule::in(array_keys((array) config('publicaciones.drivers'))),
            ];
        }

        return $reglas;
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 60 caracteres.',
            'nombre.unique' => 'Ya existe un canal con ese nombre.',
            'tipo.required' => 'El tipo de canal es obligatorio.',
            'tipo.in' => 'El tipo de canal no es valido.',
            'habilitado.boolean' => 'El estado del canal no es valido.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id = $this->route('id');
            $canal = $id ? PublicacionCanal::find($id) : null;
            $tipo = $canal ? $canal->tipo : (string) $this->input('tipo');

            if ($canal !== null
                && $this->filled('tipo')
                && (string) $this->input('tipo') !== $canal->tipo
            ) {
                $validator->errors()->add('tipo', 'El tipo de un canal no se puede cambiar.');
            }

            if ($this->filled('vigente_desde') && !$this->fechaValida((string) $this->input('vigente_desde'))) {
                $validator->errors()->add('vigente_desde', 'La fecha de vigencia no es valida.');
            }

            $this->validarCredenciales($validator, $tipo, $canal);
        });
    }

    private function validarCredenciales($validator, string $tipo, $canal): void
    {
        $token = trim((string) $this->input('token', ''));
        $chatId = trim((string) $this->input('chat_id', ''));
        $webhook = trim((string) $this->input('webhook_url', ''));

        if ($tipo === 'telegram') {
            if ($token === '' && $chatId === '') {
                if ($canal === null) {
                    $validator->errors()->add(
                        'token',
                        'Las credenciales de Telegram requieren token y chat_id juntos.'
                    );
                }

                return;
            }

            if ($token === '' || $chatId === '') {
                $validator->errors()->add(
                    'token',
                    'Las credenciales de Telegram requieren token y chat_id juntos.'
                );

                return;
            }

            $resultado = Credenciales::validar('telegram', [
                'token' => $token,
                'chat_id' => $chatId,
            ]);

            if (!$resultado['ok']) {
                $campo = strpos((string) $resultado['detalle'], 'chat_id') !== false
                    ? 'chat_id'
                    : 'token';

                $validator->errors()->add($campo, $resultado['detalle']);
            }

            return;
        }

        if ($tipo === 'discord') {
            if ($webhook === '') {
                if ($canal === null) {
                    $validator->errors()->add(
                        'webhook_url',
                        'La URL del webhook de Discord es obligatoria.'
                    );
                }

                return;
            }

            $resultado = Credenciales::validar('discord', ['webhook_url' => $webhook]);

            if (!$resultado['ok']) {
                $validator->errors()->add('webhook_url', $resultado['detalle']);
            }
        }
    }

    private function fechaValida(string $valor): bool
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor, 'UTC');

                if ($fecha !== false) {
                    return true;
                }
            } catch (\Exception $error) {
                // Se prueba el siguiente formato.
            }
        }

        return false;
    }
}
