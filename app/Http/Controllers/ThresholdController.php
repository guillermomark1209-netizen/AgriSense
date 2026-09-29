<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\CropSensorThreshold;
use App\Models\Source;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ThresholdController extends Controller
{
    public function store(Request $request, Crop $crop): RedirectResponse
    {
        Gate::authorize('update', $crop);
        $data = $request->validate([
            'sensor_type' => ['required', Rule::in(array_keys(config('agrisense.sensors')))],
            'min_value' => ['nullable', 'numeric', 'required_without:max_value'],
            'max_value' => ['nullable', 'numeric', 'required_without:min_value', ...($request->filled('min_value') ? ['gt:min_value'] : [])],
            'source_id' => ['required', 'integer'],
            'source_reference' => ['required', 'string', 'max:255'],
        ]);
        $source = Source::eligible()->findOrFail($data['source_id']);
        abort_unless($source->crop === '*' || mb_strtolower($source->crop) === mb_strtolower($crop->name), 422, 'The source must match this crop.');
        DB::transaction(function () use ($crop, $data): void {
            $threshold = $crop->thresholds()->updateOrCreate(['sensor_type' => $data['sensor_type'], 'variety' => $crop->variety, 'growth_stage' => $crop->growth_stage], [...$data, 'unit' => config('agrisense.sensors.'.$data['sensor_type'].'.unit')]);
            app(AdminAuditService::class)->record('THRESHOLD_UPDATED', 'crop_sensor_thresholds', $threshold->id, 'Verified crop range updated.');
        });

        return back()->with('success', 'Threshold saved for this variety and growth stage.');
    }

    public function destroy(Request $request, Crop $crop, CropSensorThreshold $threshold): RedirectResponse
    {
        Gate::authorize('update', $crop);
        abort_unless($threshold->crop_id === $crop->id, 403);
        DB::transaction(function () use ($threshold): void {
            app(AdminAuditService::class)->record('THRESHOLD_DELETED', 'crop_sensor_thresholds', $threshold->id, 'Verified crop range removed.');
            $threshold->delete();
        });

        return back()->with('success', 'Threshold removed.');
    }
}
