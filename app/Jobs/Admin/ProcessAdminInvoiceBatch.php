<?php

namespace App\Jobs\Admin;

use App\Models\User;
use App\Notifications\Admin\AdminInvoiceBatchProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessAdminInvoiceBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $admin;
    protected $user;
    protected $invoiceIds;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $admin, User $user, array $invoiceIds, array $options = [])
    {
        $this->admin = $admin;
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
                $jobs[] = new GenerateAdminInvoicePdf($this->admin, $this->user, $invoiceId, $this->options);
            }

            // Create a batch of jobs without using closures
            $batch = Bus::batch($jobs)
                ->name('Generate Admin Invoices for User ' . $this->user->id)
                ->allowFailures()
                ->dispatch();

            // Store the batch ID for the admin to track progress
            Log::info('Admin invoice batch created', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'batch_id' => $batch->id,
                'invoice_count' => count($this->invoiceIds)
            ]);

            // Send an initial progress notification
            $this->admin->notify(new AdminInvoiceBatchProgress($batch->id, count($this->invoiceIds), 0, $this->user->id, $this->user->name));

            // Start tracking the batch progress
            TrackAdminInvoiceBatchProgress::dispatch($this->admin, $this->user, $batch->id, count($this->invoiceIds))
                ->delay(now()->addSeconds(2));

            // Schedule the completion handler as a safety net
            AdminBatchCompleteHandler::dispatch(
                $this->admin->id,
                $this->user->id,
                $batch->id,
                count($this->invoiceIds)
            )
            ->delay(now()->addMinutes(10))
            ->onQueue('admin-batch-complete-' . $batch->id);

            // Log that we scheduled the safety net
            Log::info('Scheduled safety net admin batch completion handler', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'batch_id' => $batch->id,
                'delay_minutes' => 10
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create admin invoice batch', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
