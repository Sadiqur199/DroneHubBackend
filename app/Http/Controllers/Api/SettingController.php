<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    /**
     * Get home page hero banner configuration
     */
    public function getBanner()
    {
        $banner = DB::table('hero_banners')->where('is_active', true)->orderBy('id', 'desc')->first();

        if (!$banner) {
            $banner = (object) [
                'id'         => 1,
                'media_type' => 'video',
                'media_url'  => 'https://www.youtube.com/embed/6oPtcmr50Ls?autoplay=1&mute=1&loop=1&playlist=6oPtcmr50Ls&controls=0&showinfo=0&rel=0&iv_load_policy=3&disablekb=1&modestbranding=1&playsinline=1&fs=0',
                'badge_text' => 'DJI Agras T50 & Commercial Fleet | Central QLD Hub · Mackay',
                'title'      => 'Australia’s Premier Commercial Drone Fleet & Genuine Aviation Spares',
                'subtitle'   => 'Supplying heavy-lift agricultural spraying drones (DJI Agras T50), radiometric thermal survey platforms, and certified factory replacement parts. Serviced in our Mackay workshop with rapid deployment support across Brisbane, Sydney, and Gold Coast.',
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $banner->id,
                'media_type' => $banner->media_type,
                'media_url'  => $this->resolveMediaUrl($banner->media_url),
                'badge_text' => $banner->badge_text,
                'title'      => $banner->title,
                'subtitle'   => $banner->subtitle,
            ]
        ]);
    }

    /**
     * Admin update home page banner (upload video or image or provide custom video URL)
     */
    public function updateBanner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_type'  => 'required|in:video,image',
            'media_url'   => 'nullable|string',
            'video_file'  => 'nullable|file|mimes:mp4,webm,mov,avi|max:102400', // 100MB max
            'image_file'  => 'nullable|file|mimes:jpeg,png,jpg,webp|max:20480', // 20MB max
            'title'       => 'nullable|string|max:255',
            'subtitle'    => 'nullable|string',
            'badge_text'  => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $mediaType = $request->input('media_type', 'video');
        $mediaUrl = $request->input('media_url');

        // Handle uploaded video file
        if ($mediaType === 'video' && $request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('banners/videos', 'public');
            $mediaUrl = '/storage/' . $path;
        }

        // Handle uploaded image file
        if ($mediaType === 'image' && $request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('banners/images', 'public');
            $mediaUrl = '/storage/' . $path;
        }

        if (empty($mediaUrl)) {
            // Keep existing media URL if not uploaded
            $existing = DB::table('hero_banners')->first();
            $mediaUrl = $existing ? $existing->media_url : 'https://www.youtube.com/embed/6oPtcmr50Ls?autoplay=1&mute=1&loop=1&playlist=6oPtcmr50Ls';
        }

        DB::table('hero_banners')->updateOrInsert(
            ['id' => 1],
            [
                'media_type' => $mediaType,
                'media_url'  => $mediaUrl,
                'title'      => $request->input('title', 'Australia’s Premier Commercial Drone Fleet & Genuine Aviation Spares'),
                'subtitle'   => $request->input('subtitle', 'Supplying heavy-lift agricultural spraying drones (DJI Agras T50), radiometric thermal survey platforms, and certified factory replacement parts.'),
                'badge_text' => $request->input('badge_text', 'DJI Agras T50 & Commercial Fleet | Central QLD Hub · Mackay'),
                'is_active'  => true,
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Home page banner updated successfully',
            'data'    => [
                'media_type' => $mediaType,
                'media_url'  => $this->resolveMediaUrl($mediaUrl),
                'title'      => $request->input('title'),
                'subtitle'   => $request->input('subtitle'),
                'badge_text' => $request->input('badge_text'),
            ]
        ]);
    }

    /**
     * Get store settings
     */
    public function getSettings()
    {
        $settings = DB::table('settings')->get()->pluck('value', 'key');
        return response()->json([
            'success' => true,
            'data'    => $settings
        ]);
    }

    private function resolveMediaUrl($path)
    {
        if (!$path) return '';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/storage/')) {
            return 'http://127.0.0.1:8000' . $path;
        }
        return $path;
    }
}
