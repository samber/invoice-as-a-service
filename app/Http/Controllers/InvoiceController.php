<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Controller;
use App\Helpers\PDF;
use App\Helpers\Storage;
use App\Rules\OtherField;

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
            'customer.other.*' => ['nullable', new OtherField],

            // company
            'company.summary' => 'required|string|min:2|max:255',
            'company.address_line_1' => 'required|string|min:1|max:255',
            'company.address_line_2' => 'nullable|string|min:1|max:255',
            'company.address_line_3' => 'nullable|string|min:1|max:255',
            'company.address_line_4' => 'nullable|string|min:1|max:255',
            'company.phone' => 'nullable|string|min:3|max:255',
            'company.email' => 'nullable|email|min:3|max:255',
            'company.logo' => 'nullable|string|max:255',
            'company.siret' => 'nullable|string|min:3|max:255',
            'company.other.*' => ['nullable', new OtherField],

            // optional
            's3.presigned_url' => 'nullable|string|active_url',

            'ftp.host' => 'nullable|string',
            'ftp.port' => 'nullable|integer',
            'ftp.ssl' => 'nullable|boolean',
            'ftp.passive' => 'nullable|boolean',
            'ftp.username' => 'nullable|string|required_with:ftp.host',
            'ftp.password' => 'nullable|string|required_with:ftp.host',
            'ftp.path' => 'nullable|string|required_with:ftp.host'
        ]);

        if ($validator->fails())
            return response()->json($validator->errors()->toJson(), 422);

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

        if (isset($data['ftp']) && isset($data['ftp']['host'])) {
            $storage = new Storage($doc);
            $res_body = $storage->uploadFTP($data['ftp'], $data['id']);
            return response(['ftp' => $res_body], $res_body['uploaded'] == true ? 201 : 500);
        }

        if (isset($data['s3']) && isset($data['s3']['presigned_url'])) {
            $storage = new Storage($doc);
            $res_body = $storage->uploadS3($data['s3']['presigned_url']);
            return response(['s3' => $res_body], $res_body['uploaded'] == true ? 201 : 500);
        } else {
            return response($doc, 201);
        }
    }
}
