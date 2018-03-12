<?php

namespace App\Helpers;

use Log;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class PDF
{

    public $id;
    public $currency;
    public $lang;
    public $date;
    public $due_date;
    public $notes;

    public $items;
    public $customer;
    public $company;

    public $sub_total;
    public $tax_total;
    public $total;

    public function __construct($id, $currency, $lang, $date, $due_date, $notes, $items, $customer, $company, $sub_total, $tax_total, $total)
    {
        $this->id = $id;
        $this->currency = $currency;
        $this->lang = $lang;
        $this->date = $date;
        $this->due_date = $due_date;
        $this->notes = $notes;
        $this->items = $items;
        $this->customer = $customer;
        $this->company = $company;
        $this->sub_total = $sub_total;
        $this->tax_total = $tax_total;
        $this->total = $total;
    }

    public function build($template = 'default')
    {
        Log::info("💰💰💰  Building invoice #" . $this->id . ': ' . $this->total . ' ' . $this->currency . ' (from ' . $this->company['summary'] . ' to ' . $this->customer['summary'] . ')');
        Log::debug($this->toString());

        $template = strtolower($template);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ],
        ]);
        $pdf->setHttpContext($context);
        $pdf->loadHtml(View::make('invoices.'.$template, ['data' => $this]));
        $pdf->render();

        return $pdf->output();
    }

    public function toString()
    {
        return json_encode([
          'id' => $this->id,
          'currency' => $this->currency,
          'lang' => $this->lang,
          'date' => $this->date,
          'due_date' => $this->due_date,
          'notes' => $this->notes,
          'items' => $this->items,
          'customer' => $this->customer,
          'company' => $this->company,
          'sub_total' => $this->sub_total,
          'tax_total' => $this->tax_total,
          'total' => $this->total,
        ], JSON_PRETTY_PRINT);
    }

}
