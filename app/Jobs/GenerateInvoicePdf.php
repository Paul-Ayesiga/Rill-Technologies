<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\InvoiceReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $invoiceId;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $invoiceId, array $options = [])
    {
        $this->user = $user;
        $this->invoiceId = $invoiceId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Set default options if not provided
            if (empty($this->options)) {
                $this->options = [
                    'vendor' => config('app.name'),
                    'product' => 'Subscription Service',
                    'street' => config('invoice.street', 'Main Street 1'),
                    'location' => config('invoice.location', '2000 Antwerp, Belgium'),
                    'phone' => config('invoice.phone', '+32 499 00 00 00'),
                    'email' => config('invoice.email', 'info@example.com'),
                    'url' => config('app.url'),
                    'vendorVat' => config('invoice.vat', 'BE123456789'),
                ];
            }

            // Generate the invoice PDF
            $invoicePdf = $this->user->downloadInvoice($this->invoiceId, $this->options);

            // Create a unique filename
            $filename = 'invoice_' . $this->invoiceId . '_' . time() . '.pdf';

            // Store the PDF in a private storage location
            Storage::disk('private')->put('invoices/' . $this->user->id . '/' . $filename, $invoicePdf);

            // Get the storage path for the invoice
            $invoicePath = 'invoices/' . $this->user->id . '/' . $filename;

            // Notify the user that the invoice is ready
            $notification = new InvoiceReady($invoicePath, $this->invoiceId);
            $this->user->notify($notification);

            Log::info('Invoice PDF generated successfully', [
                'user_id' => $this->user->id,
                'invoice_id' => $this->invoiceId,
                'path' => $invoicePath
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
                'user_id' => $this->user->id,
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Notify the user about the failure
            $this->user->notify(new InvoiceReady(null, $this->invoiceId, false));
        }
    }
}
