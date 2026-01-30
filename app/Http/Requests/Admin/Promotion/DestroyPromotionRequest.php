<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Promotion;

use Illuminate\Foundation\Http\FormRequest;

final class DestroyPromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sync_to_polar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Check if should sync deletion to Polar.
     */
    public function syncToPolar(): bool
    {
        return $this->boolean('sync_to_polar');
    }
}
