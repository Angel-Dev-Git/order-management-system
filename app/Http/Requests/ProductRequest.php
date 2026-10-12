<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id;
        return [
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'sku' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:100|unique:products,sku' . ($productId ? ',' . $productId : ''),
            'description' => 'sometimes|nullable|string',
            'price' => ($isUpdate ? 'sometimes|' : '') . 'required|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
