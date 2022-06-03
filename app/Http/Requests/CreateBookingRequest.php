<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'from_language_id' => 'required',
            'duration' => 'required',
            'due_date' => 'required_if:immediate,==,no',
            'due_time' => 'required_if:immediate,==,no',
            'customer_phone_type' => 'required_if:immediate,==,no',
            'customer_physical_type' => 'required_if:immediate,==,no',
        ];
    }

    public function messages()
    {
        return [
            'from_language_id.required' => 'Du måste fylla in alla fält',
            'duration.required' => 'Du måste fylla in alla fält'
        ];
    }
}
