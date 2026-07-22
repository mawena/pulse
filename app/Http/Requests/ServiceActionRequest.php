<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation stricte anti command-injection :
 * - service : uniquement les clés déclarées dans config/pulse.php
 * - action : whitelist restart/reload
 */
class ServiceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission manage/system vérifiée par le middleware de route
    }

    public function rules(): array
    {
        return [
            'service' => ['required', 'string', Rule::in(array_keys(config('pulse.services')))],
            'action' => ['required', 'string', Rule::in(config('pulse.service_actions'))],
        ];
    }
}
