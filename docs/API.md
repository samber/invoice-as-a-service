# API reference

Endpoint: `POST /api/invoice/generate`, JSON body. See the [README](../README.md) for a quick start.

## Full example

Every property, with every destination. Each destination object is optional: remove the ones you do not use. The credentials and URLs are placeholders.

```sh
curl "https://invoice-as-a-service.cleverapps.io/api/invoice/generate" \
  -X POST -H "content-type: application/json" \
  -o invoice.pdf \
  -d '{
    "id": "42",
    "currency": "€",
    "lang": "en",
    "date": 1520852472,
    "due_date": 1521457272,
    "paid": false,
    "payment_link": "https://apple.com/user/invoices/42/pay",
    "purchase_order": "PO-2018-0042",
    "decimals": 2,
    "notes": "Payment due within 30 days.",
    "items": [
      {
        "title": "'Growth' plan Apple.com",
        "description": "1 year subscription",
        "price": 42,
        "quantity": 1,
        "tax": 20
      },
      {
        "title": "Priority support",
        "description": "Extra seats, billed per seat",
        "price": 12.5,
        "quantity": 2,
        "tax": 20
      }
    ],
    "customer": {
      "summary": "John Doe",
      "address_line_1": "Baxter Building, 42nd street, Madison Avenue",
      "address_line_2": "Manhattan, NY, 11234",
      "address_line_3": "United States",
      "address_line_4": "Earth",
      "phone": "1-888-548-0034",
      "email": "john@gmail.com"
    },
    "company": {
      "summary": "Apple",
      "address_line_1": "123, place de Bretagne",
      "address_line_2": "44000 Nantes",
      "address_line_3": "France",
      "address_line_4": "Earth",
      "phone": "1-888-548-0034",
      "email": "billing@apple.com",
      "logo_url": "https://raw.githubusercontent.com/samber/invoice-as-a-service/master/apple-logo.png",
      "other": [
        "EMEA office",
        {
          "title": "Business hours",
          "content": "9am - 6pm"
        }
      ]
    },
    "s3": {
      "presigned_url": "https://my-bucket.s3.eu-central-1.amazonaws.com/invoice-42.pdf?X-Amz-Signature=xxxx"
    },
    "ftp": {
      "host": "ftp.example.com",
      "username": "ftpuser",
      "password": "change-me",
      "path": "/var/html/share/"
    },
    "webhook": {
      "url": "https://webhook.example.com/invoice/store",
      "headers": {
        "x-token": "change-me"
      }
    },
    "zapier": {
      "zap_url": "https://hooks.zapier.com/hooks/catch/xxxxxxx/yyyyyy",
      "filename": "invoice-42.pdf"
    },
    "email": {
      "to": "john@gmail.com",
      "cc": [
        "accounting@gmail.com"
      ]
    }
  }'
```

## Request properties

| Property           | Type    | Required | Description                                                                       | Example                                               |
| ------------------ | ------- | :------: | --------------------------------------------------------------------------------- | ----------------------------------------------------- |
| **id**             | string  |   yes    | Your invoice reference                                                            | "42"                                                  |
| **currency**       | string  |   yes    | Your billing currency                                                             | "€"                                                   |
| **lang**           | string  |   yes    | Invoice language: `en` or `es`                                                    | "en"                                                  |
| **date**           | integer |   yes    | Timestamp of invoice creation date                                                | 1520852472                                            |
| **due_date**       | integer |   yes    | Timestamp of invoice due date                                                     | 1521457272                                            |
| **paid**           | boolean |    no    | Adding a "paid" image (default: false)                                            | false                                                 |
| **payment_link**   | string  |    no    | Payment link                                                                      | "https://<span></span>apple.com/user/invoices/42/pay" |
| **purchase_order** | string  |    no    | Customer purchase order reference, shown in the invoice header                    | "PO-2018-0042"                                        |
| **decimals**       | integer |    no    | Number decimals for prices (default: 2)                                           | 2                                                     |
| **notes**          | string  |    no    | Terms, conditions or anything you have to write in order to edit a valid invoice. | "Lorem ipsum dolor sit amet."                         |
| **items**          | array   |   yes    | List of items                                                                     | [ Item(...), Item(...) ]                              |
| **customer**       | object  |   yes    | Customer infos                                                                    | Customer(...)                                         |
| **company**        | object  |   yes    | Company infos                                                                     | Company(...)                                          |
| **s3**             | object  |    no    | AWS S3 invoice upload                                                             | S3Upload(...)                                         |
| **ftp**            | object  |    no    | FTP invoice upload                                                                | FTPUpload(...)                                        |
| **webhook**        | object  |    no    | Webhook invoice upload                                                            | WebhookUpload(...)                                    |
| **zapier**         | object  |    no    | Zapier invoice upload                                                             | ZapierUpload(...)                                     |
| **email**          | object  |    no    | Send the invoice by email                                                         | Email(...)                                            |

### Item

| Property        | Type   | Required | Description                              | Example                   |
| --------------- | ------ | :------: | ---------------------------------------- | ------------------------- |
| **title**       | string |   yes    | Product or service name                  | "'Growth' plan Apple.com" |
| **description** | string |    no    | Product or service description           | "1 year subscription"     |
| **price**       | float  |   yes    | Product or service price                 | 42                        |
| **quantity**    | float  |    no    | Product or service quantity (default: 1) | 1                         |
| **tax**         | float  |   yes    | Tax rate, in percent                     | 20                        |

### Customer

| Property               | Type                              | Required | Description                          | Example                                        |
| ---------------------- | --------------------------------- | :------: | ------------------------------------ | ---------------------------------------------- |
| **summary**            | string                            |   yes    | Organisation or customer name        | "John Doe"                                     |
| **address_line_1**     | string                            |   yes    | Customer address, line 1             | "Baxter Building, 42nd street, Madison Avenue" |
| **address_line_2**     | string                            |    no    | Customer address, line 2             | "Manhattan, NY, 11234"                         |
| **address_line_3**     | string                            |    no    | Customer address, line 3             | "United States"                                |
| **address_line_4**     | string                            |    no    | Customer address, line 4             | "Earth"                                        |
| **phone**              | string                            |    no    | Customer phone number                | "1-888-548-0034"                               |
| **email**              | string                            |    no    | Customer email address               | "john@gmail.com"                               |
| **siret** (deprecated) | string                            |    no    | French company identification number | "539 138 107 00021"                            |
| **other**              | array of mixed string and Other() |    no    | Customer additional infos            | [ String, Other(), ... ]                       |

### Company

| Property               | Type                              | Required | Description                               | Example                                   |
| ---------------------- | --------------------------------- | :------: | ----------------------------------------- | ----------------------------------------- |
| **summary**            | string                            |   yes    | Your organisation name                    | "Apple"                                   |
| **address_line_1**     | string                            |   yes    | Company address, line 1                   | "123, place de Bretagne"                  |
| **address_line_2**     | string                            |    no    | Company address, line 2                   | "44000 Nantes"                            |
| **address_line_3**     | string                            |    no    | Company address, line 3                   | "France"                                  |
| **address_line_4**     | string                            |    no    | Company address, line 4                   | "Earth"                                   |
| **phone**              | string                            |    no    | Company phone number                      | "1-888-548-0034"                          |
| **email**              | string                            |    no    | Company email address                     | "billing@apple.com"                       |
| **logo_url**           | string                            |    no    | URL of your company logo                  | "https://<span></span>acme.corp/logo.png" |
| **logo_b64**           | string                            |    no    | Base64 encoded image of your company logo | "data:image/png;base64,........."         |
| **siret** (deprecated) | string                            |    no    | French company identification number      | "539 138 107 00021"                       |
| **other**              | array of mixed string and Other() |    no    | Company additional infos                  | [ String, Other(), ... ]                  |

### customer.other[].* and company.other[].*

`customer.other` and `company.other` fields are arrays of mixed type: simple string or the following object:

| Property    | Type   | Required | Description | Example          |
| ----------- | ------ | :------: | ----------- | ---------------- |
| **title**   | string |   yes    | Field name  | "Twitter handle" |
| **content** | string |   yes    | Field value | "@foobar"        |

### S3 upload

| Property          | Type   | Required | Description                 | Example                                                                                                                                                                                                                                     |
| ----------------- | ------ | :------: | --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **presigned_url** | string |    no    | Presigned AWS S3 upload url | "https://<span></span>my-bucket.s3.eu-central-1.amazonaws.com/201807250018--foobar@example.com.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=xxxx&X-Amz-Date=xxxx&X-Amz-Expires=xxxx&X-Amz-Signature=xxxx&X-Amz-SignedHeaders=host" |

### FTP upload

| Property     | Type    | Required | Description                                                            | Example            |
| ------------ | ------- | :------: | ---------------------------------------------------------------------- | ------------------ |
| **host**     | string  |   yes    | The host, IP Address of the server                                     | "ftp.example.com"  |
| **username** | string  |   yes    | The ftp username to connect                                            | "john"             |
| **password** | string  |   yes    | The ftp password of the user you are trying to connect                 | "test1234"         |
| **port**     | integer |    no    | The port used to connect, default is 21.                               | 21                 |
| **ssl**      | boolean |    no    | If the connection supports SSL mention that here, default is false.    | true               |
| **passive**  | boolean |    no    | If it should use a passive connection, default is true.                | true               |
| **path**     | string  |   yes    | The full path on the server where you want the invoice to be uploaded. | "/home/john/share" |

### Webhook upload

| Property    | Type   | Required | Description                                         | Example                                     |
| ----------- | ------ | :------: | --------------------------------------------------- | ------------------------------------------- |
| **url**     | string |   yes    | The URL of the destination webhook                  | "https://webhook.example.com/invoice/store" |
| **headers** | object |    no    | These headers will be inserted into webhook request | { "x-token": "very-secret-token" }          |

### Zapier upload

| Property     | Type   | Required | Description                                     | Example                                               |
| ------------ | ------ | :------: | ----------------------------------------------- | ----------------------------------------------------- |
| **zap_url**  | string |   yes    | URL of Zapier Hook                              | "https://hooks.zapier.com/hooks/catch/xxxxxxx/yyyyyy" |
| **filename** | string |    no    | Filename that will be provided into Zapier Hook | "invoice-42.pdf"                                      |

### Email

The PDF is attached to an email sent through the server's SMTP account. SMTP credentials are never part of the request: the operator sets them with the `MAIL_*` environment variables (see `.env.dist`). Subject and body are fixed server side and translated according to `lang`. `Reply-To` is `company.email` when set.

| Property     | Type   | Required | Description                                        | Example                  |
| ------------ | ------ | :------: | -------------------------------------------------- | ------------------------ |
| **to**       | string |    no    | Recipient address. The email is skipped when empty | "john@gmail.com"         |
| **cc**       | array  |    no    | Up to 10 copy recipients                           | ["accounting@gmail.com"] |
| **bcc**      | array  |    no    | Up to 10 blind copy recipients                     | ["archive@apple.com"]    |
| **template** | string |    no    | Email template name (default: "default")           | "default"                |

The response is JSON, like other destinations: `{"email": {"sent": true, "message": null, "recipients": [...]}}`. A mail failure returns `sent: false` and the error `message`, with HTTP 201.

### Instance-wide delivery settings

By default every destination is configured per request, in the JSON body. An operator can also configure destinations for the whole instance with `DELIVERY_*` environment variables, and lock the request body out of delivery entirely.

```sh
# Run every invoice through this FTP server and this mailbox, whatever the request says
DELIVERY_FTP_HOST=ftp.example.com
DELIVERY_FTP_USERNAME=john
DELIVERY_FTP_PASSWORD=test1234
DELIVERY_FTP_PATH=/home/john/share
DELIVERY_EMAIL_TO=accounting@example.com

# Reject any `s3`, `ftp`, `webhook`, `zapier` or `email` object in the request body
DELIVERY_PER_REQUEST_ENABLED=false
```

| Variable                                                                            | Default | Description                                                                                                                                                                                                                                                                                        |
| ----------------------------------------------------------------------------------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DELIVERY_PER_REQUEST_ENABLED`                                                      | `true`  | When `false`, a request carrying `s3`, `ftp`, `webhook`, `zapier` or `email` values is rejected with HTTP 422 and only the instance destinations run. Unset or empty keeps it enabled. Any value other than `true`/`1`/`on`/`yes` disables it, so a typo locks the instance instead of opening it. |
| `DELIVERY_FTP_HOST`, `_PORT`, `_SSL`, `_PASSIVE`, `_USERNAME`, `_PASSWORD`, `_PATH` | unset   | Same fields as [FTP upload](#ftp-upload).                                                                                                                                                                                                                                                          |
| `DELIVERY_WEBHOOK_URL`, `DELIVERY_WEBHOOK_HEADERS`                                  | unset   | Same fields as [Webhook upload](#webhook-upload). Headers are a JSON object: `{"x-token":"secret"}`. Invalid JSON fails every request with HTTP 500.                                                                                                                                               |
| `DELIVERY_ZAPIER_URL`, `DELIVERY_ZAPIER_FILENAME`                                   | unset   | `zap_url` and `filename` of [Zapier upload](#zapier-upload).                                                                                                                                                                                                                                       |
| `DELIVERY_EMAIL_TO`, `_CC`, `_BCC`, `_TEMPLATE`                                     | unset   | Same fields as [Email](#email). `CC` and `BCC` are comma-separated. SMTP credentials stay in the `MAIL_*` variables.                                                                                                                                                                               |

How settings combine:

- A destination runs when its key field is set, either in the environment or in the request: `ftp.host`, `webhook.url`, `zapier.zap_url`, `email.to`, `s3.presigned_url`.
- A field in the request body wins over the environment value of the same field. Other environment fields still apply: a request that sends only `ftp.password` reuses the instance host, username and path.
- A list or object field (`email.cc`, `webhook.headers`) is replaced as a whole, never appended.
- A `null` field in the request counts as absent. `false` and `0` are real values.
- Blank environment variables count as unset.
- Merged settings go through the same validation as a request body, so an invalid environment value returns HTTP 422.
- There is no S3 variable: a presigned URL is signed for a single object, so it only makes sense per request.
- With an environment destination configured, every invoice is delivered to it. A request cannot opt out.
- Webhook and Zapier receive the request body as the caller sent it, without the instance settings, so instance credentials are never forwarded.

## Notes

`company.logo_url` must be reachable from the server running `invoice-as-a-service`. Use `company.logo_b64` to send the image inline.

### PDF metadata

Every generated PDF carries these document properties:

- **Title**: `Invoice <id>` (localized)
- **Author**: `company.summary`
- **Subject**: `Invoice for <customer.summary>` (localized)
- **Creator** and **Producer**: `invoice-as-a-service`
- **CreationDate** and **ModDate**: generation time, in UTC (not the invoice `date`)

### PDF signature

The operator can sign every generated PDF with a server-side key. The signature is never part of the request. It is an invisible CMS signature (`adbe.pkcs7.detached`, added as a PDF incremental update), so PDF readers show the document as signed and warn about any later edit. Without a key, the PDF is not signed.

Set a certificate and its private key, both PEM encoded, with environment variables:

| Variable                    | Description                                                               |
| --------------------------- | ------------------------------------------------------------------------- |
| **PDF_SIGN_CERT**           | X.509 certificate, as plain text (`\n` accepted instead of real newlines) |
| **PDF_SIGN_CERT_FILE_PATH** | Path to the certificate file, used when `PDF_SIGN_CERT` is empty          |
| **PDF_SIGN_KEY**            | Private key, as plain text                                                |
| **PDF_SIGN_KEY_FILE_PATH**  | Path to the private key file, used when `PDF_SIGN_KEY` is empty           |
| **PDF_SIGN_KEY_PASSPHRASE** | Passphrase of the private key (optional)                                  |

- Signing is on as soon as a certificate and a key are set. Nothing set means no signing.
- Only one of the two set, an unreadable file, a wrong passphrase or a key that does not match the certificate fails the request with a server error. An invoice is never silently sent unsigned.
- A self-signed certificate works, but readers show it as untrusted. Use a certificate from a CA trusted by your readers (for example an Adobe Approved Trust List one) to get a green check.

Generate a self-signed pair for testing:

```sh
openssl req -x509 -newkey rsa:3072 -nodes -days 365 -subj "/CN=My Company" -keyout sign.key -out sign.crt
PDF_SIGN_CERT_FILE_PATH=sign.crt PDF_SIGN_KEY_FILE_PATH=sign.key
```

Check a signed PDF with `pdfsig invoice.pdf` (poppler) or Adobe Reader.
