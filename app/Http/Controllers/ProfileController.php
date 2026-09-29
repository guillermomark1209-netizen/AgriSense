<?php

namespace App\Http\Controllers;

use App\Services\SupabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('profile.index', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:255'], 'bio' => ['nullable', 'string', 'max:1000'], 'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $profile = collect($data)->only('phone', 'address', 'bio')->all();
        try {
            if ($request->hasFile('avatar')) {
                $profile['avatar_url'] = app(SupabaseStorageService::class)->upload($request->file('avatar'), 'profile-images', $user->id);
            }
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['avatar' => 'Your photo could not be stored. Check Supabase Storage configuration.']);
        }
        $user->fill(collect($data)->only('name', 'email')->all());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        $user->profile()->updateOrCreate(['user_id' => $user->id], [...$profile, 'full_name' => $user->name]);

        return back()->with('success', 'Profile updated.');
    }

    public function image(Request $request): RedirectResponse
    {
        $path = $request->user()->profile?->avatar_url;
        abort_unless($path, 404);

        return redirect()->away(app(SupabaseStorageService::class)->signedUrl($path));
    }
}
