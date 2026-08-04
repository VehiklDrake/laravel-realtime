<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CreateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:24', "regex:/^[\pL\pN][\pL\pN ._'-]*$/u"],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::squish($this->string('name')->toString())]);
    }
}
