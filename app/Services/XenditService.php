<?php

namespace App\Services;

use App\Models\Order;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Illuminate\Support\Facades\Log;

class XenditService
{
    private InvoiceApi $invoiceApi;

    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $this->invoiceApi = new InvoiceApi();
    }

    /**
     * Create a Xendit Invoice for an order
     */
    public function createInvoice(Order $order): string
    {
        try {
            $params = new CreateInvoiceRequest([
                'external_id'      => $order->order_number,
                'amount'           => (float) $order->total_price,
                'payer_email'      => $order->user->email,
                'description'      => 'Payment for Order #' . $order->order_number,
                'invoice_duration' => 86400, // 24 hours
                'success_redirect_url' => config('app.frontend_url') . '/profile',
                'failure_redirect_url' => config('app.frontend_url') . '/profile',
                'currency'         => 'IDR',
            ]);

            $invoice = $this->invoiceApi->createInvoice($params);

            return $invoice->getInvoiceUrl();
        } catch (\Exception $e) {
            Log::error('Xendit Create Invoice Error: ' . $e->getMessage(), [
                'order_number' => $order->order_number,
                'exception'    => $e
            ]);
            throw $e;
        }
    }

    /**
     * Sync invoice status from Xendit to local database
     */
    public function syncInvoice(Order $order): string
    {
        try {
            // Fetch invoices by external_id (order_number)
            $invoices = $this->invoiceApi->getInvoices(null, $order->order_number);

            if (empty($invoices) || count($invoices) === 0) {
                return $order->status;
            }

            // Take the most recent invoice if multiple exist
            $invoice = $invoices[0];
            $xenditStatus = $invoice->getStatus(); // e.g., PENDING, PAID, SETTLED, EXPIRED

            [$paymentStatus, $orderStatus, $paidAt] = $this->resolveStatus($xenditStatus, $order->status);

            if ($order->payment) {
                $order->payment->update([
                    'status'       => $paymentStatus,
                    'paid_at'      => $paidAt,
                    'raw_response' => json_decode((string) $invoice, true),
                ]);
            }

            $order->update([
                'status'  => $orderStatus,
                'paid_at' => $paidAt,
            ]);

            return $orderStatus;
        } catch (\Exception $e) {
            Log::error('Xendit Sync Invoice Error: ' . $e->getMessage(), [
                'order_number' => $order->order_number,
                'exception'    => $e
            ]);
            return $order->status;
        }
    }

    /**
     * Resolve Xendit status to local application statuses
     */
    private function resolveStatus(string $xenditStatus, string $currentOrderStatus): array
    {
        if ($xenditStatus === 'PAID' || $xenditStatus === 'SETTLED') {
            return ['success', 'paid', now()];
        }

        if ($xenditStatus === 'EXPIRED') {
            return ['expired', 'cancelled', null];
        }

        if ($xenditStatus === 'FAILED') {
            return ['failed', 'cancelled', null];
        }

        return ['pending', $currentOrderStatus, null];
    }
}
