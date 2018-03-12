# Invoice as a service

This service generates professional looking PDF invoices, from a simple POST HTTP request with json payload.

## Example

![Screenshot](/example.png)

## Usage

I provide `invoice-as-a-service` with a full hosted environment for fast and easy setup.

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
        "decimals": 2,
        "notes": "Lorem ipsum dolor sit amet.",

        "items": [
            {
                "title": "'Growth' plan Bienavous.io",
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
            "summary": "Bienavous",
            "address_line_1": "123, place de Bretagne",
            "address_line_2": "44000 Nantes",
            "address_line_3": "France",
            "address_line_4": "Earth",
            "phone": "1-888-548-0034",
            "email": "billing@bienavous.io",
            "logo": "https://upload.wikimedia.org/wikipedia/commons/7/70/Amazon_logo_plain.svg",
            "siret": "539 138 107 00021"
        }

     }'
```

### Self hosted

```sh
$ php artisan serve
```

```sh
$ curl "http://localhost:8000/api/invoice/generate" \
     -X POST -H "content-type: application/json" \
     -d '{ ... }'
```

## Properties

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **id** | string | yes | Your invoice reference | "42" |
| **currency** | string | yes | Your billing currency | "€" |
| **lang** | string | yes | Only english supported for now | "en" |
| **tax** | float | yes | Tax percentage | 20 |
| **date** | integer | yes | Timestamp of invoice creation date | 1520852472 |
| **due_date** | integer | yes | Timestamp of invoice due date | 1521457272 |
| **decimals** | integer | no | Number decimals for prices (default: 2) | 2 |
| **notes** | string | no | Terms, conditions or anything you have to write to print a valid invoice. | "Lorem ipsum dolor sit amet." |
| **items** | array | yes | List of items | [ Item(...), Item(...) ] |
| **customer** | object | yes | Customer infos | Customer(...) |
| **company** | object | yes | Company infos | Company(...) |

**Item**:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **title** | string | yes | Product or service name | "'Growth' plan Bienavous.io" |
| **description** | string | no | Product or service description | "1 year subscription" |
| **price** | float | yes | Product or service price | 42 |
| **quantity** | float | no | Product or service quantity (default: 1) | 1 |
| **tax** | float | no | Tax rate (default: 0) | 1 |

**Customer**:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **summary** | string | yes | Organisation or customer name | "John Doe" |
| **address_line_1** | string | yes | Customer address, line 1 | "Baxter Building, 42nd street, Madison Avenue" |
| **address_line_2** | string | no | Customer address, line 2 | "Manhattan, NY, 11234" |
| **address_line_3** | string | no | Customer address, line 3 | "United States" |
| **address_line_4** | string | no | Customer address, line 4 | "Earth" |
| **phone** | string | no | Customer phone number | "1-888-548-0034" |
| **email** | string | no | Customer email address | "john@gmail.com" |

**Company**:

| Property | Type | Required | Description | Example |
| --- | --- | :---: | --- | --- |
| **summary** | string | yes | Your organisation name | "Bienavous" |
| **address_line_1** | string | yes | Customer address, line 1 | "123, place de Bretagne" |
| **address_line_2** | string | no | Customer address, line 2 | "44000 Nantes" |
| **address_line_3** | string | no | Customer address, line 3 | "France" |
| **address_line_4** | string | no | Customer address, line 4 | "Earth" |
| **phone** | string | no | Customer phone number | "1-888-548-0034" |
| **email** | string | no | Customer email address | "billing@bienavous.io" |
| **logo** | string | no | URL of your company logo | "https://acme.corp/logo.png" |
| **siret** | string | no | French company identification number | "539 138 107 00021" |

## Notes

The logo (optional) used by the API must be accessible by `invoice-as-a-service` !

## Contribute

Fuck yeah!

Clone + pull-request. I usually reply in hours or days ;)
