<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TZSMMPayController extends Controller
{
    public function callback(Request $request)
    {
        try {
            // Validate the request inputs
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric',
                'cus_name' => 'required|string',
                'cus_email' => 'required|email',
                'cus_number' => 'required|string',
                'trx_id' => 'required|string',
                'status' => 'required|string',
                'extra' => 'nullable|array',
            ]);

            // Return validation errors if any
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'messages' => implode(', ', $validator->errors()->all()),
                ]);
            }

            // Retrieve the transaction ID from the request
            $track = $request->trx_id;

            // Check if a deposit exists for the provided transaction ID
            $deposit = Deposit::where('transaction_id', $track)->orderBy('id', 'DESC')->first();

            if (!$deposit) {
                return response()->json([
                    'success' => false,
                    'messages' => 'Deposit not found for transaction ID: ' . $track,
                ]);
            }

            // Check the payment status
            if ($request->status === 'Completed') {

                // Update user balance and handle VIP upgrades
                return $this->updateDeposit($deposit->order_id, $deposit->amount, $request->all());

            } else {
                return response()->json([
                    'success' => false,
                    'messages' => 'Payment status is not completed.',
                ]);
            }
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'success' => false,
                'messages' => 'An error occurred: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Update deposit transaction
     */
    public static function updateDeposit(string $reference, string $amount, array $data)
    {
        try {
            // Find the transaction by reference
            $transaction = Deposit::where('order_id', $reference)->first();

            // Check if transaction exists
            if (!$transaction) {
                throw new \Exception('Transaction not found.');
            }

            // Ensure the transaction status is not already completed
            if ($transaction->status == 'approved') {
                throw new \Exception('Transaction has already been completed.');
            }

            // Update transaction details
            $transaction->data = json_encode($data);
            $transaction->status = 'approved';
            $transaction->transaction_id = $data['trx_id'] ?? $data['orderNo'] ?? 'N/A';
            $transaction->save();

            // Retrieve the user
            $user = User::find($transaction->user_id);

            if (!$user) {
                throw new \Exception('User not found.');
            }

            // Update user balance
            $user->balance += $transaction->amount;
            $user->save();
            return 'Success';

        } catch (\Exception $e) {
            // Re-throw the exception to propagate it to the calling method
            throw new \Exception('Update Deposit Error: ' . $e->getMessage());
        }
    }


}
