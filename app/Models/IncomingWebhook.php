<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Partner;
use Database\Factories\IncomingWebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class IncomingWebhook extends Model
{
    /** @use HasFactory<IncomingWebhookFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner',
        'webhook_id',
        'event_type',
        'payload',
        'headers',
        'ip_address',
        'response_code',
        'response_body',
        'processed_at',
    ];

    /**
     * Check if a webhook has already been received (for deduplication).
     */
    public static function wasReceived(Partner $partner, string $webhookId): bool
    {
        return self::query()
            ->where('partner', $partner)
            ->where('webhook_id', $webhookId)
            ->exists();
    }

    /**
     * Check if a webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Mark the webhook as processed with the response.
     */
    public function markAsProcessed(int $responseCode, mixed $responseBody = null): void
    {
        $this->forceFill([
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'partner' => Partner::class,
            'payload' => 'array',
            'headers' => 'array',
            'response_body' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
