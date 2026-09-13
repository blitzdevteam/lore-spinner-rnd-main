<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;

final class StripeSubscriptionInvoiceHistory
{
    /**
     * @return array{
     *     invoices:list<array{
     *         id:string,
     *         number:string,
     *         date:\Carbon\CarbonInterface,
     *         total:string,
     *         status:string,
     *         hosted_invoice_url:?string
     *     }>,
     *     unavailable:?string
     * }
     */
    public function for(Subscription $subscription): array
    {
        $user = $subscription->user;

        if (! $user instanceof User || blank($user->stripe_id)) {
            return [
                'invoices' => [],
                'unavailable' => null,
            ];
        }

        if (blank(config('cashier.secret'))) {
            return [
                'invoices' => [],
                'unavailable' => 'Stripe invoice access is not configured.',
            ];
        }

        try {
            return [
                'invoices' => $subscription
                    ->invoicesIncludingPending(['limit' => 100])
                    ->map(fn (Invoice $invoice): array => $this->invoiceData($invoice))
                    ->values()
                    ->all(),
                'unavailable' => null,
            ];
        } catch (ApiErrorException $exception) {
            report($exception);

            return [
                'invoices' => [],
                'unavailable' => 'Stripe could not load invoice history. The local subscription record remains available.',
            ];
        }
    }

    /**
     * @return array{
     *     id:string,
     *     number:string,
     *     date:\Carbon\CarbonInterface,
     *     total:string,
     *     status:string,
     *     hosted_invoice_url:?string
     * }
     */
    private function invoiceData(Invoice $invoice): array
    {
        $stripeInvoice = $invoice->asStripeInvoice();
        $hostedInvoiceUrl = $stripeInvoice->hosted_invoice_url;

        return [
            'id' => (string) $stripeInvoice->id,
            'number' => is_string($stripeInvoice->number) && filled($stripeInvoice->number)
                ? $stripeInvoice->number
                : (string) $stripeInvoice->id,
            'date' => $invoice->date(),
            'total' => $invoice->total(),
            'status' => is_string($stripeInvoice->status) ? $stripeInvoice->status : 'unknown',
            'hosted_invoice_url' => is_string($hostedInvoiceUrl) && filter_var($hostedInvoiceUrl, FILTER_VALIDATE_URL)
                ? $hostedInvoiceUrl
                : null,
        ];
    }
}
