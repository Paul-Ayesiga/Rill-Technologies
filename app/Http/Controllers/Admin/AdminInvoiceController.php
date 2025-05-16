<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\GenerateAdminInvoicePdf;
use App\Jobs\Admin\ProcessAdminInvoiceBatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminInvoiceController extends Controller
{
    /**
     * Request an invoice to be generated in the background for a specific user.
     */
    public function generate(Request $request, string $userId, string $invoiceId)
    {
        $admin = Auth::user();
        $user = User::findOrFail($userId);

        // Log the request
        Log::info('Admin requested invoice generation', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'invoice_id' => $invoiceId
        ]);

        // Dispatch the job to generate the invoice PDF in the background
        GenerateAdminInvoicePdf::dispatch($admin, $user, $invoiceId, [
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
     * Generate multiple invoices in a batch for a specific user.
     */
    public function generateBatch(Request $request, string $userId)
    {
        $admin = Auth::user();
        $user = User::findOrFail($userId);

        // Validate the request
        $validated = $request->validate([
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'required|string'
        ]);

        $invoiceIds = $validated['invoice_ids'];

        // Log the batch request
        Log::info('Admin requested batch invoice generation', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'invoice_count' => count($invoiceIds)
        ]);

        // Dispatch the batch processing job
        ProcessAdminInvoiceBatch::dispatch($admin, $user, $invoiceIds, [
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
        $admin = Auth::user();

        // Log the requested path for debugging
        Log::info('Admin invoice download requested', [
            'admin_id' => $admin->id,
            'requested_path' => $path
        ]);

        // Extract the invoice ID and user ID from the path
        // The path format is typically 'admin_invoices/admin_id/user_id/invoice_id_timestamp.pdf'
        
        // Check if the file exists
        $fullPath = 'admin_invoices/' . $admin->id . '/' . basename($path);
        
        if (!Storage::disk('private')->exists($fullPath)) {
            Log::error('Admin invoice file not found', [
                'admin_id' => $admin->id,
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
     * Download all invoices from a batch.
     */
    public function downloadBatch(Request $request, string $batchId)
    {
        $admin = Auth::user();

        // Log the requested batch ID
        Log::info('Admin batch invoice download requested', [
            'admin_id' => $admin->id,
            'batch_id' => $batchId
        ]);

        try {
            // Find all invoice files for this admin and batch
            $invoiceDir = 'admin_invoices/' . $admin->id;

            // Check if the directory exists
            if (!Storage::disk('private')->exists($invoiceDir)) {
                Log::error('Admin invoice directory not found', [
                    'admin_id' => $admin->id,
                    'directory' => $invoiceDir
                ]);

                return back()->with('error', 'No invoices found for download.');
            }

            // Get all files in the directory
            $files = Storage::disk('private')->files($invoiceDir);

            // Filter files to only include those from this batch
            $batchFiles = [];
            foreach ($files as $file) {
                // Check if the file is a PDF and contains the batch ID
                if (str_ends_with($file, '.pdf') && str_contains($file, $batchId)) {
                    $batchFiles[] = $file;
                }
            }

            // If no files found, redirect back with an error
            if (empty($batchFiles)) {
                Log::warning('No admin invoice files found for batch', [
                    'admin_id' => $admin->id,
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
            $zipFileName = 'admin_invoices_batch_' . substr($batchId, 0, 8) . '_' . date('Ymd_His') . '.zip';
            $zipFilePath = storage_path('app/private/temp/' . $zipFileName);

            // Create the temp directory if it doesn't exist
            if (!Storage::disk('private')->exists('temp')) {
                Storage::disk('private')->makeDirectory('temp');
            }

            // Create a new zip archive
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== true) {
                Log::error('Failed to create zip archive for admin', [
                    'admin_id' => $admin->id,
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
            Log::error('Failed to download batch invoices for admin', [
                'admin_id' => $admin->id,
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to download invoices. Please try again later.');
        }
    }
}
