<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FacturaloPlus Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for FacturaloPlus electronic invoicing service
    |
    */

    'account' => env('FACTURALO_ACCOUNT', 'readymind.ms'),
    'api_key' => env('FACTURALO_API_KEY'),
    'test_mode' => env('FACTURALO_TEST_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Service URLs
    |--------------------------------------------------------------------------
    */
    'urls' => [
        'sandbox' => env('FACTURALO_URL_SANDBOX', 'https://dev.facturaloplus.com/api/rest/servicio'),
        'production' => env('FACTURALO_URL_PRODUCTION', 'https://app.facturaloplus.com/api/rest/servicio'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issuer Information (Company Data)
    |--------------------------------------------------------------------------
    */
    'issuer' => [
        'rfc' => env('FACTURALO_RFC'),
        'name' => env('FACTURALO_RAZON_SOCIAL'),
        'tax_regime' => env('FACTURALO_REGIMEN_FISCAL'),
        'postal_code' => env('FACTURALO_CP'),
        'certificate_number' => env('FACTURALO_NO_CERTIFICADO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Configuration
    |--------------------------------------------------------------------------
    */
    'certificates' => [
        'key_pem' => env('FACTURALO_KEY_PEM_PATH'),
        'cert_pem' => env('FACTURALO_CERT_PEM_PATH'),
        'key_password' => env('FACTURALO_KEY_PASSWORD'),
        'key_pem_content' => "-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC2Z5w7qfYZLTMv\nTbmBscZBXHOc8MgKhfNHa5SeDPgOrFVQL/D1wToXFKJebZqnwZyODvCZxl+bzkQF\nrELH/Qna7kKqeHMigYJ2EE+6FZCciHlMuCP9fq42q7BmQoKFs171p4WKtT8lvI3s\nSCJr2gof0plSyh2Iz+lYAfTQ8K2gon04FxIcyrxGprcoqgFx+SDVmuC559/BEvCz\n3I5xFYtvTawM4/MYib+MQWJxVi3YfDWp1ETnGZNnsAYbRQez66eQaOkJV4n4EQGf\nQk8tZW8ModkBAyPqzkGS7fn538zIBOSk4+wZP9VOeyi7F6QN+KQkPV/sDVmkluoO\nmQmeyze9AgMBAAECggEAM8vEL6UZvxh4umwFy3Bh7dmE8wHkrChRZuyDrUXdgr0p\nFLYoZIDUMA2p9cqF6jEudaCEbgZIzAOMiVfbNtMB42tY/vNpLlk8ZK5JFXxeLjUK\nzOBVR/ybF+c1FjS4v6KpynQhlbvWDK84Veo97AZkbZjt9PZz4I+5oUYc8OAHTd+b\nNgL6r7SnudH/2p7rI9kMFp3h9oov+H91IguFn4SF0aVMPiLMUVUYA3QpvIA03c7A\ncY8tloFArpPkpa1yyZ7ToFx3mPsYNxyn4Ud3lx0PHJ0ulUjPBVoi+ewFLHG0Z8HS\n2CdBe+7ptS0u1e58+RsX5tlf61xGwlHzMbfqyrBrQQKBgQDp/VO5GmFSzvIlrCJg\nX3R6f+YWraYqqL4uND56Y5q+MaPE2lAEzYKkGdHotiYu3Ta6picBo8BamMQBC93r\nmiwJYBHZWih56Xb/lSdV1GGHUbNCCqhR78EabVgbNj8vWYaCRezXFjMdnG5dTYsn\nNsMtvV6/dBXxoLpJWi7YCM6QlQKBgQDHkBNQ1DNUvpSMeICd7OgUTiQKSGViMRKE\nBoARNGE5f6ZbCACY69gSDs/OdZUqq6gboCu4VNWrl0/mOZrhkVJEicDKcahDuM6R\n5q3EPcv/XhonAlvAXueDCVEaNh7iPyt0XTsDsMFsAi4xs9n6DcOvp8DLDBa9rfWW\ngJghwhJ4iQKBgQCdZR5QQQJ+ZV5VIPjuwsbjL+1iC+TehSuIgPQV97I7hcCXxhJV\naLgcEpI0a3I7y+nom6NZYwEc7+3Ilmbs+IPuuoTHI/JeL0EwovAWuAnysG5YWjrn\nAtNuJ2dAxrix7RKhxs+Nzw0LOhKzCFo2Qov/1IZLWpb4NhmZBeTExQfOEQKBgQCp\nmkbwQQhHE5mIMLmOflNXPnney1xusSn9TtD0zkF67oPhhdGMJZSC9FekLcZSd4Tt\nxZiilmbp6DUdgwOghTMX0MMqyqGovJ3C3fv8dlCzfbRYRubXfVDgysBEuI5S1kEG\nKl4HaudX9hpwX1v36k7PMJDngCSrm/bGrA+fUSMFkQKBgQDnHnYRit6FIr1IgLoA\nuWEYY/R1cVv5tWtDbHqa/I4+oS+qDA8HNNKVvm8HO8JLRU5f+ZhBQfJVhWbEybOj\nKpTeg0L+wtXEw8Qfb2we1GFJ/LaiXTzeJVttnARJXce+G0U/Ei8EthOCh4PTNawU\nYTt70e4jJwOsG6DZFT8EnM7O7Q==\n-----END PRIVATE KEY-----",
        'cert_pem_content' => "-----BEGIN CERTIFICATE-----\nMIIFsDCCA5igAwIBAgIUMzAwMDEwMDAwMDA1MDAwMDM0MTYwDQYJKoZIhvcNAQEL\nBQAwggErMQ8wDQYDVQQDDAZBQyBVQVQxLjAsBgNVBAoMJVNFUlZJQ0lPIERFIEFE\nTUlOSVNUUkFDSU9OIFRSSUJVVEFSSUExGjAYBgNVBAsMEVNBVC1JRVMgQXV0aG9y\naXR5MSgwJgYJKoZIhvcNAQkBFhlvc2Nhci5tYXJ0aW5lekBzYXQuZ29iLm14MR0w\nGwYDVQQJDBQzcmEgY2VycmFkYSBkZSBjYWxpejEOMAwGA1UEEQwFMDYzNzAxCzAJ\nBgNVBAYTAk1YMRkwFwYDVQQIDBBDSVVEQUQgREUgTUVYSUNPMREwDwYDVQQHDAhD\nT1lPQUNBTjERMA8GA1UELRMIMi41LjQuNDUxJTAjBgkqhkiG9w0BCQITFnJlc3Bv\nbnNhYmxlOiBBQ0RNQS1TQVQwHhcNMjMwNTE4MTE0MzUxWhcNMjcwNTE4MTE0MzUx\nWjCB1zEnMCUGA1UEAxMeRVNDVUVMQSBLRU1QRVIgVVJHQVRFIFNBIERFIENWMScw\nJQYDVQQpEx5FU0NVRUxBIEtFTVBFUiBVUkdBVEUgU0EgREUgQ1YxJzAlBgNVBAoT\nHkVTQ1VFTEEgS0VNUEVSIFVSR0FURSBTQSBERSBDVjElMCMGA1UELRMcRUtVOTAw\nMzE3M0M5IC8gVkFEQTgwMDkyN0RKMzEeMBwGA1UEBRMVIC8gVkFEQTgwMDkyN0hT\nUlNSTDA1MRMwEQYDVQQLEwpTdWN1cnNhbCAxMIIBIjANBgkqhkiG9w0BAQEFAAOC\nAQ8AMIIBCgKCAQEAtmecO6n2GS0zL025gbHGQVxznPDICoXzR2uUngz4DqxVUC/w\n9cE6FxSiXm2ap8Gcjg7wmcZfm85EBaxCx/0J2u5CqnhzIoGCdhBPuhWQnIh5TLgj\n/X6uNquwZkKChbNe9aeFirU/JbyN7Egia9oKH9KZUsodiM/pWAH00PCtoKJ9OBcS\nHMq8Rqa3KKoBcfkg1ZrgueffwRLws9yOcRWLb02sDOPzGIm/jEFicVYt2Hw1qdRE\n5xmTZ7AGG0UHs+unkGjpCVeJ+BEBn0JPLWVvDKHZAQMj6s5Bku35+d/MyATkpOPs\nGT/VTnsouxekDfikJD1f7A1ZpJbqDpkJnss3vQIDAQABox0wGzAMBgNVHRMBAf8E\nAjAAMAsGA1UdDwQEAwIGwDANBgkqhkiG9w0BAQsFAAOCAgEAFaUgj5PqgvJigNMg\ntrdXZnbPfVBbukAbW4OGnUhNrA7SRAAfv2BSGk16PI0nBOr7qF2mItmBnjgEwk+D\nTv8Zr7w5qp7vleC6dIsZFNJoa6ZndrE/f7KO1CYruLXr5gwEkIyGfJ9NwyIagvHH\nMszzyHiSZIA850fWtbqtythpAliJ2jF35M5pNS+YTkRB+T6L/c6m00ymN3q9lT1r\nB03YywxrLreRSFZOSrbwWfg34EJbHfbFXpCSVYdJRfiVdvHnewN0r5fUlPtR9stQ\nHyuqewzdkyb5jTTw02D2cUfL57vlPStBj7SEi3uOWvLrsiDnnCIxRMYJ2UA2ktDK\nHk+zWnsDmaeleSzonv2CHW42yXYPCvWi88oE1DJNYLNkIjua7MxAnkNZbScNw01A\n6zbLsZ3y8G6eEYnxSTRfwjd8EP4kdiHNJftm7Z4iRU7HOVh79/lRWB+gd171s3d/\nmI9kte3MRy6V8MMEMCAnMboGpaooYwgAmwclI2XZCczNWXfhaWe0ZS5PmytD/GDp\nXzkX0oEgY9K/uYo5V77NdZbGAjmyi8cE2B2ogvyaN2XfIInrZPgEffJ4AB7kFA2m\nwesdLOCh0BLD9itmCve3A1FGR4+stO2ANUoiI3w3Tv2yQSg4bjeDlJ08lXaaFCLW\n2peEXMXjQUk7fmpb5MNuOUTW6BE=\n-----END CERTIFICATE-----",
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'serie' => 'FAC',
        'currency' => 'MXN',
        'exchange_rate' => 1.000000,
        'voucher_type' => 'I', // Ingreso
        'exportation' => '01',
        'payment_method' => 'PUE', // Pago en una sola exhibición
        'payment_form' => '03', // Transferencia electrónica
        'expedition_place' => env('FACTURALO_CP'),
        'cfdi_use' => 'G03', // Gastos en general
        'product_service_code' => '43232408', // Software - Computers
        'unit_code' => 'E48', // Unidad de servicio
        'unit' => 'Unidad de servicio',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    */
    'taxes' => [
        'iva' => [
            'rate' => (float) env('TAX_RATE', 16) / 100, // Convert percentage to decimal (16 -> 0.16)
            'tax_code' => '002',
            'factor_type' => 'Tasa',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    */
    'pdf' => [
        'template' => env('FACTURALO_PDF_TEMPLATE', '1'),
        'logo_path' => env('FACTURALO_LOGO_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'url' => env('FACTURALO_WEBHOOK_URL'),
        'secret' => env('FACTURALO_WEBHOOK_SECRET'),
    ],
];
