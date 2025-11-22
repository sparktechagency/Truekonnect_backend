<?php

namespace App\Http\Controllers\API;

use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\KorbaXchangeService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function GetPaymentFromBrand(KorbaXchangeService $korba, Request $request)
    {
       $data = $request->validate([
//            'brand_id'       => 'required|exists:users,id',
            'amount'         => 'required|numeric|min:1',
            'network_code'   => 'required|string',
            'customer_number'=> 'required|string',
//            'customer_number'=> '0555804252',
            'task_id'        => 'required|exists:tasks,id',
        ]);

        $transactionId = 'PAY' . time() . rand(100, 999);

        try {
            $payload = [
                'customer_number' => $request->customer_number,
                'amount'          => $request->amount,
                'transaction_id'  => $transactionId,
                'client_id'       => 1358,
                'network_code'    => $request->network_code,
//                'callback_url'    =>"https://6c3ef2f08d3a.ngrok-free.app/webhook/75f9cdac-43b4-41c6-8b16-c72a421c9e52?token=9a3009fff5cbf31ac08041e42e756f2ad4bff14b",
//                'callback_url'    => 'https://6c3ef2f08d3a.ngrok-free.app/webhook/',
                'callback_url' => route('korba.callback'),
                'description'     => 'Task Payment from Brand',
            ];

            $response = $korba->collect($payload);

            Payment::create([
                'user_id'         => Auth::id(),
                'task_id'         => $request->task_id,
                'transaction_id'  => $transactionId,
                'amount'          => $request->amount,
                'status'          => 'pending',
                'network_code'    => $request->network_code,
                'customer_number' => $request->customer_number,
            ]);

//            return response()->json([
//                'status'  => true,
//                'message' => 'Payment initiated successfully by Brand.',
//                'data'    =>[
//                    $response,
//                    'trnxId'=> $transactionId
//                ],
//            ]);

            return $this->successResponse([
                $response,
                'trnxId'=> $transactionId
            ],'Payment Initiated Successfully by Brand.',Response::HTTP_OK);

        } catch (\Exception $e) {
//            return response()->json([
//                'status'  => false,
//                'message' => 'Payment request failed.',
//                'error'   => $e->getMessage(),
//            ], 500);

            return $this->errorResponse('Something went wrong, please try again later. ' .$e->getMessage(),Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function PayoutToPerformer(KorbaXchangeService $korba, Request $request)
    {
        $request->validate([
//            'performer_id'    => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:1',
            'network_code'    => 'required|string',
            'customer_number' => 'required|string',
//            'customer_number' => '0555804252',
            'bank_code'=>'sometimes|string',
            'recipient_bank_name'=>'sometimes|string',
            'bank_account_number'=>'sometimes|string',
            'bank_account_name'=>'sometimes|string',
        ]);
//        dd(Auth::user()->balance);
        if (Auth::user()->balance < $request->amount) {
            return response()->json([
                'status'  => false,
                'message' => 'Insufficient Balance.',
            ], 400);
        }

        $transactionId = 'WD' . time() . rand(100, 999);

        try {
            if ($request->network_code == 'ISP') {
                $payload = [
                    'customer_number' => $request->customer_number,
                    'amount' => $request->amount,
                    'transaction_id' => $transactionId,
                    'network_code' => $request->network_code,
                    'callback_url' => route('korba.callback'),
//                  'callback_url'     => "https://webhook.site/1c4d4a5d?transaction_id={$transactionId}",
                    'description' => 'Payout to Task Performer',
                    'client_id' => 1358,
                    'beneficiary_name' => Auth::user()->name,
                    'bank_code'=>$request->bank_code,
                    'recipient_bank_name'=>$request->recipient_bank_name,
                    'bank_account_number'=>$request->bank_account_number,
                    'bank_account_name'=>$request->bank_account_name,
                ];
            }else{
                $payload = [
                    'customer_number' => $request->customer_number,
                    'amount' => $request->amount,
                    'transaction_id' => $transactionId,
                    'network_code' => $request->network_code,
                    'callback_url' => route('korba.callback'),
//                  'callback_url'     => "https://webhook.site/1c4d4a5d?transaction_id={$transactionId}",
                    'description' => 'Payout to Task Performer',
                    'client_id' => 1358,
                    'beneficiary_name' => Auth::user()->name,
                ];
            }

            $response = $korba->disburse($payload);

            Withdrawal::create([
                'user_id'         => Auth::id(),
                'transaction_id'  => $transactionId,
                'amount'          => $request->amount,
                'status'          => 'pending',
                'network_code'    => $request->network_code,
                'customer_number' => $request->customer_number,
            ]);

            Auth::user()->decrement('balance', $request->amount);

//            return response()->json([
//                'status'  => true,
//                'message' => 'Withdrawal request sent successfully.',
//                'data'    => [$response,'transactionId'=>$transactionId],
//            ]);

            return $this->successResponse([$response,'transactionId'=>$transactionId], 'Withdrawal request sent successfully.', Response::HTTP_OK);
        } catch (\Exception $e) {
//            return response()->json([
//                'status'  => false,
//                'message' => 'Failed to process payout.',
//                'error'   => $e->getMessage(),
//            ], 500);

            return $this->errorResponse('Failed to process payout. '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function callbackURL(Request $request)
    {
        try {
            $transactionId = $request->transaction_id;
            $status = strtolower($request->status ?? 'pending');
            $message = $request->message ?? null;
//        dd($transactionId);
            Payment::where('transaction_id', $transactionId)
                ->update([
                    'status' => $status,
                    'message' => $message,
                ]);

            // Update Withdrawal record if applicable
            Withdrawal::where('trnx_id', $transactionId)
                ->update([
                    'status' => $status,
                    'message' => $message
                ]);


            return response()->json(['status' => true]);
        }
        catch (\Exception $e) {
            return $this->errorResponse('Something went wrong. ' .$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCollectionNetworks()
    {
        $payload = [
            'client_id' => env('KORBA_CLIENT_ID'),
        ];

        // HMAC signature for authentication
        $sortedKeys = collect($payload)->keys()->sort()->toArray();
        $message = collect($sortedKeys)->map(fn($k) => "$k={$payload[$k]}")->implode('&');
        $signature = hash_hmac('sha256', $message, env('KORBA_SECRET_KEY'));

        $headers = [
            'Authorization' => "HMAC ".env('KORBA_CLIENT_KEY').":$signature",
            'Content-Type'  => 'application/json'
        ];

        $url = env('KORBA_BASE_URL', 'https://testxchange.korba365.com/api/v1.0/') . 'collection_network_options/';

        $response = Http::withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch collection network list: ' . $response->body());
        }

        return $response->json();
    }

    public function bankLookup()
    {
        $payload = [
            'client_id' => env('KORBA_CLIENT_ID'),
        ];

        // HMAC signature for authentication
        $sortedKeys = collect($payload)->keys()->sort()->toArray();
        $message = collect($sortedKeys)->map(fn($k) => "$k={$payload[$k]}")->implode('&');
        $signature = hash_hmac('sha256', $message, env('KORBA_SECRET_KEY'));

        $headers = [
            'Authorization' => "HMAC ".env('KORBA_CLIENT_KEY').":$signature",
            'Content-Type'  => 'application/json'
        ];

        $url = env('KORBA_BASE_URL', 'https://testxchange.korba365.com/api/v1.0/') . 'authorized_bank_list/';

        $response = Http::withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch collection network list: ' . $response->body());
        }

        return $this->successResponse(collect($response->json()),'Bank Lookup', Response::HTTP_OK);
    }

    public function customerLookup(Request $request)
    {
        $data = $request->validate([
            'bank_account_number'=>'required|string',
            'bank_code' => 'required|string',
        ]);
        $payload = [
            'client_id' => env('KORBA_CLIENT_ID'),
            'bank_account_number' => $data['bank_account_number'],
            'bank_code' => $data['bank_code'],
        ];

        // HMAC signature for authentication
        $sortedKeys = collect($payload)->keys()->sort()->toArray();
        $message = collect($sortedKeys)->map(fn($k) => "$k={$payload[$k]}")->implode('&');
        $signature = hash_hmac('sha256', $message, env('KORBA_SECRET_KEY'));

        $headers = [
            'Authorization' => "HMAC ".env('KORBA_CLIENT_KEY').":$signature",
            'Content-Type'  => 'application/json'
        ];

        $url = env('KORBA_BASE_URL', 'https://testxchange.korba365.com/api/v1.0/') . 'authorized_customer_lookup/';

        $response = Http::withHeaders($headers)->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch collection network list: ' . $response->body());
        }

        return $this->successResponse(collect($response->json()),'Customer Lookup', Response::HTTP_OK);
    }
}
