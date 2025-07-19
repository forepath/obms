<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    /**
     * Show list of settings.
     *
     * @return Renderable
     */
    public function settings_index(): Renderable
    {
        return view('admin.settings.home');
    }

    /**
     * Get list of settings.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function settings_list(Request $request): JsonResponse
    {
        session_write_close();

        $query = Setting::whereNotIn('setting', [
            'company.logo',
            'company.favicon',
        ]);

        $totalCount = (clone $query)->count();

        if (! empty($request->search['value'])) {
            $query = $query->where(function (Builder $query) use ($request) {
                return $query->where('id', 'LIKE', '%' . $request->search['value'] . '%')
                    ->orWhere('setting', 'LIKE', '%' . $request->search['value'] . '%');
            });
        }

        if (! empty($request->order)) {
            foreach ($request->order as $order) {
                switch ($request->columns[$order['column']]) {
                    case 'setting':
                        $orderBy = 'setting';

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
                ->transform(function (Setting $setting) {
                    $edit = '
<a class="btn btn-warning btn-sm w-100" data-toggle="modal" data-target="#edit' . $setting->id . '" data-type="edit" data-category="' . $setting->id . '" data-table="#settings-' . $setting->id . '"><i class="bi bi-pencil-square"></i></a>
<div class="modal fade" id="edit' . $setting->id . '" tabindex="-1" aria-labelledby="edit' . $setting->id . 'Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="edit' . $setting->id . 'Label">' . __('interface.actions.edit') . ' (' . $setting->setting . ')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <form action="' . route('admin.settings.update', $setting->id) . '" method="post">
                    <input type="hidden" name="_token" value="' . csrf_token() . '" />
                    <input type="hidden" name="setting_id" value="' . $setting->id . '" />

                    <div class="form-group row">
                        <label for="value" class="col-md-4 col-form-label text-md-right">' . __('interface.data.value') . '</label>

                        <div class="col-md-8">
                            <input id="value" type="text" class="form-control" name="value" value="' . $setting->value . '">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100"><i class="bi bi-pencil-square"></i> ' . __('interface.actions.edit') . '</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">' . __('interface.actions.close') . '</button>
            </div>
        </div>
    </div>
</div>
';

                    return (object) [
                        'id'      => $setting->id,
                        'setting' => $setting->setting,
                        'value'   => $setting->value,
                        'edit'    => $edit,
                    ];
                }),
        ]);
    }

    /**
     * Update a setting.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function settings_update(Request $request): RedirectResponse
    {
        Validator::make($request->toArray(), [
            'setting_id' => ['required', 'integer'],
            'value'      => ['string', 'nullable'],
        ])->validate();

        /* @var Setting $setting */
        if (! empty($setting = Setting::find($request->setting_id))) {
            $defaultValue = function (string $setting): ?string {
                switch ($setting) {
                    case 'theme.primary':
                        return '#040E29';
                    case 'theme.white':
                        return '#FFFFFF';
                    case 'theme.gray':
                        return '#F3F9FC';
                    case 'theme.success':
                        return '#0F7038';
                    case 'theme.warning':
                        return '#FFD500';
                    case 'theme.danger':
                        return '#B21E35';
                    case 'theme.info':
                        return '#1464F6';
                    case 'theme.body':
                        return '#3C4858';
                    default:
                        return null;
                }
            };

            $setting->update([
                'value' => $request->value ? $request->value : $defaultValue($setting->setting),
            ]);

            $tenant   = request()->tenant;
            $cacheKey = 'app-settings' . ($tenant ? '-' . $tenant->id : '');
            Cache::forget($cacheKey);

            if (
                collect([
                    'theme.primary',
                    'theme.white',
                    'theme.gray',
                    'theme.success',
                    'theme.warning',
                    'theme.danger',
                    'theme.info',
                    'theme.body',
                ])->contains($setting->setting)
            ) {
                $tenant   = request()->tenant;
                $cacheKey = 'stylesheet-' . config('app.theme', 'aurora') . '-' . str_replace(['/', ':'], '_', str_replace(['http://', 'https://'], '', config('app.url'))) . ($tenant ? '-' . $tenant->id : '');
                Cache::forget($cacheKey);
            }

            return redirect()->back()->with('success', __('interface.messages.setting_updated'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Update a setting.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function settings_assets_update(Request $request): RedirectResponse
    {
        $assetsUpdated = false;

        if (
            ! empty($file = $request->files->get('logo')) &&
            ! empty($setting = Setting::where('setting', 'company.logo')->first())
        ) {
            $setting->update([
                'value' => 'data:' . $file->getClientMimeType() . ';base64,' . base64_encode($file->getContent()),
            ]);

            $assetsUpdated = true;
        }

        if (
            ! empty($file = $request->files->get('favicon')) &&
            ! empty($setting = Setting::where('setting', 'company.favicon')->first())
        ) {
            $setting->update([
                'value' => 'data:' . $file->getClientMimeType() . ';base64,' . base64_encode($file->getContent()),
            ]);

            $assetsUpdated = true;
        }

        if ($assetsUpdated) {
            $tenant   = $request->attributes->get('tenant');
            $cacheKey = 'app-settings' . ($tenant ? '-' . $tenant->id : '');

            Cache::forget($cacheKey);

            return redirect()->back()->with('success', __('interface.messages.setting_updated'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }

    /**
     * Update a setting.
     *
     * @param Request $request
     *
     * @throws ValidationException
     *
     * @return RedirectResponse
     */
    public function settings_assets_remove(Request $request): RedirectResponse
    {
        Validator::make([
            'setting' => $request->setting,
        ], [
            'setting' => ['required', 'string'],
        ])->validate();

        /* @var Setting $setting */
        if (! empty($setting = Setting::where('setting', $request->setting)->first())) {
            $setting->update([
                'value' => null,
            ]);

            $tenant   = $request->attributes->get('tenant');
            $cacheKey = 'app-settings' . ($tenant ? '-' . $tenant->id : '');

            Cache::forget($cacheKey);

            return redirect()->back()->with('success', __('interface.messages.setting_updated'));
        }

        return redirect()->back()->with('warning', __('interface.misc.something_wrong_notice'));
    }
}
