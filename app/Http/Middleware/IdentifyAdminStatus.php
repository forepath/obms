<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Shop\OrderQueue\ShopOrderQueue;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class IdentifyAdminStatus.
 *
 * This class is the middleware for identifying the admin status.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class IdentifyAdminStatus
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
        $request->attributes->add([
            'badges' => (object) [
                'orders' => ShopOrderQueue::where('invalid', '=', false)
                    ->where('approved', '=', true)
                    ->where('disapproved', '=', false)
                    ->where('setup', '=', false)
                    ->where('deleted', '=', false)
                    ->where('fails', '<', 3)
                    ->count(),
                'tickets' => SupportTicket::where('status', '=', 'open')
                    ->where(function (Builder $builder) {
                        return $builder->where('category_id', '=', 0)
                            ->orWhereNull('category_id')
                            ->orWhereHas('category', function (Builder $builder) {
                                return $builder->whereHas('assignments', function (Builder $builder) {
                                    return $builder->where('user_id', '=', Auth::id());
                                });
                            });
                    })
                    ->get()
                    ->filter(function (SupportTicket $ticket) {
                        $lastMessage = $ticket->messages->filter(function (SupportTicketMessage $message) {
                            return !$message->note;
                        })->last();

                        return !in_array($lastMessage->user?->role, [
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
