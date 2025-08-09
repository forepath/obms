<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Support\Category\SupportCategory;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Class SupportRun.
 *
 * This class is the helper for ticket run actions.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class SupportRun
{
    /**
     * Get the next available ticket for a ticket run which is not currently
     * locked by another user.
     *
     * @param int|null $category_id
     *
     * @return SupportTicket|null
     */
    public static function nextTicket(?int $category_id): ?SupportTicket
    {
        if (! empty($category_id)) {
            if (
                $category_id > 0 &&
                ! empty($category = SupportCategory::where('id', '=', $category_id)->first()) &&
                $category->assignments->where('user_id', '=', Auth::id())
            ) {
                $tickets = SupportTicket::where('category_id', '=', $category->id)
                    ->where('status', '=', 'open')
                    ->whereDoesntHave('history', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id())
                            ->where('type', '=', 'run')
                            ->where('action', '=', 'opened')
                            ->where('created_at', '>=', Carbon::now()->subHour());
                    })
                    ->whereDoesntHave('run', function (Builder $builder) {
                        return $builder->whereNull('ended_at');
                    })
                    ->orderByDesc('escalated')
                    ->orderBy('hold')
                    ->orderBy('created_at')
                    ->get();
            } elseif ($category_id === 0) {
                $tickets = SupportTicket::where(function (Builder $builder) {
                    return $builder->where('category_id', '=', 0)
                        ->orWhereNull('category_id');
                })
                    ->where('status', '=', 'open')
                    ->whereDoesntHave('history', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id())
                            ->where('type', '=', 'run')
                            ->where('action', '=', 'opened')
                            ->where('created_at', '>=', Carbon::now()->subHour());
                    })
                    ->whereDoesntHave('run', function (Builder $builder) {
                        return $builder->whereNull('ended_at');
                    })
                    ->orderByDesc('escalated')
                    ->orderBy('hold')
                    ->orderBy('created_at')
                    ->get();
            }
        } else {
            $tickets = SupportTicket::where(function (Builder $builder) {
                return $builder->where('category_id', '=', 0)
                        ->orWhereNull('category_id')
                        ->orWhereHas('category', function (Builder $builder) {
                            return $builder->whereHas('assignments', function (Builder $builder) {
                                return $builder->where('user_id', '=', Auth::id());
                            });
                        });
            })
                ->where('status', '=', 'open')
                ->whereDoesntHave('history', function (Builder $builder) {
                    return $builder->where('user_id', '=', Auth::id())
                        ->where('type', '=', 'run')
                        ->where('action', '=', 'opened')
                        ->where('created_at', '>=', Carbon::now()->subHour());
                })
                ->whereDoesntHave('run', function (Builder $builder) {
                    return $builder->whereNull('ended_at');
                })
                ->orderByDesc('escalated')
                ->orderBy('hold')
                ->orderBy('created_at')
                ->get();
        }

        if (empty($tickets)) {
            return null;
        }

        return $tickets->filter(function (SupportTicket $ticket) {
            $lastMessage = $ticket->messages->filter(function (SupportTicketMessage $message) {
                return !$message->note;
            })->last();

            return !in_array($lastMessage->user?->role, [
                'admin',
                'employee',
            ]);
        })->first();
    }
}
