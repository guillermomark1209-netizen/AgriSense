<?php

namespace App\Http\Controllers;

use App\Http\Requests\CropRequest;
use App\Models\Crop;
use App\Models\Source;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\GeminiService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CropController extends Controller
{
    private function authorizeCrop(Crop $crop): void
    {
        Gate::authorize('view', $crop);
    }

    public function index(Request $request): View
    {
        return view('crops.index', ['crops' => Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->with('devices')->latest()->paginate(12)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Crop::class);

        return view('crops.form', ['crop' => new Crop, 'owners' => $request->user()->isAdmin() ? User::orderBy('name')->get(['id', 'name', 'email']) : collect()]);
    }

    public function store(CropRequest $request, GeminiService $gemini): RedirectResponse
    {
        Gate::authorize('create', Crop::class);
        try {
            $identification = $gemini->identifyCropImage($request->file('image'));
            if ($identification['crop_name'] === 'Not a plant') {
                return back()->withErrors(['image' => 'The uploaded image does not appear to contain a crop or plant.']);
            }
            if ($identification['crop_name'] === 'Unknown') {
                return back()->withErrors(['image' => 'Unable to identify this crop from the image. Please try a clearer photo.']);
            }
            $data = [
                'user_id' => $request->user()->id,
                'name' => $identification['crop_name'],
                'crop_name' => $identification['crop_name'],
                'common_name' => $identification['common_name'],
                'scientific_name' => $identification['scientific_name'],
                'variety' => 'Unknown',
                'growth_stage' => 'Unknown',
                'location' => 'Unspecified',
                'planting_date' => now()->toDateString(),
                'status' => 'unknown',
                'image_url' => $this->storeImage($request->file('image'), $request->user()->id),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['image' => 'AI crop analysis is currently unavailable. Please try again.']);
        }
        $crop = DB::transaction(function () use ($data) {
            $crop = Crop::create($data);
            DB::table('crop_events')->insert(['crop_id' => $crop->id, 'event' => 'Crop planted / registered', 'created_at' => now(), 'updated_at' => now()]);
            app(AdminAuditService::class)->record('PLANT_CREATED', 'crops', $crop->id, 'Crop registered.');

            return $crop;
        });

        return redirect()->route('crops.show', $crop)->with('success', 'Your crop has been added.');
    }

    public function show(Crop $crop): View
    {
        $this->authorizeCrop($crop);
        $crop->load('devices', 'thresholds.source');

        $crop->variety = '';
        $crop->location = '';

        return view('crops.show', ['crop' => $crop, 'latestReading' => $crop->readings()->latest('reading_at')->first(), 'sources' => Source::eligible()->where(fn ($q) => $q->whereRaw('LOWER(crop) = ?', [mb_strtolower($crop->name)])->orWhere('crop', '*'))->get()]);
    }

    public function edit(Crop $crop): View
    {
        Gate::authorize('update', $crop);

        return view('crops.form', compact('crop'));
    }

    public function update(CropRequest $request, Crop $crop): RedirectResponse
    {
        Gate::authorize('update', $crop);
        $data = array_filter($request->safe()->except('image'), fn ($value) => $value !== null);
        try {
            if ($request->hasFile('image')) {
                $data['image_url'] = $this->storeImage($request->file('image'), $crop->user_id);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['image' => 'The image could not be stored. Please try again.']);
        }
        DB::transaction(function () use ($crop, $data) {
            $crop->update($data);
            DB::table('crop_events')->insert(['crop_id' => $crop->id, 'event' => 'Crop details updated: '.$crop->growth_stage, 'created_at' => now(), 'updated_at' => now()]);
            app(AdminAuditService::class)->record('PLANT_UPDATED', 'crops', $crop->id, 'Crop details updated.');
        });

        return redirect()->route('crops.show', $crop)->with('success', 'Crop updated.');
    }

    public function destroy(Crop $crop): RedirectResponse
    {
        Gate::authorize('delete', $crop);
        DB::transaction(function () use ($crop): void {
            app(AdminAuditService::class)->record('PLANT_DELETED', 'crops', $crop->id, 'Crop removed by an administrator.');
            $crop->delete();
        });

        return redirect()->route('crops.index')->with('success', 'Crop removed. Connected devices are now unassigned.');
    }

    private function storeImage(UploadedFile $image, int $ownerId): string
    {
        if (config('agrisense.supabase_url') && config('agrisense.supabase_key')) {
            return app(SupabaseStorageService::class)->upload($image, 'crop-images', $ownerId);
        }

        $path = $image->store('crop-images/local/'.$ownerId, 'local');

        if ($path === false) {
            throw new RuntimeException('The crop image could not be written to local storage.');
        }

        return $path;
    }

    public function image(Crop $crop): RedirectResponse|StreamedResponse
    {
        $this->authorizeCrop($crop);
        abort_unless($crop->image_url && str_starts_with($crop->image_url, 'crop-images/'), 404);

        if (str_starts_with($crop->image_url, 'crop-images/local/')) {
            abort_unless(Storage::disk('local')->exists($crop->image_url), 404);

            return Storage::disk('local')->response($crop->image_url);
        }

        return redirect()->away(app(SupabaseStorageService::class)->signedUrl($crop->image_url));
    }
}
