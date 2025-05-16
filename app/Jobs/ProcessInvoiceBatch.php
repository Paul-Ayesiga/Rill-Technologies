<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\InvoiceBatchProgress;
use App\Jobs\TrackInvoiceBatchProgress;
use App\Jobs\BatchCompleteHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessInvoiceBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $invoiceIds;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $invoiceIds, array $options = [])
    {
        $this->user = $user;
        $this->invoiceIds = $invoiceIds;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create an array of jobs for each invoice
            $jobs = [];
            foreach ($this->invoiceIds as $invoiceId) {
                $jobs[] = new GenerateInvoicePdf($this->user, $invoiceId, $this->options);
            }

            // Create a batch of jobs without using closures
            $batch = Bus::batch($jobs)
                ->name('Generate Invoices for User ' . $this->user->id)
                ->allowFailures()
                ->dispatch();

            // Store the batch ID for the user to track progress
            Log::info('Invoice batch created', [
                'user_id' => $this->user->id,
                'batch_id' => $batch->id,
                'invoice_count' => count($this->invoiceIds)
            ]);

            // Send an initial progress notification
            $this->user->notify(new InvoiceBatchProgress($batch->id, count($this->invoiceIds), 0));

            // Start tracking the batch progress
            TrackInvoiceBatchProgress::dispatch($this->user, $batch->id, count($this->invoiceIds))
                ->delay(now()->addSeconds(2));

            // Schedule the completion handler as a safety net
            // This will only run if the TrackInvoiceBatchProgress job fails to detect completion
            // Use a unique job ID to prevent duplicate notifications
            // Increase the delay to 10 minutes to ensure the TrackInvoiceBatchProgress has enough time
            BatchCompleteHandler::dispatch(
                $this->user->id,
                $batch->id,
                count($this->invoiceIds)
            )
            ->delay(now()->addMinutes(10))
            ->onQueue('batch-complete-' . $batch->id); // Use a unique queue name based on batch ID

            // Log that we scheduled the safety net
            Log::info('Scheduled safety net batch completion handler', [
                'user_id' => $this->user->id,
                'batch_id' => $batch->id,
                'delay_minutes' => 10
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create invoice batch', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
