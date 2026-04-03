# Keepz API SDK

PHP SDK for the [Keepz](https://www.developers.keepz.me) eCommerce payment API.

## Setup

```php
use KeepzSdk\Client;
use KeepzSdk\Http\HttpClient;
use KeepzSdk\Crypto\Encryptor;

$publicKey = "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----";

$client = new Client(
    'https://gateway.dev.keepz.me/ecommerce-service',
    'your-integrator-id',   // provided by Keepz
    new HttpClient(),
    new Encryptor($publicKey)
);
```

All credentials (`integratorId`, `receiverId`, RSA public key) are provided by Keepz during onboarding.

---

## Create an order

```php
$response = $client->orders()->create([
    'amount'            => 100,
    'receiverId'        => 'uuid-provided-by-keepz',
    'receiverType'      => 'BRANCH',
    'integratorId'      => 'your-integrator-id',
    'integratorOrderId' => 'your-unique-order-uuid',
]);
```

The payload is encrypted automatically before sending. On success the response contains `encryptedData` (decrypt to get `integratorOrderId` and `urlForQR`) and on error a plain JSON with `message`, `statusCode`, and `exceptionGroup`.

### Optional fields

| Field | Values | Description |
|---|---|---|
| `currency` | `GEL` `USD` `EUR` | Displayed currency (default: `GEL`) |
| `language` | `EN` `IT` `KA` | Checkout page language |
| `commissionType` | `SENDER` `RECEIVER` `BOTH` | Who pays the transaction fee |
| `successRedirectUri` | URL | Redirect after successful payment |
| `failRedirectUri` | URL | Redirect after failed payment |
| `callbackUri` | URL | Webhook called on payment completion |
| `validUntil` | `yyyy-MM-dd HH:mm:ss` | Order expiry datetime |

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
$client->orders()->createSplit(
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
```

`receiverType` per distribution can be `BRANCH`, `USER`, or `IBAN`.
