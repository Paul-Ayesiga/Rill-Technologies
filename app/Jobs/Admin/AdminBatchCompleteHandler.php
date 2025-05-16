<?php

namespace App\Jobs\Admin;

use App\Models\User;
use App\Notifications\Admin\AdminInvoiceBatchComplete;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class AdminBatchCompleteHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $adminId;
    protected $userId;
    protected $batchId;
    protected $totalJobs;

    /**
     * Create a new job instance.
     */
    public function __construct(int $adminId, int $userId, string $batchId, int $totalJobs)
    {
        $this->adminId = $adminId;
        $this->userId = $userId;
        $this->batchId = $batchId;
        $this->totalJobs = $totalJobs;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find the admin and user
            $admin = User::find($this->adminId);
            $user = User::find($this->userId);

            if (!$admin) {
                Log::error('Admin not found for batch completion handler', [
                    'admin_id' => $this->adminId,
                    'batch_id' => $this->batchId
                ]);
                return;
            }

            if (!$user) {
                Log::error('User not found for admin batch completion handler', [
                    'admin_id' => $this->adminId,
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId
                ]);
                return;
            }

            // Find the batch
            $batch = Bus::findBatch($this->batchId);

            // If batch not found, assume it's completed and send notification
            if (!$batch) {
                Log::warning('Batch not found in completion handler, assuming completed', [
                    'admin_id' => $this->adminId,
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId
                ]);

                // Send completion notification
                $admin->notify(new AdminInvoiceBatchComplete(
                    $this->batchId,
                    $this->totalJobs,
                    0, // Assume no failed jobs
                    $this->userId,
                    $user->name
                ));

                return;
            }

            // If batch is still running, log but don't send notification
            if (!$batch->finished()) {
                Log::info('Batch still running in completion handler', [
                    'admin_id' => $this->adminId,
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId,
                    'processed' => $batch->processedJobs(),
                    'total' => $this->totalJobs
                ]);
                return;
            }

            // Batch is finished, send completion notification
            Log::info('Sending batch completion notification from safety handler', [
                'admin_id' => $this->adminId,
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'processed' => $batch->processedJobs(),
                'total' => $this->totalJobs,
                'failed' => $batch->failedJobs
            ]);

            // Send completion notification
            $admin->notify(new AdminInvoiceBatchComplete(
                $this->batchId,
                $this->totalJobs,
                $batch->failedJobs,
                $this->userId,
                $user->name
            ));

        } catch (\Exception $e) {
            Log::error('Error in admin batch completion handler', [
                'admin_id' => $this->adminId,
                'user_id' => $this->userId,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
