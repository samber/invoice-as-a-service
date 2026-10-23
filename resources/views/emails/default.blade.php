<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.email_subject', ['id' => $id, 'company' => $company]) }}</title>
</head>
<body style="font-family: Helvetica, Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>{{ __('messages.email_greeting', ['customer' => $customer]) }}</p>
    <p>{{ __('messages.email_intro', ['id' => $id, 'company' => $company]) }}</p>
    <ul>
        <li>{{ __('messages.summary_total') }}: <strong>{{ $total }} {{ $currency }}</strong></li>
        <li>{{ __('messages.due_date') }}: {{ $due_date }}</li>
        @if ($purchase_order)
            <li>{{ __('messages.purchase_order') }}: {{ $purchase_order }}</li>
        @endif
    </ul>
    @if ($payment_link)
        <p><a href="{{ $payment_link }}">{{ __('messages.payment_link') }}</a></p>
    @endif
    <p>{{ __('messages.email_signature', ['company' => $company]) }}</p>
</body>
</html>
