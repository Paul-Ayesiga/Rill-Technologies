<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateInvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    /**
     * Request an invoice to be generated in the background.
     */
    public function generate(Request $request, string $invoiceId)
    {
        $user = Auth::user();

        // Dispatch the job to generate the invoice PDF in the background
        GenerateInvoicePdf::dispatch($user, $invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription Service',
            'street' => config('invoice.street', 'Main Street 1'),
            'location' => config('invoice.location', '2000 Antwerp, Belgium'),
            'phone' => config('invoice.phone', '+32 499 00 00 00'),
            'email' => config('invoice.email', 'info@example.com'),
            'url' => config('app.url'),
            'vendorVat' => config('invoice.vat', 'BE123456789'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Invoice generation has been queued. You will be notified when it is ready.'
            ]);
        }

        return back()->with('success', 'Invoice generation has been queued. You will be notified when it is ready.');
    }

    /**
     * Download a generated invoice.
     */
    public function download(Request $request, string $path)
    {
        $user = Auth::user();

        // Log the requested path for debugging
        Log::info('Invoice download requested', [
            'user_id' => $user->id,
            'requested_path' => $path
        ]);

        // Extract the invoice ID from the path
        // The path format is typically 'invoices/user_id/invoice_id_timestamp.pdf'
        // We need to extract the invoice ID from this path

        // First, check if this is a full path or just an invoice ID
        if (str_contains($path, 'invoice_')) {
            // This appears to be a filename with the invoice ID embedded
            // Extract the invoice ID from the filename (format: invoice_in_INVOICE_ID_timestamp.pdf)
            preg_match('/invoice_in_([A-Za-z0-9]+)_/', $path, $matches);

            if (isset($matches[1])) {
                $invoiceId = $matches[1];
                Log::info('Extracted invoice ID from filename', [
                    'invoice_id' => $invoiceId
                ]);

                // Use the downloadInvoice method from Cashier
                return $this->downloadInvoiceById($invoiceId);
            }
        }

        // If we couldn't extract an invoice ID, try to serve the file directly
        $fullPath = 'invoices/' . $user->id . '/' . basename($path);

        // Check if the file exists
        if (!Storage::disk('private')->exists($fullPath)) {
            Log::error('Invoice file not found', [
                'user_id' => $user->id,
                'path' => $fullPath
            ]);

            abort(404, 'Invoice not found');
        }

        // Get the filename from the path
        $filename = basename($fullPath);

        // Return the file for download
        return response()->download(
            Storage::disk('private')->path($fullPath),
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    /**
     * Helper method to download an invoice by ID.
     */
    private function downloadInvoiceById(string $invoiceId)
    {
        $user = Auth::user();

        try {
            return $user->downloadInvoice($invoiceId, [
                'vendor' => config('app.name'),
                'product' => 'Subscription Service',
                'street' => config('invoice.street', 'Main Street 1'),
                'location' => config('invoice.location', '2000 Antwerp, Belgium'),
                'phone' => config('invoice.phone', '+32 499 00 00 00'),
                'email' => config('invoice.email', 'info@example.com'),
                'url' => config('app.url'),
                'vendorVat' => config('invoice.vat', 'BE123456789'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download invoice by ID', [
                'user_id' => $user->id,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            abort(404, 'Invoice not found or could not be generated');
        }
    }

    /**
     * Download an invoice directly (synchronous).
     * This is a fallback method for direct downloads.
     */
    public function downloadDirect(Request $request, string $invoiceId)
    {
        $user = Auth::user();

        // Log the requested invoice ID
        Log::info('Direct invoice download requested', [
            'user_id' => $user->id,
            'invoice_id' => $invoiceId
        ]);

        try {
            // Check if the invoice ID already has the 'in_' prefix
            // If not, add it (Stripe invoice IDs always start with 'in_')
            if (!str_starts_with($invoiceId, 'in_')) {
                $invoiceId = 'in_' . $invoiceId;

                Log::info('Added prefix to invoice ID', [
                    'adjusted_invoice_id' => $invoiceId
                ]);
            }

            return $user->downloadInvoice($invoiceId, [
                'vendor' => config('app.name'),
                'product' => 'Subscription Service',
                'street' => config('invoice.street', 'Main Street 1'),
                'location' => config('invoice.location', '2000 Antwerp, Belgium'),
                'phone' => config('invoice.phone', '+32 499 00 00 00'),
                'email' => config('invoice.email', 'info@example.com'),
                'url' => config('app.url'),
                'vendorVat' => config('invoice.vat', 'BE123456789'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download invoice directly', [
                'user_id' => $user->id,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to download invoice. Please try again later.');
        }
    }
}
