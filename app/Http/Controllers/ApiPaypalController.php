<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class ApiPaypalController extends Controller
{
    public function createOrder(Request $request)
    {
        $request->validate([
            'price' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'description' => 'required|string',
        ]);

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));

        try {
            $provider->getAccessToken();
            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('paypal.success'),
                    "cancel_url" => route('paypal.cancel'),
                ],
                "purchase_units" => [
                    [
                        "amount" => [
                            "currency_code" => $request->currency,
                            "value" => $request->price,
                        ],
                        "description" => $request->description,
                    ],
                ],
            ]);

            Log::info("PayPal Order Created: ", $response);

            if (isset($response['id'])) {
                $approvalUrl = collect($response['links'])->firstWhere('rel', 'approve')['href'] ?? null;

                if ($approvalUrl) {
                    return response()->json([
                        'success' => true,
                        'approval_url' => $approvalUrl,
                        'order_id' => $response['id'],
                    ]);
                }
            }

            return response()->json(['success' => false, 'message' => 'Failed to create PayPal order'], 500);
        } catch (\Exception $e) {
            Log::error("Error Creating PayPal Order: ", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred'], 500);
        }
    }

    public function captureOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));

        try {
            $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($request->order_id);

            Log::info("PayPal Payment Captured: ", $response);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                // Save payment details to the database
                return response()->json(['success' => true, 'message' => 'Payment completed', 'data' => $response]);
            }

            return response()->json(['success' => false, 'message' => 'Payment not completed', 'data' => $response], 400);
        } catch (\Exception $e) {
            Log::error("Error Capturing PayPal Payment: ", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred'], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info("PayPal Webhook Received: ", $payload);

        if (isset($payload['event_type'])) {
            switch ($payload['event_type']) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    // Handle payment success
                    Log::info("Payment Completed: ", $payload);
                    break;
                case 'PAYMENT.CAPTURE.DENIED':
                    // Handle payment failure
                    Log::info("Payment Denied: ", $payload);
                    break;
                default:
                    Log::info("Unhandled Event: ", $payload);
            }
        }

        return response()->json(['status' => 'success']);
    }

    // public function success(Request $request)
    // {
    //     // Retrieve the PayPal order token from the query string
    //     $token = $request->query('token');
    //     return response()->json(['success' => true, 'message' => 'Payment approved', 'token' => $token]);
    // }

    public function success(Request $request)
    {
        // Retrieve the PayPal token from the query string
        $orderId = $request->query('token');

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'No order ID provided'], 400);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));

        try {
            $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($orderId);

            Log::info("PayPal Payment Captured: ", $response);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                // Save payment details to the database
                // Example: Payment::create([...]);

                return response()->json(['success' => true, 'message' => 'Payment completed', 'data' => $response]);
            }

            return response()->json(['success' => false, 'message' => 'Payment not completed', 'data' => $response], 400);
        } catch (\Exception $e) {
            Log::error("Error Capturing PayPal Payment: ", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'An error occurred'], 500);
        }
    }


    public function cancel()
    {
        return response()->json(['success' => false, 'message' => 'Payment cancelled']);
    }

}
