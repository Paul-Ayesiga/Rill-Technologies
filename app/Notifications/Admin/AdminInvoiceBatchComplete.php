<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvoiceBatchComplete extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batchId;
    protected $totalJobs;
    protected $failedJobs;
    protected $userId;
    protected $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $batchId, int $totalJobs, int $failedJobs, int $userId, string $userName)
    {
        $this->batchId = $batchId;
        $this->totalJobs = $totalJobs;
        $this->failedJobs = $failedJobs;
        $this->userId = $userId;
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        $successJobs = $this->totalJobs - $this->failedJobs;
        
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'batch_id' => $this->batchId,
            'total_jobs' => $this->totalJobs,
            'success_jobs' => $successJobs,
            'failed_jobs' => $this->failedJobs,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'message' => $this->failedJobs > 0
                ? "Completed generating invoices for {$this->userName}. {$successJobs} succeeded, {$this->failedJobs} failed."
                : "Successfully generated all {$this->totalJobs} invoices for {$this->userName}.",
            'time' => now()->toIso8601String(),
            'download_url' => route('admin.invoice.download-batch', ['batchId' => $this->batchId]),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $successJobs = $this->totalJobs - $this->failedJobs;
        $message = (new MailMessage)
            ->subject("Invoice Batch Completed for {$this->userName}")
            ->greeting('Invoice Batch Processing Complete');

        if ($this->failedJobs > 0) {
            $message->line("We've completed generating invoices for {$this->userName}.")
                ->line("{$successJobs} invoices were generated successfully.")
                ->line("{$this->failedJobs} invoices failed to generate.");
        } else {
            $message->line("We've successfully generated all {$this->totalJobs} invoices for {$this->userName}.");
        }

        $message->action('Download All Invoices', route('admin.invoice.download-batch', ['batchId' => $this->batchId]))
            ->line('Thank you for using our service!');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $successJobs = $this->totalJobs - $this->failedJobs;
        
        return [
            'batch_id' => $this->batchId,
            'total_jobs' => $this->totalJobs,
            'success_jobs' => $successJobs,
            'failed_jobs' => $this->failedJobs,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'message' => $this->failedJobs > 0
                ? "Completed generating invoices for {$this->userName}. {$successJobs} succeeded, {$this->failedJobs} failed."
                : "Successfully generated all {$this->totalJobs} invoices for {$this->userName}.",
            'download_url' => route('admin.invoice.download-batch', ['batchId' => $this->batchId]),
        ];
    }
}
