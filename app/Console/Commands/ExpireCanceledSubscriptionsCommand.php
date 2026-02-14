<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscriptions\ExpireCanceledSubscriptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ExpireCanceledSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire-canceled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downgrade workspaces with expired canceled subscriptions to Foundation plan.';

    /**
     * Execute the console command.
     */
    public function handle(ExpireCanceledSubscriptions $expireCanceledSubscriptions): int
    {
        $this->info('Checking for expired canceled subscriptions...');

        $result = $expireCanceledSubscriptions->handle();
        $expiredCount = $result['count'];

        foreach ($result['expired'] as $expiredSubscription) {
            $this->line(sprintf(
                '  Expired subscription #%s for workspace #%s',
                $expiredSubscription['subscription_id'],
                $expiredSubscription['workspace_id'],
            ));
        }

        $this->info(sprintf('Done. %d expired subscription(s) downgraded to Foundation.', $expiredCount));

        Log::info('Expired canceled subscriptions processed.', ['count' => $expiredCount]);

        return self::SUCCESS;
    }
}
