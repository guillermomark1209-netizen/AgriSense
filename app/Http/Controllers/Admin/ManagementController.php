<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIAuditLog;
use App\Models\AIConversation;
use App\Models\Alert;
use App\Models\Crop;
use App\Models\Device;
use App\Models\SensorReading;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ManagementController extends Controller
{
    public function index(Request $request, string $section): View
    {
        Gate::authorize('manage-system');
        if ($section === 'audit-logs') {
            return view('admin.management', ['section' => $section, 'columns' => ['user_id', 'action', 'table_name', 'record_id', 'description', 'ip_address', 'created_at'], 'records' => DB::table('audit_logs')->latest()->paginate(20)]);
        }
        $maps = [
            'users' => [User::class, ['name', 'email', 'role']],
            'crops' => [Crop::class, ['name', 'variety', 'growth_stage', 'location', 'status']],
            'devices' => [Device::class, ['name', 'device_id', 'status', 'last_seen_at']],
            'sensor-data' => [SensorReading::class, ['device_id', 'temperature', 'humidity', 'soil_moisture', 'soil_ph', 'light_intensity', 'reading_at']],
            'alerts' => [Alert::class, ['sensor_type', 'severity', 'status', 'message', 'detected_at']],
            'conversations' => [AIConversation::class, ['user_id', 'title', 'status', 'updated_at']],
            'ai-audit-logs' => [AIAuditLog::class, ['user_id', 'event_type', 'details', 'created_at']],
        ];
        abort_unless(isset($maps[$section]), 404);
        [$model,$columns] = $maps[$section];

        return view('admin.management', ['section' => $section, 'columns' => $columns, 'records' => $model::latest()->paginate(20)]);
    }

    public function role(Request $request, User $user, AdminAuditService $audit): RedirectResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate(['role' => ['required', 'in:admin,user']]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');
        DB::transaction(function () use ($user, $data, $audit): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $previousRole = $user->role;
            $user->role = $data['role'];
            $user->save();
            $audit->record('USER_ROLE_CHANGED', 'users', $user->id, 'Role changed from '.$previousRole.' to '.$user->role.'.');
        });

        return back()->with('success', 'User role updated.');
    }

    public function settings(): View
    {
        Gate::authorize('manage-system');

        return view('admin.settings', ['settings' => DB::table('system_settings')->pluck('value', 'key')]);
    }

    public function saveSettings(Request $request, AdminAuditService $audit): RedirectResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate(['farm_notice' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($data, $audit): void {
            DB::table('system_settings')->updateOrInsert(['key' => 'farm_notice'], ['value' => $data['farm_notice'] ?? '', 'created_at' => now(), 'updated_at' => now()]);
            $audit->record('SYSTEM_SETTINGS_CHANGED', 'system_settings', 'farm_notice', 'Farm notice updated.');
        });

        return back()->with('success', 'Settings saved.');
    }
}
