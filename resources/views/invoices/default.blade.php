<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $data->id }}</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        <style>
            h1,h2,h3,h4,p,span,div { font-family: DejaVu Sans; }
        </style>
    </head>
    <body>
        <div style="clear:both; position:relative;">
            <div style="position:absolute; left:0pt; width:250pt;">
                @if (array_key_exists('logo', $data->company) && strlen($data->company['logo']) > 0)
                    <img class="img-rounded" style="max-width:250pt;max-height:100pt" src="{{ $data->company['logo'] }}">
                @endif
            </div>
            <div style="margin-left:300pt;">
                <b>Date: </b> {{ $data->date }}<br />
                <b>Due date: </b> {{ $data->due_date }}<br />
                @if ($data->id)
                    <b>Invoice: </b> #{{ $data->id }}
                @endif
                <br />
            </div>
        </div>
        <br />
        <!-- <h2>#{{ $data->id }}</h2> -->
        <div style="clear:both; position:relative;">
            <div style="position:absolute; left:0pt; width:250pt;">
                <h4>Business Details:</h4>
                <div class="panel panel-default">
                    <div class="panel-body">
                        <b>{{ $data->company['summary'] }}</b>

                        <br/><br/>

                        {!! array_key_exists('address_line_1', $data->company) && strlen($data->company['address_line_1']) > 0 ? $data->company['address_line_1'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_2', $data->company) && strlen($data->company['address_line_2']) > 0 ? $data->company['address_line_2'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_3', $data->company) && strlen($data->company['address_line_3']) > 0 ? $data->company['address_line_3'] . '<br/>' : '' !!}
                        {!! array_key_exists('address_line_4', $data->company) && strlen($data->company['address_line_4']) > 0 ? $data->company['address_line_4'] . '<br/>' : '' !!}

                        <br/>

                        {!! array_key_exists('phone', $data->company) && strlen($data->company['phone']) > 0 ? 'Phone: ' . $data->company['phone'] . '<br/>' : '' !!}
                        {!! array_key_exists('email', $data->company) && strlen($data->company['email']) > 0 ? 'Email: ' . $data->company['email'] . '<br/>' : '' !!}
                        {!! array_key_exists('siret', $data->company) && strlen($data->company['siret']) > 0 ? 'SIRET: ' . $data->company['siret'] . '<br/>' : '' !!}
                    </div>
                </div>
            </div>
            <div style="margin-left: 300pt;">
                <h4>Customer Details:</h4>
                <div class="panel panel-default">
                    <div class="panel-body">
                    <b>{{ $data->customer['summary'] }}</b>

                    <br/><br/>

                    {!! array_key_exists('address_line_1', $data->customer) && strlen($data->customer['address_line_1']) > 0 ? $data->customer['address_line_1'] . '<br/>' : '' !!}
                    {!! array_key_exists('address_line_2', $data->customer) && strlen($data->customer['address_line_2']) > 0 ? $data->customer['address_line_2'] . '<br/>' : '' !!}
                    {!! array_key_exists('address_line_3', $data->customer) && strlen($data->customer['address_line_3']) > 0 ? $data->customer['address_line_3'] . '<br/>' : '' !!}
                    {!! array_key_exists('address_line_4', $data->customer) && strlen($data->customer['address_line_4']) > 0 ? $data->customer['address_line_4'] . '<br/>' : '' !!}

                    <br/>

                    {!! array_key_exists('phone', $data->customer) && strlen($data->customer['phone']) > 0 ? 'Phone: ' . $data->customer['phone'] . '<br/>' : '' !!}
                    {!! array_key_exists('email', $data->customer) && strlen($data->customer['email']) > 0 ? 'Email: ' . $data->customer['email'] . '<br/>' : '' !!}
                    </div>
                </div>
            </div>
        </div>
        <h4>Items:</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit price</th>
                    <th>Tax</th>
                    <th>Total</th>
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
                    <h4>Notes:</h4>
                    <div class="panel panel-default">
                        <div class="panel-body">
                            {{ $data->notes }}
                        </div>
                    </div>
                </div>
            @endif
            <div style="margin-left: 300pt;">
                <h4>Total:</h4>
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <td><b>Subtotal</b></td>
                            <td>{{ $data->sub_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>
                                    Taxes
                                </b>
                            </td>
                            <td>{{ $data->tax_total }} {{ $data->currency }}</td>
                        </tr>
                        <tr>
                            <td><b>TOTAL (including taxes)</b></td>
                            <td><b>{{ $data->total }} {{ $data->currency }}</b></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
</html>
