<?php

namespace App\Http\Controllers\API;

use App\Models\Payment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\KorbaXchangeService;

class PaymentController extends Controller
{
    // public function testPayment(KorbaXchangeService $korba)
    // {
    //     $payload = [
    //         'customer_number' => '0555804252',
    //         'amount'          => 50.0,
    //         'transaction_id'  => 'trx124',
    //         'network_code'    => 'MTN',
    //         'callback_url'    => 'https://10.10.10.90:8002/api/callback',
    //         'description'     => 'Task Purchase Payment',
    //         'client_id'       => 1358,
    //     ];

    //     try {
    //         $response = $korba->collect($payload);
    //         return response()->json([
    //             'status'  => true,
    //             'message' => 'Payment request sent successfully.',
    //             'data'    => $response,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Payment request failed.',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    // public function callback(Request $request){
    //     return $request;
    // }

    public function GetPaymentFromBrand(KorbaXchangeService $korba, Request $request)
    {
//        dd($korba);
       $data = $request->validate([
            'brand_id'       => 'required|exists:users,id',
            'amount'         => 'required|numeric|min:1',
            'network_code'   => 'required|string',
//            'customer_number'=> 'required|string',
//            'customer_number'=> '0555804252',
            'task_id'        => 'required|exists:tasks,id',
        ]);



        $transactionId = 'PAY' . time() . rand(100, 999);

        try {
            $payload = [
                'customer_number' => '0555804252',
                'amount'          => $request->amount,
                'transaction_id'  => $transactionId,
                'client_id'       => 1358,
                'network_code'    => $request->network_code,
                'callback_url'    => route('korba.callback'),
                'description'     => 'Task Payment from Brand',
            ];

            $response = $korba->collect($payload);
            dd($response);
            // Record payment in your DB
            Payment::create([
                'user_id'         => $request->brand_id,
                'task_id'         => $request->task_id,
                'transaction_id'  => $transactionId,
                'amount'          => $request->amount,
                'status'          => 'pending',
                'network_code'    => $request->network_code,
                'customer_number' => $request->customer_number,
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'Payment initiated successfully by Brand.',
                'data'    => $response,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Payment request failed.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function PayoutToPerformer(KorbaXchangeService $korba, Request $request)
    {
        $request->validate([
            'performer_id'    => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:1',
            'network_code'    => 'required|string',
//            'customer_number' => 'required|string',
            'customer_number' => '0555804252',
        ]);

        $performer = User::find($request->performer_id);
//        dd($performer->balance);
        // Optional: check if performer has enough available balance
        if ($performer->balance < $request->amount) {
            return response()->json([
                'status'  => false,
                'message' => 'Insufficient balance for withdrawal.',
            ], 400);
        }

        $transactionId = 'WD' . time() . rand(100, 999);

        try {
            $payload = [
                'customer_number'  => $request->customer_number,
                'amount'           => $request->amount,
                'transaction_id'   => $transactionId,
                'network_code'     => $request->network_code,
                'callback_url'     => route('korba.callback'),
                'description'      => 'Payout to Task Performer',
                'client_id'        => 1358,
                'beneficiary_name' => $performer->name,
            ];

            $response = $korba->disburse($payload);
            dd($response);
            // Record withdrawal
            Withdrawal::create([
                'user_id'         => $request->performer_id,
                'transaction_id'  => $transactionId,
                'amount'          => $request->amount,
                'status'          => 'pending',
                'network_code'    => $request->network_code,
                'customer_number' => $request->customer_number,
            ]);

            // Deduct pending balance immediately if needed
            $performer->decrement('available_balance', $request->amount);

            return response()->json([
                'status'  => true,
                'message' => 'Withdrawal request sent successfully.',
                'data'    => $response,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to process payout.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function callback(Request $request)
    {
        // Update both Payment or Withdrawal records if matched
        Payment::where('transaction_id', $request->transaction_id)
            ->update(['status' => strtolower($request->status), 'message' => $request->message]);

        Withdrawal::where('transaction_id', $request->transaction_id)
            ->update(['status' => strtolower($request->status), 'message' => $request->message]);

        return response()->json(['status' => true]);
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

    $response = \Http::withHeaders($headers)->post($url, $payload);

    if (!$response->successful()) {
        throw new \Exception('Failed to fetch collection network list: ' . $response->body());
    }

    return $response->json();
}
}
