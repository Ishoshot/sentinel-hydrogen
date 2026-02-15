<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\Briefings\BriefingDeliveryNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

it('sends via mail channel', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $generation = BriefingGeneration::factory()->completed()->create();

    $user->notify(new BriefingDeliveryNotification($generation));

    Notification::assertSentTo($user, BriefingDeliveryNotification::class, function ($notification, $channels): bool {
        return in_array('mail', $channels, true);
    });
});

it('builds mail message with correct subject using briefing title and workspace name', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Acme Corp']);
    $briefing = Briefing::factory()->create(['title' => 'Weekly Summary']);
    $generation = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($briefing)
        ->completed()
        ->create();

    $notification = new BriefingDeliveryNotification($generation);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class)
        ->and($mailMessage->subject)->toBe('Weekly Summary - Acme Corp');
});

it('uses fallback subject when briefing or workspace is missing', function (): void {
    $generation = BriefingGeneration::factory()->completed()->create();

    // Force null relationships to test fallback behavior
    $generation->setRelation('briefing', null);
    $generation->setRelation('workspace', null);

    $notification = new BriefingDeliveryNotification($generation);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toBe('Briefing - Sentinel');
});

it('includes generation data in mail view', function (): void {
    $workspace = Workspace::factory()->create();
    $briefing = Briefing::factory()->create();
    $generation = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($briefing)
        ->completed()
        ->create([
            'narrative' => 'Great week of progress.',
            'achievements' => [['title' => 'First PR', 'description' => 'Merged first PR']],
            'excerpts' => ['slack' => 'Summary here'],
        ]);

    $notification = new BriefingDeliveryNotification($generation);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->viewData['generation']->id)->toBe($generation->id)
        ->and($mailMessage->viewData['narrative'])->toBe('Great week of progress.')
        ->and($mailMessage->viewData['achievements'])->toBeArray()
        ->and($mailMessage->viewData['excerpts'])->toBeArray();
});

it('returns correct array representation', function (): void {
    $generation = BriefingGeneration::factory()->completed()->create();

    $notification = new BriefingDeliveryNotification($generation);
    $user = User::factory()->create();

    $array = $notification->toArray($user);

    expect($array)->toBe([
        'type' => 'briefing_delivery',
        'generation_id' => $generation->id,
        'briefing_id' => $generation->briefing_id,
    ]);
});

it('handles null achievements and excerpts gracefully in mail', function (): void {
    $workspace = Workspace::factory()->create();
    $briefing = Briefing::factory()->create();
    $generation = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->forBriefing($briefing)
        ->create([
            'narrative' => null,
            'achievements' => null,
            'excerpts' => null,
        ]);

    $notification = new BriefingDeliveryNotification($generation);
    $user = User::factory()->create();

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->viewData['achievements'])->toBe([])
        ->and($mailMessage->viewData['excerpts'])->toBe([]);
});
