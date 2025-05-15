<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceReady extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoicePath;
    protected $invoiceId;
    protected $success;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $invoicePath, string $invoiceId, bool $success = true)
    {
        $this->invoicePath = $invoicePath;
        $this->invoiceId = $invoiceId;
        $this->success = $success;
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
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'invoice_id' => $this->invoiceId,
            'invoice_path' => $this->invoicePath,
            'success' => $this->success,
            'message' => $this->success
                ? 'Your invoice has been generated and is ready for download.'
                : 'We encountered an issue while generating your invoice.',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->success ? 'Your Invoice is Ready' : 'Invoice Generation Failed')
            ->greeting($this->success ? 'Your Invoice is Ready!' : 'Invoice Generation Failed');

        if ($this->success) {
            $message->line('Your invoice has been generated and is ready for download.')
                ->action('Download Invoice', route('invoice.download', ['path' => $this->invoicePath]))
                ->line('Thank you for using our service!');
        } else {
            $message->line('We encountered an issue while generating your invoice.')
                ->line('Our team has been notified and will look into this issue.')
                ->line('Please try again later or contact our support team if the problem persists.');
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
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_path' => $this->invoicePath,
            'success' => $this->success,
            'message' => $this->success
                ? 'Your invoice has been generated and is ready for download.'
                : 'We encountered an issue while generating your invoice.',
        ];
    }
}
