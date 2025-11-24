<?php

namespace App\Http\Controllers\API;

use App\Models\SocialMedia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class SocialMediaController extends Controller
{
    public function addPlatform(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:social_media,name',
                'icon' => 'required|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $iconPath = null;
            if ($request->hasFile('icon')) {
                $file = $request->file('icon');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($request->name) . '.' . $extension;
                $iconPath = $file->storeAs('social_icons', $fileName, 'public');
            }
            $platform = SocialMedia::create([
                'name' => $request->name,
                'icon_url' => $iconPath,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Social media platform added successfully.',
                'data'    => $platform,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to add social media platform.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function viewAllPlatforms()
    {
        try {
            $platforms = SocialMedia::orderBy('name', 'asc')->get();

            return response()->json([
                'status'  => true,
                'message' => 'Social media platform list retrieved successfully.',
                'data'    => $platforms,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch platforms.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function editPlatform(Request $request, $id)
    {
        try {
            $platform = SocialMedia::find($id);
            if (!$platform) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Platform not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|unique:social_media,name,' . $id,
                'icon_url' => 'sometimes|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // If updating icon, delete old one first
            if ($request->hasFile('icon_url')) {
                if ($platform->icon_url && Storage::disk('public')->exists($platform->icon_url)) {
                    Storage::disk('public')->delete($platform->icon_url);
                }

                $file = $request->file('icon_url');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($request->name ?? $platform->name) . '.' . $extension;
                $iconPath = $file->storeAs('social_icons', $fileName, 'public');
                $platform->icon_url = $iconPath;
            }

            if ($request->has('name')) {
                $platform->name = $request->name;
            }

            $platform->save();

            return response()->json([
                'status'  => true,
                'message' => 'Platform updated successfully.',
                'data'    => $platform,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update platform.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function deletePlatform($id)
    {
        try {
            $platform = SocialMedia::find($id);
            if (!$platform) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Platform not found.',
                ], 404);
            }

            // Delete icon file
            if ($platform->icon_url && Storage::disk('public')->exists($platform->icon_url)) {
                Storage::disk('public')->delete($platform->icon_url);
            }

            $platform->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Platform deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete platform.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
