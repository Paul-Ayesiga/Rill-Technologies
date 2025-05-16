<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceBatchProgress extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batchId;
    protected $totalInvoices;
    protected $processedInvoices;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $batchId, int $totalInvoices, int $processedInvoices)
    {
        $this->batchId = $batchId;
        $this->totalInvoices = $totalInvoices;
        $this->processedInvoices = $processedInvoices;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only broadcast for real-time updates, no mail or database
        return ['broadcast'];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        $progress = $this->totalInvoices > 0 
            ? round(($this->processedInvoices / $this->totalInvoices) * 100) 
            : 0;

        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'batch_id' => $this->batchId,
            'total_invoices' => $this->totalInvoices,
            'processed_invoices' => $this->processedInvoices,
            'progress' => $progress,
            'message' => "Processing invoices: {$this->processedInvoices} of {$this->totalInvoices} complete",
            'type' => 'invoice_batch_progress',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $progress = $this->totalInvoices > 0 
            ? round(($this->processedInvoices / $this->totalInvoices) * 100) 
            : 0;

        return [
            'batch_id' => $this->batchId,
            'total_invoices' => $this->totalInvoices,
            'processed_invoices' => $this->processedInvoices,
            'progress' => $progress,
            'message' => "Processing invoices: {$this->processedInvoices} of {$this->totalInvoices} complete",
            'type' => 'invoice_batch_progress',
        ];
    }
}
