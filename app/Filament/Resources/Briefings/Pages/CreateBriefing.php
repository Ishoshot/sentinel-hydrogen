<?php

declare(strict_types=1);

namespace App\Filament\Resources\Briefings\Pages;

use App\Actions\Admin\Briefings\CreateBriefing as CreateBriefingAction;
use App\Filament\Resources\Briefings\BriefingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateBriefing extends CreateRecord
{
    protected static string $resource = BriefingResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateBriefingAction::class)->handle($data);
    }
}
