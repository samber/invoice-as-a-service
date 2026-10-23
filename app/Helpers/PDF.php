<?php

namespace App\Helpers;

use Log;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class PDF
{
    // Identifies this service as the tool that generated the document, in the PDF info dictionary.
    private const GENERATOR = 'invoice-as-a-service';

    public $id;
    public $currency;
    public $lang;
    public $date;
    public $due_date;
    public $paid;
    public $payment_link;
    public $purchase_order;
    public $notes;

    public $items;
    public $customer;
    public $company;

    public $sub_total;
    public $tax_total;
    public $total;

    public function __construct($id, $currency, $lang, $date, $due_date, $paid, $payment_link, $purchase_order, $notes, $items, $customer, $company, $sub_total, $tax_total, $total)
    {
        $this->id = $id;
        $this->currency = $currency;
        $this->lang = $lang;
        $this->date = $date;
        $this->due_date = $due_date;
        $this->paid = $paid;
        $this->payment_link = $payment_link;
        $this->purchase_order = $purchase_order;
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
        $this->addMetadata($pdf);

        // Signing must come last: any later byte change would invalidate the signature.
        $signer = PdfSigner::fromConfig();
        $output = $pdf->output();

        return $signer === null ? $output : $signer->sign($output);
    }

    // Dompdf already sets Title (from the template <title>) and defaults Producer and the dates;
    // override them so the PDF names this service and the dates are UTC at generation time.
    private function addMetadata(Dompdf $pdf): void
    {
        $now = 'D:' . gmdate('YmdHis') . "+00'00'";

        $pdf->addInfo('Author', $this->company['summary']);
        $pdf->addInfo('Subject', __('messages.metadata_subject', ['customer' => $this->customer['summary']]));
        $pdf->addInfo('Creator', self::GENERATOR);
        $pdf->addInfo('Producer', self::GENERATOR);
        $pdf->addInfo('CreationDate', $now);
        $pdf->addInfo('ModDate', $now);
    }

    public function toString()
    {
        return json_encode([
          'id' => $this->id,
          'currency' => $this->currency,
          'lang' => $this->lang,
          'date' => $this->date,
          'due_date' => $this->due_date,
          'paid' => $this->paid,
          'payment_link' => $this->payment_link,
          'purchase_order' => $this->purchase_order,
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
