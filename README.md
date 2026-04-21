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
- [Refund Order](#refund-order)
  - [Simple refund](#simple-refund)
  - [Refund with split breakdown](#refund-with-split-breakdown)
- [Get Saved Card](#get-saved-card)
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

## Refund Order

Use `refund()` to initiate a return of funds to the payer. Refunds are only possible when the order status is `SUCCESS`, `PARTIALLY_REFUNDED`, or `REFUNDED_FAILED`, and only if your integrator account has refund functionality enabled by Keepz.

> **Note:** The API responds immediately with `REFUND_REQUESTED`. The final outcome (`REFUNDED_BY_INTEGRATOR`, `PARTIALLY_REFUNDED`, `REFUNDED_FAILED`) is resolved asynchronously — use `getOrderStatus()` to poll for the final status.

### Simple refund

```php
use KeepzSdk\Exceptions\ApiException;

try {
    $result = $client->orders()->refund([
        'integratorId'      => 'your-integrator-id',      // UUID provided by Keepz
        'integratorOrderId' => 'your-unique-order-uuid',  // the order you want to refund
        'amount'            => 50.00,                     // must be ≤ original transaction amount
    ]);

    $result->getIntegratorOrderId(); // 'your-unique-order-uuid'
    $result->getStatus();            // 'REFUND_REQUESTED'
} catch (ApiException $e) {
    $e->getMessage();    // e.g. "You can't refund order: Order is already fully refunded"
    $e->getStatusCode(); // e.g. 6005
}
```

Optionally, pass `refundInitiator` to specify who is triggering the refund:

```php
$client->orders()->refund([
    'integratorId'      => 'your-integrator-id',
    'integratorOrderId' => 'your-unique-order-uuid',
    'amount'            => 50.00,
    'refundInitiator'   => 'INTEGRATOR', // INTEGRATOR | OPERATOR
]);
```

### Refund with split breakdown

When the original payment was a split payment, you can distribute the refunded amount among specific recipients using `refundDetails`:

```php
$result = $client->orders()->refund([
    'integratorId'      => 'your-integrator-id',
    'integratorOrderId' => 'your-unique-order-uuid',
    'amount'            => 100,
    'refundDetails'     => [
        [
            'receiverType'       => 'BRANCH',                    // BRANCH | USER | IBAN
            'receiverIdentifier' => 'branch-uuid',               // UUID for BRANCH/USER, IBAN string for IBAN
            'amount'             => 75,
        ],
        [
            'receiverType'       => 'IBAN',
            'receiverIdentifier' => 'GE34BG0000001234567890',
            'amount'             => 25,
        ],
    ],
]);
```

Each entry in `refundDetails` must have `receiverType`, `receiverIdentifier`, and `amount` (> 0). The sum of all `amount` values must not exceed the original transaction amount.

---

## Get Saved Card

Use `cards()->getSavedCard()` to retrieve tokenized card information after a customer has completed a payment with card-saving enabled.

> **Note:** The `integratorOrderId` must correspond to an order where `saveCard: true` was passed at creation time and the payment was completed successfully.

```php
use KeepzSdk\Exceptions\ApiException;

try {
    $card = $client->cards()->getSavedCard('your-unique-order-uuid');

    $card->getToken();          // 'uuid' — reuse as cardToken in future orders
    $card->getProvider();       // 'CREDO'
    $card->getCardMask();       // '411111******1111'
    $card->getExpirationDate(); // '12/27'
    $card->getCardBrand();      // 'VISA'
} catch (ApiException $e) {
    $e->getMessage();    // e.g. 'Integrator card not found'
    $e->getStatusCode(); // e.g. 6075
}
```

The `token` returned can be passed directly as `cardToken` when creating a new order to charge the saved card without requiring the customer to re-enter their details:

```php
$client->orders()->create([
    'amount'            => 50,
    'receiverId'        => 'uuid-provided-by-keepz',
    'receiverType'      => 'BRANCH',
    'integratorId'      => 'your-integrator-id',
    'integratorOrderId' => 'new-unique-order-uuid',
    'cardToken'         => $card->getToken(),
]);
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
