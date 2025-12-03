<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\SocialMediaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
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
                return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $engagement = SocialMediaService::create([
                'sm_id'           => $request->sm_id,
                'country_id'      => $request->country_id,
                'engagement_name' => $request->engagement_name,
                'description'     => $request->description,
                'min_quantity'    => $request->min_quantity,
                'unit_price'      => $request->unit_price,
            ]);

            return $this->successResponse($engagement,'Engagement service added successfully.',Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewAllEngagements($id)
    {
        try {
            $engagements = SocialMediaService::with(['socialMedia:id,name,icon_url','country:id,name,flag'])
                ->where('sm_id',$id)
                ->orderBy('engagement_name', 'asc')
                ->get();

            return $this->successResponse($engagements,'Engagements viewed successfully.',Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Edit engagement
    public function editEngagement(Request $request, $id)
    {
        try {
            $engagement = SocialMediaService::find($id);
            if (!$engagement) {
                return $this->errorResponse('Engagement not found',null,Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'engagement_name' => 'sometimes|string|max:255',
                'description'     => 'nullable|string',
                'min_quantity'    => 'sometimes|integer|min:1',
                'unit_price'      => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(),$validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $engagement->update($request->only([
                'engagement_name',
                'description',
                'min_quantity',
                'unit_price',
            ]));

            return $this->successResponse($engagement,'Engagement updated successfully.',Response::HTTP_OK);



        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function deleteEngagement($id)
    {
        try {
            $engagement = SocialMediaService::find($id);
            if (!$engagement) {
                return $this->errorResponse('Engagement not found',null,Response::HTTP_NOT_FOUND);
            }

            $engagement->delete();

            return $this->successResponse(null,'Engagement deleted successfully.',Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong ',$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
