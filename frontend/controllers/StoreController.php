<?php

namespace frontend\controllers;

use Yii;
use frontend\models\Restaurant;
use common\models\RestaurantPaymentMethod;
use common\models\Order;
use common\models\Item;
use yii\db\Expression;
use common\models\Customer;
use common\models\RestaurantTheme;
use common\models\PaymentGatewayQueue;
use common\models\TapQueue;
use common\models\AgentAssignment;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\components\FileUploader;

/**
 * StoreController implements the CRUD actions for Restaurant model.
 */
class StoreController extends Controller
{

    public $enableCsrfValidation = false;

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className (),
                'actions' => [
                    'delete' => ['POST'],
                    'delete-logo-image' => ['POST'],
                    'delete-thumbnail-image' => ['POST'],
                ],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::className (),
                'rules' => [
                    [//allow authenticated users only
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return mixed
     */
    public function actionReports($storeUuid)
    {

        $model = $this->findModel ($storeUuid);

        return $this->render ('reports', [
            'model' => $model
        ]);
    }

    /**
     * @return mixed
     */
    public function actionStatistics($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        $revenue_generated_chart_data = [];
        $months = [];
        
        $cacheDependency = Yii::createObject ([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT ' . date ('Y-m-d') . ', COUNT(*) FROM `order` WHERE restaurant_uuid="' . $storeUuid . '"',

            // we SELECT agent_id as well to make sure every cached sql statement is unique to this agent
            // don't want agents viewing the cached content of another agent
            // SUM of agent_status is to bust the cache when status changes
        ]);

        $customerCacheDependency = Yii::createObject ([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT ' . date ('Y-m-d') . ', COUNT(*) FROM customer WHERE restaurant_uuid="' . $model->restaurant_uuid . '"',

            // we SELECT agent_id as well to make sure every cached sql statement is unique to this agent
            // don't want agents viewing the cached content of another agent
            // SUM of agent_status is to bust the cache when status changes
        ]);

        $cacheDuration = 60 * 60 * 24; //24 hour then delete from cache
        
        $revenue_generated_last_five_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated (
                $model->restaurant_uuid,
                date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 5, 1)),
                date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 4, 0))
            );
        }, $cacheDuration, $cacheDependency);
        
        $lastFiveMonths = date ('M', strtotime ('-5 months'));

        array_push ($revenue_generated_chart_data, $revenue_generated_last_five_months_month ? (double)$revenue_generated_last_five_months_month : 0);

        // array_push($months, $lastFiveMonths);

        $revenue_generated_last_four_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 4, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 3, 0)));
        }, $cacheDuration, $cacheDependency);
        
        $lastFoureMonths = date ('M', strtotime ('-4 months'));

        array_push ($revenue_generated_chart_data, $revenue_generated_last_four_months_month ? (double)$revenue_generated_last_four_months_month : 0);

        // array_push($months, $lastFoureMonths);

        $revenue_generated_last_three_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 3, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 2, 0)));
        }, $cacheDuration, $cacheDependency);

        $lastThreeMonths = date ('M', strtotime ('-3 months'));

        array_push ($revenue_generated_chart_data, $revenue_generated_last_three_months_month ? (double)$revenue_generated_last_three_months_month : 0);

        // array_push($months, $lastThreeMonths);

        $revenue_generated_last_two_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 2, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 1, 0)));
        }, $cacheDuration, $cacheDependency);

        $lastTwoMonths = date ('M', strtotime ('-2 months'));

        array_push ($revenue_generated_chart_data, $revenue_generated_last_two_months_month ? (double)$revenue_generated_last_two_months_month : 0);

        // array_push($months, $lastTwoMonths);

        $revenue_generated_last_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated ($model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 1, 1)),
                date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m"), 0)));
        }, $cacheDuration, $cacheDependency);

        $lastMonth = date ('M', strtotime ('-1 months'));
        array_push ($revenue_generated_chart_data, $revenue_generated_last_month ? (double)$revenue_generated_last_month : 0);
        // array_push($months, $lastMonth);


        $revenue_generated_current_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->revenueGenerated ($model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m"), 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") + 1, 0)));
        }, $cacheDuration, $cacheDependency);

        $currentMonth = date ('M');

        array_push ($revenue_generated_chart_data, $revenue_generated_current_month ? (double)$revenue_generated_current_month : 0);

        // array_push($months, $currentMonth);

        $order_recevied_chart_data = [];

        $order_recevied_last_five_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 5, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 4, 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_last_five_months_month ? $order_recevied_last_five_months_month : 0);

        $order_recevied_last_four_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 4, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 3, 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_last_four_months_month ? $order_recevied_last_four_months_month : 0);


        $order_recevied_last_three_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 3, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 2, 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_last_three_months_month ? $order_recevied_last_three_months_month : 0);


        $order_recevied_last_two_months_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 2, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 1, 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_last_two_months_month ? $order_recevied_last_two_months_month : 0);

        $order_recevied_last_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 1, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m"), 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_last_month ? $order_recevied_last_month : 0);


        $order_recevied_current_month = Order::getDb ()->cache (function ($db) use ($model) {
            return Order::find ()
            ->ordersReceived (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m"), 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") + 1, 0)));
        }, $cacheDuration, $cacheDependency);

        array_push ($order_recevied_chart_data, $order_recevied_current_month ? $order_recevied_current_month : 0);


        $customer_gained_chart_data = [];

        $customer_gained_last_five_months_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 5, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 4, 0)));
        }, $cacheDuration, $customerCacheDependency);

        array_push ($customer_gained_chart_data, $customer_gained_last_five_months_month ? $customer_gained_last_five_months_month : 0);


        $customer_gained_last_four_months_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 4, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 3, 0)));
        }, $cacheDuration, $customerCacheDependency);

        array_push ($customer_gained_chart_data, $customer_gained_last_four_months_month ? $customer_gained_last_four_months_month : 0);


        $customer_gained_last_three_months_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 3, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 2, 0)));
        }, $cacheDuration, $customerCacheDependency);

        array_push ($customer_gained_chart_data, $customer_gained_last_three_months_month ? $customer_gained_last_three_months_month : 0);


        $customer_gained_last_two_months_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 2, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") - 1, 0)));
        }, $cacheDuration, $customerCacheDependency);

        array_push ($customer_gained_chart_data, $customer_gained_last_two_months_month ? $customer_gained_last_two_months_month : 0);


        $customer_gained_last_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m") - 1, 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m"), 0)));
        }, $cacheDuration, $customerCacheDependency);

        array_push ($customer_gained_chart_data, $customer_gained_last_month ? $customer_gained_last_month : 0);


        $customer_gained_current_month = Customer::getDb ()->cache (function ($db) use ($model) {
            return Customer::find ()
            ->customerGained (
                $model->restaurant_uuid, date ("Y-m-d H:i:s", mktime (0, 0, 0, date ("m"), 1)), date ("Y-m-d H:i:s", mktime (23, 59, 59, date ("m") + 1, 0)));
        }, $cacheDuration, $customerCacheDependency);
        
        array_push ($customer_gained_chart_data, $customer_gained_current_month ? $customer_gained_current_month : 0);


        $most_selling_items_chart_data = [];
        $number_of_sold_items_chart_data = [];

        $sold_items = \common\models\Item::find ()
            ->andWhere (['item.restaurant_uuid' => $model->restaurant_uuid])
            ->orderBy (['unit_sold' => SORT_DESC])
            ->limit (5)
            ->all ();

        foreach ($sold_items as $key => $item) {
            array_push ($most_selling_items_chart_data, $item->item_name);
            array_push ($number_of_sold_items_chart_data, $item->unit_sold ? $item->unit_sold : 0);
        }


        for ($i = 5; $i >= 0; $i--) {
            $months[] = date ("F", strtotime (date ('Y-m-01') . " -$i months"));
        }


        return $this->render ('statistics', [
            'model' => $model,
            'months' => $months,
            'most_selling_items_chart_data' => $most_selling_items_chart_data,
            'customer_gained_chart_data' => $customer_gained_chart_data,
            'number_of_sold_items_chart_data' => $number_of_sold_items_chart_data,
            'revenue_generated_chart_data' => $revenue_generated_chart_data,
            'order_recevied_chart_data' => $order_recevied_chart_data
        ]);
    }

    /**
     * Lists all Restaurant models.
     * @return mixed
     */
    public function actionIndex($storeUuid)
    {

        $model = $this->findModel ($storeUuid);


        return $this->render ('view', [
            'model' => $model
        ]);
    }

    /**
     * Disable payment method
     * @return mixed
     */
    public function actionDisablePaymentMethod($storeUuid, $paymentMethodId)
    {

        $model = $this->findModel ($storeUuid);
        RestaurantPaymentMethod::deleteAll (['restaurant_uuid' => $model->restaurant_uuid, 'payment_method_id' => $paymentMethodId]);
        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     * Enable payment method
     * @return mixed
     */
    public function actionEnablePaymentMethod($storeUuid, $paymentMethodId)
    {

        $model = $this->findModel ($storeUuid);

        $restaurant_payment_method = new RestaurantPaymentMethod();
        $restaurant_payment_method->payment_method_id = $paymentMethodId;
        $restaurant_payment_method->restaurant_uuid = $model->restaurant_uuid;
        $restaurant_payment_method->save (false);

        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     * View payment settings page
     * @return mixed
     */
    public function actionViewPaymentMethods($storeUuid)
    {

        $model = $this->findModel ($storeUuid);
        $isCashOnDeliveryEnabled = $model->getPaymentMethods ()->where (['payment_method_id' => 3])->exists ();
        $isOnlinePaymentEnabled = $model->getPaymentMethods ()->where (['payment_method_id' => 1])->exists ();

        return $this->render ('payment-methods', [
            'model' => $model,
            'isCashOnDeliveryEnabled' => $isCashOnDeliveryEnabled,
            'isOnlinePaymentEnabled' => $isOnlinePaymentEnabled
        ]);
    }


    /**
     * Create paymentGateway account
     * @param type $id
     * @return type $paymentGateway
     */
    public function actionCreatePaymentGatewayAccount($id, $paymentGateway)
    {

        $model = $this->findModel ($id);

        if ($model->currency->code == 'BHD' && $paymentGateway == 'myfatoorah')
            return $this->redirect (['setup-online-payments', 'storeUuid' => $model->restaurant_uuid]);

        if ($model->currency->code != 'KWD' && $model->currency->code != 'OMR')
            $model->business_type = 'corp';


        if ($paymentGateway == 'tap')
            $model->setScenario (Restaurant::SCENARIO_CREATE_TAP_ACCOUNT);
        else if ($paymentGateway == 'myfatoorah')
            $model->setScenario (Restaurant::SCENARIO_CREATE_MYFATOORAH_ACCOUNT);


        if (($model->is_myfatoorah_enable && $paymentGateway == 'myfatoorah') || ($model->is_tap_enable && $paymentGateway == 'tap'))
            return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);


        if (Yii::$app->request->isPost && $model->load (Yii::$app->request->post ()) && $model->save ()) {

            $model->setScenario (Restaurant::SCENARIO_UPLOAD_STORE_DOCUMENT);


            // initialize FileUploader
            $FileUploader = new FileUploader('identification_file_front_side', array(
                'limit' => null,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $owner_identification_file_front_side = $FileUploader->getFileList ();

            // initialize FileUploader
            $FileUploader = new FileUploader('identification_file_back_side', array(
                'limit' => null,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));


            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $owner_identification_file_back_side = $FileUploader->getFileList ();


            // initialize FileUploader
            $FileUploader = new FileUploader('commercial_license', array(
                'limit' => null,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $restaurant_commercial_license_file = $FileUploader->getFileList ();

            // initialize FileUploader
            $FileUploader = new FileUploader('authorized_signature', array(
                'limit' => null,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $restaurant_authorized_signature_file = $FileUploader->getFileList ();

            if (sizeof ($restaurant_commercial_license_file) > 0)
                $model->commercial_license_file = str_replace ('uploads/', '', $restaurant_commercial_license_file[0]['file']); //Commercial License

            if (sizeof ($restaurant_authorized_signature_file) > 0)
                $model->authorized_signature_file = str_replace ('uploads/', '', $restaurant_authorized_signature_file[0]['file']);  //Authorized signature

            if (sizeof ($owner_identification_file_front_side) > 0)
                $model->identification_file_front_side = str_replace ('uploads/', '', $owner_identification_file_front_side[0]['file']); //Owner's civil id front side

            if (sizeof ($owner_identification_file_back_side) > 0)
                $model->identification_file_back_side = str_replace ('uploads/', '', $owner_identification_file_back_side[0]['file']); //Owner's civil id back side

            $model->is_myfatoorah_enable = 0;
            $model->is_tap_enable = 0;
            
            //make sure we delete all payment methods
            foreach ($model->getRestaurantPaymentMethods ()->all () as $restaurant_payment_method) {
                if ($restaurant_payment_method->payment_method_id != 3)
                    $restaurant_payment_method->delete ();
            }


            if ($model->validate () && $model->save ()) {


                // if ($paymentGateway == 'tap ? !$model->is_myfatoorah_enable : ($paymentGateway == 'my')) {
                $payment_gateway_queue = new PaymentGatewayQueue;
                $payment_gateway_queue->queue_status = PaymentGatewayQueue::QUEUE_STATUS_PENDING;
                $payment_gateway_queue->payment_gateway = $paymentGateway;
                $payment_gateway_queue->restaurant_uuid = $model->restaurant_uuid;
                if ($payment_gateway_queue->save ()) {
                    $model->payment_gateway_queue_id = $payment_gateway_queue->payment_gateway_queue_id;
                    $model->save (false);
                }
                // }

                return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
            } else {
                Yii::$app->session->setFlash ('error', print_r ($model->errors, true));
            }
        }


        return $this->render ($paymentGateway == 'tap' ? 'create-tap-account' : 'create-myfatoorah-account', [
            'model' => $model
        ]);
    }

    /**
     *  Enable OnlinePayment on delivery
     */
    public function actionEnableOnlinePayment($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        if (!$model->getRestaurantPaymentMethods ()->where (['payment_method_id' => 1])->exists ()) {

            $payments_method = new RestaurantPaymentMethod();
            $payments_method->payment_method_id = 1; //K-net
            $payments_method->restaurant_uuid = $model->restaurant_uuid;
            $payments_method->save (false);
        }

        if (!$model->getRestaurantPaymentMethods ()->where (['payment_method_id' => 2])->exists ()) {

            $payments_method = new RestaurantPaymentMethod();
            $payments_method->payment_method_id = 2; //Credit Card
            $payments_method->restaurant_uuid = $model->restaurant_uuid;
            $payments_method->save (false);
        }


        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     *  Disable OnlinePayment on delivery
     */
    public function actionDisableOnlinePayment($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        $online_payments = $model->getRestaurantPaymentMethods ()->where (['<>', 'payment_method_id', 3])->all ();

        foreach ($online_payments as $key => $online_payment) {
            $online_payment->delete ();
        }

        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     *  Enable Cash on delivery
     */
    public function actionEnableCod($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        if (!$model->getRestaurantPaymentMethods ()->where (['payment_method_id' => 3])->exists ()) {

            $payments_method = new RestaurantPaymentMethod();
            $payments_method->payment_method_id = 3; //Cash
            $payments_method->restaurant_uuid = $model->restaurant_uuid;
            $payments_method->save ();
        }
        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     *  Disable Cash on delivery
     */
    public function actionDisableCod($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        if ($cashOnDelivery = $model->getRestaurantPaymentMethods ()->where (['payment_method_id' => 3])->one ())
            $cashOnDelivery->delete ();

        return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
    }

    /**
     * View Design & layout page
     * @return mixed
     */
    public function actionViewDesignLayout($storeUuid)
    {

        $model = $this->findModel ($storeUuid);

        $store_theme = RestaurantTheme::findOne ($model->restaurant_uuid);

        return $this->render ('design-layout/view-design-layout', [
            'model' => $model,
            'store_theme' => $store_theme
        ]);
    }

    /**
     * Updates an existing Restaurant model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel ($id);

        if (Yii::$app->request->isPost && $model->load (Yii::$app->request->post ()) && $model->save ()) {

            return $this->render ('update', [
                'model' => $model
            ]);
        }

        return $this->render ('update', [
            'model' => $model
        ]);
    }

    public function actionSetupOnlinePayments($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        return $this->render ('setup-online-payments', [
            'model' => $model
        ]);
    }

    public function actionSwitchToMyfatoorah($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        if ($model->currency->code == 'BHD') {
            Yii::$app->session->setFlash ('error', 'Contact us if you want to enable this option');
            return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);
        }


        if ($model->supplierCode) {
            $model->is_tap_enable = 0;
            $model->is_myfatoorah_enable = 1;
            $model->save (false);


            foreach ($model->getRestaurantPaymentMethods ()->all () as $restaurant_payment_method) {
                if ($restaurant_payment_method->payment_method_id != 3)
                    $restaurant_payment_method->delete ();
            }

            return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);

        }

        return $this->redirect (['create-payment-gateway-account', 'paymentGateway' => 'myfatoorah', 'id' => $model->restaurant_uuid]);

    }

    public function actionSwitchToTap($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        if ($model->live_api_key && $model->test_api_key) {
            $model->is_tap_enable = 1;
            $model->is_myfatoorah_enable = 0;
            $model->save (false);

            foreach ($model->getRestaurantPaymentMethods ()->all () as $restaurant_payment_method) {
                if ($restaurant_payment_method->payment_method_id != 3)
                    $restaurant_payment_method->delete ();
            }

            return $this->redirect (['view-payment-methods', 'storeUuid' => $model->restaurant_uuid]);

        }

        return $this->redirect (['create-payment-gateway-account', 'paymentGateway' => 'tap', 'id' => $model->restaurant_uuid]);

    }


    public function actionViewTapRates($storeUuid)
    {

        $model = $this->findModel ($storeUuid);

        return $this->render ('view-tap-rates', [
            'model' => $model
        ]);
    }


    public function actionViewMyfatoorahRates($storeUuid)
    {

        $model = $this->findModel ($storeUuid);

        if ($model->currency->code == 'BHD')
            return $this->redirect (['setup-online-payments', 'storeUuid' => $model->restaurant_uuid]);


        return $this->render ('view-myfatoorah-rates', [
            'model' => $model
        ]);


        return $this->redirect (['setup-online-payments', 'storeUuid' => $model->restaurant_uuid]);

    }

    /**
     * Updates an existing Analytics integration.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdateAnalyticsIntegration($id)
    {
        $model = $this->findModel ($id);

        $model->setScenario(Restaurant::SCENARIO_UPDATE_ANALYTICS);

        if (Yii::$app->request->isPost && $model->load (Yii::$app->request->post ())) {

            $model->sitemap_require_update = 1;

            if ($model->save ())
                return $this->redirect (['update-analytics-integration', 'id' => $id]);
        }

        return $this->render ('integration/analytics/update-analytics-integration', [
            'model' => $model
        ]);
    }

    /**
     * Updates an existing Delivery integration.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdateDeliveryIntegration($id)
    {
        $model = $this->findModel ($id);

        $model->setScenario(Restaurant::SCENARIO_UPDATE_DELIVERY);

        if (Yii::$app->request->isPost && $model->load (Yii::$app->request->post ()) && $model->save ()) {
            return $this->redirect (['update-delivery-integration', 'id' => $id]);
        }

        return $this->render ('integration/delivery/update-delivery-integration', [
            'model' => $model
        ]);
    }

    /**
     * Updates an existing payment method.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdateDesignLayout($id)
    {
        $model = $this->findModel ($id);

        $model->setScenario(Restaurant::SCENARIO_UPDATE_DESIGN_LAYOUT);

        $store_theme = RestaurantTheme::findOne ($model->restaurant_uuid);

        if (Yii::$app->request->isPost && $model->load (Yii::$app->request->post ()) && $store_theme->load (Yii::$app->request->post ())) {

            if (!$model->phone_number)
                $model->phone_number_display = Restaurant::PHONE_NUMBER_DISPLAY_DONT_SHOW_PHONE_NUMBER;

            $FileUploader = new FileUploader('restaurant_logo', array(
                'limit' => 1,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $logo = $FileUploader->getFileList ();

            if ($logo)
                $model->restaurant_logo = $logo[0]['file'];


            $FileUploader = new FileUploader('restaurant_thumbnail_image', array(
                'limit' => 1,
                'maxSize' => 30,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload ();

            // if uploaded and success
            if ($data['isSuccess'] && count ($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }

            // get the fileList
            $thumbnail_image = $FileUploader->getFileList ();

            if ($thumbnail_image)
                $model->restaurant_thumbnail_image = $thumbnail_image[0]['file'];

            $model->sitemap_require_update = 1;

            if ($model->save () && $store_theme->save ()) {


                if ($thumbnail_image)
                    $model->uploadThumbnailImage ($model->restaurant_thumbnail_image);

                if ($logo)
                    $model->uploadLogo ($model->restaurant_logo);
            }
        }

        return $this->render ('design-layout/update-design-layout', [
            'model' => $model,
            'store_theme' => $store_theme
        ]);
    }

    /**
     * Delete logo image
     * @param type $storeUuid
     * @return boolean
     */
    public function actionDeleteLogoImage($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        $model->setScenario(Restaurant::SCENARIO_UPDATE_LOGO);

        $file_name = Yii::$app->request->getBodyParam ("file");

        if ($model && $model->logo == $file_name) {
            $model->deleteRestaurantLogo ();

            $model->logo = null;
            $model->save (false);

            return true;
        }
        return false;
    }

    /**
     * Delete thumbnail image
     * @param type $storeUuid
     * @return boolean
     */
    public function actionDeleteThumbnailImage($storeUuid)
    {
        $model = $this->findModel ($storeUuid);

        $model->setScenario(Restaurant::SCENARIO_UPDATE_THUMBNAIL);

        $file_name = Yii::$app->request->getBodyParam ("file");

        if ($model && $model->thumbnail_image == $file_name) {
            $model->deleteRestaurantThumbnailImage ();

            $model->thumbnail_image = null;
            $model->save (false);

            return true;
        }
        return false;
    }

    /**
     * Finds the Restaurant model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Restaurant the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount ($id);

        if ($restaurant !== null) {
            if (Yii::$app->user->identity->isOwner ($id))
                return $restaurant;
            else
                throw new \yii\web\BadRequestHttpException('Sorry, you are not allowed to access this page.');
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
