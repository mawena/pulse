<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation stricte anti command-injection :
 * - pid : entier pur (aucune chaîne arbitraire), >= 2
 * - signal : whitelist de config/pulse.php uniquement
 */
class KillProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission manage/system vérifiée par le middleware de route
    }

    public function rules(): array
    {
        return [
            'pid' => ['required', 'integer', 'min:2', 'max:4194304'],
            'signal' => ['sometimes', 'string', Rule::in(config('pulse.kill_signals'))],
        ];
    }
}
