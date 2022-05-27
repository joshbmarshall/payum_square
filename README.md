# Square Payment Module

The Payum extension to purchase through Square using Elements

## Install and Use

To install, it's easiest to use composer:

    composer require cognito/payum_square

### Build the config

```php
<?php

use Payum\Core\PayumBuilder;
use Payum\Core\GatewayFactoryInterface;

$defaultConfig = [];

$payum = (new PayumBuilder)
    ->addGatewayFactory('square', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
        return new \Cognito\PayumSquare\SquareGatewayFactory($config, $coreGatewayFactory);
    })

    ->addGateway('square', [
        'factory' => 'square',
        'access_token' => 'Your-access-token',
        'app_id' => 'Your-app-id',
        'location_id' => 'Your-location-id',
        'sandbox' => false,
        'img_url' => 'https://path/to/logo/image.jpg',
    ])

    ->addGateway('square_afterpay', [
        'factory' => 'square_afterpay',
        'access_token' => 'Your-access-token',
        'app_id' => 'Your-app-id',
        'location_id' => 'Your-location-id',
        'sandbox' => false,
        'img_url' => 'https://path/to/logo/image.jpg',
    ])

    ->getPayum()
;
```

### Request card payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('square', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Request Afterpay payment

Afterpay requires more information about the customer to process the payment

```php
<?php

use Payum\Core\Request\Capture;

$storage = $payum->getStorage(\Payum\Core\Model\Payment::class);
$request = [
    'invoice_id' => 100,
];

$payment = $storage->create();
$payment->setNumber(uniqid());
$payment->setCurrencyCode($currency);
$payment->setTotalAmount(100); // Total cents
$payment->setDescription(substr($description, 0, 45));
$payment->setDetails([
    'shipping' => [
        'name' => 'Firstname Lastname',
        'address' => [
            'line1' => 'Address Line 1',
            'city' => 'Address City',
            'state' => 'Address State',
            'country' => 'Address Country',
            'postal_code' => 'Address Postal Code',
        ],
    ],
    'billing' => [
        'name' => trim($shopper['first_name'] . ' ' . $shopper['last_name']),
        'email' => $shopper['email'],
        'address' => [
            'line1' => 'Address Line 1',
            'city' => 'Address City',
            'state' => 'Address State',
            'country' => 'Address Country',
            'postal_code' => 'Address Postal Code',
        ],
    ],
]);
$storage->setInternalDetails($payment, $request);

$captureToken = $payum->getTokenFactory()->createCaptureToken('square', $payment, 'done.php');
$url = $captureToken->getTargetUrl();
header("Location: " . $url);
die();
```

### Check it worked

```php
<?php
/** @var \Payum\Core\Model\Token $token */
$token = $payum->getHttpRequestVerifier()->verify($request);
$gateway = $payum->getGateway($token->getGatewayName());

/** @var \Payum\Core\Storage\IdentityInterface $identity **/
$identity = $token->getDetails();
$model = $payum->getStorage($identity->getClass())->find($identity);
$gateway->execute($status = new GetHumanStatus($model));

/** @var \Payum\Core\Request\GetHumanStatus $status */

// using shortcut
if ($status->isNew() || $status->isCaptured() || $status->isAuthorized()) {
    // success
} elseif ($status->isPending()) {
    // most likely success, but you have to wait for a push notification.
} elseif ($status->isFailed() || $status->isCanceled()) {
    // the payment has failed or user canceled it.
}
```

## License

Payum Square is released under the [MIT License](LICENSE).
