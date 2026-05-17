<?php

namespace api\modules\v2\controllers\payment;

use Yii;
use agent\models\PaymentMethod;
use api\models\Order;
use common\models\Payment;
use common\models\Setting;
use yii\helpers\Url;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;
use api\modules\v2\controllers\BaseController;


class UpaymentController extends BaseController
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
        $json = [];

        $order_uuid = Yii::$app->request->get ('order_uuid');

        $order_info = $this->findOrder($order_uuid);

        $unique_order_id = $order_info['order_uuid'];//md5($order_info['order_uuid'] * time());

        $api_key = Setting::getConfig($order_info->restaurant_uuid, "Upayment", 'payment_upayment_api_key');

        $customer_unq_token = $this->getCustomerUniqueToken($order_info, $api_key);

        $return_url = str_replace("%2F", "/", Url::to(['payment/upayment/callback', 'order_uuid' => $order_info["order_uuid"]], true));
        $return_url = str_replace('&amp;', '&', $return_url);

        /*$src = str_replace('upay.', '', $this->session->data['payment_method']['code']);
        if($src == 'upay'){
            $src = null;
        }*/

        $params = json_encode([
            "returnUrl" => $return_url,
            "cancelUrl" => $return_url,
            "notificationUrl" => $return_url,
            "order" =>[
                "amount" => $order_info['total'],
                "currency" => $order_info->currency_code,//['currency'] ,
                "id" => $unique_order_id,
            ],
            "reference" => [
                "id" => "".$order_info['order_uuid'],
            ],
            "customer" => [
                "uniqueId" => $customer_unq_token,
                "name" => $order_info['customer_name'],
                "email" => $order_info['customer_email'],
                "mobile" => $order_info['customer_phone_number'],
            ],
            /*"plugin" => [
                "src" => "Plugin",
            ],*/
            "language" => Yii::$app->language == "ar"? "ar": "en",
            //"paymentGateway" => ["src" => $src,],
            "tokens" => [
                "creditCard" => '',
                "customerUniqueToken" => $customer_unq_token,
            ],
            "device" => [
                "browser" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36 OPR/93.0.0.0",
                "browserDetails" => [
                    "screenWidth" => "1920",
                    "screenHeight" => "1080",
                    "colorDepth" => "24",
                    "javaEnabled" => "false",
                    "language" => "en",
                    "timeZone" => "-180",
                    "3DSecureChallengeWindowSize" => "500_X_600", ],
            ],
        ]);

        $curl = curl_init();

        if($order_info->restaurant->is_sandbox) {
            $apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
        } else {
            $apiUrl = 'https://apiv2api.upayments.com/api/v1/';
        }

        $payment_upayment_api_key = Setting::getConfig($order_info->restaurant_uuid, "Upayment", 'payment_upayment_api_key');

        curl_setopt_array($curl, array(
            CURLOPT_URL =>  $apiUrl.'charge',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '. $payment_upayment_api_key
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response, true);
        if($result){
            if (isset($result["errors"])) {
                $json['error'] = "Error from UPayments: ".$result["message"];
            } elseif (isset($result["message"]) && (!isset($result["status"]) || $result["status"] == false)){
                $json['error'] = "Error from UPayments: ".$result["message"];
            }
            if (!$json){
                $json['redirect']=$result["data"]["link"];
            }
        } else {
            $json['error'] = "Error from UPayments: Your IP is not whiltelisted";
        }

        return $json;
    }

    /**
     * @param $order
     * @param $api_key
     * @return string
     */
    public function getCustomerUniqueToken($order, $api_key) {

        $token = '';

        $phone = $order['customer_phone_number'];

        if (!empty($phone))
        {
            $token = $phone;
            $params = json_encode(["customerUniqueToken" => $token, ]);

            $curl = curl_init();

            if($order->restaurant->is_sandbox) {
                $apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
            } else {
                $apiUrl = 'https://apiv2api.upayments.com/api/v1/';
            }

            curl_setopt_array($curl, [CURLOPT_URL => $apiUrl.'create-customer-unique-token' ,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $params, CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json", "Authorization: Bearer " . $api_key, ], ]);

            $response = curl_exec($curl);
            if ($response)
            {
                $result = json_decode($response, true);
                if (isset($result["status"]) && $result["status"] == true)
                {
                    $token = $token;
                }
            }
        }
        return $token;
    }

    private function getStatus($order, $track_id) {

        /*$client = new \GuzzleHttp\Client();

        if($order->restaurant->is_sandbox) {
            $apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
        } else {
            $apiUrl = 'https://apiv2api.upayments.com/api/v1/';
        }*/

        $api_key = Setting::getConfig($order->restaurant_uuid, "Upayment", 'payment_upayment_api_key');

        /*$response = $client->request('GET', $apiUrl. 'get-payment-status/' . $track_id, [
            'headers' => [
                'accept' => 'application/json',
                "Authorization: Bearer " . $api_key,

            ],
        ]);*/
//CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $params, CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json", "Authorization: Bearer " . $api_key,
//                    ]

        $result = [];

        $curl = curl_init();

        if($order->restaurant->is_sandbox) {
            $apiUrl = 'https://sandboxapi.upayments.com/api/v1/';
        } else {
            $apiUrl = 'https://apiv2api.upayments.com/api/v1/';
        }

        curl_setopt_array($curl, [CURLOPT_URL => $apiUrl.'get-payment-status/' . $track_id ,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "GET", CURLOPT_HTTPHEADER => ["Accept: application/json", "Content-Type: application/json", "Authorization: Bearer " . $api_key, ], ]);

        $response = curl_exec($curl);
        if ($response)
        {
            $result = json_decode($response, true);
        }

        return $result; //$response->getBody();

        /*Array
(
    [status] => 1
    [message] => Response received successfully
    [data] => Array
        (
            [transaction] => Array
                (
                    [order_id] => Lq22u273r72WL2776a6uF4M145l5i21011AFv44062U8724d3622
                    [refund_order_id] => Lq22u273r72WL2776a6uF4M145l5i21011AFv44062U8724d3622
                    [payment_id] => 100402201000006228
                    [result] => CAPTURED
                    [payment_type] => knet
                    [track_id] => 21924Dy13Y05195552222295bx4030201599944S
                    [transaction_date] => 2024-01-22 11:12:48
                    [is_save_card] =>
                    [from_plugin] =>
                    [product_details] => {"title":null,"name":null,"price":null,"qty":null,"more_details":""}
                    [reference] => 189RPJ
                    [total_paid_non_kwd] => 0.012
                    [total_price] => 0.012
                    [currency_type] => KWD
                    [status] => done
                    [session_id] => 32fb249c6e3b3e317f82fa17000982cb
                    [error_url] => http://localhost:8888/bawes/plugn/api/web/v2/payment/upayment/callback?order_uuid=189RPJ
                    [success_url] => http://localhost:8888/bawes/plugn/api/web/v2/payment/upayment/callback?order_uuid=189RPJ
                    [redirect_url] => http://localhost:8888/bawes/plugn/api/web/v2/payment/upayment/callback?order_uuid=189RPJ?payment_id=100402201000006228&result=CAPTURED&post_date=&tran_id=&ref=&track_id=21924Dy13Y05195552222295bx4030201599944S&auth=&order_id=Lq22u273r72WL2776a6uF4M145l5i21011AFv44062U8724d3622&requested_order_id=189RPJ&refund_order_id=Lq22u273r72WL2776a6uF4M145l5i21011AFv44062U8724d3622&payment_type=knet&invoice_id=5960936&transaction_date=2024-01-22 11:01:12&receipt_id=Lq22u273r72WL2776a6uF4M145l5i21011AFv44062U8724d3622
                    [notify_url] => http://localhost:8888/bawes/plugn/api/web/v2/payment/upayment/callback?order_uuid=189RPJ
                    [notify_url_called] => 1
                    [notify_url_response] => {"success":false,"message":"Failed to connect to localhost port 8888: Connection refused"}
                    [whitelabled] =>
                    [customer_id] =>
                    [customer_unique_id] => 234234234
                    [merchant_requested_order_id] => 189RPJ
                    [extra_merchants_data] =>
                    [is_paid_from_knet] => 1
                    [is_paid_from_cc] =>
                    [is_from_nbk] =>
                    [customer_extra_data] =>
                    [created_at] => 2024-01-22 11:12:48
                )

        )

)*/
    }

    /**
     * callback from gateway
     */
    public function actionCallback()
    {
        $order_id = Yii::$app->request->get('order_uuid');
        $track_id = Yii::$app->request->get('track_id');
        $status = Yii::$app->request->get('result');
        $order_uuid = Yii::$app->request->get('requested_order_id');

        $payment_id = "";
        $pos = strpos($order_id, "?payment_id");
        if ($pos !== false)
        {
            $payment_id = substr($order_id, $pos + strlen("?payment_id") + 1);
            $order_id = (int)substr($order_id, 0, $pos);
        }

        $order = $this->findOrder($order_uuid);

        $response = $this->getStatus($order, $track_id);

        if($response && $response['status'] != "1") {
            echo "wrong track id?"; die();
        }

        //$refid = $this->request->get['ref'];
        //$api_key = Setting::getConfig($order->restaurant_uuid, "Upayment", 'payment_upayment_api_key');

        //add payment entry for debug

        $paymentMethod = PaymentMethod::findOne(['payment_method_code' => PaymentMethod::CODE_UPAYMENT]);

        $order->payment_method_id = $paymentMethod->payment_method_id;
        $order->payment_method_name = $paymentMethod->payment_method_name;
        $order->payment_method_name_ar = $paymentMethod->payment_method_name_ar;

        $payment = new \api\models\Payment;
        $payment->restaurant_uuid = $order->restaurant_uuid;
        $payment->customer_id = $order->customer? $order->customer->customer_id: null; //customer id
        $payment->order_uuid = $order->order_uuid;
        $payment->payment_amount_charged = $order->total;
        //$payment->is_sandbox = $order->restaurant->is_sandbox;
        $payment->response_message = $response['message'];
        //todo: need when free customer can use $payment->payment_gateway_fee = $paymentDetail['fee'] / pow(10, $order->currency->decimal_place);//in halals
        //$payment->payment_gateway_order_id = $id;
        $payment->payment_gateway_payment_id = $response['data']['transaction']['order_id'];
        $payment->received_callback = true;

        // Net amount after deducting gateway fee
        $payment->payment_net_amount = $payment->payment_amount_charged - $payment->payment_gateway_fee;

        if ($order->restaurant->platform_fee)
            $payment->plugn_fee = ($payment->payment_amount_charged * $order->restaurant->platform_fee / 100) - $payment->payment_gateway_fee;
        else
            $payment->plugn_fee = 0;

        if ($status == "CAPTURED" || $status == "SUCCESS") {
            $payment->payment_current_status = "CAPTURED";
        } else {
            $payment->payment_current_status = $status;
        }

        $payment->save(false);

        $order->payment_uuid = $payment->payment_uuid;
        $order->save(false);

        $error = null;

        /*if ($status == "CAPTURED" || $status == "SUCCESS") {
            $error = 'UPayment payment is successful but amount does not match paid, possible tampering. #' . $id;
        } else if (empty($paymentDetail['amount']) || $paymentDetail['amount'] != $order_amount) {
            $error = 'UPayment Payment Verification Failed: '. $message;
        }

        /*if (!$payment) {
            Yii::error('Moyasar payment is successful but payment_uuid does not match, possible tampering. #' . $id);
            return false;
        }*/

        //Yii::$app->currency->format($payment['total'], $payment['currency_code'], $payment['currency_value'], false)*100;

        $url = null;

        if ($status == "CAPTURED" || $status == "SUCCESS") {

            //payment was made successfully

            Payment::onPaymentCaptured($payment);

            $url = $this->buildRestaurantReturnUrl($payment->restaurant, 'payment-success/' . $payment->order_uuid . '/' . $payment->payment_uuid);

        } else {
            //notify tech team + vendor

            //Yii::error($error);

            Payment::notifyTapError($payment, $status);

            $url = $this->buildRestaurantReturnUrl($payment->restaurant, 'payment-failed/' . $payment->order_uuid);
        }

        return Yii::$app->getResponse()->redirect($url)->send(301);
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
