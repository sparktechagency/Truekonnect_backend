<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PromoLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContentControll extends Controller
{
    public function index()
    {
        try {
            $allLinks = PromoLink::all();

            return $this->successResponse($allLinks, 'All Links Found', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'link' => 'required|string'
            ]);

//            $data['link'] = $this->uploadFile($request->file('link'),'video/');
            $promoLink = PromoLink::updateOrCreate(['id'=>1],$data);
//            dd($promoLink);
            return $this->successResponse($promoLink, 'Link Added Successfully', Response::HTTP_CREATED);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete($id)
    {
        try {
            $promo = PromoLink::find($id);

            $this->deleteFile($promo->link);
            $promo->delete();

            return $this->successResponse(null, 'Link Deleted Successfully', Response::HTTP_OK);
        }
        catch (\Exception $e){
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
