<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Crop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['crop_id' => ['nullable', Rule::exists('crops', 'id')->when(! $request->user()->isAdmin(), fn ($rule) => $rule->where('user_id', $request->user()->id))], 'status' => ['nullable', 'in:active,resolved'], 'severity' => ['nullable', 'in:critical,warning'], 'sensor' => ['nullable', Rule::in(array_keys(config('agrisense.sensors')))], 'date' => ['nullable', 'date']]);
        $query = Alert::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->with('crop');
        foreach (['crop_id', 'status', 'severity'] as $key) {
            if (! empty($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }
        if (! empty($data['sensor'])) {
            $query->where('sensor_type', $data['sensor']);
        }
        if (! empty($data['date'])) {
            $query->whereDate('detected_at', $data['date']);
        }

        return view('alerts.index', ['alerts' => $query->latest('detected_at')->paginate(15)->withQueryString(), 'crops' => Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->get()]);
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id || $request->user()->hasRole('admin'), 403);
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'Alert marked resolved.');
    }
}
