<?php

namespace App\Jobs;

use App\Models\BookingAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordBookingAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        BookingAction::query()->create($this->data);
    }
}
