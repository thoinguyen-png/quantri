<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffCardExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'selection_mode' => ['required', Rule::in(['all_active', 'selected'])],
            'user_ids' => ['required_if:selection_mode,selected', 'array'],
            'user_ids.*' => ['integer', 'distinct'],
            'template' => ['required', Rule::in(['horizontal', 'vertical', 'both'])],
        ];
    }
}
