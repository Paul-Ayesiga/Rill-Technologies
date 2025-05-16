<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\InvoiceBatchProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchFailedHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $batchId;
    protected $errorMessage;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $batchId, string $errorMessage)
    {
        $this->userId = $userId;
        $this->batchId = $batchId;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Log the batch failure
            Log::error('Invoice batch job failed', [
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'error' => $this->errorMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in batch failed handler', [
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
