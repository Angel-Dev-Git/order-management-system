<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'email' => ($isUpdate ? 'sometimes|' : ''). 'required|email|max:255|unique:users,email'. ($userId ? ',' . $userId : ''),
            'password' => ($isUpdate ? 'sometimes|nullable' : 'required'). '|string|min:8',
            'role_id' => ($isUpdate ? 'sometimes|' : ''). 'required|integer|'. Rule::exists('roles', 'id')->whereIn('name', ['Manager', 'Staff']),
        ];
    }
}
