<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DeviceApprovalRequest;
use App\Models\DeviceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Подключение ТВ по коду: ввод кода с экрана телевизора, затем подтверждение
 * с именем устройства — чтобы не подключить чужое устройство по присланному коду.
 */
class DeviceController extends Controller
{
    public function show(Request $request): Response
    {
        $input = is_string($request->query('code')) ? $request->query('code') : '';
        $code = $input !== '' ? DeviceCode::pending($input) : null;

        return Inertia::render('auth/device', [
            'code' => $code?->displayCode() ?? $input,
            'device' => $code !== null ? ['name' => $code->device_name, 'requested_at' => $code->created_at?->toIso8601String()] : null,
            'invalid' => $input !== '' && $code === null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(DeviceApprovalRequest $request): RedirectResponse
    {
        $code = $request->deviceCode() ?? abort(404);
        $code->forceFill($request->approves()
            ? ['user_id' => $request->user()->id, 'approved_at' => now()]
            : ['denied_at' => now()])->save();

        return redirect()->route('device.show')->with('status', $request->approves() ? 'approved' : 'denied');
    }
}
