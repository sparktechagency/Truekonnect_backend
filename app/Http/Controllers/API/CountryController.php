<?php

namespace App\Http\Controllers\API;

use App\Models\Countrie;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CountryController extends Controller
{
    // public function addNewCountry(Request $request)
    // {
    //     try {
    //         // Validate request
    //         $validator = Validator::make($request->all(), [
    //             'name'              => 'required|string|unique:countries,name',
    //             'dial_code'         => 'required|string|unique:countries,dial_code',
    //             'rate'              => 'required|numeric',
    //             'currency'          => 'required|string',
    //         ]);
    //         $request->validate([
    //             'flag'              => 'required|image|mimes:png,jpg,jpeg|max:2048'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status'  => false,
    //                 'message' => 'Validation failed.',
    //                 'errors'  => $validator->errors()->first()
    //             ], 422);
    //         }

    //         // Handle file upload
    //         $flagPath = null;
    //         if ($request->hasFile('flag')) {
    //         $file = $request->file('flag');
    //         $extension = $file->getClientOriginalExtension();
    //         $fileName = Str::slug($request->name) . '-' . preg_replace('/[^0-9+]/', '', $request->dial_code) . '.' . $extension;
    //         $flagPath = $file->storeAs('country_flags', $fileName, 'public');
    //         }

    //         // Create country record
    //         $country = Countrie::create([
    //             'name'          => $request->name,
    //             'dial_code'     => $request->dial_code,
    //             'flag'          => $flagPath,
    //             'token_rate'    => $request->rate,
    //             'currency_code' => $$request->currency
    //         ]);

    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'Successfully added new country.',
    //             'data'    => $country
    //         ], 201);

    //     } catch (\Exception $e) {
    //         // Something went wrong
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Failed to add new country.',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function addNewCountry(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'name'       => 'required|string|unique:countries,name',
                'dial_code'  => 'required|string|unique:countries,dial_code',
                'rate'       => 'required|numeric',
                'currency'   => 'required|string',
                'flag' => 'file|mimetypes:image/jpeg,image/jpg,image/png,image/webp|max:2048',
            ]);
            // $request->validate([
            //     'flag' => 'required|image|mimes:png,jpg,jpeg|max:2048'
            // ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Upload flag
            $flagPath = null;
            if ($request->hasFile('flag')) {
                $file = $request->file('flag');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($request->name) . '-' . preg_replace('/[^0-9+]/', '', $request->dial_code) . '.' . $extension;
                $flagPath = $file->storeAs('country_flags', $fileName, 'public');
            }

            // Create record
            $country = Countrie::create([
                'name'          => $request->name,
                'dial_code'     => $request->dial_code,
                'flag'          => $flagPath,
                'token_rate'    => $request->rate,
                'currency_code' => $request->currency,
            ]);

            return $this->successResponse($country, 'Successfully signed out.', Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewAllCountries()
    {
        try {
           $countries = Countrie::orderBy('created_at', 'desc')->get();

           return $this->successResponse($countries, 'Country list retrieved successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function editCountry(Request $request, $id)
    {
        try {
            $country = Countrie::find($id);
            if (!$country) {
                return $this->errorResponse('Country not found.',null, Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name'          => 'sometimes|string|unique:countries,name,' . $id,
                'dial_code'     => 'sometimes|string|unique:countries,dial_code,' . $id,
                'flag'          => 'sometimes|image|mimes:png,jpg,jpeg|max:2048',
                'rate'          => 'sometimes|numeric',
                'currency'      => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), $validator->errors()->first(),Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($request->hasFile('flag')) {
                if ($country->flag && Storage::disk('public')->exists($country->flag)) {
                    Storage::disk('public')->delete($country->flag);
                }
                $file = $request->file('flag');
                $extension = $file->getClientOriginalExtension();
                $fileName = Str::slug($request->name ?? $country->name)
                . '-' . preg_replace('/[^0-9+]/', '', $request->dial_code ?? $country->dial_code)
                . '.' . $extension;
                $flagPath = $file->storeAs('country_flags', $fileName, 'public');
                $country->flag = $flagPath;
            }
            if ($request->has('name')) {
                $country->name = $request->name;
            }
            if ($request->has('dial_code')) {
                $country->dial_code = $request->dial_code;
            }
            if ($request->has('rate')) {
                $country->token_rate = $request->rate;
            }
            if ($request->has('currency')) {
                $country->currency_code = $request->currency;
            }
            $country->save();
            return $this->successResponse($country, 'Country updated successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function deleteCountry($id)
    {
        try {
            $country = Countrie::find($id);
            if (!$country) {
                return $this->errorResponse('Country not found.', null,Response::HTTP_NOT_FOUND);
            }

            $country->delete();

            return $this->successResponse(null, 'Country deleted successfully.', Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong.',$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
