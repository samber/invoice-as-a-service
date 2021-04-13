<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Controller;
use App\Helpers\PDF;
use App\Helpers\Storage;
use App\Rules\OtherField;
use App\Rules\Base64Image;
use Alphametric\Validation\Rules\EncodedImage;

class InvoiceController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            // required
            'id' => 'required|string|max:255',
            'currency' => 'required|string|max:5',
            'lang' => 'required|string|in:en',
            'date' => 'required|integer|min:0|max:2147483647',
            'due_date' => 'required|integer|min:0|max:2147483647',
            'paid' => 'nullable|boolean',
            'payment_link' => 'nullable|string|active_url',

            // optional
            'decimals' => 'nullable|integer|min:0|max:10',
            'notes' => 'nullable|string|max:65535',

            // items
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|min:3|max:255',
            'items.*.description' => 'nullable|string|min:3|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.tax' => 'required|numeric',

            // customer
            'customer.summary' => 'required|string|min:2|max:255',
            'customer.address_line_1' => 'required|string|min:1|max:255',
            'customer.address_line_2' => 'nullable|string|min:1|max:255',
            'customer.address_line_3' => 'nullable|string|min:1|max:255',
            'customer.address_line_4' => 'nullable|string|min:1|max:255',
            'customer.phone' => 'nullable|string|min:3|max:255',
            'customer.email' => 'nullable|email|min:3|max:255',
            'customer.siret' => 'nullable|string|min:3|max:255',    // @Deprecated
            'customer.other.*' => ['nullable', new OtherField],

            // company
            'company.summary' => 'required|string|min:2|max:255',
            'company.address_line_1' => 'required|string|min:1|max:255',
            'company.address_line_2' => 'nullable|string|min:1|max:255',
            'company.address_line_3' => 'nullable|string|min:1|max:255',
            'company.address_line_4' => 'nullable|string|min:1|max:255',
            'company.phone' => 'nullable|string|min:3|max:255',
            'company.email' => 'nullable|email|min:3|max:255',
            'company.logo' => 'nullable|string|max:255',    // @Deprecated
            'company.logo_url' => 'nullable|string|max:255',
            'company.logo_b64' => ['nullable', new Base64Image(['png', 'jpg', 'jpeg', 'gif', 'bmp'])],
            'company.siret' => 'nullable|string|min:3|max:255',    // @Deprecated
            'company.other.*' => ['nullable', new OtherField],

            // upload pdf into aws-s3 - optional
            's3.presigned_url' => 'nullable|string|active_url',

            // upload pdf into ftp - optional
            'ftp.host' => 'nullable|string',
            'ftp.port' => 'nullable|integer',
            'ftp.ssl' => 'nullable|boolean',
            'ftp.passive' => 'nullable|boolean',
            'ftp.username' => 'nullable|string|required_with:ftp.host',
            'ftp.password' => 'nullable|string|required_with:ftp.host',
            'ftp.path' => 'nullable|string|required_with:ftp.host',

            // upload pdf into webhook - optional
            'webhook.url' => 'nullable|string|active_url',
            'webhook.headers' => 'nullable',

            // upload pdf into zapier hook - optional
            'zapier.zap_url' => 'nullable|string|url|starts_with:https://hooks.zapier.com/hooks/catch/',
            'zapier.filename' => 'nullable|string',
        ]);

        if ($validator->fails())
            return response()->json($validator->errors(), 422);

        $date = date("d M Y", $data['date']);
        $due_date = date("d M Y", $data['due_date']);
        $paid = !array_key_exists('paid', $data) || $data['paid'] != true ? false : $data['due_date'];
        $payment_link = !array_key_exists('payment_link', $data) || $data['payment_link'] == NULL ? NULL : $data['payment_link'];
        $decimals = !array_key_exists('decimals', $data) || $data['decimals'] == NULL ? 2 : $data['decimals'];

        $sub_total = 0;
        $tax_total = 0;
        foreach ($data['items'] as $i => $item) {
            if ($item['quantity'] == NULL)
                $data['items'][$i]['quantity'] = 1;

            $tax_rate = $item['tax'] == NULL ? 0 : $item['tax'];
            $amount = round($item['price'] * $data['items'][$i]['quantity'], $decimals);
            $tax = round($amount * $tax_rate / 100, $decimals);

            $data['items'][$i]['total'] = $amount;
            $sub_total += $amount;
            $tax_total += $tax;
        }

        $total = $sub_total + $tax_total;

        $pdf = new PDF(
            $data['id'],
            $data['currency'],
            $data['lang'],
            $date,
            $due_date,
            $paid,
            $payment_link,
            $data['notes'],
            $data['items'],
            $data['customer'],
            $data['company'],
            $sub_total,
            $tax_total,
            $total
        );

        $doc = $pdf->build('default');

		$response = [];
        $fileName = "invoice_". $data["id"] ."_" . time() .".pdf";

        if (isset($data['s3']) && isset($data['s3']['presigned_url'])) {
            $storage = new Storage($doc);
			$response['s3'] = $storage->uploadS3($data['s3']['presigned_url']);
        }

		if (isset($data['ftp']) && isset($data['ftp']['host'])) {
            $storage = new Storage($doc);
            $response['ftp'] = $storage->uploadFTP($data['ftp'], $fileName);
        }

		if (isset($data['webhook']) && isset($data['webhook']['url'])) {
            $storage = new Storage($doc);
            $response['webhook'] = $storage->uploadWebhook($data['webhook'], $fileName, $data);
        }

		if (isset($data['zapier']) && isset($data['zapier']['zap_url'])) {
            $storage = new Storage($doc);
            $response['zapier'] = $storage->uploadZapier($data['zapier'], $fileName, $data);
        }

		if (!count($response)) {
            return response($doc, 201)->header('Content-Type', 'application/pdf');
        } else {
			return response($response, 201);
		}
    }
}
