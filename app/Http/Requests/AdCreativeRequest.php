<?php

namespace App\Http\Requests;

use App\AdCreative;
use App\Services\AdService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validacion y normalizacion del alta/edicion de creatividades
 * (REQ-ADM-04, REQ-CRT-01..04, D-25).
 *
 * El formulario usa `datetime-local` (hora de pared sin zona): se interpreta
 * en config('ads.timezone') y se guarda convertido a UTC. `campaign_key` es
 * inmutable despues de crear (D-01).
 */
class AdCreativeRequest extends FormRequest
{
    const LOCAL_FORMAT = 'Y-m-d\TH:i';

    const LOCAL_FORMAT_SECONDS = 'Y-m-d\TH:i:s';

    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return void
     */
    protected function prepareForValidation()
    {
        $data = $this->all();

        if ($this->creative() === null) {
            // Solo en create: prellenar desde el producto elegido (REQ-CRT-04).
            $data = app(AdService::class)->prefillFromProducto($data);
        }

        if (! isset($data['status']) || $data['status'] === '') {
            $data['status'] = 'draft';
        }

        if (! isset($data['priority']) || $data['priority'] === '') {
            $data['priority'] = 0;
        }

        if (! isset($data['cta_label']) || $data['cta_label'] === '') {
            $data['cta_label'] = 'Ver más';
        }

        if ((! isset($data['alt_text']) || $data['alt_text'] === '') && isset($data['headline'])) {
            $data['alt_text'] = $data['headline'];
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $data[$field] = $this->toUtc($data[$field]);
            }
        }

        $this->replace($data);
    }

    /**
     * @return array
     */
    public function rules()
    {
        // `^(https?://|/(?!/))` = criterio de AdService::safeTargetUrl:
        // rechaza javascript:, data: y `//host`.
        $safeUrl = 'regex:#^(https?://|/(?!/))#i';

        return [
            'campaign_key' => $this->creative() === null
                ? 'required|string|max:64|regex:/^[a-z0-9]+([_-][a-z0-9]+)*$/|unique:ad_creatives,campaign_key'
                : 'nullable|string|max:64',
            'name' => 'required|string|max:100',
            'placement_id' => 'required|integer|exists:ad_placements,id',
            'status' => 'required|in:draft,active,paused,ended',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'priority' => 'required|integer|between:-100,100',
            'image_url' => ['required', 'string', 'max:2048', $safeUrl],
            'alt_text' => 'nullable|string|max:150',
            'headline' => 'required|string|max:120',
            'body' => 'nullable|string|max:500',
            'cta_label' => 'nullable|string|max:40',
            'target_url' => ['required', 'string', 'max:2048', $safeUrl],
            'producto_id' => 'nullable|integer|exists:productos,id',
        ];
    }

    /**
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $creative = $this->creative();
            $key = $this->input('campaign_key');

            if ($creative !== null && is_string($key) && $key !== '' && $key !== $creative->campaign_key) {
                $validator->errors()->add(
                    'campaign_key',
                    'El campaign_key no se puede cambiar despues de crear la creatividad.'
                );
            }

            $starts = $this->input('starts_at');
            $ends = $this->input('ends_at');

            if (! empty($starts) && ! empty($ends) && strtotime($ends) < strtotime($starts)) {
                $validator->errors()->add('ends_at', 'ends_at debe ser igual o posterior a starts_at.');
            }
        });
    }

    /**
     * Creatividad del route binding (null en create).
     *
     * @return \App\AdCreative|null
     */
    private function creative()
    {
        $route = $this->route('publicidad');

        if ($route instanceof AdCreative) {
            return $route;
        }

        return $route === null ? null : AdCreative::find($route);
    }

    /**
     * datetime-local local -> DATETIME UTC (D-25). Si el valor no tiene forma
     * de datetime-local se deja intacto para que la regla `date` lo rechace.
     *
     * @param  string  $value
     * @return string
     */
    private function toUtc($value)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return $value;
        }

        $format = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value) === 1
            ? self::LOCAL_FORMAT_SECONDS
            : self::LOCAL_FORMAT;

        $timezone = (string) config('ads.timezone', 'America/Bogota');

        return Carbon::createFromFormat($format, $value, $timezone)->utc()->toDateTimeString();
    }
}
