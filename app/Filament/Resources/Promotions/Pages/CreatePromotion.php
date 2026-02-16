<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Pages;

use App\Actions\Admin\Promotions\CreatePromotion as CreatePromotionAction;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePromotionAction::class)->handle(
            data: $data,
            syncToPolar: (bool) ($data['sync_to_polar'] ?? false),
        );
    }
}
