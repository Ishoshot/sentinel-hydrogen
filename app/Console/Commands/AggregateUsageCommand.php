<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Usage\AggregateUsage;
use App\Services\Queue\JobContext;
use App\Services\Queue\QueueResolver;
use Illuminate\Console\Command;

final class AggregateUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:aggregate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch the monthly usage aggregation job.';

    /**
     * Execute the console command.
     */
    public function handle(QueueResolver $queueResolver): int
    {

        $this->info('Dispatching usage aggregation job.');

        $resolution = $queueResolver->resolve(JobContext::forSystemJob(AggregateUsage::class));

        AggregateUsage::dispatch()->onQueue($resolution->queue->value);

        $this->info('Usage aggregation job dispatched.');

        return self::SUCCESS;
    }
}
