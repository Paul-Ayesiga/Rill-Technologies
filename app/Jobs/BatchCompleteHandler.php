<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\InvoiceBatchComplete;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BatchCompleteHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return 'batch_complete_' . $this->batchId;
    }

    protected $userId;
    protected $batchId;
    protected $totalInvoices;
    protected $failedJobs;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $batchId, int $totalInvoices, int $failedJobs = 0)
    {
        $this->userId = $userId;
        $this->batchId = $batchId;
        $this->totalInvoices = $totalInvoices;
        $this->failedJobs = $failedJobs;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create a unique cache key for this batch completion
            $cacheKey = 'batch_complete_' . $this->batchId;

            // Check if we've already processed this batch completion
            // But only if this is the safety net job (not the direct call from TrackInvoiceBatchProgress)
            $isDirectCall = defined('DIRECT_CALL') && DIRECT_CALL === true;

            if (!$isDirectCall && Cache::has($cacheKey)) {
                Log::info('Skipping duplicate batch completion notification', [
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId,
                    'is_direct_call' => $isDirectCall
                ]);
                return;
            }

            // Log that we're processing the batch completion
            Log::info('Processing batch completion notification', [
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'is_direct_call' => $isDirectCall
            ]);

            // Get the user
            $user = User::find($this->userId);

            if (!$user) {
                Log::error('User not found for batch completion', [
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId
                ]);
                return;
            }

            // Log batch completion
            Log::info('Invoice batch completed', [
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'total_invoices' => $this->totalInvoices,
                'failed_jobs' => $this->failedJobs
            ]);

            // Notify the user that all invoices are ready
            if ($this->failedJobs > 0) {
                $user->notify(new InvoiceBatchComplete(
                    $this->batchId,
                    $this->totalInvoices,
                    $this->totalInvoices - $this->failedJobs,
                    $this->failedJobs
                ));
            } else {
                $user->notify(new InvoiceBatchComplete($this->batchId, $this->totalInvoices));
            }

            // Store in cache that we've processed this batch completion
            // Keep it for 1 hour to prevent duplicate notifications
            Cache::put($cacheKey, true, now()->addHour());

            // We don't need to send a final progress notification here
            // It's already handled by the TrackInvoiceBatchProgress job

        } catch (\Exception $e) {
            Log::error('Error in batch complete handler', [
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
