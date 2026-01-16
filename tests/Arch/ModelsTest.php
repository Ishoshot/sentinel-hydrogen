<?php

declare(strict_types=1);

arch('models')
    ->expect('App\Models')
    ->toHaveMethod('casts')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toOnlyBeUsedIn([
        'App\Actions',
        'App\Console',
        'App\Contracts',
        'App\Events',
        'App\Http',
        'App\Jobs',
        'App\Notifications',
        'App\Observers',
        'App\Mail',
        'App\Models',
        'App\Policies',
        'App\Providers',
        'App\Rules',
        'App\Services',
        'Database\Factories',
        'Database\Seeders',
    ]);

arch('ensure factories', function () {
    $models = getModels();

    foreach ($models as $model) {
        /* @var \Illuminate\Database\Eloquent\Factories\HasFactory $model */
        expect($model::factory())
            ->toBeInstanceOf(Illuminate\Database\Eloquent\Factories\Factory::class);
    }
});

/**
 * Get all models in the app/Models directory.
 *
 * @return array<int, class-string<Illuminate\Database\Eloquent\Model>>
 */
function getModels(): array
{
    $models = glob(__DIR__.'/../../app/Models/*.php');

    return collect($models)
        ->map(function ($file) {
            return 'App\Models\\'.basename($file, '.php');
        })->toArray();
}
