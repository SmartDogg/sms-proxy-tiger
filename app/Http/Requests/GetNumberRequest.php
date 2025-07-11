<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:getNumber',
            'country' => 'required|string|size:2',
            'service' => 'required|string|min:2|max:10',
            'token' => 'required|string|size:32',
            'rent_time' => 'nullable|integer|min:1|max:168' // max 7 days
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action parameter is required',
            'action.in' => 'Invalid action parameter',
            'country.required' => 'Country parameter is required',
            'country.size' => 'Country must be 2 characters',
            'service.required' => 'Service parameter is required',
            'token.required' => 'Token parameter is required',
            'token.size' => 'Invalid token format',
            'rent_time.integer' => 'Rent time must be a number',
            'rent_time.min' => 'Rent time must be at least 1 hour',
            'rent_time.max' => 'Rent time cannot exceed 168 hours (7 days)'
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