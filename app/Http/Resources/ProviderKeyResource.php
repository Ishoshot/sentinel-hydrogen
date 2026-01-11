<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProviderKey
 *
 * SECURITY: encrypted_key is NEVER included in responses.
 * Users can only see that a key exists, not its value.
 */
final class ProviderKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'provider_label' => $this->getProviderLabel(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the provider label.
     */
    private function getProviderLabel(): string
    {
        return match ($this->provider) {
            AiProvider::Anthropic => 'Anthropic (Claude)',
            AiProvider::OpenAI => 'OpenAI (GPT-4)',
        };
    }
}
