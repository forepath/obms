<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\API\OauthClient;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

class AdminAPIController extends Controller
{
    /**
     * Show list of api users.
     *
     * @return Renderable
     */
    public function apiuser_index(): Renderable
    {
        return view('admin.api.users');
    }

    /**
     * Get list of api users.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function apiuser_list(Request $request): JsonResponse
    {
        session_write_close();

        $query = User::where('role', '=', 'api');

        $totalCount = (clone $query)->count();

        if (! empty($request->search['value'])) {
            $query = $query->where(function (Builder $query) use ($request) {
                return $query->where('id', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('name', 'LIKE', '%' . $request->search['value'] . '%');
            });
        }

        if (! empty($request->order)) {
            foreach ($request->order as $order) {
                switch ($request->columns[$order['column']]) {
                    case 'name':
                        $orderBy = 'name';

                        break;
                    case 'status':
                        $orderBy = 'locked';

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
                ->transform(function (User $user) {
                    $edit = '
<a class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editAPIUser' . $user->id . '"><i class="bi bi-pencil-square"></i></a>
<div class="modal fade" id="editAPIUser' . $user->id . '" tabindex="-1" aria-labelledby="editAPIUser' . $user->id . 'Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="editAPIUser' . $user->id . 'Label">' . __('interface.actions.edit') . ' (' . __($user->name) . ')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form action="' . route('admin.api.users.update', $user->id) . '" method="post">
                <div class="modal-body">
                    <input type="hidden" name="_token" value="' . csrf_token() . '" />
                    <input type="hidden" name="user_id" value="' . $user->id . '" />
                    <div class="form-group row">
                        <label for="name" class="col-md-4 col-form-label text-md-right">' . __('interface.data.name') . '</label>

                        <div class="col-md-8">
                            <input id="name" type="text" class="form-control" name="name" value="' . $user->name . '">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="name" class="col-md-4 col-form-label text-md-right">' . __('interface.data.email') . '</label>

                        <div class="col-md-8">
                            <input id="email" type="email" class="form-control" name="email" value="' . $user->email . '">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="password" class="col-md-4 col-form-label text-md-right">' . __('interface.data.password') . '</label>

                        <div class="col-md-8">
                            <input id="password" type="password" class="form-control" name="password">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="password-confirm" class="col-md-4 col-form-label text-md-right">' . __('interface.data.confirm_password') . '</label>

                        <div class="col-md-8">
                            <input id="password-confirm" type="password" class="form-control" name="password_confirmation">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil-square"></i> ' . __('interface.actions.edit') . '</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">' . __('interface.actions.close') . '</button>
                </div>
            </form>
        </div>
    </div>
</div>
';

                    return (object) [
                        'id'     => $user->number,
                        'name'   => $user->realName,
                        'email'  => $user->email,
                        'status' => $user->locked ? '<span class="badge badge-warning">' . __('interface.status.locked') . '</span>' : '<span class="badge badge-success">' . __('interface.status.unlocked') . '</span>',
                        'lock'   => $user->locked ? '<a href="' . route('admin.api.users.lock', $user->id) . '" class="btn btn-success btn-sm"><i class="bi bi-unlock-fill"></i></a>' : '<a href="' . route('admin.api.users.lock', $user->id) . '" class="btn btn-warning btn-sm"><i class="bi bi-lock-fill"></i></a>',
                        'edit'   => $edit,
                        'delete' => '<a href="' . route('admin.api.users.delete', $user->id) . '" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>',
                    ];
                }),
        ]);
    }

    /**
     * Create a new api user account.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiuser_create(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ])->validate();

        if (
            ! empty(
                $user = User::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'password' => Hash::make($request->password),
                    'role'     => 'api',
                ])
            )
        ) {
            return redirect()->route('admin.api.users')->with('success', __('interface.messages.api_account_created'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Create a new api user account.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiuser_update(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'user_id' => ['required', 'integer'],
            'name'    => ['required', 'string', 'max:255'],
            'email'   => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ])->validate();

        if (
            ! empty(
                $user = User::where('id', '=', $request->user_id)
                    ->where('role', '=', 'api')
                    ->first()
            )
        ) {
            $data = [
                'name'  => $request->name,
                'email' => $request->email,
            ];

            if (! empty($request->password)) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            return redirect()->route('admin.api.users', $user->id)->with('success', __('interface.messages.api_account_created'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Lock api user account.
     *
     * @param int $user_id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiuser_lock(int $user_id): RedirectResponse
    {
        Validator::make([
            'user_id' => $user_id,
        ], [
            'user_id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $user = User::where('id', '=', $user_id)
                    ->where('role', '=', 'api')
                    ->first()
            )
        ) {
            $status = ! $user->locked;

            $user->update([
                'locked' => $status,
            ]);

            if ($status) {
                return redirect()->back()->with('success', __('interface.messages.api_account_locked'));
            } else {
                return redirect()->back()->with('success', __('interface.messages.api_account_unlocked'));
            }
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Delete api user account.
     *
     * @param int $user_id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiuser_delete(int $user_id): RedirectResponse
    {
        Validator::make([
            'user_id' => $user_id,
        ], [
            'user_id' => ['required', 'integer'],
        ])->validate();

        if (
            ! empty(
                $user = User::where('id', '=', $user_id)
                    ->where('role', '=', 'api')
                    ->first()
            )
        ) {
            $user->delete();

            return redirect()->back()->with('success', __('interface.messages.api_account_deleted'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Show list of api users.
     *
     * @return Renderable
     */
    public function apiclient_index(): Renderable
    {
        return view('admin.api.oauth-clients');
    }

    /**
     * Get list of api users.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function apiclient_list(Request $request): JsonResponse
    {
        session_write_close();

        $query = OauthClient::query();

        $totalCount = (clone $query)->count();

        if (! empty($request->search['value'])) {
            $query = $query->where(function (Builder $query) use ($request) {
                return $query->where('id', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('name', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('secret', 'LIKE', '%' . $request->search['value'] . '%');
            });
        }

        if (! empty($request->order)) {
            foreach ($request->order as $order) {
                switch ($request->columns[$order['column']]) {
                    case 'name':
                        $orderBy = 'name';

                        break;
                    case 'secret':
                    case 'public':
                        $orderBy = 'secret';

                        break;
                    case 'redirect':
                        $orderBy = 'redirect';

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
                ->transform(function (OauthClient $client) {
                    return (object) [
                        'id'       => $client->id,
                        'name'     => $client->name,
                        'public'   => !$client->secret ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>',
                        'redirect' => $client->redirect,
                        'secret'   => $client->secret,
                        'type'     => $client->personal_access_client ? '<span class="badge badge-primary">' . __('interface.data.personal') . '</span>' : ($client->password_client ? '<span class="badge badge-primary">' . __('interface.data.password') . '</span>' : '<span class="badge badge-primary">' . __('interface.data.client') . '</span>'),
                        'delete'   => '<a href="' . route('admin.api.oauth-clients.delete', $client->id) . '" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></a>',
                    ];
                }),
        ]);
    }

    /**
     * Create a new api client.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiclient_create(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'string'],
            'grant_type' => ['required', 'string'],
            'redirect'   => ['nullable', 'string'],
            'public'     => ['nullable', 'string'],
        ])->validate();

        $clientRepository = app(ClientRepository::class);
        $client           = false;
        $public           = ! empty($request->public) && $request->public == 'true';

        switch ($request->type) {
            case 'personal':
                $client = $clientRepository->create(
                    null,
                    $request->name,
                    $request->redirect, // Redirect URL
                    $request->grant_type, // Grant Type
                    true, // Personal Access Client
                    false,  // Password Grant Client
                    !$public, // Confidential Client
                );

                break;
            case 'password':
                $client = $clientRepository->create(
                    null,
                    $request->name,
                    $request->redirect, // Redirect URL
                    $request->grant_type, // Grant Type
                    false, // Personal Access Client
                    true,  // Password Grant Client
                    !$public, // Confidential Client
                );

                break;
            case 'client':
                $client = $clientRepository->create(
                    null,
                    $request->name,
                    $request->redirect, // Redirect URL
                    $request->grant_type, // Grant Type
                    false, // Personal Access Client
                    false,  // Password Grant Client
                    !$public, // Confidential Client
                );

                break;
        }

        if ($client) {
            return redirect()->route('admin.api.oauth-clients')->with('success', __('interface.messages.api_client_created'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Delete api client.
     *
     * @param int $client_id
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function apiclient_delete(int $client_id): RedirectResponse
    {
        Validator::make([
            'client_id' => $client_id,
        ], [
            'client_id' => ['required', 'integer'],
        ])->validate();

        if (! empty($client = OauthClient::find($client_id))) {
            $client->delete();

            return redirect()->back()->with('success', __('interface.messages.api_client_deleted'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }
}
