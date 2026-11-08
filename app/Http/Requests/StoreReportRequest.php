<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in([Report::TYPE_COMMENT, Report::TYPE_CHAT])],
            'target_id' => ['required', 'string', 'max:32'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
