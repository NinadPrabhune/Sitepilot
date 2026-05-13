<?php

namespace App\Observers;

use App\Models\PurchaseInvoice;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Purchase Invoice "created" event.
     */
    public function created(PurchaseInvoice $invoice): void
    {
        // Delay notification and PDF generation until after transaction commits to ensure data is fully available
        DB::afterCommit(function () use ($invoice) {
            try {
                // Refresh model to ensure all fields (including invoice_number) are loaded
                $freshInvoice = $invoice->fresh();
                $this->notificationService->createInvoiceCreatedNotification($freshInvoice);

                // Generate PDF for the invoice
                $this->generateInvoicePdf($freshInvoice);
            } catch (\Exception $e) {
                Log::error('Failed to send invoice created notification', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /**
     * Handle the Purchase Invoice "updated" event.
     */
    public function updated(PurchaseInvoice $invoice): void
    {
        try {
            // Early return if nothing changed
            if (!$invoice->wasChanged()) {
                return;
            }

            // Ignore if only updated_at changed
            if ($invoice->wasChanged(['updated_at'])) {
                return;
            }

            // Explicit status detection
            if ($invoice->wasChanged('status')) {
                $event = 'invoice.status_changed';
                $oldStatus = $invoice->getOriginal('status');
                $newStatus = $invoice->status;

                $this->notificationService->createInvoiceStatusChangedNotification($invoice, $oldStatus, $newStatus, $event);
                return; // IMPORTANT: avoid duplicate "updated"
            }

            // Regular update notification
            $event = 'invoice.updated';
            $this->notificationService->createInvoiceUpdatedNotification($invoice, $event);

            // Regenerate PDF on update (if relevant fields changed)
            $relevantFields = ['invoice_date', 'supplier_id', 'site_id', 'total_amount', 'invoice_type'];
            $hasRelevantChange = false;
            foreach ($relevantFields as $field) {
                if ($invoice->wasChanged($field)) {
                    $hasRelevantChange = true;
                    break;
                }
            }

            if ($hasRelevantChange) {
                DB::afterCommit(function () use ($invoice) {
                    try {
                        $freshInvoice = $invoice->fresh();
                        $this->generateInvoicePdf($freshInvoice);
                    } catch (\Exception $e) {
                        Log::error('Failed to regenerate PDF on invoice update', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send invoice updated notification', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate PDF for Purchase Invoice.
     * This is called from the observer to ensure PDF is generated regardless of how the invoice is created.
     * If PDF already exists, it will be deleted and a new one created.
     */
    private function generateInvoicePdf(PurchaseInvoice $invoice): void
    {
        try {
            // Delete existing PDF if it exists
            if (!empty($invoice->pi_pdf)) {
                try {
                    delete_file($invoice->pi_pdf);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete existing PDF', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $invoice->load([
                'items.material',
                'items.gstMaster',
                'supplier',
                'site',
                'purchaseOrder',
                'creator',
                'workspace'
            ]);

            $workspaceId = $invoice->workspace_id ?? null;
            if (!$workspaceId && function_exists('getActiveWorkSpace')) {
                $workspaceId = getActiveWorkSpace();
            }

            $settings = [];
            $keys = [
                'company_name', 'company_email', 'company_address', 'company_city',
                'company_state', 'company_country', 'company_zipcode', 'company_telephone',
                'registration_number', 'vat_number', 'tax_type', 'company_gst', 'site_rtl',
                'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'bank_ifsc_code',
                'company_logo'
            ];
            foreach ($keys as $key) {
                $settings[$key] = company_setting($key, null, $workspaceId);
            }

            $workspaceDetails = null;
            if ($invoice->workspace) {
                $workspaceDetails = $invoice->workspace;
                $settings['workspace_name'] = $workspaceDetails->name;
            }

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $data['isPdf'] = true;
            $data['purchaseInvoice'] = $invoice;
            $data['settings'] = $settings;
            $data['workspaceDetails'] = $workspaceDetails;

            $html = view('purchase-invoice.print', $data)->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfContent = $dompdf->output();

            $fileName = $invoice->id . '_' . $invoice->invoice_number . '.pdf';

            $uploadPath = 'pdf/purchase-invoice';
            $uploadResult = upload_pdf_content($pdfContent, $uploadPath, $fileName);

            if ($uploadResult['flag'] === 1 && !empty($uploadResult['url'])) {
                $invoice->pi_pdf = $uploadResult['url'];
                $invoice->save();
            }
        } catch (\Exception $e) {
            Log::error('Purchase Invoice PDF Generation Error: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id
            ]);
        }
    }
}
