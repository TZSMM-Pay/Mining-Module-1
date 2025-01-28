
// Add After  " if($method->auto && $setting->auto_deposit) {" this  
if ($method->tag == 'tzsmmpay') {
    $apiKey = $method->settings;
    $settings = json_decode($apiKey, true);
    $apiKey = $settings['api_key']['value'] ?? null;

    $url = 'https://tzsmmpay.com/api/payment/create';

    $paymentData = [
        'api_key' => $apiKey,
        'cus_name' => auth()->user()->name,
        'cus_email' => auth()->user()->email,
        'cus_number' => auth()->user()->phone,
        'amount' => $request->amount,
        'currency' => 'BDT',
        'success_url' => route('user.onepay'),
        'cancel_url' => route('user.onepay'),
        'callback_url' => route('tzsmmpay.callback', auth()->user()->id),
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($paymentData),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // Decode the JSON response
    $responseData = json_decode($response, true);

    if ($responseData && $responseData['success']) {
        $model = new Deposit();
        $model->user_id = auth()->id();
        $model->method_name = $method->name;
        $model->order_id = $reference;
        $model->transaction_id = $responseData['trx_id'];
        $model->number = $request->acc_acount;
        $model->amount = $request->amount;
        $model->charge_amount = $final_amount;
        $model->final_amount = $request->amount - $final_amount;
        $model->pay_link = $responseData['payment_url'];
        $model->data = json_encode($responseData); // Convert array to JSON string
        $model->date = date('d-m-Y H:i:s');
        $model->status = 'pending';
        $model->save();

        return redirect($responseData['payment_url']);
    } else {
        return redirect()->route('user.onepay')->with("error", $responseData['messages'] ?? 'An error occurred.');
    }
}

// Charge Payment
$chargePayment = $payment->charge($reference, $setting->cur_text, $request->amount, $method->tag);

// Exception
if($chargePayment['status'] == false) {
    return redirect()->route('user.onepay')->with("error", $chargePayment['message']);
}

$jsonData = json_encode([
    'reference' => $chargePayment['data']['reference'],
    'order_ref' => $chargePayment['data']['order_ref']
]);

$linkData = $chargePayment['data']['link'];

$model = new Deposit();
$model->user_id = Auth::id();
$model->method_name = $method->name;
$model->order_id = $reference;
$model->transaction_id = $chargePayment['data']['order_ref'];
$model->number = $request->acc_acount;
$model->amount = $request->amount;
$model->charge_amount = $final_amount;
$model->final_amount = $request->amount - $final_amount;
$model->pay_link = $linkData;
$model->data = $jsonData;
$model->date = date('d-m-Y H:i:s');
$model->status = 'pending';
$model->save();

return redirect()->away($linkData);
}
