<?php

namespace App\Jobs\Admin;

use App\Models\User;
use App\Notifications\Admin\AdminInvoiceBatchComplete;
use App\Notifications\Admin\AdminInvoiceBatchProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class TrackAdminInvoiceBatchProgress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $admin;
    protected $user;
    protected $batchId;
    protected $totalJobs;
    protected $lastProgress;

    /**
     * Create a new job instance.
     */
    public function __construct(User $admin, User $user, string $batchId, int $totalJobs, int $lastProgress = 0)
    {
        $this->admin = $admin;
        $this->user = $user;
        $this->batchId = $batchId;
        $this->totalJobs = $totalJobs;
        $this->lastProgress = $lastProgress;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find the batch
            $batch = Bus::findBatch($this->batchId);

            // If batch not found, log and exit
            if (!$batch) {
                Log::warning('Admin invoice batch not found during tracking', [
                    'admin_id' => $this->admin->id,
                    'user_id' => $this->user->id,
                    'batch_id' => $this->batchId
                ]);
                return;
            }

            // Calculate progress
            $processedJobs = $batch->processedJobs();
            $progress = $this->totalJobs > 0 ? round(($processedJobs / $this->totalJobs) * 100) : 0;

            // Log the current progress
            Log::info('Admin invoice batch progress', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'batch_id' => $this->batchId,
                'processed' => $processedJobs,
                'total' => $this->totalJobs,
                'progress' => $progress,
                'last_progress' => $this->lastProgress
            ]);

            // Only send notifications at 50% and 100% to reduce notification spam
            if (($progress >= 50 && $this->lastProgress < 50) || ($progress >= 100 && $this->lastProgress < 100)) {
                // Send progress notification
                $this->admin->notify(new AdminInvoiceBatchProgress(
                    $this->batchId,
                    $this->totalJobs,
                    $processedJobs,
                    $this->user->id,
                    $this->user->name
                ));

                // Update last progress
                $this->lastProgress = $progress;
            }

            // If batch is finished, send completion notification
            if ($batch->finished()) {
                Log::info('Admin invoice batch completed', [
                    'admin_id' => $this->admin->id,
                    'user_id' => $this->user->id,
                    'batch_id' => $this->batchId,
                    'processed' => $processedJobs,
                    'total' => $this->totalJobs,
                    'failed' => $batch->failedJobs
                ]);

                // Send completion notification
                $this->admin->notify(new AdminInvoiceBatchComplete(
                    $this->batchId,
                    $this->totalJobs,
                    $batch->failedJobs,
                    $this->user->id,
                    $this->user->name
                ));

                return;
            }

            // If not finished, check again in a few seconds
            TrackAdminInvoiceBatchProgress::dispatch(
                $this->admin,
                $this->user,
                $this->batchId,
                $this->totalJobs,
                $this->lastProgress
            )->delay(now()->addSeconds(5));

        } catch (\Exception $e) {
            Log::error('Error tracking admin invoice batch progress', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
