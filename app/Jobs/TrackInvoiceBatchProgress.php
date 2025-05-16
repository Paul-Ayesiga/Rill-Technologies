<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\InvoiceBatchProgress;
use App\Jobs\BatchCompleteHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class TrackInvoiceBatchProgress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $batchId;
    protected $totalInvoices;
    protected $lastProcessed;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $batchId, int $totalInvoices, int $lastProcessed = 0)
    {
        $this->user = $user;
        $this->batchId = $batchId;
        $this->totalInvoices = $totalInvoices;
        $this->lastProcessed = $lastProcessed;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [
            // Use a unique lock key for this batch and set a longer timeout
            (new WithoutOverlapping('batch-'.$this->batchId))
                ->dontRelease() // Don't release the job back to the queue if it's locked
                ->expireAfter(60) // Lock expires after 60 seconds
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the batch
            $batch = Bus::findBatch($this->batchId);

            // If the batch doesn't exist, don't continue
            if (!$batch) {
                return;
            }

            // If the batch is finished, send a final update and don't continue
            if ($batch->finished()) {
                // Log that we detected batch completion
                Log::info('Batch finished detected', [
                    'user_id' => $this->user->id,
                    'batch_id' => $this->batchId,
                    'processed_jobs' => $batch->processedJobs(),
                    'total_invoices' => $this->totalInvoices,
                    'failed_jobs' => $batch->failedJobs
                ]);

                // Always send a final progress notification with 100%
                $this->user->notify(new InvoiceBatchProgress(
                    $this->batchId,
                    $this->totalInvoices,
                    $this->totalInvoices // Force 100% progress
                ));

                // Add a small delay before sending the completion notification
                // This ensures the progress notification is processed first
                sleep(1);

                // Dispatch the batch complete handler
                // Use a unique queue name to prevent duplicate notifications
                $completeHandler = new BatchCompleteHandler(
                    $this->user->id,
                    $this->batchId,
                    $this->totalInvoices,
                    $batch->failedJobs
                );

                // Define a constant to indicate this is a direct call
                if (!defined('DIRECT_CALL')) {
                    define('DIRECT_CALL', true);
                }

                // Dispatch directly without using a queue to ensure it runs immediately
                $completeHandler->handle();

                // Log that we dispatched the completion handler
                Log::info('Batch completion handler dispatched', [
                    'user_id' => $this->user->id,
                    'batch_id' => $this->batchId
                ]);

                // Also send a direct notification to ensure it's received
                // This is a backup in case the BatchCompleteHandler doesn't work
                if ($batch->failedJobs > 0) {
                    $this->user->notify(new \App\Notifications\InvoiceBatchComplete(
                        $this->batchId,
                        $this->totalInvoices,
                        $this->totalInvoices - $batch->failedJobs,
                        $batch->failedJobs
                    ));
                } else {
                    $this->user->notify(new \App\Notifications\InvoiceBatchComplete(
                        $this->batchId,
                        $this->totalInvoices
                    ));
                }

                return;
            }

            // Get the current progress
            $processedJobs = $batch->processedJobs();

            // Only send a notification if there's been progress
            if ($processedJobs > $this->lastProcessed) {
                // Send a progress notification
                $this->user->notify(new InvoiceBatchProgress($this->batchId, $this->totalInvoices, $processedJobs));

                // Log the progress
                Log::info('Invoice batch progress', [
                    'user_id' => $this->user->id,
                    'batch_id' => $this->batchId,
                    'processed' => $processedJobs,
                    'total' => $this->totalInvoices,
                    'progress' => round(($processedJobs / $this->totalInvoices) * 100)
                ]);
            }

            // If the batch is not finished, schedule another check
            if (!$batch->finished()) {
                // Schedule the next check in 3 seconds
                TrackInvoiceBatchProgress::dispatch($this->user, $this->batchId, $this->totalInvoices, $processedJobs)
                    ->delay(now()->addSeconds(3));
            }
            // We don't need an else clause here because we already handle batch completion above
        } catch (\Exception $e) {
            Log::error('Error tracking invoice batch progress', [
                'user_id' => $this->user->id,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
