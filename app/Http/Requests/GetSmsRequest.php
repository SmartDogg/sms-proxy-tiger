<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:getSms',
            'token' => 'required|string|size:32',
            'activation' => 'required|string|min:1|max:20'
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action parameter is required',
            'action.in' => 'Invalid action parameter',
            'token.required' => 'Token parameter is required',
            'token.size' => 'Invalid token format',
            'activation.required' => 'Activation parameter is required',
            'activation.string' => 'Activation must be a string',
            'activation.min' => 'Invalid activation format'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 'error',
            'message' => $validator->errors()->first()
        ], 400));
    }
}