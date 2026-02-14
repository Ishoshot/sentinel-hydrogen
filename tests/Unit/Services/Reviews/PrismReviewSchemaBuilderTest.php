<?php

declare(strict_types=1);

use App\Services\Reviews\Support\PrismReviewSchemaBuilder;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;

it('builds a review response schema with summary and findings', function (): void {
    $schema = (new PrismReviewSchemaBuilder)->build();

    expect($schema)->toBeInstanceOf(ObjectSchema::class)
        ->and($schema->name)->toBe('review_response')
        ->and($schema->requiredFields)->toContain('summary', 'findings');

    $findingsSchema = collect($schema->properties)
        ->first(fn (mixed $property): bool => $property instanceof ArraySchema && $property->name === 'findings');

    expect($findingsSchema)->toBeInstanceOf(ArraySchema::class);
});
