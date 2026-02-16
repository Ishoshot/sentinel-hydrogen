<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiOptions\Pages;

use App\Actions\Admin\AiOptions\CreateAiOption as CreateAiOptionAction;
use App\Filament\Resources\AiOptions\AiOptionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateAiOption extends CreateRecord
{
    protected static string $resource = AiOptionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAiOptionAction::class)->handle($data);
    }
}
