<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Download;
use App\Models\FileManager\File;
use App\Models\Support\Category\SupportCategory;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketAssignment;
use App\Models\Support\SupportTicketFile;
use App\Models\Support\SupportTicketHistory;
use App\Models\Support\SupportTicketMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerSupportController extends Controller
{
    /**
     * Show list of support tickets.
     *
     * @return Renderable
     */
    public function support_index(): Renderable
    {
        return view('customer.support.home', [
            'categories' => SupportCategory::all(),
        ]);
    }

    /**
     * Get list of support tickets.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function support_index_list(Request $request): JsonResponse
    {
        session_write_close();

        $query = SupportTicket::whereHas('assignments', function (Builder $builder) {
            return $builder->where('user_id', '=', Auth::id());
        });

        switch ($request->type) {
            case 'open':
                $query = $query->where('status', '=', 'open');

                break;
            case 'closed':
                $query = $query->where('status', '=', 'closed');

                break;
            case 'locked':
                $query = $query->where('status', '=', 'locked');

                break;
        }

        $totalCount = (clone $query)->count();

        if (! empty($request->search['value'])) {
            $query = $query->where(function (Builder $query) use ($request) {
                return $query->where('id', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('subject', 'LIKE', '%' . $request->search['value'] . '%');
            });
        }

        if (! empty($request->order)) {
            foreach ($request->order as $order) {
                switch ($request->columns[$order['column']]) {
                    case 'subject':
                        $orderBy = 'subject';

                        break;
                    case 'id':
                    default:
                        $orderBy = 'id';

                        break;
                }

                $query = $query->orderBy($orderBy, $order['dir']);
            }
        }

        $filteredCount = (clone $query)->count();

        $query = $query->offset($request->start)
            ->limit($request->length);

        return response()->json([
            'draw'            => (int) $request->draw,
            'recordsTotal'    => $totalCount,
            'recordsFiltered' => $filteredCount,
            'data'            => $query
                ->get()
                ->transform(function (SupportTicket $ticket) {
                    $status = '';

                    switch ($ticket->status) {
                        case 'open':
                            $status = '<span class="badge badge-primary">' . __('interface.status.open') . '</span>';

                            break;
                        case 'closed':
                            $status = '<span class="badge badge-secondary">' . __('interface.status.closed') . '</span>';

                            break;
                        case 'locked':
                            $status = '<span class="badge badge-danger">' . __('interface.status.locked') . '</span>';

                            break;
                    }

                    if ($ticket->escalated) {
                        $status .= (! empty($status) ? ' ' : '') . '<span class="badge badge-warning">' . __('interface.status.escalated') . '</span>';
                    }

                    if ($ticket->hold) {
                        $status .= (! empty($status) ? ' ' : '') . '<span class="badge badge-secondary">' . __('interface.status.on_hold') . '</span>';
                    }

                    switch ($ticket->priority) {
                        case 'low':
                        default:
                            $priority = '<span class="badge badge-secondary">' . __('interface.priorities.low') . '</span>';

                            break;
                        case 'medium':
                            $priority = '<span class="badge badge-success">' . __('interface.priorities.medium') . '</span>';

                            break;
                        case 'high':
                            $priority = '<span class="badge badge-warning">' . __('interface.priorities.high') . '</span>';

                            break;
                        case 'emergency':
                            $priority = '<span class="badge badge-danger">' . __('interface.priorities.emergency') . '</span>';

                            break;
                    }

                    return (object) [
                        'id'       => $ticket->id,
                        'subject'  => $ticket->subject,
                        'category' => ! empty($ticket->category) ? $ticket->category->name : __('interface.status.uncategorized'),
                        'status'   => $status,
                        'priority' => $priority,
                        'view'     => '<a href="' . route('customer.support.details', $ticket->id) . '" class="btn btn-primary btn-sm"><i class="bi bi-eye"></i></a>',
                    ];
                }),
        ]);
    }

    /**
     * View a support ticket.
     *
     * @param Request $request
     *
     * @return Application|Factory|RedirectResponse|View
     */
    public function support_details(Request $request)
    {
        $ticket = SupportTicket::find($request->id);

        if (
            ! empty($ticket) &&
            $ticket->assignments->where('user_id', '=', Auth::id())->isNotEmpty()
        ) {
            return view('customer.support.details', [
                'ticket' => $ticket,
            ]);
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Create a new support ticket.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_create(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'category_id' => ['required', 'integer'],
            'subject'     => ['required', 'string'],
            'priority'    => ['required', 'string'],
            'message'     => ['required', 'string'],
        ])->validate();

        /* @var SupportTicket $ticket */
        $ticket = SupportTicket::create([
            'category_id' => $request->category_id,
            'subject'     => $request->subject,
            'priority'    => $request->priority,
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $request->message,
        ]);

        SupportTicketAssignment::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'role'      => 'customer',
        ]);

        SupportTicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'type'      => 'status',
            'action'    => 'open',
        ]);

        SupportTicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'type'      => 'assignment',
            'action'    => 'assign',
            'reference' => Auth::id(),
        ]);

        /* @var UploadedFile|null $file */
        if (! empty($file = $request->files->get('file'))) {
            $file = File::create([
                'user_id'   => ! empty($request->private) ? Auth::id() : null,
                'folder_id' => null,
                'name'      => Carbon::now()->format('YmdHis') . '_' . $file->getClientOriginalName(),
                'data'      => $file->getContent(),
                'mime'      => $file->getClientMimeType(),
                'size'      => $file->getSize(),
            ]);

            if ($file instanceof File) {
                SupportTicketFile::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => Auth::id(),
                    'file_id'   => $file->id,
                ]);

                SupportTicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => Auth::id(),
                    'type'      => 'file',
                    'action'    => 'add',
                    'reference' => $file->id,
                ]);
            }
        }

        $ticket->sendEmailCreationNotification();

        return redirect()->back()->with('success', __('interface.messages.ticket_opened'));
    }

    /**
     * Create a new support ticket answer.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_answer(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'id'      => ['required', 'integer'],
            'message' => ['required', 'string'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $request->id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            )
        ) {
            SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id'   => Auth::id(),
                'message'   => $request->message,
            ]);

            /* @var UploadedFile|null $file */
            if (! empty($file = $request->files->get('file'))) {
                $file = File::create([
                    'user_id'   => ! empty($request->private) ? Auth::id() : null,
                    'folder_id' => null,
                    'name'      => Carbon::now()->format('YmdHis') . '_' . $file->getClientOriginalName(),
                    'data'      => $file->getContent(),
                    'mime'      => $file->getClientMimeType(),
                    'size'      => $file->getSize(),
                ]);

                if ($file instanceof File) {
                    SupportTicketFile::create([
                        'ticket_id' => $ticket->id,
                        'user_id'   => Auth::id(),
                        'file_id'   => $file->id,
                    ]);

                    SupportTicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'user_id'   => Auth::id(),
                        'type'      => 'file',
                        'action'    => 'add',
                        'reference' => $file->id,
                    ]);
                }
            }

            return redirect()->back()->with('success', __('interface.messages.ticket_added'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Close a support ticket.
     *
     * @param int $id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_close(int $id): RedirectResponse
    {
        Validator::make([
            'id' => $id,
        ], [
            'id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            )
        ) {
            $ticket->update([
                'status' => 'closed',
            ]);

            SupportTicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => Auth::id(),
                'type'      => 'status',
                'action'    => 'close',
            ]);

            $ticket->sendEmailCloseNotification();

            return redirect()->back()->with('success', __('interface.messages.ticket_closed'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Re-open a support ticket.
     *
     * @param int $id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_reopen(int $id): RedirectResponse
    {
        Validator::make([
            'id' => $id,
        ], [
            'id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            )
        ) {
            $ticket->update([
                'status' => 'open',
            ]);

            SupportTicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => Auth::id(),
                'type'      => 'status',
                'action'    => 'reopen',
            ]);

            $ticket->sendEmailReopenNotification();

            return redirect()->back()->with('success', __('interface.messages.ticket_reopened'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Download a file linked to a support ticket.
     *
     * @param int $id
     * @param int $filelink_id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_file_download(int $id, int $filelink_id): RedirectResponse
    {
        Validator::make([
            'id'          => $id,
            'filelink_id' => $filelink_id,
        ], [
            'id'          => ['required', 'integer'],
            'filelink_id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            ) &&
            ! empty(
                $file = $ticket->fileLinks
                    ->where('id', '=', $filelink_id)
                    ->where('internal', '=', false)
                    ->first()
                    ->file ?? null
            )
        ) {
            $file = $file->makeVisible('data');

            Download::prepare($file->name)
                ->data($file->data)
                ->output();
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Delete a file linked to a support ticket.
     *
     * @param int $id
     * @param int $filelink_id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_file_delete(int $id, int $filelink_id): RedirectResponse
    {
        Validator::make([
            'id'          => $id,
            'filelink_id' => $filelink_id,
        ], [
            'id'          => ['required', 'integer'],
            'filelink_id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            ) &&
            ! empty(
                $fileLink = $ticket->fileLinks
                        ->where('id', '=', $filelink_id)
                        ->first()
            ) &&
            $fileLink->user_id == Auth::id()
        ) {
            SupportTicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => Auth::id(),
                'type'      => 'file',
                'action'    => 'remove',
                'reference' => $fileLink->file_id,
            ]);

            $fileLink->file()->delete();
            $fileLink->delete();

            return redirect()->back()->with('success', __('interface.messages.ticket_attachment_removed'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Upload a new ticket attachment without answering the ticket.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function support_file_upload(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $ticket = SupportTicket::where('id', '=', $request->id)
                    ->whereHas('assignments', function (Builder $builder) {
                        return $builder->where('user_id', '=', Auth::id());
                    })
                    ->first()
            ) &&
            ! empty($file = $request->files->get('file'))
        ) {
            $file = File::create([
                'user_id'   => ! empty($request->private) ? Auth::id() : null,
                'folder_id' => null,
                'name'      => Carbon::now()->format('YmdHis') . '_' . $file->getClientOriginalName(),
                'data'      => $file->getContent(),
                'mime'      => $file->getClientMimeType(),
                'size'      => $file->getSize(),
            ]);

            if ($file instanceof File) {
                SupportTicketFile::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => Auth::id(),
                    'file_id'   => $file->id,
                ]);

                SupportTicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => Auth::id(),
                    'type'      => 'file',
                    'action'    => 'add',
                    'reference' => $file->id,
                ]);

                return redirect()->back()->with('success', __('interface.messages.ticket_attachment_uploaded'));
            }
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }
}
