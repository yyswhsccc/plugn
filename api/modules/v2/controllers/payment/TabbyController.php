<?php

namespace api\modules\v2\controllers\payment;

use common\models\OrderHistory;
use Yii;
use api\models\Order;
use api\modules\v2\controllers\BaseController;
use common\models\PaymentMethod;
use common\models\Setting;
use common\models\Tabby;
use common\models\TabbyTransaction;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class TabbyController extends BaseController
{
    var $code = 'tabby';

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
        $order_uuid = Yii::$app->request->get ('order_uuid');

        $order = $this->findOrder($order_uuid);

        $tabby = new Tabby;
        return $tabby->getCheckoutData($order);
    }


    public function actionCreate() {
        $order_uuid = Yii::$app->request->get("order_uuid");
        $payment_id = Yii::$app->request->get("payment_id");

        $order = $this->findOrder($order_uuid);

        $json = array();

        $tabby = new Tabby();

        // use only for current payment method
        //if (preg_match('#^' . $this->code . '#', $this->session->data['payment_method']['code'])) {

            $res = $tabby->execute("GET", $payment_id, [], $order->restaurant_uuid);

            if (empty($res) || $res->status == 'error') {
                if (!empty($res->errors)) {
                    $json['error'] = '';
                    foreach ($res->errors as $error) {
                        $json['error'] .= $error['name'] . ': ' . $error['message'] . "\r\n";
                    }
                } else {
                    $json['error'] = "Transaction not found.";
                }

                $json['redirect'] = $order->restaurant->restaurant_domain . '/confirm/' . $order->order_uuid;

            } elseif ($res->status !== 'CREATED') {
                $json['error'] = "Transaction state is not valid.";
            } elseif (
                $res->amount != number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place) ||
                $res->currency != $order['currency_code']
            ) {
                $json['error'] = "Transaction amount or currency issue.";
            } else {
                // assign transaction to order
                $tabby->addTransaction([
                    'order_uuid'          => $order_uuid,
                    'transaction_id'    => $payment_id,
                    'body'              => json_encode($res),
                    'status'            => strtolower($res->status),
                    'source'            => 'checkout'
                ]);

            }

            $json['success'] = !array_key_exists('error', $json);
        //}

        return $json;
    }

    public function actionCapture() {
        $json = array();

        $transaction_id = Yii::$app->request->get("transaction_id");
        $amount = Yii::$app->request->getBodyParam('amount');

        $tabby = new Tabby();
        $result = $tabby->capture($transaction_id, $amount);

        if ($result['error']) {
            $json['success'] = false;
            $json['error'] = $result['message'];
        } else {
            $json['success'] = "Transaction updated successfully!";
            $json['error'] = false;
        }

        return $json;
    }

    public function actionRefund() {
        $json = array();

        $transaction_id = Yii::$app->request->get("transaction_id");
        $amount = Yii::$app->request->getBodyParam('amount');

        $tabby = new Tabby();
        $result = $tabby->refund($transaction_id, $amount);

        if ($result['error']) {
            $json['success'] = false;
            $json['error'] = $result['message'];
        } else {
            $json['success'] = "Transaction updated successfully!";
            $json['error'] = false;
        }

        return $json;
    }

    public function actionClose() {
        $json = array();

        $transaction_id = Yii::$app->request->get("transaction_id");

        $tabby = new Tabby();
        $result = $tabby->close($transaction_id);

        if ($result['error']) {
            $json['success'] = false;
            $json['error'] = $result['message'];
        } else {
            $json['success'] = "Transaction updated successfully!";
            $json['error'] = false;
        }

        return $json;
    }

    public function actionConfirm() {

        $payment_id = Yii::$app->request->get("payment_id");
        $order_uuid = Yii::$app->request->get("order_uuid");
        $redirect = Yii::$app->request->get("redirect");

        $order = $this->findOrder($order_uuid);

        $payment_tabby_capture_on = Setting::getConfig($order->restaurant_uuid,
            PaymentMethod::CODE_TABBY,
            'payment_tabby_capture_on');

        $tabby = new Tabby;

        $json = array();

        // use only for current payment method
        //if (preg_match('#^' . $this->code . '#', $this->session->data['payment_method']['code'])) {

            //$this->model_extension_module_tabby_lock->lock($this->session->data['order_uuid']);

            $res = $tabby->execute("GET", $payment_id, [], $order->restaurant_uuid);

            if (empty($res) || $res->status == 'REJECTED' || $res->status == 'error') {
                if (!empty($res->errors)) {
                    $json['error'] = '';
                    foreach ($res->errors as $error) {
                        $json['error'] .= $error['name'] . ': ' . $error['message'] . "\r\n";
                    }
                } else {
                    $json['error'] = "Transaction not found.";
                }

                /*$payment_methods = $this->session->data['payment_methods'];
                $this->session->data['payment_methods'] = [];
                $payment_method = $this->session->data['payment_method']['code'];
                foreach ($payment_methods as $method => $value) {
                    if ($method != $payment_method) {
                        $this->session->data['payment_methods'][$method] = $value;
                    }
                }
                $this->session->data['payment_methods_not_unset'] = true;*/

                $json['redirect'] = "confirm";

            } elseif ($res->status !== 'AUTHORIZED' && $res->status != 'CLOSED') {
                $json['error'] = "Transaction state is not valid.";
                //$this->formatAmount($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false))
            } elseif ($res->amount != number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place) || $res->currency != $order['currency_code']) {
                $json['error'] = "Transaction amount or currency issue.";
            } else {
                // post order if not posted

                if (in_array($order['order_status'], [Order::STATUS_ABANDONED_CHECKOUT, Order::STATUS_DRAFT])) {

                    $payment_tabby_order_status = Setting::getConfig($order->restaurant_uuid,
                        PaymentMethod::CODE_TABBY,
                        'payment_tabby_order_status');

                    if (!$payment_tabby_order_status) {
                        $payment_tabby_order_status = Order::STATUS_PENDING;
                    }

                    OrderHistory::addOrderHistory($order->order_uuid, $payment_tabby_order_status,
                        sprintf("Authorization transaction #%s. Amount %s %s", $payment_id, $res->amount, $res->currency));

                    // assign transaction to order
                    $transaction_status = $tabby->getTransactionStatus($payment_id);

                    if ($transaction_status == 'created') {

                        $tabby->updateTransaction([
                            'order_uuid' => $order_uuid,
                            'body'     => json_encode($res),
                            'status'   => 'authorized',
                            'source'   => 'checkout'
                        ]);
                    }

                    // capture only authorized payments
                    if (
                        $payment_tabby_capture_on == "order_placed" &&
                        $res->status == 'AUTHORIZED'
                    ) {
                        $capture_exec = $tabby->capture($payment_id, $res->amount, $order->restaurant_uuid);

                        if (
                            array_key_exists('error', $capture_exec) &&
                            !empty($capture_exec['error'])
                        ) {
                            $json['error'] = $capture_exec['error'];
                        }
                    }
                }

                $json['redirect'] = "confirm";
            }

            // unlock order
            //$this->model_extension_module_tabby_lock->unlock($this->session->data['order_uuid']);

            $json['success'] = !array_key_exists('error', $json);
        //}

        // direct call with redirect=1
        if ($redirect == 1) {
            if ($json['success']) {
                $url = $this->buildRestaurantReturnUrl($order->restaurant, 'payment-success/' . $order->order_uuid);
            } else {
                $url = $this->buildRestaurantReturnUrl($order->restaurant, 'payment-failed/' . $order->order_uuid);
            }

            return Yii::$app->getResponse()->redirect($url)->send(301);
        }

        return $json;
    }

    public function actionCancel() {
        $order_uuid = Yii::$app->request->get("order_uuid");

        $order = $this->findOrder($order_uuid);

        $url = $order->restaurant->restaurant_domain . '/confirm/' . $order->order_uuid;

        return Yii::$app->getResponse()->redirect($url)->send(301);
    }

    public function actionFailure() {
        $order_uuid = Yii::$app->request->get("order_uuid");

        $order = $this->findOrder($order_uuid);

        $url = $order->restaurant->restaurant_domain . '/confirm/' . $order->order_uuid;

        return Yii::$app->getResponse()->redirect($url)->send(301);
    }

    public function actionCallback() {

        $callback_json = @file_get_contents('php://input');

        $webhook = json_decode($callback_json);

        TabbyTransaction::ddlog('info', 'webhook received', null, array(
            'data'      => $webhook
        ));

        if (isset($webhook->id)) {

            $tabby = new Tabby;

            $order_uuid = $webhook->order->reference_id;
            // lock order

            //$this->model_extension_module_tabby_lock->lock($order_uuid);

            $transaction = $tabby->getTabbyTransaction($webhook->id);

            $transaction_id = $webhook->id;
            $status = strtoupper($transaction->status);
            $amount = $transaction->amount;//$this->formatAmount(
            $currency = $transaction->currency;
            $sid = json_decode($webhook->description);

            @file_put_contents("test/" . substr($transaction_id, 0, 8) . "_" . date("H-i-s") . "_callback.json", $callback_json);

            $order = $this->findOrder($order_uuid);

            $transaction_status = $tabby->getTransactionStatus($transaction_id);

            //$this->formatAmount($this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false))
            if (!empty($order) && in_array($status, ['AUTHORIZED', 'CLOSED']) &&
                $amount == number_format($order->total_price * $order->currency_rate, $order->currency->decimal_place)  &&
                $currency == $order['currency_code']
            ) {
                // post order
                if (
                    in_array($order['order_status'], array(
                            Order::STATUS_DRAFT,
                            Order::STATUS_CANCELED
                        )
                    )
                ) {
                    if ($transaction_status == 'created') {

                        $payment_tabby_order_status = Setting::getConfig($order->restaurant_uuid,
                            PaymentMethod::CODE_TABBY,
                            'payment_tabby_order_status');

                        if (!$payment_tabby_order_status) {
                            $payment_tabby_order_status = Order::STATUS_PENDING;
                        }

                        OrderHistory::addOrderHistory($order->order_uuid, $payment_tabby_order_status,
                            sprintf("Authorization webhook #%s. Amount %s %s", $transaction_id, $amount, $currency));

                        // assign transaction to order
                        $tt = new TabbyTransaction();
                        $tt->order_uuid = $order_uuid;
                        $tt->transaction_id = $transaction_id;
                        $tt->body           = json_encode($transaction);
                        $tt->status         = $status;
                        $tt->source         = 'webhook';

                        if (!$tt->save()) {
                            Yii::error($tt->errors);
                            Yii::error([
                                "order_uuid" => $order_uuid,
                                "transaction_id" => $transaction_id,
                                "body"           => json_encode($transaction),
                                "status"         => $status,
                                "source"         => 'webhook',
                            ]);
                            print_r($tt->errors);
                            die();
                        }

                        //$this->clear_customer_session($order['customer_id'], $sid->sid);
                    }

                    $payment_tabby_capture_on = Setting::getConfig($order->restaurant_uuid,
                        PaymentMethod::CODE_TABBY,
                        'payment_tabby_capture_on');

                    if ($payment_tabby_capture_on == "order_placed") {
                        $capture_exec = $tabby->capture($transaction_id, $amount, $order->restaurant_uuid);
                    }
                }
            } elseif (!empty($order) && in_array($status, ['EXPIRED', 'REJECTED'])) {

                if ($order['order_status'] == 0) {

                    /*$payment_tabby_cancel_status_id = Setting::getConfig($order->restaurant_uuid,
                        PaymentMethod::CODE_TABBY,
                        'payment_tabby_cancel_status_id');*/

                    OrderHistory::addOrderHistory($order->order_uuid, \common\models\Order::STATUS_CANCELED,
                        sprintf("Webhook notification: Payment \"#%s\" is \"%s\".", $transaction_id, $status));
                };
            }

            // unlock order
            //$this->model_extension_module_tabby_lock->unlock($order_uuid);
        }
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
