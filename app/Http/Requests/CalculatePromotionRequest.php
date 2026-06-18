<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculatePromotionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_code' => ['required', 'string', Rule::exists('suppliers', 'code')->where('status', 'active')],
            'office_code' => ['required', 'string', 'max:255'],
            'acriss_code' => ['required', 'string', 'max:10'],
            'pickup_at' => ['required', 'date'],
            'dropoff_at' => ['required', 'date', 'after:pickup_at'],
            'base_amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
