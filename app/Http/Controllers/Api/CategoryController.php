<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Category tree (top level categories with their subcategories).
     */
    public function index()
    {
        $rows = DB::table('categories')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $top = $rows->whereNull('parent_id')->values();
        $children = $rows->whereNotNull('parent_id')->groupBy('parent_id');

        $data = $top->map(function ($category) use ($children) {
            $subs = $children->get($category->id, collect())->map(function ($sub) {
                return [
                    'id'          => $sub->id,
                    'name'        => $sub->name,
                    'slug'        => $sub->slug,
                    'description' => $sub->description,
                ];
            })->values();

            return [
                'id'             => $category->id,
                'name'           => $category->name,
                'slug'           => $category->slug,
                'type'           => $category->type,
                'description'    => $category->description,
                'visibility_mode' => $category->visibility_mode,
                'subcategories'  => $subs->pluck('name')->all(),
                'subcategory_objects' => $subs->all(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'count'   => $data->count(),
            'data'    => $data,
        ]);
    }

    /**
     * Create a category.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:drone,part,accessory,combo,other',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $id = DB::table('categories')->insertGetId([
            'parent_id'    => $request->input('parent_id'),
            'type'         => $request->type,
            'name'         => $request->name,
            'slug'         => $this->uniqueSlug($request->name),
            'description'  => $request->input('description'),
            'sort'         => (int) DB::table('categories')->max('sort') + 1,
            'is_active'    => true,
            'visibility_mode' => 'public',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'id'      => $id,
        ], 201);
    }

    /**
     * Edit a category.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:drone,part,accessory,combo,other',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::table('categories')->where('id', $id)->update([
            'name'        => $request->name,
            'type'        => $request->type,
            'description' => $request->input('description'),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'id'      => $id,
        ]);
    }

    /**
     * Soft delete a category (and its subcategories).
     */
    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            DB::table('categories')->where('id', $id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('categories')->where('parent_id', $id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Category removed successfully',
        ]);
    }

    /**
     * Add a subcategory under a parent category.
     */
    public function storeSubcategory(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $parent = DB::table('categories')->where('id', $id)->first();

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        DB::table('categories')->insert([
            'parent_id'   => $id,
            'type'        => $parent->type,
            'name'        => $request->name,
            'slug'        => $this->uniqueSlug($request->name),
            'sort'        => (int) DB::table('categories')->where('parent_id', $id)->max('sort') + 1,
            'is_active'   => true,
            'visibility_mode' => 'public',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory added successfully',
        ], 201);
    }

    /**
     * Remove a subcategory.
     */
    public function destroySubcategory(Request $request, $id, $slug)
    {
        DB::table('categories')
            ->where('parent_id', $id)
            ->where('slug', $slug)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategory removed successfully',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 1;

        while (DB::table('categories')->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
