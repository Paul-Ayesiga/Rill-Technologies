<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateInvoicePdf;
use App\Jobs\ProcessInvoiceBatch;
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
     * Generate multiple invoices in a batch.
     */
    public function generateBatch(Request $request)
    {
        $user = Auth::user();

        // Validate the request
        $validated = $request->validate([
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'required|string'
        ]);

        $invoiceIds = $validated['invoice_ids'];

        // Log the batch request
        Log::info('Batch invoice generation requested', [
            'user_id' => $user->id,
            'invoice_count' => count($invoiceIds)
        ]);

        // Dispatch the batch processing job
        ProcessInvoiceBatch::dispatch($user, $invoiceIds, [
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
                'message' => 'Batch invoice generation has been queued. You will be notified of progress and completion.',
                'invoice_count' => count($invoiceIds)
            ]);
        }

        return back()->with('success', 'Batch invoice generation has been queued. You will be notified of progress and completion.');
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

    /**
     * Download all invoices from a batch.
     * This creates a zip file containing all the invoices from the batch.
     */
    public function downloadBatch(Request $request, string $batchId)
    {
        $user = Auth::user();

        // Log the requested batch ID
        Log::info('Batch invoice download requested', [
            'user_id' => $user->id,
            'batch_id' => $batchId
        ]);

        try {
            // Find all invoice files for this user and batch
            $invoiceDir = 'invoices/' . $user->id;

            // Check if the directory exists
            if (!Storage::disk('private')->exists($invoiceDir)) {
                Log::error('Invoice directory not found', [
                    'user_id' => $user->id,
                    'directory' => $invoiceDir
                ]);

                return back()->with('error', 'No invoices found for download.');
            }

            // Get all files in the directory
            $files = Storage::disk('private')->files($invoiceDir);

            // Filter files to only include those from this batch
            // The batch ID is included in the filename when generated
            $batchFiles = [];
            foreach ($files as $file) {
                // Check if the file is a PDF and contains the batch ID
                if (str_ends_with($file, '.pdf') && str_contains($file, $batchId)) {
                    $batchFiles[] = $file;
                }
            }

            // If no files found, redirect back with an error
            if (empty($batchFiles)) {
                Log::warning('No invoice files found for batch', [
                    'user_id' => $user->id,
                    'batch_id' => $batchId
                ]);

                return back()->with('error', 'No invoices found for this batch.');
            }

            // If only one file, return it directly
            if (count($batchFiles) === 1) {
                $file = $batchFiles[0];
                $filename = basename($file);

                return response()->download(
                    Storage::disk('private')->path($file),
                    $filename,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                    ]
                );
            }

            // For multiple files, create a zip archive
            $zipFileName = 'invoices_batch_' . substr($batchId, 0, 8) . '_' . date('Ymd_His') . '.zip';
            $zipFilePath = storage_path('app/private/temp/' . $zipFileName);

            // Create the temp directory if it doesn't exist
            if (!Storage::disk('private')->exists('temp')) {
                Storage::disk('private')->makeDirectory('temp');
            }

            // Create a new zip archive
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== true) {
                Log::error('Failed to create zip archive', [
                    'user_id' => $user->id,
                    'batch_id' => $batchId
                ]);

                return back()->with('error', 'Failed to create download archive.');
            }

            // Add each file to the zip
            foreach ($batchFiles as $file) {
                $filename = basename($file);
                $zip->addFile(Storage::disk('private')->path($file), $filename);
            }

            // Close the zip
            $zip->close();

            // Return the zip file for download
            return response()->download(
                $zipFilePath,
                $zipFileName,
                [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"'
                ]
            )->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Failed to download batch invoices', [
                'user_id' => $user->id,
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to download invoices. Please try again later.');
        }
    }
}
