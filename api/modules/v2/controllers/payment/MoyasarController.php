<?php

namespace api\modules\v2\controllers\payment;


use agent\models\PaymentMethod;
use api\models\Order;
use common\models\Payment;
use Yii;
use common\models\Setting;
use yii\helpers\Url;
//use api\modules\v2\controllers\BaseController;
use yii\rest\Controller;
use yii\web\Cookie;
use yii\web\NotFoundHttpException;
use api\modules\v2\controllers\BaseController;

class MoyasarController extends BaseController
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
     * return params for payment
     * @return array
     */
    public function actionIndex()
    {
        //Yii::$app->session->set('payment_uuid', $payment->payment_uuid);

        $order_uuid = Yii::$app->request->get ('order_uuid');

        //$paymentMethod = PaymentMethod::findOne(['payment_method_code' => 'Moyasar']);

        $order = $this->findOrder($order_uuid);

        $data['action'] = 'https://api.moyasar.com/v1/payments.html';

        //payment_moyasar_api_secret_key
        $data['payment_moyasar_api_key'] = Setting::getConfig($order->restaurant_uuid, "Moyasar", 'payment_moyasar_api_key');

        $data['payment_moyasar_payment_type'] = Setting::getConfig($order->restaurant_uuid, "Moyasar", 'payment_moyasar_payment_type');

        $data['payment_moyasar_network_type'] = Setting::getConfig($order->restaurant_uuid, "Moyasar", 'payment_moyasar_network_type');

        //$country = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

        $data['payment_moyasar_payment_methods'] = ["creditcard", "stcpay", "applepay"];

        //array_push($data['payment_moyasar_payment_methods'], ($data['payment_moyasar_payment_type']['cc'])? 'creditcard' : 'creditcard');
        //array_push($data['payment_moyasar_payment_methods'], ($data['payment_moyasar_payment_type']['stcpay'])? 'stcpay' : 'stcpay');
        //array_push($data['payment_moyasar_payment_methods'], ($data['payment_moyasar_payment_type']['applepay'])? 'applepay' : '');
        $data['payment_moyasar_payment_methods_json'] = json_encode($data['payment_moyasar_payment_methods']);

        // get network support from admin
        $data['payment_moyasar_network_support'] = ['mada', 'visa', 'mastercard', 'amex'];
        /*array_push($data['payment_moyasar_network_support'], ($data['payment_moyasar_network_type']['mada'])? 'mada' : '');
        array_push($data['payment_moyasar_network_support'], ($data['payment_moyasar_network_type']['visa'])? 'visa' : '');
        array_push($data['payment_moyasar_network_support'], ($data['payment_moyasar_network_type']['mastercard'])? 'mastercard' : '');
        array_push($data['payment_moyasar_network_support'], ($data['payment_moyasar_network_type']['amex'])? 'amex' : '');*/
        $data['payment_moyasar_network_support_json'] = json_encode($data['payment_moyasar_network_support']);

        $data['callback_url'] = str_replace("%2F", "/", Url::to(['payment/moyasar/callback'], true));

        $data['validate_merchant_url'] = 'https://api.moyasar.com/v1/applepay/initiate';

        $data['amount'] = $order->total;
        $data['amount_in_halals'] =  $order->total * pow(10, $order->currency->decimal_place);
        $data['language_code'] = Yii::$app->language;
        $data['currency'] = $order->currency_code;// $payment['currency_code'];
        $data['country'] =  $order->restaurant->country? $order->restaurant->country->iso : "KW";
        $data['store_name'] = Yii::$app->params['appName'];
        $data['orderdate'] = $order->order_created_at;
        $data['description'] = "Order placed from: " . $order->customer_name;
        $data['domain_name'] = $order->restaurant->restaurant_domain;  //Yii::$app->request->hostName;

        $data['text_cc'] = Yii::t('app', "Credit card");
        $data['text_mada'] = Yii::t('app', "Mada");
        $data['text_cc_mada'] = Yii::t('app', "Credit card mada");
        $data['text_stc_pay'] = Yii::t('app', "STC pay");
        $data['text_applepay'] = Yii::t('app', "Apple pay");
        $data['text_stcpay'] = Yii::t('app', "Stc Pay");
        $data['text_applepay_not_configured'] = Yii::t('app', "Apple pay not configured");
        $data['text_applepay_not_supported'] = Yii::t('app', "Apple pay not supported");

        //metadata
        $data['metadata'] = [
            'order_uuid' => $order->order_uuid,
            'restaurant_uuid' => $order->restaurant_uuid,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            //'email' => $payment->user->email
        ];

        $data['metadata_json'] = $data['metadata'];

        //todo: update url
        $data['applepay_on_cancel_url'] = $data['callback_url'];//  Url::to(['site/index'], true);
        $data['applepay_on_payment_success_url'] = $data['callback_url'];//Url::to(['site/index'], true);
        $data['button_confirm'] = "Confirm";

        return $data;
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

    // todo: old callback in general form
    protected function updateOrder()
    {
        /*if (isset($get_data['sid'])) {
            $this->session->start($get_data['sid']);
        }*/

        $id = Yii::$app->request->get('id');
        $status = Yii::$app->request->get('status');
        $message = Yii::$app->request->get('message');

        $paymentDetail = $this->_paymentDetail($id);

        $order_uuid = $paymentDetail['metadata']['order_uuid'];

        $order = $this->findOrder($order_uuid);

        $order_amount = $order->total * pow(10, $order->currency->decimal_place);

        //add payment entry for debug

        $paymentMethod = PaymentMethod::findOne(['payment_method_code' => PaymentMethod::CODE_MOYASAR]);

        $order->payment_method_id = $paymentMethod->payment_method_id;
        $order->payment_method_name = $paymentMethod->payment_method_name;
        $order->payment_method_name_ar = $paymentMethod->payment_method_name_ar;

        $payment = new \api\models\Payment;
        $payment->restaurant_uuid = $order->restaurant_uuid;
        $payment->customer_id = $order->customer? $order->customer->customer_id: null; //customer id
        $payment->order_uuid = $order->order_uuid;
        $payment->payment_amount_charged = $order->total;
        //$payment->is_sandbox = $order->restaurant->is_sandbox;
        $payment->response_message = $message;
        $payment->payment_gateway_fee = $paymentDetail['fee'] / pow(10, $order->currency->decimal_place);//in halals
        //$payment->payment_gateway_order_id = $id;
        $payment->payment_gateway_payment_id = $id;
        $payment->received_callback = true;

        // Net amount after deducting gateway fee
        $payment->payment_net_amount = $payment->payment_amount_charged - $payment->payment_gateway_fee;

        if ($order->restaurant->platform_fee)
            $payment->plugn_fee = ($payment->payment_amount_charged * $order->restaurant->platform_fee / 100) - $payment->payment_gateway_fee;
        else
            $payment->plugn_fee = 0;

        if ($status == 'paid')
            $payment->payment_current_status = 'CAPTURED';
        else
            $payment->payment_current_status = $status;

        $payment->save(false);

        $order->payment_uuid = $payment->payment_uuid;
        $order->save(false);

        $error = null;

        if ($status != 'paid') {
            $error = 'Moyasar Payment Verification Failed: '. $message;
        } else if (empty($paymentDetail['amount']) || $paymentDetail['amount'] != $order_amount) {
            $error = 'Moyasar payment is successful but amount does not match paid, possible tampering. #' . $id;
        }

        /*if (!$payment) {
            Yii::error('Moyasar payment is successful but payment_uuid does not match, possible tampering. #' . $id);
            return false;
        }*/

        //Yii::$app->currency->format($payment['total'], $payment['currency_code'], $payment['currency_value'], false)*100;

        if($error) {

            //notify tech team + vendor

            Yii::error($error);

            Payment::notifyTapError($payment, $paymentDetail);

            return false;
        }

        //payment was made successfully

        Payment::onPaymentCaptured($payment);

        return $payment;
    }

    public function actionRegisterInitiatedOrder()
    {
        //Yii::debug(Yii::$app->request);

        $this->addOrder(
            Yii::$app->request->get('id'),
            Yii::$app->request->get('payment_id')
        );
    }

    private function _paymentDetail($payment_id)
    {
        $secret_key = Setting::getConfig(null, "Moyasar", 'payment_moyasar_api_secret_key');

        $header = [
            'Authorization: Basic '. base64_encode($secret_key.':')
        ];

        $curl = curl_init('https://api.moyasar.com/v1/payments/'.$payment_id);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        return json_decode(curl_exec($curl), true);
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
