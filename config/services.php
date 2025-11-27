<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'infobip' => [
        'api_key' => env('INFOBIP_API_KEY'),
        'sender_id' => env('INFOBIP_SENDER_ID', 'TaskManager'),
    ],

    'whatsapp' => [
        'api_key' => env('WHATSAPP_API_KEY'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://connect.wadina.agency/webhooks'),
        'webhook_id' => env('WHATSAPP_WEBHOOK_ID'),
        'test_mode' => env('WHATSAPP_TEST_MODE', false),
    ],

    'infobip' => [
        'api_key' => env('INFOBIP_API_KEY'),
        'base_url' => env('INFOBIP_BASE_URL', 'https://xl4ln4.api.infobip.com/sms/2/text/advanced'),
        'sender_id' => env('INFOBIP_SENDER_ID', 'TaskManager'),
    ],

    'firebase' => [
        'type' => env('FIREBASE_TYPE', 'service_account'),
        'project_id' => env('FIREBASE_PROJECT_ID', 'sahariano-app'),
        'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID', '13ff135cd2cf18fdd232e0326258affec417c3e3'),
        'private_key' => env('FIREBASE_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC+UJNNc6s6H4Zz\nZKSKSHey1PsU9sQ/NMfw73sHDHnEH1kSX7f8Td4aR7nQivhRmzZAxjOL9psCEj+h\nE1R/eWRzoheiHXwvAe0zrE3Sn2w9f2jln+er3NHIrS8E7eZ3wjbaSogzh9EjyfJm\n2904X4Cukms4zk850IfjSv7Y83mH0mhUou16VSJNQjGH7bhnzagPhcDPI3xcQ1Ov\n7dIkKo+ZgUeUD2g+UKRvlOl7D2s7EjssJe+6gLXP41Yy1rqGIjqdfYtAyzvaFc8D\nZVLXOhx4Xjnq1HxqxK+vW/VlQoboaWVq6ZWRL/l4iyNHmVqlI3FSz7jUGzjCTOsS\nRj37dJqlAgMBAAECggEAL4v3ZTYE/RIzt8AY6JBm0UerDAeDq4ve+PeHu9DW2hP/\n35qI35C/sL6jMnIJzG+T3BZ4edsMSnXvLimjZm6YVVhVgbPOmvrm/U4qqjiYpPug\nJCAxU8tpIPL9iIR8HAbqR9qvkPo5WrDhLef3qpYXkuWzQn+upPHPhU5vAZpAjlko\n9HYEqF7ZlpB8tmnQ0AshzoEOVa+BVb5RQbSE8R9yDHr4/TFQhO7ehUHEALgnj2nt\nPPI8QGUb64RTdelSO+fLMRQTMgrdSogN41+SzR1CFiHnuuXILvsoZwr+njUwkO/w\nMxj7aGq9nZB0r9rDJTQlTe0f+1txtusVDqdaf9EbOQKBgQDwaZ9u+KIpa31R8o3R\nS3wqB3DwsaBpWFBn70Mm8vsnUSIst+8+eRK0WH3+Jb0AeZCyuzBRqCTide9PM4s5\nzv4EklRI9uTZQa7FcaBTT9mLX1d12OPhXMRi9/B84EyIOrkXHBThoAF+CmevjaIk\nz1c21PD9omcyYBJohUZgeqNU3QKBgQDKp20/y5xbP6JWrMa4egx1CQaFW/01crzr\nrpCGNme8Dr1Wz4jVgRjwD5SDY+w9eaCDCY9bGpbD/d6Te/FP/Ayss5p8NijUa/Av\n7YCPVcf/qwlTDL6vWFtsfPQP0ZChjooOVfErs7OPp1DFVW6G4IvepifH1OW7cjEB\nDU3F+i88aQKBgQDWDFSFSx2mXyuvAJQ/2kNscD+gLaYy5QyB3UcesIvoz5Xr1sBO\nESIULA3Rb+w9Nf8dAwjcSya78mDlVXEKQT9s9pPQevH6dT6UULx8MMXyDysho8AQ\n8LVxoGsf49yAFjihWMFGuV1ayQzUAvhwaaKvERyX1janZV4+bRrh3474iQKBgQCQ\n/ZLrhtjabD/QtZMEH8ZT4d45geQ63lmOYfnjHH/Bi+YpexiScOgPsYX3L4GxRhjy\nR9+6Nd7SYQtjB9VR/apv0Zxg7DrwKD3TfKBzbNNH9+4W7lJrj9LxXsEbpDtPa3UY\n5qJDOzHoQLRIS2Rlubg41zY1AfxPzVaEQyl20RYEqQKBgFrK0kNaqPGHMc7oKepV\n6i60Kd4VFf49xwE8NvO1W+mOfmx3EZ1ypwbtrvrhtlawQR3X0qOXNHEvnaJ8jRV8\nprv52N9JQHcA4bE8JZjPnXhphnexg7DyFC/hQu+21pnZriDV3L6dGgXoRwwjw6r9\nw9nysgbwsKsNjLzFnLjAJDMI\n-----END PRIVATE KEY-----\n'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL', 'firebase-adminsdk-fbsvc@sahariano-app.iam.gserviceaccount.com'),
        'client_id' => env('FIREBASE_CLIENT_ID', '110541018238768059111'),
        'auth_uri' => env('FIREBASE_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri' => env('FIREBASE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'auth_provider_x509_cert_url' => env('FIREBASE_AUTH_PROVIDER_X509_CERT_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
        'client_x509_cert_url' => env('FIREBASE_CLIENT_X509_CERT_URL', 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk-fbsvc%40sahariano-app.iam.gserviceaccount.com'),
        'universe_domain' => env('FIREBASE_UNIVERSE_DOMAIN', 'googleapis.com'),
    ],

];
