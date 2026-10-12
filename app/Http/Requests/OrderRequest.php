<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        return [
            'customer_id' => ($isUpdate ? 'sometimes' : 'required') . '|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => ($isUpdate ? 'sometimes' : 'required') . '|in:draft,pending,confirmed,processing,ready,completed,cancelled',
            'priority' => 'sometimes|in:low,normal,high',
            'items' => ($isUpdate ? 'sometimes' : 'required') . '|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'sometimes|numeric|min:0',
            'items.*.tax' => 'sometimes|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.sku' => 'required|string|max:100',
        ];
    }
}
