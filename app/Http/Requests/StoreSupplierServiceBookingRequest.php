<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierServiceBookingRequest extends FormRequest
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
            'supplier_code' => ['required', 'string', Rule::exists('suppliers', 'code')],
            'reservation_code' => ['required', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'vehicle_class' => ['nullable', 'string', 'max:255'],
            'pickup_office_code' => ['required', 'string', 'max:255'],
            'dropoff_office_code' => ['required', 'string', 'max:255'],
            'pickup_at' => ['required', 'date'],
            'dropoff_at' => ['required', 'date', 'after:pickup_at'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
