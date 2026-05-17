<?php

namespace frontend\controllers;

use Yii;
use common\models\Item;
use frontend\models\ItemSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\Option;
use common\models\Category;
use common\models\CategoryItem;
use common\models\ExtraOption;
use wbraganca\dynamicform\DynamicFormWidget;
use frontend\base\Model;
use yii\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;
use common\components\FileUploader;
use common\models\Order;

/**
 * ItemController implements the CRUD actions for Item model.
 */
class ItemController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'delete-item-image' => ['POST'],
                ],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
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
     * @param $storeUuid
     */
    public function actionExportToExcel($storeUuid)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $model = Item::find()->where([
            'item.restaurant_uuid' => $restaurant->restaurant_uuid])->all();

        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment;filename=\"item-report.xlsx\"");
        header("Cache-Control: max-age=0");

        \common\components\PhpExcel::export([
            'isMultipleSheet' => false,
            'models' => $model,
            'columns' => [
                [
                    'header' => 'Item name',
                    "format" => "raw",
                    "value" => function($data) {
                      return $data->item_name;
                    }
                ],
                'sku',
                'barcode',
                'stock_qty',
                'unit_sold',
                [
                    'attribute' => 'item_price',
                    "format" => "raw",
                    "value" => function($data) {
                      return Yii::$app->formatter->asCurrency($data->item_price, $data->currency->code, [
                          \NumberFormatter::MAX_FRACTION_DIGITS => $data->currency->decimal_place
                      ]);
                    }
                ]
            ],
        ]);
    }

    /**
     * Lists all Item models.
     * @return mixed
     */
    public function actionIndex($storeUuid)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $searchModel = new ItemSearch();

        $count = $searchModel->search([], $restaurant->restaurant_uuid)->getCount();

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $restaurant->restaurant_uuid);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'count' => $count,
                    'dataProvider' => $dataProvider,
                    'restaurant' => $restaurant
        ]);
    }

    /**
     * Lists all Item models.
     * @return mixed
     */
    public function actionItemsReport($storeUuid)
    {
        $store = Yii::$app->accountManager->getManagedAccount($storeUuid);

        if ($store->load(Yii::$app->request->post())) {

          list($start_date, $end_date) = explode(' - ', $store->export_sold_items_data_in_specific_date_range);

            $searchResult = Item::find()
                    ->joinWith(['orderItems', 'orderItems.order'])
                    ->andWhere([
                        'IN',
                        'order.order_status', [
                            Order::STATUS_ACCEPTED,
                            Order::STATUS_PENDING,
                            Order::STATUS_BEING_PREPARED,
                            Order::STATUS_OUT_FOR_DELIVERY,
                            Order::STATUS_COMPLETE
                        ]
                    ])
                    ->andWhere(['order.restaurant_uuid' => $store->restaurant_uuid])
                    ->andWhere(['between', 'order.order_created_at', $start_date, $end_date])
                    ->all();

            header('Access-Control-Allow-Origin: *');
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment;filename=\"sold-items.xlsx\"");
            header("Cache-Control: max-age=0");


            \common\components\PhpExcel::export([
                'isMultipleSheet' => false,
                'models' => $searchResult,
                'columns' => [
                    'item_name',
                    'sku',
                    'barcode',
                    [
                        'header' => 'Unit sold',
                        'label' => 'Sold items',
                        'format' => 'html',
                        'value' => function ($data)  use ($start_date,$end_date) {
                            $unitSold = $data->getSoldUnitsInSpecifcDate($start_date,$end_date);
                            return $unitSold ? $unitSold : 0;
                        },
                    ],
                ],
            ]);

        }

        return $this->render('items-report', [
                    'store' => $store
        ]);
    }

    /**
     * Lists all Item models.
     * @return mixed
     */
    public function actionInventory($storeUuid)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $searchModel = new ItemSearch();

        $count = $searchModel->searchTrackQuantity([], $restaurant->restaurant_uuid)->getCount();

        $dataProvider = $searchModel->searchTrackQuantity(Yii::$app->request->queryParams, $restaurant->restaurant_uuid);

        // foreach ($dataProvider->query->all() as $key => $item) {
        //     if (isset($_POST[$item->item_uuid])) {
        //         $item->stock_qty = $_POST['Item']['stock_qty'];
        //         $item->save(false);
        //     }
        // }

        return $this->render('inventory', [
                    'searchModel' => $searchModel,
                    'count' => $count,
                    'dataProvider' => $dataProvider,
                    'restaurant' => $restaurant
        ]);
    }



    /**
     * Update Stock Qty
     * @param type $storeUuid
     * @param type $itemUuid
     * @return boolean
     */
    public function actionUpdateStockQty($itemUuid, $storeUuid){

        $model = $this->findModel($itemUuid, $storeUuid);

        $model->setScenario(Item::SCENARIO_UPDATE_STOCK);

        if($model->load(Yii::$app->request->post()) && $model->save(false) ){
          return $this->redirect(['inventory', 'storeUuid' => $storeUuid]);
        }

        return $this->redirect(['inventory', 'storeUuid' => $storeUuid]);

    }

    /**
     * Delete item image
     * @param type $storeUuid
     * @param type $itemUuid
     * @return boolean
     */
    public function actionDeleteItemImage($storeUuid, $itemUuid)
    {
        $model = $this->findModel($itemUuid, $storeUuid);


        $file_name = Yii::$app->request->getBodyParam("file");

        if ($model && $file_name) {
            $item_image = \common\models\ItemImage::find()->where(['item_image.item_uuid' => $itemUuid, 'item_image.product_file_name' => $file_name])->one();
            if($item_image)
                $item_image->delete();

            return true;
        }
        return false;
    }

    /**
     * Creates a new Item model.
     * If creation is successful, the browser will be redirected to the 'index' page.
     * @return mixed
     */
    public function actionCreate($storeUuid)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $modelItem = new Item;
        $modelItem->restaurant_uuid = $restaurant->restaurant_uuid;
        $categoryQuery = Category::find()->where(['category.restaurant_uuid' => $modelItem->restaurant_uuid])->asArray()->all();


        $modelsOption = [new Option];
        $modelsExtraOption = [[new ExtraOption]];

        if ($modelItem->load(Yii::$app->request->post())) {


//            $itemImages = \yii\web\UploadedFile::getInstances($modelItem, 'item_images');
            // initialize FileUploader
            $FileUploader = new FileUploader('item_images', array(
                'limit' => 10,
                'maxSize' => null,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload();

            // if uploaded and success
            if ($data['isSuccess'] && count($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }
            // if warnings
//            if ($data['hasWarnings']) {
//                // get warnings
//                $warnings = $data['warnings'];
//
//                echo '<pre>';
//                print_r($warnings);
//                echo '</pre>';
//                exit;
//            }
            // get the fileList
            $itemImages = $FileUploader->getFileList();


            $modelsOption = Model::createMultiple(Option::classname());
            Model::loadMultiple($modelsOption, Yii::$app->request->post());

            // validate item and options models
            $valid = $modelItem->validate();
            $valid = Model::validateMultiple($modelsOption) && $valid;

            if (isset($_POST['ExtraOption'][0][0])) {
                foreach ($_POST['ExtraOption'] as $indexOption => $extraOptions) {
                    foreach ($extraOptions as $indexExtraOption => $extraOption) {
                        $data['ExtraOption'] = $extraOption;
                        $modelExtraOption = new ExtraOption;
                        $modelExtraOption->load($data);
                        $modelsExtraOption[$indexOption][$indexExtraOption] = $modelExtraOption;
                        $valid = $modelExtraOption->validate();
                    }
                }
            }

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();

                try {
                    if ($flag = $modelItem->save(false)) {
                        if ($modelItem->items_category) {
                            $modelItem->saveItemsCategory($modelItem->items_category);
                        }

                        if (!empty($itemImages)) {
                            $modelItem->uploadItemImage($itemImages);
                        }

                        Yii::info("[" . $modelItem->restaurant->name . ": " . $modelItem->item_name . " has been added  " . '] ' . $modelItem->restaurant->restaurant_domain . '/product/' . $modelItem->item_uuid, __METHOD__);

                        foreach ($modelsOption as $indexOption => $modelOption) {
                            if ($flag === false) {
                                break;
                            }

                            $modelOption->item_uuid = $modelItem->item_uuid;

                            if (!($flag = $modelOption->save(false))) {
                                break;
                            }

                            if (isset($modelsExtraOption[$indexOption]) && is_array($modelsExtraOption[$indexOption])) {
                                foreach ($modelsExtraOption[$indexOption] as $indexExtraOption => $modelExtraOption) {
                                    $modelExtraOption->option_id = $modelOption->option_id;
                                    if (!($flag = $modelExtraOption->save(false))) {
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['index', 'storeUuid' => $storeUuid]);
                    } else {
                        $transaction->rollBack();
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }
        }

        return $this->render('create', [
                    'modelItem' => $modelItem,
                    'categoryQuery' => $categoryQuery,
                    'modelsOption' => (empty($modelsOption)) ? [new Option] : $modelsOption,
                    'modelsExtraOption' => (empty($modelsExtraOption)) ? [[new ExtraOption]] : $modelsExtraOption,
                    'storeUuid' => $restaurant->restaurant_uuid
        ]);
    }

    /**
     * Change item status
     * @param type $id
     * @param type $storeUuid
     * @return type
     */
    public function actionChangeItemStatus($id, $storeUuid)
    {
        $model = $this->findModel($id, $storeUuid);

        $model->setScenario(Item::SCENARIO_UPDATE_STATUS);

        $model->item_status = $model->item_status == Item::ITEM_STATUS_PUBLISH ? Item::ITEM_STATUS_UNPUBLISH : Item::ITEM_STATUS_PUBLISH;

        $model->save(false);

        return $this->redirect(['index', 'storeUuid' => $storeUuid]);
    }

    /**
     * Updates an existing Item model.
     * If update is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id, $storeUuid)
    {
        $modelItem = $this->findModel($id, $storeUuid);
        $categoryQuery = Category::find()->where(['category.restaurant_uuid' => $modelItem->restaurant_uuid])->asArray()->all();

        $modelsOption = $modelItem->getOptions()->all();
        $modelsExtraOption = [];
        $oldExtraOptions = [];


        if (!empty($modelsOption)) {
            foreach ($modelsOption as $indexOption => $modelOption) {
                $extraOptions = $modelOption->getExtraOptions()->all();
                $modelsExtraOption[$indexOption] = $extraOptions;
                $oldExtraOptions = ArrayHelper::merge(ArrayHelper::index($extraOptions, 'extra_option_id'), $oldExtraOptions);
            }
        }



        if ($modelItem->load(Yii::$app->request->post())) {
            // initialize FileUploader
            $FileUploader = new FileUploader('item_images', array(
                'limit' => 10,
                'maxSize' => null,
                'extensions' => null,
                'uploadDir' => 'uploads/',
                'title' => 'name'
            ));

            // call to upload the files
            $data = $FileUploader->upload();

            // if uploaded and success
            if ($data['isSuccess'] && count($data['files']) > 0) {
                // get uploaded files
                $uploadedFiles = $data['files'];
            }
            // if warnings
//            if ($data['hasWarnings']) {
//                // get warnings
//                $warnings = $data['warnings'];
//
//                echo '<pre>';
//                print_r($warnings);
//                echo '</pre>';
//                exit;
//            }
            // get the fileList
            $itemImages = $FileUploader->getFileList();


            // reset
            $modelsExtraOption = [];

            $oldOptionIDs = ArrayHelper::map($modelsOption, 'option_id', 'option_id');
            $modelsOption = Model::createMultiple(Option::classname(), $modelsOption);
            Model::loadMultiple($modelsOption, Yii::$app->request->post());
            $deletedOptionIDs = array_diff($oldOptionIDs, array_filter(ArrayHelper::map($modelsOption, 'option_id', 'option_id')));


            // validate Item and options models
            $valid = $modelItem->validate();
            $valid = Model::validateMultiple($modelsOption) && $valid;


            $extraOptionsIDs = [];
            if (isset($_POST['ExtraOption'][0][0])) {
                foreach ($_POST['ExtraOption'] as $indexOption => $extraOptions) {
                    $extraOptionsIDs = ArrayHelper::merge($extraOptionsIDs, array_filter(ArrayHelper::getColumn($extraOptions, 'extra_option_id')));
                    foreach ($extraOptions as $indexExtraOption => $extraOption) {
                        $data['ExtraOption'] = $extraOption;
                        $modelExtraOption = (isset($extraOption['extra_option_id']) && isset($oldExtraOptions[$extraOption['extra_option_id']])) ? $oldExtraOptions[$extraOption['extra_option_id']] : new ExtraOption;
                        $modelExtraOption->load($data);
                        $modelsExtraOption[$indexOption][$indexExtraOption] = $modelExtraOption;
                        $valid = $modelExtraOption->validate();
                    }
                }
            }



            $oldExtraOptionsIDs = ArrayHelper::getColumn($oldExtraOptions, 'extra_option_id');
            $deletedExtraOptionsIDs = array_diff($oldExtraOptionsIDs, $extraOptionsIDs);

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($flag = $modelItem->save(false)) {
                        if ($modelItem->items_category) {
                            $modelItem->saveItemsCategory($modelItem->items_category);
                        } else {
                            CategoryItem::deleteAll(['item_uuid' => $modelItem->item_uuid]);
                        }

                        if (!empty($itemImages)) {
                            $modelItem->uploadItemImage($itemImages);
                        }


                        Yii::info("[" . $modelItem->restaurant->name . ": " . $modelItem->item_name . " has been updated  " . '] ' . $modelItem->restaurant->restaurant_domain . '/product/' . $modelItem->item_uuid, __METHOD__);

                        if (!empty($deletedExtraOptionsIDs)) {
                            ExtraOption::deleteAll(['extra_option_id' => array_values($deletedExtraOptionsIDs)]);
                        }

                        if (!empty($deletedOptionIDs)) {
                            Option::deleteAll(['option_id' => $deletedOptionIDs]);
                        }

                        foreach ($modelsOption as $indexOption => $modelOption) {

                            if ($flag === false) {
                                break;
                            }

                            $modelOption->item_uuid = $modelItem->item_uuid;

                            if (!($flag = $modelOption->save(false))) {
                                break;
                            }

                            if (isset($modelsExtraOption[$indexOption]) && is_array($modelsExtraOption[$indexOption])) {
                                foreach ($modelsExtraOption[$indexOption] as $indexExtraOption => $modelExtraOption) {
                                    $modelExtraOption->option_id = $modelOption->option_id;
                                    if (!($flag = $modelExtraOption->save(false))) {
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ($flag) {
                        $transaction->commit();
                        return $this->redirect(['index', 'storeUuid' => $storeUuid]);
                    } else {
                        $transaction->rollBack();
                    }
                } catch (Exception $e) {
                    $transaction->rollBack();
                }
            }
        }


        return $this->render('update', [
                    'modelItem' => $modelItem,
                    'categoryQuery' => $categoryQuery,
                    'modelsOption' => (empty($modelsOption)) ? [new Option] : $modelsOption,
                    'modelsExtraOption' => (empty($modelsExtraOption)) ? [[new ExtraOption]] : $modelsExtraOption,
                    'storeUuid' => $storeUuid
        ]);
    }

    /**
     * Deletes an existing Item model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id, $storeUuid)
    {
        $this->findModel($id, $storeUuid)->delete();

        return $this->redirect(['index', 'storeUuid' => $storeUuid]);
    }

    /**
     * Finds the Item model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id, $storeUuid)
    {
        $store = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $model  = Item::find()->where(['item_uuid' => $id, 'restaurant_uuid' => $store->restaurant_uuid])->one();

        if (($model) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
