<?php

namespace App\Http\Controllers\API;

use App\Models\Countrie;
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
    //                 'errors'  => $validator->errors()
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
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
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

            return response()->json([
                'status'  => true,
                'message' => 'Successfully added new country.',
                'data'    => $country,
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to add new country.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function viewAllCountries()
    {
        try {
           $countries = Countrie::orderBy('created_at', 'desc')->get();
            return response()->json([
                'status'  => true,
                'message' => 'Country list retrieved successfully.',
                'data'    => $countries
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch countries.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function editCountry(Request $request, $id)
    {
        try {
            $country = Countrie::find($id);
            if (!$country) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Country not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name'          => 'sometimes|string|unique:countries,name,' . $id,
                'dial_code'     => 'sometimes|string|unique:countries,dial_code,' . $id,
                'flag'          => 'sometimes|image|mimes:png,jpg,jpeg|max:2048',
                'rate'          => 'sometimes|numeric',
                'currency'      => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors()
                ], 422);
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
            return response()->json([
                'status'  => true,
                'message' => 'Country updated successfully.',
                'data'    => $country
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to update country.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function deleteCountry($id)
    {
        try {
            $country = Countrie::find($id);
            if (!$country) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Country not found.'
                ], 404);
            }

            $country->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Country deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to delete country.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


}
