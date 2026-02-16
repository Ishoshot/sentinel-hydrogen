<?php

declare(strict_types=1);

namespace App\Filament\Resources\Promotions\Pages;

use App\Actions\Admin\Promotions\DeletePromotion as DeletePromotionAction;
use App\Actions\Admin\Promotions\UpdatePromotion as UpdatePromotionAction;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Promotion;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete Promotion')
                ->schema([
                    Toggle::make('sync_to_polar')
                        ->label('Revoke in Polar')
                        ->default(false),
                ])
                ->action(function (array $data, DeleteAction $action): void {
                    $record = $this->getRecord();

                    if (! $record instanceof Promotion) {
                        throw new LogicException('Expected promotion record.');
                    }

                    app(DeletePromotionAction::class)->handle(
                        promotion: $record,
                        syncToPolar: (bool) ($data['sync_to_polar'] ?? false),
                    );

                    $action->success();
                    $this->redirect(PromotionResource::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Promotion) {
            throw new LogicException('Expected promotion record.');
        }

        return app(UpdatePromotionAction::class)->handle(
            promotion: $record,
            data: $data,
            syncToPolar: (bool) ($data['sync_to_polar'] ?? false),
        );
    }
}
