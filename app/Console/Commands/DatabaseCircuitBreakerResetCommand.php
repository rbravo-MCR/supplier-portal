<?php

namespace App\Console\Commands;

use App\Modules\System\Application\Services\PrimaryDatabaseCircuitBreaker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:database-circuit-reset')]
#[Description('Reset the primary database circuit breaker.')]
class DatabaseCircuitBreakerResetCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrimaryDatabaseCircuitBreaker $circuitBreaker): int
    {
        $circuitBreaker->reset();

        $this->info('Primary database circuit breaker reset.');

        return self::SUCCESS;
    }
}
