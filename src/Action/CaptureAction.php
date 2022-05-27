<?php

namespace Cognito\PayumSquare\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Cognito\PayumSquare\Request\Api\ObtainNonce;

class CaptureAction implements ActionInterface, GatewayAwareInterface {
    use GatewayAwareTrait;

    private $config;

    /**
     * @param string $templateName
     */
    public function __construct(ArrayObject $config) {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request) {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        if ($model['status']) {
            return;
        }

        $model['app_id'] = $this->config['app_id'];
        $model['location_id'] = $this->config['location_id'];
        $model['img_url'] = $this->config['img_url'] ?? '';

        $obtainNonce = new ObtainNonce($request->getModel());
        $obtainNonce->setModel($model);

        $this->gateway->execute($obtainNonce);
        if (!$model->offsetExists('status')) {
            $model['status'] = 'success';
            $model['transactionReference'] = 'test';
            $model['result'] = 'result';

            $client = new \Square\SquareClient([
                'accessToken' => $this->config['access_token'],
                'environment' => $this->config['sandbox'] ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION,
            ]);

            $amount_money = new \Square\Models\Money();
            $amount_money->setAmount($model['amount'] * 100);
            $amount_money->setCurrency($model['currency']);

            $body = new \Square\Models\CreatePaymentRequest(
                $model['nonce'],
                $request->getToken()->getHash(),
                $amount_money
            );
            $body->setAutocomplete(true);
            $body->setVerificationToken($model['verificationToken']);
            $body->setCustomerId($model['customer_id'] ?? null);
            $body->setLocationId($model['location_id']);
            $body->setReferenceId($model['reference_id'] ?? null);
            $body->setNote($model['description']);

            $api_response = $client->getPaymentsApi()->createPayment($body);

            if ($api_response->isSuccess()) {
                $result = $api_response->getResult();
                $model['status'] = 'success';
                $model['transactionReference'] = $result->getPayment()->getId();
                $model['result'] = $result->getPayment();
            } else {
                $errors = $api_response->getErrors();
                $model['status'] = 'failed';
                $model['error'] = 'failed';
                foreach ($errors as $error) {
                    $model['error'] = $error->getDetail();
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request) {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
