<?php

namespace App\Http\Requests\Tables;

use App\Models\DiningTable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

// Authorization: route can:tables.manage.
class SaveTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = $this->route('table');

        return [
            'label' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) use ($table) {
                $taken = DiningTable::whereRaw('lower(label) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($table, fn ($q) => $q->whereKeyNot($table->getKey()))
                    ->exists();
                $taken && $fail('Another table already uses this name.');
            }],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function tableData(): array
    {
        $data = $this->validated();
        $data['label'] = trim($data['label']);
        if (($data['sort_order'] ?? null) === null) {
            unset($data['sort_order']);
        }

        return $data;
    }
}
