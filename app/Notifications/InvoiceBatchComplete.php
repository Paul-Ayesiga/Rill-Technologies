<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceBatchComplete extends Notification implements ShouldQueue
{
    use Queueable;

    protected $batchId;
    protected $totalInvoices;
    protected $successfulInvoices;
    protected $failedInvoices;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $batchId, int $totalInvoices, ?int $successfulInvoices = null, int $failedInvoices = 0)
    {
        $this->batchId = $batchId;
        $this->totalInvoices = $totalInvoices;
        $this->successfulInvoices = $successfulInvoices ?? $totalInvoices;
        $this->failedInvoices = $failedInvoices;
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
     * @return \Illuminate\Notifications\Messages\BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        $success = $this->failedInvoices === 0;
        $message = $success
            ? "All {$this->totalInvoices} invoices have been generated successfully."
            : "{$this->successfulInvoices} of {$this->totalInvoices} invoices were generated successfully.";

        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'batch_id' => $this->batchId,
            'total_invoices' => $this->totalInvoices,
            'successful_invoices' => $this->successfulInvoices,
            'failed_invoices' => $this->failedInvoices,
            'success' => $success,
            'message' => $message,
            'type' => 'invoice_batch_complete',
            'time' => now()->toIso8601String(),
            'data' => [
                'batch_id' => $this->batchId,
                'success' => $success,
                'message' => $message
            ]
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $success = $this->failedInvoices === 0;

        $message = (new MailMessage)
            ->subject($success ? 'Invoice Generation Complete' : 'Invoice Generation Completed with Issues')
            ->greeting($success ? 'Invoice Generation Complete!' : 'Invoice Generation Completed with Issues');

        if ($success) {
            $message->line("All {$this->totalInvoices} invoices have been generated successfully.")
                ->line('You can download them from your billing page.')
                ->action('View Invoices', route('billing'))
                ->line('Thank you for using our service!');
        } else {
            $message->line("{$this->successfulInvoices} of {$this->totalInvoices} invoices were generated successfully.")
                ->line("{$this->failedInvoices} invoices could not be generated.")
                ->line('You can download the successful ones from your billing page.')
                ->action('View Invoices', route('billing'))
                ->line('Our team has been notified about the failed invoices.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $success = $this->failedInvoices === 0;
        $message = $success
            ? "All {$this->totalInvoices} invoices have been generated successfully."
            : "{$this->successfulInvoices} of {$this->totalInvoices} invoices were generated successfully.";

        return [
            'batch_id' => $this->batchId,
            'total_invoices' => $this->totalInvoices,
            'successful_invoices' => $this->successfulInvoices,
            'failed_invoices' => $this->failedInvoices,
            'success' => $success,
            'message' => $message,
            'type' => 'invoice_batch_complete',
            'data' => [
                'batch_id' => $this->batchId,
                'success' => $success,
                'message' => $message
            ]
        ];
    }
}
