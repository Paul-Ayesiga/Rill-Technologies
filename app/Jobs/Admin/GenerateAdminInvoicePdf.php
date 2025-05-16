<?php

namespace App\Jobs\Admin;

use App\Models\User;
use App\Notifications\Admin\AdminInvoiceReady;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateAdminInvoicePdf implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $admin;
    protected $user;
    protected $invoiceId;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $admin, User $user, string $invoiceId, array $options = [])
    {
        $this->admin = $admin;
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

            // Generate the invoice PDF using the user's account
            $invoicePdf = $this->user->downloadInvoice($this->invoiceId, $this->options);

            // Create a unique filename
            $filename = 'admin_invoice_' . $this->user->id . '_' . $this->invoiceId . '_' . time();

            // Add batch ID to filename if this is part of a batch
            if ($this->batch()) {
                $filename .= '_batch_' . $this->batch()->id;
            }

            // Add file extension
            $filename .= '.pdf';

            // Store the PDF in a private storage location for the admin
            Storage::disk('private')->put('admin_invoices/' . $this->admin->id . '/' . $filename, $invoicePdf);

            // Get the storage path for the invoice
            $invoicePath = 'admin_invoices/' . $this->admin->id . '/' . $filename;

            // Check if this job is part of a batch
            if ($this->batch()) {
                // If it's part of a batch, we'll let the batch handler send notifications
                Log::info('Admin invoice PDF generated successfully (batch job)', [
                    'admin_id' => $this->admin->id,
                    'user_id' => $this->user->id,
                    'invoice_id' => $this->invoiceId,
                    'path' => $invoicePath,
                    'batch_id' => $this->batch()->id
                ]);
            } else {
                // If it's not part of a batch, send individual notification
                $notification = new AdminInvoiceReady($invoicePath, $this->invoiceId, $this->user->id, $this->user->name);
                $this->admin->notify($notification);

                Log::info('Admin invoice PDF generated successfully', [
                    'admin_id' => $this->admin->id,
                    'user_id' => $this->user->id,
                    'invoice_id' => $this->invoiceId,
                    'path' => $invoicePath
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate admin invoice PDF', [
                'admin_id' => $this->admin->id,
                'user_id' => $this->user->id,
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this job is part of a batch
            if (!$this->batch()) {
                // Only notify for individual jobs, not batch jobs
                $this->admin->notify(new AdminInvoiceReady(null, $this->invoiceId, $this->user->id, $this->user->name, false));
            }

            // If this is part of a batch, we need to fail the job
            if ($this->batch()) {
                $this->fail($e);
            }
        }
    }
}
