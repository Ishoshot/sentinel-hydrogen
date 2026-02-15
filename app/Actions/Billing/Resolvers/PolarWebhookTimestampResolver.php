<?php

declare(strict_types=1);

namespace App\Actions\Billing\Resolvers;

use Carbon\CarbonImmutable;
use Throwable;

final class PolarWebhookTimestampResolver
{
    /**
     * Convert a timestamp payload value to an immutable date instance.
     */
    public function parse(mixed $timestamp): ?CarbonImmutable
    {
        if (is_int($timestamp)) {
            return CarbonImmutable::createFromTimestampUTC($timestamp);
        }

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}
