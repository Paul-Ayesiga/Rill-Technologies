<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvoiceBatchProgress extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batchId;
    protected $totalJobs;
    protected $processedJobs;
    protected $userId;
    protected $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $batchId, int $totalJobs, int $processedJobs, int $userId, string $userName)
    {
        $this->batchId = $batchId;
        $this->totalJobs = $totalJobs;
        $this->processedJobs = $processedJobs;
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
        // Only send email for the initial notification (0 processed)
        if ($this->processedJobs === 0) {
            return ['database', 'broadcast'];
        }
        
        return ['database', 'broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        $progress = $this->totalJobs > 0 ? round(($this->processedJobs / $this->totalJobs) * 100) : 0;
        
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'batch_id' => $this->batchId,
            'total_jobs' => $this->totalJobs,
            'processed_jobs' => $this->processedJobs,
            'progress' => $progress,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'message' => $this->processedJobs === 0
                ? "Started generating {$this->totalJobs} invoices for {$this->userName}."
                : "Generated {$this->processedJobs} of {$this->totalJobs} invoices for {$this->userName} ({$progress}%).",
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $progress = $this->totalJobs > 0 ? round(($this->processedJobs / $this->totalJobs) * 100) : 0;
        
        return (new MailMessage)
            ->subject("Invoice Batch Processing Started for {$this->userName}")
            ->greeting('Invoice Batch Processing Started')
            ->line("We've started generating {$this->totalJobs} invoices for {$this->userName}.")
            ->line("You'll receive progress updates and a completion notification when all invoices are ready.")
            ->line("Current progress: {$progress}%");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $progress = $this->totalJobs > 0 ? round(($this->processedJobs / $this->totalJobs) * 100) : 0;
        
        return [
            'batch_id' => $this->batchId,
            'total_jobs' => $this->totalJobs,
            'processed_jobs' => $this->processedJobs,
            'progress' => $progress,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'message' => $this->processedJobs === 0
                ? "Started generating {$this->totalJobs} invoices for {$this->userName}."
                : "Generated {$this->processedJobs} of {$this->totalJobs} invoices for {$this->userName} ({$progress}%).",
        ];
    }
}
