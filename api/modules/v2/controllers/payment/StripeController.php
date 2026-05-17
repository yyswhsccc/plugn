<?php

namespace api\modules\v2\controllers\payment;

use Yii;
use agent\models\PaymentMethod;
use api\models\Order;
use common\models\Payment;
use common\models\Setting;
use yii\helpers\Url;
use yii\rest\Controller;
use yii\web\Cookie;
use yii\web\NotFoundHttpException;
use api\modules\v2\controllers\BaseController;

class StripeController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove authentication filter for cors to work
        unset($behaviors['authenticator']);

        // Allow XHR Requests from our different subdomains and dev machines
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::className(),
            'cors' => [
                'Origin' => Yii::$app->params['allowedOrigins'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => [
                    'X-Pagination-Current-Page',
                    'X-Pagination-Page-Count',
                    'X-Pagination-Per-Page',
                    'X-Pagination-Total-Count'
                ],
            ],
        ];

        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
        //$behaviors['authenticator']['except'] = ['options', 'index', 'callback'];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $actions = parent::actions();
        $actions['options'] = [
            'class' => 'yii\rest\OptionsAction',
            // optional:
            'collectionOptions' => ['GET', 'POST', 'HEAD', 'OPTIONS'],
            'resourceOptions' => ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        ];
        return $actions;
    }

    /**
     * return client secret to initiate stripe form
     * @return array
     * @throws NotFoundHttpException
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function actionClientSecret() {

        $order_uuid = Yii::$app->request->get ('order_uuid');

        $order = $this->findOrder($order_uuid);

        try {

            $stripeSecretKey = Setting::getConfig($order->restaurant_uuid, "Stripe", 'payment_stripe_secret_key');
            $stripePublishableKey = Setting::getConfig($order->restaurant_uuid, "Stripe", 'payment_stripe_publishable_key');

            \Stripe\Stripe::setApiKey($stripeSecretKey);

            // Create a PaymentIntent with amount and currency
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $order->total * pow(10, $order->currency->decimal_place),
                'currency' => strtolower($order->currency_code),
                "payment_method_types" => ["card"],
                /*'automatic_payment_methods' => [
                    'enabled' => true,
                ],*/
            ]);

            $paymentMethod = PaymentMethod::findOne(['payment_method_code' => PaymentMethod::CODE_STRIPE]);

            $order->payment_method_id = $paymentMethod->payment_method_id;
            $order->payment_method_name = $paymentMethod->payment_method_name;
            $order->payment_method_name_ar = $paymentMethod->payment_method_name_ar;

            $payment = new \api\models\Payment;
            $payment->restaurant_uuid = $order->restaurant_uuid;
            $payment->customer_id = $order->customer? $order->customer->customer_id: null; //customer id
            $payment->order_uuid = $order->order_uuid;
            $payment->payment_amount_charged = $order->total;
            $payment->payment_gateway_transaction_id = $paymentIntent->id;
            $payment->payment_gateway_payment_id = $paymentIntent->id;
            $payment->save(false);

            $order->payment_uuid = $payment->payment_uuid;
            $order->save(false);

            return [
                'operation' => 'success',
                'clientSecret' => $paymentIntent->client_secret,
                "stripePublishableKey" => $stripePublishableKey,
                'success_url' => Url::to(['payment/stripe/callback', 'order_uuid' => $order->order_uuid], true),
                'cancel_url' => Url::to(['payment/stripe/callback', 'order_uuid' => $order->order_uuid], true),
            ];

        } catch (\Stripe\Exception\InvalidRequestException $e) {

            return [
                'operation' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (Error $e) {

            return [
                'operation' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * return params for payment
     * @return array
     */
    public function actionIndex()
    {
        //Yii::$app->session->set('payment_uuid', $payment->payment_uuid);

        $order_uuid = Yii::$app->request->get ('order_uuid');

        //$paymentMethod = PaymentMethod::findOne(['payment_method_code' => 'Moyasar']);

        $order = $this->findOrder($order_uuid);

        $stripeSecretKey = Setting::getConfig($order->restaurant_uuid, "Stripe", 'payment_stripe_secret_key');

        \Stripe\Stripe::setApiKey($stripeSecretKey);

        $checkout_session = \Stripe\Checkout\Session::create([
            'line_items' => [[
                # Provide the exact Price ID (e.g. pr_1234) of the product you want to sell
                'price' => $order->total,
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $order->customer_email,
            'currency' => $order->currency_code,
            //'amount_total' => $order->total,
            'client_reference_id' => $order->order_uuid,
            'success_url' => Url::to(['payment/stripe/callback', 'order_uuid' => $order->order_uuid], true),
            'cancel_url' => Url::to(['payment/stripe/callback', 'order_uuid' => $order->order_uuid], true),
        ]);

        $paymentMethod = PaymentMethod::findOne(['payment_method_code' => PaymentMethod::CODE_STRIPE]);

        $order->payment_method_id = $paymentMethod->payment_method_id;
        $order->payment_method_name = $paymentMethod->payment_method_name;
        $order->payment_method_name_ar = $paymentMethod->payment_method_name_ar;

        $payment = new \api\models\Payment;
        $payment->restaurant_uuid = $order->restaurant_uuid;
        $payment->customer_id = $order->customer? $order->customer->customer_id: null; //customer id
        $payment->order_uuid = $order->order_uuid;
        $payment->payment_amount_charged = $order->total;
        $payment->payment_gateway_transaction_id  = $checkout_session->id;
        $payment->payment_gateway_payment_id = $checkout_session->payment_intent;
        $payment->save(false);

        $order->payment_uuid = $payment->payment_uuid;
        $order->save(false);

        return [
            'operation' => 'redirecting',
            'redirectUrl' => $checkout_session->url,
            'orderUuid' => $order->order_uuid
        ];

        //$this->redirect($checkout_session->url, 302);
    }

    protected function updateOrder()
    {
        /*if (isset($get_data['sid'])) {
            $this->session->start($get_data['sid']);
        }*/

        $order_uuid = Yii::$app->request->get('order_uuid');

        $order = $this->findOrder($order_uuid);

        //add payment entry for debug

        $payment = $order->getPayments()->orderBy('payment_created_at DESC')->one();

        //

        $stripeSecretKey = Setting::getConfig($order->restaurant_uuid, "Stripe", 'payment_stripe_secret_key');

        $stripe = new \Stripe\StripeClient($stripeSecretKey);

        $paymentIntent = $stripe->paymentIntents->retrieve(
            $payment->payment_gateway_payment_id,
            []
        );

       // $payment->payment_gateway_fee = $paymentDetail['fee'] / pow(10, $order->currency->decimal_place);//todo: fees from stripe?

        //$payment->payment_gateway_fee = $paymentIntent->application_fee_amount;
        //$payment-> currency

        $payment->received_callback = true;

        // Net amount after deducting gateway fee
        $payment->payment_net_amount = $payment->payment_amount_charged - $payment->payment_gateway_fee;

        if ($order->restaurant->platform_fee)
            $payment->plugn_fee = ($payment->payment_amount_charged * $order->restaurant->platform_fee / 100) - $payment->payment_gateway_fee;
        else
            $payment->plugn_fee = 0;

        if ($paymentIntent->status == 'succeeded')
            $payment->payment_current_status = 'CAPTURED';
        else
            $payment->payment_current_status = $paymentIntent->status;

        $payment->save(false);

        $error = null;

        if ($paymentIntent->status != 'succeeded') {
            $error = 'Stripe Payment Verification Failed: '. $paymentIntent->status;
        }

        /*if (!$payment) {
            Yii::error('Moyasar payment is successful but payment_uuid does not match, possible tampering. #' . $id);
            return false;
        }*/

        //Yii::$app->currency->format($payment['total'], $payment['currency_code'], $payment['currency_value'], false)*100;

        if($error) {

            //notify tech team + vendor

            Yii::error($error);

            Payment::notifyTapError($payment, $paymentIntent);

            return false;
        }

        //payment was made successfully

        Payment::onPaymentCaptured($payment);

        return $payment;
    }

    /**
     * callback from gateway
     */
    public function actionCallback()
    {
        $payment = $this->updateOrder();

        if ($payment) {
            $url = $this->buildRestaurantReturnUrl($payment->restaurant, 'payment-success/' . $payment->order_uuid . '/' . $payment->payment_uuid);
        } else {
            $url = $this->buildRestaurantReturnUrl(null, 'payment-failed');
        }

        return Yii::$app->getResponse()->redirect($url)->send(301);
    }

    /**
     * add cookies to show error message in front app
     */
    private function _addCallbackCookies($name, $msg)
    {
        $cookie = new Cookie([
            'name' => $name,
            'value' => $msg,
            'expire' => time () + 86400,
            'domain' => Yii::$app->params['dashboardCookieDomain'],
            'httpOnly' => false,
            'secure' => str_contains (Yii::$app->params['newDashboardAppUrl'], 'https://')? true: false,
        ]);

        $cookie->sameSite = PHP_VERSION_ID >= 70300 ? 'None' : null;

        \Yii::$app->getResponse ()->getCookies ()->add ($cookie);
    }

    /**
     * Finds the Order model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Order the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findOrder($order_uuid)
    {
        $model = Order::find()
            ->andWhere([
                'order_uuid' => $order_uuid
            ])
            ->one();

        if ($model !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested record does not exist.');
        }
    }
}
