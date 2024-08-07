<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>@lang('messages.invoice') {{ $data->id }}</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <style>
            h1,h2,h3,h4,p,span,div { font-family: DejaVu Sans; }
        </style>
    </head>
    <body>
        <h1 style="margin-bottom: 0px; font-size: 25px;">@lang('messages.invoice')</h1>
        <hr style="margin: 10px 0px;">
        <div style="clear:both; position:relative;">
            <div style="position:absolute; top: 20px; left:0pt; width:200pt;">
                @if (array_key_exists('logo', $data->company) && strlen($data->company['logo']) > 0)
                    <!-- DEPRECATED -->
                    <img style="max-width:200pt;max-height:100pt" src="{{ $data->company['logo'] }}">
                @elseif (array_key_exists('logo_url', $data->company) && strlen($data->company['logo_url']) > 0)
                    <img style="max-width:200pt;max-height:100pt" src="{{ $data->company['logo_url'] }}">
                @elseif (array_key_exists('logo_b64', $data->company) && strlen($data->company['logo_b64']) > 0)
                    <img style="max-width:200pt;max-height:100pt" src="{{ $data->company['logo_b64'] }}">
                @endif
            </div>
            <div style="margin-left:300pt;">
                <b>@lang('messages.date'): </b> {{ $data->date }}<br />
                <b>@lang('messages.due_date'): </b> {{ $data->due_date }}<br />
                @if ($data->id)
                    <b>@lang('messages.invoice'): </b> @lang('messages.number_abbreviation') {{ $data->id }}
                @endif
                <br />
                @if ($data->payment_link)
                    <b>@lang('messages.payment_link'): </b> <a href="{{ $data->payment_link }}">{{ $data->payment_link }}</a>
                @endif
                <br />
            </div>
            @if($data->paid == true)
                <div style="position: absolute; top: 0px; right: 0px;">
                    <img src="{{ public_path('img/paid.png') }}" />
                </div>
            @endif
        </div>
        <br />
        <!-- <h2>#{{ $data->id }}</h2> -->
        <div style="clear:both; position:relative;">
            <div style="width: 250pt; float: left;">
                <h4>@lang('messages.bussiness_details'):</h4>
                <div class="panel panel-default">
                    <div style="padding: 15px;">
                        <b>{{ $data->company['summary'] }}</b>

                        <br/><br/>

                        {!! array_key_exists('address_line_1', $data->company) && strlen($data->company['address_line_1']) > 0 ? $data->company['address_line_1'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_2', $data->company) && strlen($data->company['address_line_2']) > 0 ? $data->company['address_line_2'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_3', $data->company) && strlen($data->company['address_line_3']) > 0 ? $data->company['address_line_3'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_4', $data->company) && strlen($data->company['address_line_4']) > 0 ? $data->company['address_line_4'] . '<br/>' : '' !!}

                        <br/>

                        {!! array_key_exists('phone', $data->customer) && strlen($data->customer['phone']) > 0 ? __('messages.phone') . ': ' . $data->customer['phone'] . '<br/>' : '' !!}
                        {!! array_key_exists('email', $data->customer) && strlen($data->customer['email']) > 0 ? __('messages.email') . ': ' . $data->customer['email'] . '<br/>' : '' !!}
                        <!-- DEPRECATED -->
                        {!! array_key_exists('siret', $data->company) && strlen($data->company['siret']) > 0 ? 'SIRET: ' . $data->company['siret'] . '<br/>' : '' !!}

                        @if(array_key_exists("other", $data->company) && count($data->company['other']) > 0)
                            <br/>
                            @foreach ($data->company["other"] as $item)
                                @if (gettype($item) == "string")
                                    {{ $item }}
                                    <br/>
                                @elseif (gettype($item) == "array")
                                    {{ $item['title'] . ': ' . $item['content'] }}
                                    <br/>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
            <div style="width: 250pt; float: right;">
                <h4>@lang('messages.customer_details'):</h4>
                <div class="panel panel-default">
                    <div style="padding: 15px;">
                        <b>{{ $data->customer['summary'] }}</b>

                        <br/><br/>

                        {!! array_key_exists('address_line_1', $data->customer) && strlen($data->customer['address_line_1']) > 0 ? $data->customer['address_line_1'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_2', $data->customer) && strlen($data->customer['address_line_2']) > 0 ? $data->customer['address_line_2'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_3', $data->customer) && strlen($data->customer['address_line_3']) > 0 ? $data->customer['address_line_3'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_4', $data->customer) && strlen($data->customer['address_line_4']) > 0 ? $data->customer['address_line_4'] . '<br/>' : '' !!}

                        <br/>

                        {!! array_key_exists('phone', $data->customer) && strlen($data->customer['phone']) > 0 ? __('messages.phone') . ': ' . $data->customer['phone'] . '<br/>' : '' !!}
                        {!! array_key_exists('email', $data->customer) && strlen($data->customer['email']) > 0 ? __('messages.email') . ': ' . $data->customer['email'] . '<br/>' : '' !!}
                        <!-- DEPRECATED -->
                        {!! array_key_exists('siret', $data->customer) && strlen($data->customer['siret']) > 0 ? 'SIRET: ' . $data->customer['siret'] . '<br/>' : '' !!}

                        @if(array_key_exists("other", $data->customer) && count($data->customer['other']) > 0)
                            <br/>
                            @foreach ($data->customer["other"] as $item)
                                @if (gettype($item) == "string")
                                    {{ $item }}
                                    <br/>
                                @elseif (gettype($item) == "array")
                                    {{ $item['title'] . ': ' . $item['content'] }}
                                    <br/>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <h4 style="clear: both; position: relative;">@lang('messages.items'):</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>@lang('messages.number_abbreviation')</th>
                    <th>@lang('messages.item_header_name')</th>
                    <th>@lang('messages.item_header_description')</th>
                    <th>@lang('messages.item_header_quantity')</th>
                    <th>@lang('messages.item_header_unitprice')</th>
                    <th>@lang('messages.item_header_tax')</th>
                    <th>@lang('messages.item_header_total')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['price'] }} {{ $data->currency }}</td>
                        <td>{{ $item['tax'] }} %</td>
                        <td>{{ $item['total'] }} {{ $data->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="clear:both; position:relative;">
            @if($data->notes)
                <div style="position:absolute; left:0pt; width:250pt;">
                    <h4>@lang('messages.notes'):</h4>
                    <div class="panel panel-default">
                        <div class="panel-body">
                            {{ $data->notes }}
                        </div>
                    </div>
                </div>
            @endif
            <div style="margin-left: 300pt;">
                <h4>@lang('messages.summary_title'):</h4>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><b>@lang('messages.summary_subtotal')</b></td>
                            <td>{{ $data->sub_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>
                                    @lang('messages.summary_taxes')
                                </b>
                            </td>
                            <td>{{ $data->tax_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr>
                            <td><b>@lang('messages.summary_total')</b></td>
                            <td><b>{{ $data->total }} {{ $data->currency }}</b></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
</html>
