# Keepz API SDK

PHP SDK for the [Keepz](https://www.developers.keepz.me) eCommerce payment API.

- [Setup](#setup)
- [Create an Order](#create-an-order)
  - [Optional fields](#optional-fields)
  - [Payment method pre-selection](#payment-method-pre-selection)
  - [Save a card](#save-a-card)
  - [Charge a saved card](#charge-a-saved-card)
  - [Split payment](#split-payment)
- [Get Order Status](#get-order-status)
- [Cancel Order](#cancel-order)
- [Error handling](#error-handling)

## Setup

```php
use KeepzSdk\Client;
use KeepzSdk\Http\HttpClient;
use KeepzSdk\Crypto\Encryptor;
use KeepzSdk\Crypto\Decryptor;

$publicKey  = "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----";
$privateKey = "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----";

$client = new Client(
    'https://gateway.dev.keepz.me/ecommerce-service',
    'your-integrator-id',   // provided by Keepz
    new HttpClient(),
    new Encryptor($publicKey),
    new Decryptor($privateKey)
);
```

All credentials (`integratorId`, `receiverId`, RSA key pair) are provided by Keepz during onboarding.

The SDK encrypts every outgoing request with RSA + AES automatically, and decrypts every successful response before returning it. You always work with plain PHP arrays — encryption is invisible.

---

## Create an order

```php
use KeepzSdk\Exceptions\ApiException;

try {
    $order = $client->orders()->create([
        'amount'            => 100,
        'receiverId'        => 'uuid-provided-by-keepz',
        'receiverType'      => 'BRANCH',
        'integratorId'      => 'your-integrator-id',
        'integratorOrderId' => 'your-unique-order-uuid',
    ]);

    $order->getIntegratorOrderId(); // 'your-unique-order-uuid'
    $order->getUrlForQR();          // 'https://...' — show this to the customer
} catch (ApiException $e) {
    $e->getMessage();        // human-readable error from Keepz
    $e->getStatusCode();     // e.g. 6031
    $e->getExceptionGroup(); // e.g. 3
    $e->getRawResponse();    // full response array as received from the API
}
```

On success `create()` returns an `OrderCreatedData` object. On any API error it throws `ApiException` — decryption is never attempted on error responses.

### Optional fields

| Field                | Values                     | Description                          |
|----------------------|----------------------------|--------------------------------------|
| `currency`           | `GEL` `USD` `EUR`          | Displayed currency (default: `GEL`)  |
| `language`           | `EN` `IT` `KA`             | Checkout page language               |
| `commissionType`     | `SENDER` `RECEIVER` `BOTH` | Who pays the transaction fee         |
| `successRedirectUri` | URL                        | Redirect after successful payment    |
| `failRedirectUri`    | URL                        | Redirect after failed payment        |
| `callbackUri`        | URL                        | Webhook called on payment completion |
| `validUntil`         | `yyyy-MM-dd HH:mm:ss`      | Order expiry datetime                |

### Payment method pre-selection

If none of these are sent, the customer sees the full checkout page to pick a method themselves.

**Card**
```php
$client->orders()->create([
    ...,
    'directLinkProvider' => 'DEFAULT', // BOG | TBC | CREDO | DEFAULT
]);
```

**Open banking**
```php
$client->orders()->create([
    ...,
    'openBankingLinkProvider' => 'TBC', // TBC | BOG | CREDO | LB
]);
```

**Crypto**
```php
$client->orders()->create([
    ...,
    'cryptoPaymentProvider' => 'CITYPAY',
]);
```

**Installment**
```php
$client->orders()->create([
    ...,
    'installmentPaymentProvider' => 'CREDO',
    'personalNumber'             => '61000000000',
    'isForeign'                  => false,
]);
```

### Save a card

```php
$client->orders()->create([
    ...,
    'directLinkProvider' => 'CREDO',
    'saveCard'           => true,
]);
// cardToken is returned in the payment callback inside cardInfo
```

### Charge a saved card

```php
$client->orders()->create([
    ...,
    'cardToken' => 'uuid-returned-from-callback',
]);
```

### Split payment

Use the dedicated `createSplit()` method, passing the base order data and the distributions as separate arguments:

```php
$order = $client->orders()->createSplit(
    [
        'amount'            => 100,
        'receiverId'        => 'uuid-provided-by-keepz',
        'receiverType'      => 'BRANCH',
        'integratorId'      => 'your-integrator-id',
        'integratorOrderId' => 'your-unique-order-uuid',
    ],
    [
        ['receiverType' => 'BRANCH', 'receiverIdentifier' => 'branch-uuid', 'amount' => 75],
        ['receiverType' => 'IBAN',   'receiverIdentifier' => 'GE34BG0000001234567890', 'amount' => 25],
    ]
);

$order->getUrlForQR(); // show to the customer
```

`receiverType` per distribution can be `BRANCH`, `USER`, or `IBAN`.

## Get Order Status

```php

$orderStatusData = $client->orders()->getOrderStatus($integratorId, $integratorOrderId);

$order->getIntegratorOrderId(); // 'your-unique-order-uuid'
$order->getStatus(); // Order Status

```

You can see possible statuses in official [keepz api documentation](https://www.developers.keepz.me/eCommerece%20integration/get-order-status#response-details)

## Cancel Order

```php

$orderStatusData = $client->orders()->cancel($integratorId, $integratorOrderId);

$order->getIntegratorOrderId(); // 'your-unique-order-uuid'
$order->getStatus(); // Order Status (CANCELED)

```

---

## Error handling

All API errors are thrown as `KeepzSdk\Exceptions\ApiException` before any decryption is attempted:

```php
use KeepzSdk\Exceptions\ApiException;

try {
    $order = $client->orders()->create([...]);
} catch (ApiException $e) {
    // Log or handle the API-level error
    error_log(sprintf(
        'Keepz error %d (group %d): %s',
        $e->getStatusCode(),
        $e->getExceptionGroup(),
        $e->getMessage()
    ));
}
```
