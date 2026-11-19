<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:approvals.grant.
class UpdateApprovalPinRequest extends FormRequest
{
    protected $errorBag = 'approvalPin';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // An unattended signed-in tablet must not be enough to take over a supervisor's approvals.
            'current_password' => ['required', 'current_password'],
            'approval_pin' => ['required', 'regex:/^\d{4,6}$/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.*' => 'Enter your current password.',
            'approval_pin.regex' => 'The PIN must be 4 to 6 digits.',
            'approval_pin.confirmed' => 'The two PINs do not match.',
        ];
    }
}
