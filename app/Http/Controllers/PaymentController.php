<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\PaymentGateways;
use App\Models\Accounting\Invoice\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Check payment response.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function customer_check(Request $request): RedirectResponse
    {
        return PaymentGateways::check($request->payment_method, $request->payment_type);
    }

    /**
     * Check payment response pingback.
     *
     * @param Request $request
     */
    public function pingback(Request $request): void
    {
        PaymentGateways::pingback($request->payment_method, $request->payment_type);
    }

    /**
     * Generate payment response.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function customer_response(Request $request): RedirectResponse
    {
        if ($request->payment_type == 'prepaid') {
            $baseRedirect = redirect()->route('customer.profile.transactions');
        } else {
            $baseRedirect = redirect()->route('customer.invoices');
        }

        if ($request->payment_status == 'success') {
            $redirect = $baseRedirect->with('success', __('interface.messages.payment_successful_booked'));
        } elseif ($request->payment_status == 'failure') {
            $redirect = $baseRedirect->with('warning', __('interface.messages.payment_failed'));
        } elseif ($request->payment_status == 'waiting') {
            $redirect = $baseRedirect->with('success', __('interface.messages.payment_waiting_booked'));
        } else {
            $redirect = $baseRedirect->with('warning', __('interface.misc.something_wrong_notice'));
        }

        return $redirect;
    }

    /**
     * Initialize a deposit payment.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function customer_initialize_invoice(Request $request): RedirectResponse
    {
        /* @var Invoice|null $invoice */
        if (! empty($invoice = Invoice::find($request->invoice_id))) {
            return PaymentGateways::initialize($request->payment_method, $invoice->grossSumDiscounted, 'invoice', $invoice);
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Initialize a deposit payment.
     *
     * @param Request $request
     */
    public function customer_initialize_deposit(Request $request): RedirectResponse
    {
        return PaymentGateways::initialize($request->payment_method, $request->amount);
    }
}
