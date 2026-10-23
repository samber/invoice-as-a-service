<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>@lang('messages.invoice') {{ $data->id }}</title>
        {{-- Dompdf supports CSS 2.1 only: layout uses tables, no flexbox or grid. --}}
        <style>
            @page { margin: 40pt 44pt 50pt 44pt; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.45; color: #222; margin: 0; }
            table { border-collapse: collapse; width: 100%; }
            td, th { vertical-align: top; }
            a { color: #1d4f91; text-decoration: none; }
            .muted { color: #777; }
            .right { text-align: right; }
            .title { font-size: 26pt; font-weight: bold; letter-spacing: 1pt; color: #111; margin: 0; }
            .label { font-size: 7.5pt; color: #777; padding-bottom: 2pt; }
            .party-name { font-size: 10.5pt; font-weight: bold; color: #111; padding-bottom: 3pt; }
            .rule { border-top: 2pt solid #111; margin: 14pt 0 16pt 0; height: 0; }
            .meta td { padding: 2pt 0; }
            .meta .k { color: #777; width: 70pt; }
            .items { margin-top: 24pt; }
            .items th { background: #f1f1f1; border-top: 1pt solid #111; border-bottom: 1pt solid #111; padding: 6pt 7pt; text-align: left; font-size: 8pt; }
            .items th.right { text-align: right; }
            .items td { padding: 7pt; border-bottom: 0.5pt solid #ddd; }
            .items .desc { color: #777; font-size: 8pt; }
            .totals td { padding: 4pt 7pt; }
            .totals .grand td { border-top: 1pt solid #111; border-bottom: 1pt solid #111; background: #f1f1f1; font-size: 11pt; font-weight: bold; padding: 7pt; }
            .notes { border-left: 2pt solid #ccc; padding-left: 9pt; white-space: pre-wrap; }
            .paid-stamp { position: absolute; top: -8pt; right: 0; }
        </style>
    </head>
    <body>
        @php
            // Renders the non-empty address lines of a party, one per row.
            $addressKeys = ['address_line_1', 'address_line_2', 'address_line_3', 'address_line_4'];
            $filled = fn ($party, $key) => array_key_exists($key, $party) && strlen($party[$key]) > 0;
            $logo = null;
            foreach (['logo', 'logo_url', 'logo_b64'] as $key) {
                if ($filled($data->company, $key)) {
                    $logo = $data->company[$key];
                    break;
                }
            }
        @endphp

        <div style="position: relative;">
            @if($data->paid == true)
                <div class="paid-stamp"><img src="{{ public_path('img/paid.png') }}" /></div>
            @endif

            <table>
                <tr>
                    <td style="width: 55%;">
                        @if ($logo)
                            <img style="max-width: 180pt; max-height: 70pt;" src="{{ $logo }}">
                        @else
                            <div class="party-name" style="font-size: 14pt;">{{ $data->company['summary'] }}</div>
                        @endif
                    </td>
                    <td class="right">
                        <h1 class="title">@lang('messages.invoice')</h1>
                        @if ($data->id)
                            <div class="muted" style="font-size: 10pt;">@lang('messages.number_abbreviation') {{ $data->id }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="rule"></div>

            <table>
                <tr>
                    <td style="width: 55%; padding-right: 20pt;">
                        <div class="label">@lang('messages.bussiness_details')</div>
                        <div class="party-name">{{ $data->company['summary'] }}</div>
                        @foreach ($addressKeys as $key)
                            @if ($filled($data->company, $key)){{ $data->company[$key] }}<br/>@endif
                        @endforeach
                        @if ($filled($data->company, 'phone'))@lang('messages.phone'): {{ $data->company['phone'] }}<br/>@endif
                        @if ($filled($data->company, 'email'))@lang('messages.email'): {{ $data->company['email'] }}<br/>@endif
                        {{-- DEPRECATED --}}
                        @if ($filled($data->company, 'siret'))SIRET: {{ $data->company['siret'] }}<br/>@endif
                        @if (array_key_exists('other', $data->company) && count($data->company['other']) > 0)
                            <div style="margin-top: 5pt;" class="muted">
                                @foreach ($data->company['other'] as $item)
                                    @if (gettype($item) == 'string'){{ $item }}<br/>
                                    @elseif (gettype($item) == 'array'){{ $item['title'] . ': ' . $item['content'] }}<br/>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        <table class="meta">
                            <tr><td class="k">@lang('messages.date')</td><td>{{ $data->date }}</td></tr>
                            <tr><td class="k">@lang('messages.due_date')</td><td><b>{{ $data->due_date }}</b></td></tr>
                            @if ($data->payment_link)
                                <tr>
                                    <td class="k">@lang('messages.payment_link')</td>
                                    <td><a href="{{ $data->payment_link }}">{{ $data->payment_link }}</a></td>
                                </tr>
                            @endif
                        </table>

                        <div style="margin-top: 16pt; padding: 10pt 12pt; border: 1pt solid #ddd;">
                            <div class="label">@lang('messages.customer_details')</div>
                            <div class="party-name">{{ $data->customer['summary'] }}</div>
                            @foreach ($addressKeys as $key)
                                @if ($filled($data->customer, $key)){{ $data->customer[$key] }}<br/>@endif
                            @endforeach
                            @if ($filled($data->customer, 'phone'))@lang('messages.phone'): {{ $data->customer['phone'] }}<br/>@endif
                            @if ($filled($data->customer, 'email'))@lang('messages.email'): {{ $data->customer['email'] }}<br/>@endif
                            {{-- DEPRECATED --}}
                            @if ($filled($data->customer, 'siret'))SIRET: {{ $data->customer['siret'] }}<br/>@endif
                            @if (array_key_exists('other', $data->customer) && count($data->customer['other']) > 0)
                                <div style="margin-top: 5pt;" class="muted">
                                    @foreach ($data->customer['other'] as $item)
                                        @if (gettype($item) == 'string'){{ $item }}<br/>
                                        @elseif (gettype($item) == 'array'){{ $item['title'] . ': ' . $item['content'] }}<br/>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 18pt;">@lang('messages.number_abbreviation')</th>
                    <th>@lang('messages.item_header_name')</th>
                    <th class="right" style="width: 40pt;">@lang('messages.item_header_quantity')</th>
                    <th class="right" style="width: 62pt;">@lang('messages.item_header_unitprice')</th>
                    <th class="right" style="width: 34pt;">@lang('messages.item_header_tax')</th>
                    <th class="right" style="width: 70pt;">@lang('messages.item_header_total')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->items as $item)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td>
                            <b>{{ $item['title'] }}</b>
                            @if (!empty($item['description']))
                                <div class="desc">{{ $item['description'] }}</div>
                            @endif
                        </td>
                        <td class="right">{{ $item['quantity'] }}</td>
                        <td class="right">{{ $item['price'] }} {{ $data->currency }}</td>
                        <td class="right">{{ $item['tax'] }} %</td>
                        <td class="right">{{ $item['total'] }} {{ $data->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="margin-top: 14pt; page-break-inside: avoid;">
            <tr>
                <td style="width: 50%; padding-right: 24pt;">
                    @if($data->notes)
                        <div class="label">@lang('messages.notes')</div>
                        <div class="notes">{{ $data->notes }}</div>
                    @endif
                </td>
                <td>
                    <table class="totals">
                        <tr>
                            <td>@lang('messages.summary_subtotal')</td>
                            <td class="right">{{ $data->sub_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr>
                            <td>@lang('messages.summary_taxes')</td>
                            <td class="right">{{ $data->tax_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr class="grand">
                            <td>@lang('messages.summary_total')</td>
                            <td class="right">{{ $data->total }} {{ $data->currency }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
