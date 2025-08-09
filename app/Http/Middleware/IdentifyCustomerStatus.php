<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Accounting\Invoice\Invoice;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class IdentifyCustomerStatus.
 *
 * This class is the middleware for identifying the customer status.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class IdentifyCustomerStatus
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $invoices = Invoice::where('user_id', '=', Auth::id())
            ->where('status', '=', 'unpaid')
            ->whereDoesntHave('type', function (Builder $builder) {
                return $builder->where('type', '=', 'prepaid');
            })
            ->get();

        $request->attributes->add([
            'badges' => (object) [
                'invoices' => (object) [
                    'total'   => (clone $invoices)->count(),
                    'overdue' => (clone $invoices)->filter(function (Invoice $invoice) {
                        return $invoice->overdue;
                    })->count(),
                ],
                'tickets' => SupportTicket::whereHas('assignments', function (Builder $builder) {
                    return $builder->where('user_id', '=', Auth::id());
                })
                    ->where('status', '=', 'open')
                    ->get()
                    ->filter(function (SupportTicket $ticket) {
                        $lastMessage = $ticket->messages->filter(function (SupportTicketMessage $message) {
                            return !$message->note;
                        })->last();

                        return in_array($lastMessage->user?->role, [
                            'admin',
                            'employee',
                        ]);
                    })
                    ->count(),
            ],
        ]);

        return $next($request);
    }
}
