<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TutorialController extends Controller
{
    /**
     * Get all tutorials with category filter
     */
    public function index(Request $request)
    {
        $category = $request->query('category');
        $search = $request->query('search');

        $query = DB::table('tutorials')->orderBy('sort_order', 'asc')->orderBy('id', 'desc');

        if ($category && $category !== 'All') {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tutorials = $query->get()->map(function ($item) {
            return [
                'id'          => $item->id,
                'drone_id'    => $item->drone_id,
                'category'    => $item->category,
                'title'       => $item->title,
                'description' => $item->description,
                'duration'    => $item->duration ?: '5:00',
                'video_url'   => $this->resolveMediaUrl($item->video_url),
                'thumbnail'   => $this->resolveMediaUrl($item->thumbnail),
                'sort_order'  => $item->sort_order,
                'created_at'  => $item->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'count'   => $tutorials->count(),
            'data'    => $tutorials
        ]);
    }

    /**
     * Admin upload new tutorial
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'category'    => 'required|string',
            'description' => 'nullable|string',
            'duration'    => 'nullable|string',
            'video_url'   => 'nullable|string',
            'video_file'  => 'nullable|file|mimes:mp4,mov,webm,avi|max:102400', // 100MB max
            'thumbnail'   => 'nullable|file|mimes:jpeg,png,jpg,webp|max:10240',
            'thumbnail_url' => 'nullable|string',
            'drone_id'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors()
            ], 422);
        }

        $videoUrl = $request->input('video_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ');
        if ($request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('tutorials/videos', 'public');
            $videoUrl = '/storage/' . $path;
        }

        $thumbnailUrl = $request->input('thumbnail_url', '/tutorials/t100-1.jpg');
        if ($request->hasFile('thumbnail')) {
            $thumbPath = $request->file('thumbnail')->store('tutorials/thumbs', 'public');
            $thumbnailUrl = '/storage/' . $thumbPath;
        }

        $id = DB::table('tutorials')->insertGetId([
            'drone_id'    => $request->input('drone_id', 1),
            'category'    => $request->category,
            'title'       => $request->title,
            'description' => $request->description,
            'duration'    => $request->duration ?: '5:00',
            'video_url'   => $videoUrl,
            'thumbnail'   => $thumbnailUrl,
            'sort_order'  => DB::table('tutorials')->max('sort_order') + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tutorial uploaded successfully',
            'id'      => $id
        ], 201);
    }

    /**
     * Admin delete tutorial
     */
    public function destroy($id)
    {
        DB::table('tutorials')->where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Tutorial deleted successfully'
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
