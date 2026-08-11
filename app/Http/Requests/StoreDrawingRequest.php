<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDrawingRequest extends FormRequest
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
            'round_id' => ['required', 'integer'],
            'strokes' => ['required', 'array', 'min:1', 'max:50'],
            'strokes.*.client_id' => ['required', 'uuid'],
            'strokes.*.from_x' => ['required', 'numeric', 'between:0,1'],
            'strokes.*.from_y' => ['required', 'numeric', 'between:0,1'],
            'strokes.*.to_x' => ['required', 'numeric', 'between:0,1'],
            'strokes.*.to_y' => ['required', 'numeric', 'between:0,1'],
            'strokes.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'strokes.*.size' => ['required', 'integer', 'between:1,32'],
        ];
    }
}
