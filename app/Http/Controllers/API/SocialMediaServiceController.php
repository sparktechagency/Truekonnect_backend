<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\SocialMediaService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class SocialMediaServiceController extends Controller
{
    public function addEngagement(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sm_id'           => 'required|exists:social_media,id',
                'country_id'      => 'required|exists:countries,id',
                'engagement_name' => 'required|string|max:255',
                'description'     => 'nullable|string',
                'min_quantity'    => 'required|integer|min:1',
                'unit_price'      => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $engagement = SocialMediaService::create([
                'sm_id'           => $request->sm_id,
                'country_id'      => $request->country_id,
                'engagement_name' => $request->engagement_name,
                'description'     => $request->description,
                'min_quantity'    => $request->min_quantity,
                'unit_price'      => $request->unit_price,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Engagement service added successfully.',
                'data'    => $engagement,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to add engagement service.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    // View all engagements
    public function viewAllEngagements($id)
    {
        try {
            $engagements = SocialMediaService::with(['socialMedia:id,name,icon_url','country:id,name,flag'])
                ->where('sm_id',$id)
                ->orderBy('engagement_name', 'asc')
                ->get();

            return response()->json([
                'status'  => true,
                'message' => 'Engagement services retrieved successfully.',
                'data'    => $engagements,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch engagement services.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Edit engagement
    public function editEngagement(Request $request, $id)
    {
        try {
            $engagement = SocialMediaService::find($id);
            if (!$engagement) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Engagement service not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'engagement_name' => 'sometimes|string|max:255',
                'description'     => 'nullable|string',
                'min_quantity'    => 'sometimes|integer|min:1',
                'unit_price'      => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $engagement->update($request->only([
                'engagement_name',
                'description',
                'min_quantity',
                'unit_price',
            ]));

            return response()->json([
                'status'  => true,
                'message' => 'Engagement service updated successfully.',
                'data'    => $engagement,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update engagement service.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Delete engagement
    public function deleteEngagement($id)
    {
        try {
            $engagement = SocialMediaService::find($id);
            if (!$engagement) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Engagement service not found.',
                ], 404);
            }

            $engagement->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Engagement service deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete engagement service.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
