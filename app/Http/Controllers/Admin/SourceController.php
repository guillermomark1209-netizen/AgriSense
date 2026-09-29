<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIAuditLog;
use App\Models\CropSensorThreshold;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        return view('admin.sources.index', ['sources' => Source::withCount('documents')->latest()->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'organization' => ['required', 'string', 'max:255'], 'crop' => ['required', 'string', 'max:255'], 'topic' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:government,academic,peer-reviewed,extension'], 'url' => ['required', 'url:http,https', 'max:2048'], 'expires_at' => ['nullable', 'date', 'after:today']]);
        $source = Source::create([...$data, 'verification_status' => 'pending', 'is_active' => false]);
        AIAuditLog::create(['user_id' => $request->user()->id, 'event_type' => 'source_added', 'details' => ['source_id' => $source->id]]);

        return back()->with('success', 'Source registered for review.');
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $data = $request->validate(['verification_status' => ['required', 'in:pending,verified,rejected,expired'], 'is_active' => ['required', 'boolean']]);
        $source->update([...$data, 'verified_by' => $data['verification_status'] === 'verified' ? $request->user()->id : null, 'verified_at' => $data['verification_status'] === 'verified' ? now() : null]);
        AIAuditLog::create(['user_id' => $request->user()->id, 'event_type' => 'source_reviewed', 'details' => ['source_id' => $source->id, ...$data]]);

        return back()->with('success', 'Source review saved.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        if (CropSensorThreshold::where('source_id', $source->id)->exists()) {
            return back()->withErrors(['source' => 'Remove thresholds referencing this source before deleting it.']);
        }
        $source->delete();

        return back()->with('success', 'Source deleted.');
    }
}
