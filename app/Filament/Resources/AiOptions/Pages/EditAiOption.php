<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions\Pages;

use App\Actions\Admin\AiOptions\DeleteAiOption as DeleteAiOptionAction;
use App\Actions\Admin\AiOptions\UpdateAiOption as UpdateAiOptionAction;
use App\Filament\Resources\AiOptions\AiOptionResource;
use App\Models\AiOption;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Override;

final class EditAiOption extends EditRecord
{
    protected static string $resource = AiOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete AI Model')
                ->action(function (DeleteAction $action): void {
                    $record = $this->getRecord();

                    if (! $record instanceof AiOption) {
                        throw new LogicException('Expected AI option record.');
                    }

                    try {
                        app(DeleteAiOptionAction::class)->handle($record);
                    } catch (InvalidArgumentException $invalidArgumentException) {
                        Notification::make()
                            ->danger()
                            ->title($invalidArgumentException->getMessage())
                            ->send();

                        $action->halt();
                    }

                    $action->success();
                    $this->redirect(AiOptionResource::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof AiOption) {
            throw new LogicException('Expected AI option record.');
        }

        return app(UpdateAiOptionAction::class)->handle($record, $data);
    }
}
