<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AiOption;
use App\Models\Briefing;
use App\Models\Promotion;

use function Pest\Laravel\actingAs;

it('shows the filament admin login page to guests', function (): void {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('Sign in');
});

it('redirects guests from admin dashboard to login', function (): void {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('allows active admins to access the admin dashboard', function (): void {
    $admin = Admin::factory()->create();

    actingAs($admin, 'admin_web');

    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('Dashboard');
});

it('renders core filament resource pages for authenticated admins', function (string $path): void {
    $admin = Admin::factory()->create();

    actingAs($admin, 'admin_web');

    $this->get($path)->assertSuccessful();
})->with([
    '/admin/promotions',
    '/admin/briefings',
    '/admin/ai-options',
]);

it('renders create pages for core resources', function (string $path): void {
    $admin = Admin::factory()->create();

    actingAs($admin, 'admin_web');

    $this->get($path)->assertSuccessful();
})->with([
    '/admin/promotions/create',
    '/admin/briefings/create',
    '/admin/ai-options/create',
]);

it('renders edit pages for core resources', function (): void {
    $admin = Admin::factory()->create();

    $promotion = Promotion::factory()->create();
    $briefing = Briefing::factory()->create();
    $aiOption = AiOption::factory()->create();

    actingAs($admin, 'admin_web');

    $this->get(sprintf('/admin/promotions/%d/edit', $promotion->id))->assertSuccessful();
    $this->get(sprintf('/admin/briefings/%d/edit', $briefing->id))->assertSuccessful();
    $this->get(sprintf('/admin/ai-options/%d/edit', $aiOption->id))->assertSuccessful();
});
