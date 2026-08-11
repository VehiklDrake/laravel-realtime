<?php

namespace App\Jobs;

use App\Actions\Games\StartNextRound;
use App\Models\Round;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdvanceRound implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(public int $roundId) {}

    /**
     * Execute the job.
     */
    public function handle(StartNextRound $startNextRound): void
    {
        $round = Round::query()->find($this->roundId);

        if ($round) {
            $startNextRound->handle($round);
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to advance drawing game round.', [
            'round_id' => $this->roundId,
            'exception' => $exception,
        ]);
    }
}
