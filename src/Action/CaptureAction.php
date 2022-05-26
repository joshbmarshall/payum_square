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
            $stripe = new \Stripe\StripeClient($this->config['secret_key']);
            $paymentIntent = $stripe->paymentIntents->retrieve($model['nonce'], []);
            if ($paymentIntent->status == \Stripe\PaymentIntent::STATUS_SUCCEEDED) {
                $model['status'] = 'success';
            } else {
                // Report error
                $model['status'] = 'failed';
                $model['error'] = 'failed';
            }
            foreach ($paymentIntent->charges as $charge) {
                $model['transactionReference'] = $charge->id;
                $model['result'] = $charge;
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
