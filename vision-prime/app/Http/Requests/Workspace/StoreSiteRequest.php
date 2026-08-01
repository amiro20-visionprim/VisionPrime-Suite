<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['project_id' => ['required', 'integer', 'exists:projects,id'], 'name' => ['required', 'string', 'min:2', 'max:160'], 'canonical_url' => ['required', 'string', 'max:2048'], 'locale' => ['required', 'in:fa,en'], 'timezone' => ['required', 'timezone'], 'business_importance' => ['required', 'integer', 'between:1,5']];
    }
}
