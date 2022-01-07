curl "http://localhost:8000/api/invoice/generate" \
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
