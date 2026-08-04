<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinGameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'code' => ['required', 'string', 'size:6', 'regex:/^[A-Z2-9]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:24', "regex:/^[\pL\pN][\pL\pN ._'-]*$/u"],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper($this->string('code')->trim()->toString()),
            'name' => Str::squish($this->string('name')->toString()),
        ]);
    }
}
