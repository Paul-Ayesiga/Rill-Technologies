<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvoiceReady extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoicePath;
    protected $invoiceId;
    protected $userId;
    protected $userName;
    protected $success;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $invoicePath, string $invoiceId, int $userId, string $userName, bool $success = true)
    {
        $this->invoicePath = $invoicePath;
        $this->invoiceId = $invoiceId;
        $this->userId = $userId;
        $this->userName = $userName;
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
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'success' => $this->success,
            'message' => $this->success
                ? "Invoice for {$this->userName} has been generated and is ready for download."
                : "We encountered an issue while generating the invoice for {$this->userName}.",
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->success ? 'Invoice Generated Successfully' : 'Invoice Generation Failed')
            ->greeting($this->success ? 'Invoice Generated Successfully!' : 'Invoice Generation Failed');

        if ($this->success) {
            $message->line("The invoice for {$this->userName} has been generated and is ready for download.")
                ->action('Download Invoice', route('admin.invoice.download', ['path' => $this->invoicePath]))
                ->line('Thank you for using our service!');
        } else {
            $message->line("We encountered an issue while generating the invoice for {$this->userName}.")
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
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'success' => $this->success,
            'message' => $this->success
                ? "Invoice for {$this->userName} has been generated and is ready for download."
                : "We encountered an issue while generating the invoice for {$this->userName}.",
        ];
    }
}
