<?php

namespace App\Http\Controllers\API;

use App\Models\SocialMedia;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
                return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
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

            return $this->successResponse($platform, 'Social media added!',Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function viewAllPlatforms()
    {
        try {
            $platforms = SocialMedia::orderBy('name', 'asc')->get();

            return $this->successResponse($platforms, 'All Platforms', Response::HTTP_OK);


        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function editPlatform(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $platform = SocialMedia::find($id);
            if (!$platform) {
                return $this->errorResponse('Social media not found!', null,Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|unique:social_media,name,' . $id,
                'icon_url' => 'sometimes|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
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

            DB::commit();

            return $this->successResponse($platform, 'Social media updated!',Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function deletePlatform($id)
    {
        DB::beginTransaction();
        try {
            $platform = SocialMedia::find($id);
            if (!$platform) {
                return $this->errorResponse('Social media not found!',null, Response::HTTP_NOT_FOUND);
            }

            if ($platform->icon_url && Storage::disk('public')->exists($platform->icon_url)) {
                Storage::disk('public')->delete($platform->icon_url);
            }

            $platform->delete();

            DB::commit();

            return $this->successResponse(null, 'Social media deleted!',Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Something went wrong ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
