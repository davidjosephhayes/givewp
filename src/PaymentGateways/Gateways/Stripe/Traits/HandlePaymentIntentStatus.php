<?php

namespace Give\PaymentGateways\Gateways\Stripe\Traits;

use Give\Framework\PaymentGateways\Commands\PaymentProcessing;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\PaymentGateways\Gateways\Stripe\Exceptions\PaymentIntentException;
use Give\PaymentGateways\Gateways\Stripe\ValueObjects\PaymentIntent;

trait HandlePaymentIntentStatus
{
    /**
     * @param PaymentIntent $paymentIntent
     * @return PaymentProcessing|RedirectOffsite
     * @throws PaymentIntentException
     */
    public function handlePaymentIntentStatus( PaymentIntent $paymentIntent )
    {
        switch( $paymentIntent->status() )  {
            case 'requires_action':
                return new RedirectOffsite( $paymentIntent->nextActionRedirectUrl() );
            case 'succeeded':
            case 'processing':
                return new PaymentProcessing( $paymentIntent->id() );
            default:
                throw new PaymentIntentException( sprintf( __( 'Unhandled payment intent status: %s', 'give' ), $paymentIntent->status() ) );
        }
    }
}
