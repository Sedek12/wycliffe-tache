<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage', User::class);

        return response()->json([
            'files' => Setting::get('files'),
            'reminders' => Setting::get('reminders'),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('manage', User::class);

        $data = $request->validate([
            'files.max_size_mb' => ['required', 'integer', 'min:1', 'max:2048'],
            'files.allowed_extensions' => ['required', 'array', 'min:1'],
            'files.allowed_extensions.*' => ['string', 'alpha_num:ascii', 'max:10'],
            'files.retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'reminders.days_before_due' => ['required', 'array', 'min:1'],
            'reminders.days_before_due.*' => ['integer', 'min:0', 'max:30'],
        ]);

        $data['files']['allowed_extensions'] = array_values(array_unique(array_map(
            'strtolower',
            $data['files']['allowed_extensions']
        )));
        $data['reminders']['days_before_due'] = array_values(array_unique($data['reminders']['days_before_due']));
        rsort($data['reminders']['days_before_due']);

        Setting::put('files', $data['files']);
        Setting::put('reminders', $data['reminders']);

        return response()->json([
            'files' => Setting::get('files'),
            'reminders' => Setting::get('reminders'),
        ]);
    }
}
