<?php

namespace App\Jobs;

use App\Actions\Games\FinishRound;
use App\Models\Round;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EndRound implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    public int $uniqueFor = 300;

    public function __construct(public int $roundId) {}

    /**
     * Execute the job.
     */
    public function handle(FinishRound $finishRound): void
    {
        $round = Round::query()->find($this->roundId);

        if ($round) {
            $finishRound->handle($round, $round->ends_at);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->roundId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to end drawing game round.', [
            'round_id' => $this->roundId,
            'exception' => $exception,
        ]);
    }
}
