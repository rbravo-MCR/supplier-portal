<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierServiceVehicleAvailabilityRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.office_code' => ['nullable', 'required_without:items.*.iata_code', 'string', 'max:255'],
            'items.*.iata_code' => ['nullable', 'required_without:items.*.office_code', 'string', 'size:3'],
            'items.*.vehicle_class' => ['required', 'string', 'max:255'],
            'items.*.acriss_code' => ['required', 'string', 'size:4'],
            'items.*.available_quantity' => ['required', 'integer', 'min:0'],
            'items.*.valid_from' => ['required', 'date'],
            'items.*.valid_to' => ['required', 'date', 'after_or_equal:items.*.valid_from'],
            'items.*.status' => ['nullable', Rule::in(['available', 'unavailable'])],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }
}
