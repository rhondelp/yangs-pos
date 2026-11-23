<?php

namespace App\Http\Requests\Staff;

use App\Services\Staff\EmployeeService;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:employees.manage. Field rules live in EmployeeService (shared by web and API).
class EmployeeRequest extends FormRequest
{
    private const PAY = ['pay_type', 'rate', 'custom_basis'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Pay changes on an existing employee go through a new rate, so history keeps its start date.
        return $this->route('employee') ? array_fill_keys(self::PAY, ['prohibited']) : [];
    }

    public function messages(): array
    {
        return array_fill_keys(array_map(fn ($k) => "{$k}.prohibited", self::PAY), 'Change pay with a new rate on the employee page.');
    }

    /** @return array<string, mixed> */
    public function employeeData(): array
    {
        return $this->only($this->route('employee') ? EmployeeService::FIELDS : [...EmployeeService::FIELDS, ...self::PAY]);
    }
}
