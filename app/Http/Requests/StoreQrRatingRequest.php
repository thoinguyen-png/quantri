<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQrRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'in:bad,average,good'],
            'comment' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Vui long chon mot muc danh gia.',
            'rating.in' => 'Muc danh gia khong hop le.',
            'comment.max' => 'Loi nhan toi da 300 ky tu.',
        ];
    }
}
