
# Invoice as a service

This service *generates professional looking PDF invoices*, from a simple POST HTTP request with json payload.

## File destinations / storage

Rendered file can be returned into the HTTP response or exported to:

- AWS S3 (and any compatible storage destination)
- FTP server
- Webhook
- *Zapier* ("Catch hook" zap)

## Demo

![Screenshot](/example.png)

## Usage

I provide `invoice-as-a-service` with a full hosted environment for fast and easy setup (endpoint: https://invoice-as-a-service.cleverapps.io).

For improved privacy, you can also deploy the project on your own infrastructure for free.

### Hosted

```sh
$ curl "https://invoice-as-a-service.cleverapps.io/api/invoice/generate" \
     -X POST -H "content-type: application/json" \
     -d '{

        "id": "42",
        "currency": "€",
        "lang": "en",
        "date": 1520852472,
        "due_date": 1521457272,
        "paid": false,
        "payment_link": "https://screeb.app/user/invoices/42/pay",
        "decimals": 2,
        "notes": "Lorem ipsum dolor sit amet.",

        "items": [
            {
                "title": "'Growth' plan Screeb.app",
                "description": "1 year subscription",
                "price": 42,
                "quantity": 1,
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
            "summary": "Screeb",
            "address_line_1": "123, place de Bretagne",
            "address_line_2": "44000 Nantes",
            "address_line_3": "France",
            "address_line_4": "Earth",
            "phone": "1-888-548-0034",
            "email": "billing@screeb.app",
            "logo_url": "https://raw.githubusercontent.com/samber/invoice-as-a-service/master/screeb-logo.png",
            "other": [
                "EMEA office",
                {
                    "title": "Business hours",
                    "content": "9am - 6pm"
                }
            ]
        },

        "s3": {
            "presigned_url": null
        },

        "ftp": {
        	"host": "127.0.0.1",
        	"username": "ftpuser",
        	"password": "superSecretPassword",
        	"path" : "/var/html/share/"
        },

        "webhook": {
            "url": "https://webhook.example.com/invoice/store",
            "headers": {
                "x-token": "very-secret-token"
            }
        },

        "zapier": {
            "zap_url": "https://hooks.zapier.com/hooks/catch/xxxxxxx/yyyyyy",
            "filename": "invoice-42.pdf"
        }

     }'
```

### Self hosted

```sh
$ composer install
$ php artisan serve
```

```sh
$ curl "http://localhost:8000/api/invoice/generate" \
     -X POST -H "content-type: application/json" \
     -d '{ ... }'
```

### User interface from contributor

Here => [crocomo2744.github.io/Invoicing-form](https://crocomo2744.github.io/Invoicing-form/)

## Properties

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **id** | string | yes | Your invoice reference | "42" |
| **currency** | string | yes | Your billing currency | "€" |
| **lang** | string | yes | Only english supported for now | "en" |
| **tax** | float | yes | Tax percentage | 20 |
| **date** | integer | yes | Timestamp of invoice creation date | 1520852472 |
| **due_date** | integer | yes | Timestamp of invoice due date | 1521457272 |
| **paid** | boolean | no | Adding a "paid" image (default: false) | false |
| **payment_link** | string | no | Payment link | "https://<span></span>screeb.app/user/invoices/42/pay" |
| **decimals** | integer | no | Number decimals for prices (default: 2) | 2 |
| **notes** | string | no | Terms, conditions or anything you have to write in order to edit a valid invoice. | "Lorem ipsum dolor sit amet." |
| **items** | array | yes | List of items | [ Item(...), Item(...) ] |
| **customer** | object | yes | Customer infos | Customer(...) |
| **company** | object | yes | Company infos | Company(...) |
| **s3** | object | false | AWS S3 invoice upload | S3Upload(...) |
| **ftp** | object | false | FTP invoice upload | FTPUpload(...) |
| **webhook** | object | false | Webhook invoice upload | WebhookUpload(...) |
| **zapier** | object | false | Zapier invoice upload | ZapierUpload(...) |

### Item:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **title** | string | yes | Product or service name | "'Growth' plan Screeb.app" |
| **description** | string | no | Product or service description | "1 year subscription" |
| **price** | float | yes | Product or service price | 42 |
| **quantity** | float | no | Product or service quantity (default: 1) | 1 |
| **tax** | float | no | Tax rate (default: 0) | 1 |

### Customer:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **summary** | string | yes | Organisation or customer name | "John Doe" |
| **address_line_1** | string | yes | Customer address, line 1 | "Baxter Building, 42nd street, Madison Avenue" |
| **address_line_2** | string | no | Customer address, line 2 | "Manhattan, NY, 11234" |
| **address_line_3** | string | no | Customer address, line 3 | "United States" |
| **address_line_4** | string | no | Customer address, line 4 | "Earth" |
| **phone** | string | no | Customer phone number | "1-888-548-0034" |
| **email** | string | no | Customer email address | "john@gmail.com" |
| **siret** (deprecated) | string | no | French company identification number | "539 138 107 00021" |
| **other** | array of mixed string and Other() | no | Customer additional infos | [ String, Other(), ... ] |

### Company:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **summary** | string | yes | Your organisation name | "Screeb" |
| **address_line_1** | string | yes | Customer address, line 1 | "123, place de Bretagne" |
| **address_line_2** | string | no | Customer address, line 2 | "44000 Nantes" |
| **address_line_3** | string | no | Customer address, line 3 | "France" |
| **address_line_4** | string | no | Customer address, line 4 | "Earth" |
| **phone** | string | no | Customer phone number | "1-888-548-0034" |
| **email** | string | no | Customer email address | "billing@screeb.app" |
| **logo_url** | string | no | URL of your company logo | "https://<span></span>acme.corp/logo.png" |
| **logo_b64** | string | no | Base64 encoded image of your company logo | "data:image/png;base64,........." |
| **siret** (deprecated) | string | no | French company identification number | "539 138 107 00021" |
| **other** | array of mixed string and Other() | no | Company additional infos | [ String, Other(), ... ] |

### customer.other[].* and company.other[].*

`customer.other` and `company.other` fields are arrays of mixed type: simple string or the following object:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **title** | string | true | Field name | "Twitter handle" |
| **content** | string | true | Field value | "@foobar" |

### S3 upload

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **presigned_url** | string | false | Presigned AWS S3 upload url | "https://<span></span>my-bucket.s3.eu-central-1.amazonaws.com/201807250018--foobar@example.com.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=xxxx&X-Amz-Date=xxxx&X-Amz-Expires=xxxx&X-Amz-Signature=xxxx&X-Amz-SignedHeaders=host" |

### FTP upload

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **host** | string | true | The host, IP Address of the server | "ftp.example.com" |
| **username** | string | true | The ftp username to connect | "john" |
| **password** | string | true | The ftp password of the user you are trying to connect | "test1234" |
| **port** | integer | false | The port used to connect, default is 21. | 21 |
| **ssl** | boolean | false | If the connection supports SSL mention that here, default is false. | true |
| **passive** | boolean | false | If it should use a passive connection, default is true. | true |
| **path** | string | true | The full path on the server where you want the invoice to be uploaded. | "/home/john/share" |

### Webhook upload

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **url** | string | true | The URL of the destination webhook | "https://webhook.example.com/invoice/store" |
| **headers** | object | false | These headers will be inserted into webhook request | { "x-token": "very-secret-token" } |

### Zapier upload

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **zap_url** | string | true | URL of Zapier Hook | "https://hooks.zapier.com/hooks/catch/xxxxxxx/yyyyyy" |
| **filename** | string | false | Filename that will be provided into Zapier Hook | "invoice-42.pdf" |

## Notes

The provided logo_url (optional) must be accessible from the `invoice-as-a-service` API !

## Contribute

Hell yeah!

Clone + pull-request.

I usually reply in hours or days ;)

Magic happens here:
- template: `resources/views/invoices/default.blade.php``
- controller + input validation: `app/Http/Controllers/InvoiceController.php`
- pdf build: `app/Helpers/PDF.php`
- invoice storage: `app/Helpers/Storage.php`

[AWS S3] - Generate presigned upload url:

```sh
$ aws configure
$ node ./scripts/presign-upload-url.js <region> <my-bucket> invoices/201807250018--foobar@example.com.pdf
```

## Update dependencies

```bash
$ composer outdated -D
$ composer update <package-name> --with-dependencies
```
