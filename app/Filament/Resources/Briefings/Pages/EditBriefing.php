<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings\Pages;

use App\Actions\Admin\Briefings\DeleteBriefing as DeleteBriefingAction;
use App\Actions\Admin\Briefings\UpdateBriefing as UpdateBriefingAction;
use App\Filament\Resources\Briefings\BriefingResource;
use App\Models\Briefing;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Override;

final class EditBriefing extends EditRecord
{
    protected static string $resource = BriefingResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete Briefing')
                ->action(function (DeleteAction $action): void {
                    $record = $this->getRecord();

                    if (! $record instanceof Briefing) {
                        throw new LogicException('Expected briefing record.');
                    }

                    try {
                        app(DeleteBriefingAction::class)->handle($record);
                    } catch (InvalidArgumentException $invalidArgumentException) {
                        Notification::make()
                            ->danger()
                            ->title($invalidArgumentException->getMessage())
                            ->send();

                        $action->halt();
                    }

                    $action->success();
                    $this->redirect(BriefingResource::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Briefing) {
            throw new LogicException('Expected briefing record.');
        }

        return app(UpdateBriefingAction::class)->handle($record, $data);
    }
}
