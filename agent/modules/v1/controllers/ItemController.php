<?php

namespace agent\modules\v1\controllers;

use common\models\ItemVariant;
use common\models\ItemVariantImage;
use common\models\ItemVariantOption;
use common\models\ItemVideo;
use Yii;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use common\models\ItemImage;
use agent\models\Item;
use agent\models\ExtraOption;
use agent\models\Option;
use agent\models\Order;


class ItemController extends BaseController
{
    /**
     * Get all store's products
     * @return type
     * 
     * @api {get} /items Get all store's products
     * @apiName GetItems
     * 
     * @apiParam {string} keyword Keyword.
     * @apiParam {string} type Type.
     * @apiParam {string} category_id Category ID.
     * 
     * @apiGroup Item
     *
     * @apiSuccess {Array} items List of items.
     */
    public function actionList()
    {
        $keyword = Yii::$app->request->get('keyword');
        $type = Yii::$app->request->get('type');
        $category_id = Yii::$app->request->get('category_id');

        $store = Yii::$app->accountManager->getManagedAccount();

        $query = Item::find()
            ->andWhere(['restaurant_uuid'=> $store->restaurant_uuid])
            ->orderBy ([new \yii\db\Expression('item.sort_number ASC')]);

        if ($type != 'all') {
            $query->andWhere(['track_quantity'=> 1]);
        }

        if ($keyword && $keyword != 'null') {
            $query->andWhere ([
                    'or',
                    ['like', 'item_name', $keyword],
                    ['like', 'item_name_ar', $keyword],
                //https://bawes-co.sentry.io/issues/6046426048/events/7def2926637a433aa29ba62366ed14a0/?project=5220572
                //SQLSTATE[HY000]: General error: 1267 Illegal mix of collations (utf8_unicode_ci,IMPLICIT) and (utf8mb4_bin,COERCIBLE) for operation 'like'
                //    ['like', 'item_description', $keyword],
                //    ['like', 'item_description_ar', $keyword]
                ]);
        }

        if($category_id) {
            $query->filterByCategory($category_id);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return item detail
     * @param string $id
     * @return Item
     * 
     * @api {get} /items/:id Return item detail
     * @apiName GetItemDetail
     * @apiParam {string} id Item ID.
     * @apiGroup Item
     *
     * @apiSuccess {Array} item Item.
     */
    public function actionDetail($id) {
        return $this->findModel($id);
    }

    /**
     * add new item
     * @return array|string[]
     * @throws NotFoundHttpException
     * 
     * @api {post} /items Create new item
     * @apiName CreateItem
     * 
     * @apiParam {string} item_name Item name.
     * @apiParam {string} item_name_ar Item name arabic.
     * @apiParam {string} item_description Item description.
     * @apiParam {string} item_description_ar Item description arabic.
     * @apiParam {string} item_meta_description Item meta description.
     * @apiParam {string} item_meta_description_ar Item meta description arabic.
     * @apiParam {string} item_meta_title Item meta title.
     * @apiParam {string} item_meta_title_ar Item meta title arabic.
     * @apiParam {string} sort_number Sort number.
     * @apiParam {string} item_type Item type.
     * @apiParam {string} prep_time Prep time.
     * @apiParam {string} prep_time_unit Prep time unit.
     * @apiParam {string} item_price Item price.
     * @apiParam {string} compare_at_price Compare at price.
     * @apiParam {string} sku SKU.
     * @apiParam {string} barcode Barcode.
     * @apiParam {string} track_quantity Track quantity.
     * @apiParam {string} shipping Shipping.
     * @apiParam {string} weight Weight.
     * @apiParam {string} length Length.
     * @apiParam {string} height Height.
     * @apiParam {string} width Width.
     * @apiParam {string} itemCategories Item categories.
     * @apiParam {string} itemImages Item images.
     * @apiParam {string} itemVideos Item videos.
     * @apiParam {string} itemVariants Item variants.
     * @apiParam {string} options Options.
     * 
     * @apiGroup Item
     *
     * @apiSuccess {Array} item Item.
     */
    public function actionCreate()
    {
        $store = Yii::$app->accountManager->getManagedAccount();

        $model = new Item();

        $model->restaurant_uuid = $store->restaurant_uuid;
        $model->item_name = Yii::$app->request->getBodyParam ("item_name");
        $model->item_name_ar = Yii::$app->request->getBodyParam ("item_name_ar");
        $model->item_description = Yii::$app->request->getBodyParam ("item_description");
        $model->item_description_ar = Yii::$app->request->getBodyParam ("item_description_ar");
        $model->item_meta_description = Yii::$app->request->getBodyParam("item_meta_description");
        $model->item_meta_description_ar = Yii::$app->request->getBodyParam("item_meta_description_ar");
        $model->item_meta_title = Yii::$app->request->getBodyParam("item_meta_title");
        $model->item_meta_title_ar = Yii::$app->request->getBodyParam("item_meta_title_ar");

        $model->sort_number = Yii::$app->request->getBodyParam ("sort_number");
        $model->item_type = Yii::$app->request->getBodyParam ("item_type"); 
        $model->prep_time = Yii::$app->request->getBodyParam ("prep_time");
        $model->prep_time_unit = Yii::$app->request->getBodyParam ("prep_time_unit");
        $model->item_price = Yii::$app->request->getBodyParam ("item_price");
        $model->compare_at_price = Yii::$app->request->getBodyParam ("compare_at_price");
        $model->sku = Yii::$app->request->getBodyParam ("sku");
        $model->barcode = Yii::$app->request->getBodyParam ("barcode");
        $model->track_quantity = (int)Yii::$app->request->getBodyParam ("track_quantity");
        $model->stock_qty = Yii::$app->request->getBodyParam ("stock_qty");

        $model->shipping = Yii::$app->request->getBodyParam ("shipping");

        if($model->shipping) {
            $model->weight = Yii::$app->request->getBodyParam ("weight");
            $model->length = Yii::$app->request->getBodyParam ("length");
            $model->height = Yii::$app->request->getBodyParam ("height");
            $model->width = Yii::$app->request->getBodyParam ("width");
        }

        $model->items_category = Yii::$app->request->getBodyParam ("itemCategories");
        $model->item_images = Yii::$app->request->getBodyParam ("itemImages");

        $itemOptions = Yii::$app->request->getBodyParam('options');
        $transaction = Yii::$app->db->beginTransaction();

        if (!$model->save()) {
            $transaction->rollBack();
            return $this->itemOperationError($model, __METHOD__, 'Error saving item detail', 'app');
        }

        if ($itemOptions && count($itemOptions) > 0) {

            foreach ($itemOptions as $option) {

                $min_qty = (isset($option['min_qty'])) ? $option['min_qty'] : 0;
                $max_qty = (isset($option['max_qty'])) ? $option['max_qty'] : 0;

                //for radio button

                if(isset($option['option_type']) && in_array($option['option_type'], [2, '2'])) {

                    if($option['is_required']) {
                        $min_qty = 1;
                    } else {
                        //https://bawescompany.atlassian.net/browse/ENG-414
                        $min_qty = 0;
                    }

                    $max_qty = 1;
                }

                $optionModel = new Option();
                $optionModel->item_uuid = $model->item_uuid;
                $optionModel->option_name = $option['option_name'];
                $optionModel->option_name_ar = $option['option_name_ar'];
                $optionModel->max_qty = $max_qty;
                $optionModel->min_qty = $min_qty;
                $optionModel->is_required = $option['is_required'];
                $optionModel->option_price = isset($option['option_price'])? $option['option_price']: 0;
                $optionModel->sort_number = isset($option['sort_number'])? $option['sort_number']: 1;
                $optionModel->option_type = isset($option['option_type'])? $option['option_type']: 1;

                if (!$optionModel->save()) {
                    $transaction->rollBack();
                    return $this->itemOperationError($optionModel, __METHOD__, 'Error saving option', 'app');
                }

                if ($option['extraOptions'] && count($option['extraOptions']) > 0) {

                    foreach ($option['extraOptions'] as $extraOption) {

                        $extraOptionModel = new ExtraOption();
                        $extraOptionModel->option_id = $optionModel->option_id;
                        $extraOptionModel->extra_option_name = $extraOption['extra_option_name'];
                        $extraOptionModel->extra_option_name_ar = $extraOption['extra_option_name_ar'];
                        $extraOptionModel->extra_option_price = (isset($extraOption['extra_option_price'])) ? $extraOption['extra_option_price'] : 0;
                        $extraOptionModel->stock_qty = (isset($extraOption['stock_qty']))?$extraOption['stock_qty'] : null;

                        if (!$extraOptionModel->save()) {
                            $transaction->rollBack();
                            return $this->itemOperationError($extraOptionModel, __METHOD__, 'Error saving option value', 'app');
                        }
                    }
                }
            }
        }

        // save images
        $itemImages = Yii::$app->request->getBodyParam ("itemImages");
        $model->saveItemImages($itemImages);

        $itemVideos = Yii::$app->request->getBodyParam ("itemVideos", []);
        $model->saveItemVideos($itemVideos);

        //save categories
        $itemCategories = Yii::$app->request->getBodyParam ("itemCategories");
        $arrCategoryIds = ArrayHelper::getColumn ($itemCategories, 'category_id');
        $model->saveItemsCategory($arrCategoryIds);

        //add variants

        $variants = Yii::$app->request->getBodyParam ("itemVariants");

        if(!$variants)
            $variants = [];

        foreach($variants as $variant)
        {
            $itemVariant = new ItemVariant();

            $itemVariant->item_uuid = $model->item_uuid;

            $itemVariant->stock_qty = $variant['stock_qty'];
            //$itemVariant->track_quantity = $variant['track_quantity'];
            $itemVariant->sku = $variant['sku'];
            $itemVariant->barcode = $variant['barcode'];
            $itemVariant->price = $variant['price'];
            $itemVariant->compare_at_price = $variant['compare_at_price'];

            if($model->shipping) {
                $itemVariant->weight = $variant['weight'];
                $itemVariant->length = isset($variant["length"]) ? $variant["length"] : null;
                $itemVariant->height = isset($variant["height"]) ? $variant["height"] : null;
                $itemVariant->width = isset($variant["width"]) ? $variant["width"] : null;
            } else {
                $itemVariant->weight = 0;
                $itemVariant->length = 0;
                $itemVariant->height = 0;
                $itemVariant->width = 0;
            }

            $itemVariant->images = isset($variant['itemVariantImages'])? $variant['itemVariantImages']: [];

            if(!$itemVariant->save())
            {
                $transaction->rollBack();

                return $this->itemOperationError($itemVariant, __METHOD__, 'Error saving item variant', 'app');
            }


            //add variant options

            foreach($variant['itemVariantOptions'] as $value) {

                if(!empty($value['option_id']))
                {
                    $option_id = $value['option_id'];
                }
                else
                {
                    $o = Option::findOne([
                        'item_uuid' => $itemVariant->item_uuid,
                        'option_name' => $value['option']['option_name']
                    ]);

                    $option_id = $o ? $o->option_id: null;
                }

                if(empty($value['extra_option_id'])) {
                    $e = ExtraOption::findOne([
                        'option_id' => $option_id,
                        'extra_option_name' => $value['extraOption']['extra_option_name']
                    ]);

                    if($e) {
                        $extra_option_id = $e->extra_option_id;
                    }
                }
                else
                {
                    $extra_option_id = $value['extra_option_id'];
                }

                if(empty($value['item_variant_option_uuid'])) {
                    $itemVariantOption = new ItemVariantOption();
                } else {
                    $itemVariantOption = ItemVariantOption::findOne($value['item_variant_option_uuid']);
                }

                $itemVariantOption->item_variant_uuid  = $itemVariant->item_variant_uuid;
                $itemVariantOption->item_uuid = $itemVariant->item_uuid;
                $itemVariantOption->option_id = $option_id;
                $itemVariantOption->extra_option_id = $extra_option_id;

                if(!$itemVariantOption->save())
                {
                    $transaction->rollBack();

                    return $this->itemOperationError($itemVariantOption, __METHOD__, 'Error saving item variant option', 'app');
                }
            }
        }

        $transaction->commit();

        //send events to mixpanel
        $model->trackEvents(true);

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Item created successfully"),
        ];
    }

    /**
     * Update item
     * 
     * @api {post} /items/:id Update item
     * @apiName UpdateItem
     * @apiParam {string} id Item ID.
     * @apiParam {string} item_name Item name.
     * @apiParam {string} item_name_ar Item name arabic.
     * @apiParam {string} item_description Item description.
     * @apiParam {string} item_description_ar Item description arabic.
     * @apiParam {string} item_meta_description Item meta description.
     * @apiParam {string} item_meta_description_ar Item meta description arabic.
     * @apiParam {string} item_meta_title Item meta title.
     * @apiParam {string} item_meta_title_ar Item meta title arabic.
     * @apiParam {string} sort_number Sort number.
     * @apiParam {string} item_type Item type.
     * @apiParam {string} prep_time Prep time.
     * @apiParam {string} prep_time_unit Prep time unit.
     * @apiParam {string} item_price Item price.
     * @apiParam {string} compare_at_price Compare at price.
     * @apiParam {string} sku SKU.
     * @apiParam {string} barcode Barcode.
     * @apiParam {string} track_quantity Track quantity.
     * @apiParam {string} stock_qty Stock quantity.
     * @apiParam {string} shipping Shipping.
     * @apiParam {string} weight Weight.
     * @apiParam {string} length Length.
     * @apiParam {string} height Height.
     * @apiParam {string} width Width.
     * @apiParam {string} itemCategories Item categories.
     * @apiParam {string} itemImages Item images.
     * @apiParam {string} itemVideos Item videos.
     * @apiParam {string} itemVariants Item variants.
     * @apiParam {string} options Options.
     *  
     * @apiGroup Item
     *
     * @apiSuccess {Array} item Item.
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel ($id);

        $model->item_name = Yii::$app->request->getBodyParam ("item_name");
        $model->item_name_ar = Yii::$app->request->getBodyParam ("item_name_ar");
        $model->item_description = Yii::$app->request->getBodyParam ("item_description");
        $model->item_description_ar = Yii::$app->request->getBodyParam ("item_description_ar");
        $model->item_meta_description = Yii::$app->request->getBodyParam("item_meta_description");
        $model->item_meta_description_ar = Yii::$app->request->getBodyParam("item_meta_description_ar");
        $model->item_meta_title = Yii::$app->request->getBodyParam("item_meta_title");
        $model->item_meta_title_ar = Yii::$app->request->getBodyParam("item_meta_title_ar");
        $model->sort_number = Yii::$app->request->getBodyParam ("sort_number");
        $model->item_type = Yii::$app->request->getBodyParam ("item_type"); 
        $model->prep_time = Yii::$app->request->getBodyParam ("prep_time");
        $model->prep_time_unit = Yii::$app->request->getBodyParam ("prep_time_unit");
        $model->item_price = Yii::$app->request->getBodyParam ("item_price");
        $model->compare_at_price = Yii::$app->request->getBodyParam ("compare_at_price");
        $model->sku = Yii::$app->request->getBodyParam ("sku");
        $model->barcode = Yii::$app->request->getBodyParam ("barcode");
        $model->track_quantity = (int)Yii::$app->request->getBodyParam ("track_quantity");
        $model->stock_qty = Yii::$app->request->getBodyParam ("stock_qty");
        $model->items_category = Yii::$app->request->getBodyParam ("itemCategories");
        $model->item_images = Yii::$app->request->getBodyParam ("itemImages");

        $model->shipping = Yii::$app->request->getBodyParam ("shipping");

        if($model->shipping) {
            $model->weight = Yii::$app->request->getBodyParam ("weight");
            $model->length = Yii::$app->request->getBodyParam ("length");
            $model->height = Yii::$app->request->getBodyParam ("height");
            $model->width = Yii::$app->request->getBodyParam ("width");
        } else {
            $model->weight = 0;
            $model->length = 0;
            $model->height = 0;
            $model->width = 0;
        }

        $itemOptions = Yii::$app->request->getBodyParam('options');
        $transaction = Yii::$app->db->beginTransaction();

            if (!$model->save()) {
                $transaction->rollBack();
                return $this->itemOperationError($model, __METHOD__, 'Error saving item detail', 'app');
            }

            $arrOptionIds = [];

            if ($itemOptions && count($itemOptions) > 0) {

                foreach ($itemOptions as $option) {

                    $min_qty = (isset($option['min_qty'])) ? $option['min_qty'] : 0;
                    $max_qty = (isset($option['max_qty'])) ? $option['max_qty'] : 0;

                    //for radio button

                    if(isset($option['option_type']) && in_array($option['option_type'], [2, '2'])) {

                        if($option['is_required'])
                            $min_qty = 1;

                        $max_qty = 1;
                    }

                    if(empty($option['option_id'])) {
                        $optionModel = new Option();
                    } else {
                        $optionModel = Option::findOne([
                            'option_id' => $option['option_id'],
                            'item_uuid' => $id
                        ]);
                    }

                    $optionModel->item_uuid = $model->item_uuid;
                    $optionModel->option_name = $option['option_name'];
                    $optionModel->option_name_ar = $option['option_name_ar'];
                    $optionModel->is_required = $option['is_required'];
                    $optionModel->option_price = isset($option['option_price'])? $option['option_price']: 0;
                    $optionModel->sort_number = isset($option['sort_number'])? $option['sort_number']: 1;
                    $optionModel->option_type = isset($option['option_type'])? $option['option_type']: 1;
                    $optionModel->max_qty = $max_qty;
                    $optionModel->min_qty = $min_qty;

                    if (!$optionModel->save()) {
                        $transaction->rollBack();
                        return $this->itemOperationError($optionModel, __METHOD__, 'Error saving option', 'app');
                    }

                    $arrOptionIds[] =  $optionModel->option_id;

                    $arrExtraOptionIds = [];

                    if ($option['extraOptions'] && count($option['extraOptions']) > 0) {

                        foreach ($option['extraOptions'] as $extraOption) {

                            if(empty($extraOption['extra_option_id'])) {
                                $extraOptionModel = new ExtraOption();
                            } else {
                                $extraOptionModel = ExtraOption::find()->andWhere([
                                    'option_id' => $optionModel->option_id,
                                    'extra_option_id' => $extraOption['extra_option_id']
                                ])->one();

                                if (!$extraOptionModel) {
                                    throw new NotFoundHttpException('The requested record does not exist.');
                                }
                            }

                            $extraOptionModel->option_id = $optionModel->option_id;
                            $extraOptionModel->extra_option_name = $extraOption['extra_option_name'];
                            $extraOptionModel->extra_option_name_ar = $extraOption['extra_option_name_ar'];
                            $extraOptionModel->extra_option_price = $extraOption['extra_option_price'];
                            $extraOptionModel->stock_qty = (isset($extraOption['stock_qty']))?$extraOption['stock_qty'] : null;

                            if (!$extraOptionModel->save()) {
                                $transaction->rollBack();
                                return $this->itemOperationError($extraOptionModel, __METHOD__, 'Error saving option value', 'app');
                            }

                            $arrExtraOptionIds[] = $extraOptionModel->extra_option_id;
                        }
                    }

                    ExtraOption::deleteAll([
                        'AND',
                        ['NOT IN', 'extra_option_id', $arrExtraOptionIds],
                        ['option_id' => $optionModel->option_id]
                    ]);
                }
            }

            //remove other option

            Option::deleteAll([
                'AND',
                ['NOT IN', 'option_id', $arrOptionIds],
                ['item_uuid' => $model->item_uuid]
            ]);

            // save images

            $itemImages = Yii::$app->request->getBodyParam ("itemImages");

            $model->saveItemImages($itemImages);

            $itemVideos = Yii::$app->request->getBodyParam ("itemVideos", []);
            $model->saveItemVideos($itemVideos);

            //save categories
            $itemCategories = Yii::$app->request->getBodyParam ("itemCategories");
            $arrCategoryIds = ArrayHelper::getColumn ($itemCategories, 'category_id');
            $model->saveItemsCategory($arrCategoryIds);

            //add variants

            $variants = Yii::$app->request->getBodyParam ("itemVariants");

            if(!$variants)
                $variants = [];

            $arrItemVariantIds = [];
            $arrItemVariantOptionIds = [];

            foreach($variants as $variant)
            {
                if(empty($variant['item_variant_uuid'])) {
                    $itemVariant = new ItemVariant();
                }
                else
                {
                    $itemVariant = $model->getItemVariants()
                        ->andWhere(['item_variant_uuid' => $variant['item_variant_uuid']])
                        ->one();
                }

                $itemVariant->item_uuid = $model->item_uuid;

                $itemVariant->stock_qty = $variant['stock_qty'];
                //$itemVariant->track_quantity = $variant['track_quantity'];
                $itemVariant->sku = $variant['sku'];
                $itemVariant->barcode = $variant['barcode'];
                $itemVariant->price = $variant['price'];
                $itemVariant->compare_at_price = $variant['compare_at_price'];

                if($model->shipping) {
                    $itemVariant->weight = $variant['weight'];
                    $itemVariant->length = isset($variant["length"]) ? $variant["length"] : null;
                    $itemVariant->height = isset($variant["height"]) ? $variant["height"] : null;
                    $itemVariant->width = isset($variant["width"]) ? $variant["width"] : null;
                } else {
                    $itemVariant->weight = 0;
                    $itemVariant->length = 0;
                    $itemVariant->height = 0;
                    $itemVariant->width = 0;
                }

                $itemVariant->images = isset($variant['itemVariantImages'])? $variant['itemVariantImages']: [];

                if(!$itemVariant->save())
                {
                    $transaction->rollBack();

                    return $this->itemOperationError($itemVariant, __METHOD__, 'Error saving item variant', 'app');
                }

                //add variant options

                foreach($variant['itemVariantOptions'] as $value) {

                    $option_id = null;

                    if(!empty($value['option_id'])) {
                        $option_id = $value['option_id'];
                    }
                    else
                    {
                        $o = Option::findOne([
                            'item_uuid' => $itemVariant->item_uuid,
                            'option_name' => $value['option']['option_name']
                        ]);

                        if($o) {
                            $option_id = $o->option_id;
                        }
                    }

                    if(!empty($value['extra_option_id'])) {
                        $extra_option_id = $value['extra_option_id'];
                    } else {
                        $e = ExtraOption::findOne([
                            'option_id' => $option_id,
                            'extra_option_name' => $value['extraOption']['extra_option_name']
                        ]);

                        if ($e) {
                            $extra_option_id = $e->extra_option_id;
                        }
                    }

                    if(empty($value['item_variant_option_uuid'])) {
                        $itemVariantOption = new ItemVariantOption();
                    } else {
                        $itemVariantOption = ItemVariantOption::findOne($value['item_variant_option_uuid']);
                    }

                    $itemVariantOption->item_variant_uuid  = $itemVariant->item_variant_uuid;
                    $itemVariantOption->item_uuid = $itemVariant->item_uuid;
                    $itemVariantOption->option_id = $option_id;
                    $itemVariantOption->extra_option_id = $extra_option_id;

                    if(!$itemVariantOption->save())
                    {
                        $transaction->rollBack();

                        return $this->itemOperationError($itemVariantOption, __METHOD__, 'Error saving item variant option', 'app');
                    }

                    $arrItemVariantOptionIds[] = $itemVariantOption->item_variant_option_uuid;
                }

                $arrItemVariantIds[] = $itemVariant->item_variant_uuid;
            }

            //remove other variants

            ItemVariant::deleteAll([
                'AND',
                ['NOT IN', 'item_variant_uuid', $arrItemVariantIds],
                ['item_uuid' => $id]
            ]);

            //remove other variantOptions

            ItemVariantOption::deleteAll([
                'AND',
                ['NOT IN', 'item_variant_option_uuid', $arrItemVariantOptionIds],
                ['item_uuid' => $id]
            ]);

            $transaction->commit();

            return [
                "operation" => "success",
                "message" => Yii::t('agent',"Item updated successfully")
            ];
    }

    /**
     * Update Stock Qty
     * @param string $itemUuid
     * @return boolean
     * 
     * @api {post} /items/update-stock-qty Update stock qty
     * @apiName UpdateStockQty
     * @apiParam {string} item_uuid Item UUID.
     * @apiParam {string} stock_qty Stock quantity.
     * @apiParam {string} track_quantity Track quantity.
     * 
     * @apiGroup Item
     * 
     * @apiSuccess {Array} item Item.
     */
    public function actionUpdateStockQty()
    {
        $id = Yii::$app->request->getBodyParam('item_uuid');

        $stock_qty = Yii::$app->request->getBodyParam('stock_qty');
        $track_quantity = Yii::$app->request->getBodyParam('track_quantity');

        $model = $this->findModel($id);

        $model->setScenario(Item::SCENARIO_UPDATE_STOCK);

        $model->stock_qty = (int) $stock_qty;
        $model->track_quantity = (int) $track_quantity;

        if (!$model->save())
        {
            return $this->itemOperationError($model, __METHOD__, 'Unable to update item quantity. Please try again.');
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Item quantity updated successfully")
        ];
    }

    /**
     * Update Stock Qty
     * @param string $itemUuid
     * @return boolean
     * 
     * @api {post} /items/change-position Change position
     * @apiName ChangePosition
     * @apiParam {string} items Items.
     * 
     * @apiGroup Item
     * 
     * @apiSuccess {Array} item Item.
     */
    public function actionChangePosition(){

        $items = Yii::$app->request->getBodyParam('items');

        foreach ($items as $key => $value) {

            $model = $this->findModel($value);

            if(!$model) {
                continue;
            }

            $model->setScenario(Item::SCENARIO_UPDATE_SORT);

            $model->sort_number = (int) $key+1;
            $model->save();
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Item position changed successfully")
        ];
    }

    /**
     * @param $id
     * @return array|string[]
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * 
     * @api {DELETE} /items/:id Delete item
     * @apiName DeleteItem
     * @apiParam {string} id Item ID.
     * 
     * @apiGroup Item
     * 
     * @apiSuccess {Array} item Item.
     */
    public function actionDelete($id)
    {
        $model = $this->findModel ($id);

        if (!$model->delete ()) {
            return $this->itemOperationError($model, __METHOD__, "We've faced a problem deleting the item");
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent',"Item deleted successfully")
        ];
    }

    /**
     * @param $id
     * @return array|string[]
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * 
     * @api {DELETE} /items/delete-video/:id Delete video
     * @apiName DeleteVideo
     * 
     * @apiParam {string} id Item Video ID.
     * 
     * @apiGroup Item
     * 
     * @apiSuccess {Array} item Item.
     */
    public function actionDeleteVideo($id)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount();

        $model = ItemVideo::find()->andWhere(['item_video_id'=> $id])->one();

        if(!$model) {
            return [
                "operation" => "error",
                "message" => "Video not found to delete"
            ];
        }

        //check ownership

        $exists = $restaurant->getItems()
            ->andWhere(['item_uuid' => $model->item_uuid])
            ->exists();

        if(!$exists) {
            return [
                "operation" => "error",
                "message" => "We've faced a problem deleting the item"
            ];
        }

        if ($model && !$model->delete()) {
            return $this->itemOperationError($model, __METHOD__, "We've faced a problem deleting the item");
        }

        return [
            "operation" => "success",
            "message" => "Item video deleted successfully"
        ];
    }

    /**
     * @param $id
     * @return array|string[]
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * 
     * @api {DELETE} /items/delete-image/:id/:image Delete image
     * @apiName DeleteImage
     * 
     * @apiParam {string} id Item ID.
     * @apiParam {string} image Image file name/path.
     * 
     * @apiGroup Item
     * 
     * @apiSuccess {Array} item Item.
     */
    public function actionDeleteImage($id, $image = null)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount();

        if (!$image) {
            $image = Yii::$app->request->getBodyParam("image");
        }

        $model = ItemImage::findOne([
            'item_uuid'=>$id,
            'product_file_name'=>$image
        ]);

        if(!$model) {
            return [
                "operation" => "error",
                "message" => "We've faced a problem deleting the item"
            ];
        }

        //check ownership

        $exists = $restaurant->getItems()->andWhere(['item_uuid' => $model->item_uuid])->exists();

        if(!$exists) {
            return [
                "operation" => "error",
                "message" => "We've faced a problem deleting the item"
            ];
        }

        if (!$model->delete()) {
            return $this->itemOperationError($model, __METHOD__, "We've faced a problem deleting the item");
        }

        return [
            "operation" => "success",
            "message" => "Item image deleted successfully"
        ];
    }

    /**
     * @param $id
     * @return array|string[]
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * 
     * @api {DELETE} /items/delete-variant-image/:id/:image Delete variant image
     * @apiName DeleteVariantImage
     * 
     * @apiParam {string} id Item ID.
     * @apiParam {string} image Image file name/path.
     * 
     * @apiGroup Item
     */
    public function actionDeleteVariantImage($id, $image = null)
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount();

        if (!$image) {
            $image = Yii::$app->request->getBodyParam("image");
        }

        $model = ItemVariantImage::findOne(['item_uuid'=>$id, 'product_file_name'=>$image]);

        if(!$model) {
            return [
                "operation" => "error",
                "message" => "We've faced a problem deleting the item"
            ];
        }

        //check ownership

        $exists = $restaurant->getItems()->andWhere(['item_uuid' => $model->item_uuid])->exists();

        if(!$exists) {
            return [
                "operation" => "error",
                "message" => "We've faced a problem deleting the item"
            ];
        }

        if (!$model->delete()) {
            return $this->itemOperationError($model, __METHOD__, "We've faced a problem deleting the item");
        }

        return [
            "operation" => "success",
            "message" => "Item variant image deleted successfully"
        ];
    }

    /**
     * download excel of item report
     * 
     * @api {GET} /items/items-report Download excel of item report
     * @apiName ItemsReport
     * 
     * @apiParam {string} start_date Start date.
     * @apiParam {string} end_date End date.
     * 
     * @apiGroup Item
     */
    public function actionItemsReport()
    {
        ini_set('memory_limit', '-1');
        $store = Yii::$app->accountManager->getManagedAccount();

        $start_date = Yii::$app->request->get('start_date');
        $end_date = Yii::$app->request->get('end_date');

        if(!$start_date) {
            $start_date = Yii::$app->request->get('from');
        }

        if(!$end_date) {
            $end_date = Yii::$app->request->get('to');
        }
        
            $query = \agent\models\Item::find()
                ->joinWith(['orderItems', 'orderItems.order'])
                ->andWhere ([
                    'IN',
                    'order.order_status', [
                        Order::STATUS_PENDING,
                        Order::STATUS_BEING_PREPARED,
                        Order::STATUS_OUT_FOR_DELIVERY,
                        Order::STATUS_COMPLETE,
                        Order::STATUS_CANCELED
                    ]
                ])
                ->andWhere(['order.restaurant_uuid' => $store->restaurant_uuid]);

            if($start_date && $end_date) {
                $query->andWhere (new Expression('DATE(order.order_created_at) >= DATE("'.$start_date.'") AND
                    DATE(order.order_created_at) <= DATE("'.$end_date.'")'));
            }

            $searchResult = $query->all();

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
                        'header' => Yii::t('agent','Unit sold'),
                        'label' => 'Sold items',
                        'format' => 'html',
                        'value' => function ($data)  use ($start_date,$end_date) {
                            $total = $data->getSoldUnitsInSpecifcDate($start_date,$end_date);
                            return ($total) ? $total : 0;
                        },
                    ],
                ],
            ]);
    }

    /**
     * @return array|string[]
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * 
     * @api {GET} /items/export-to-excel Export to excel
     * @apiName ExportToExcel
     * 
     * @apiGroup Item
     */
    public function actionExportToExcel()
    {
        $restaurant = Yii::$app->accountManager->getManagedAccount();

        $model = \agent\models\Item::find()->where(['restaurant_uuid' => $restaurant->restaurant_uuid])->all();

        header('Access-Control-Allow-Origin: *');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment;filename=\"item-report.xlsx\"");
        header("Cache-Control: max-age=0");

        \common\components\PhpExcel::export([
            'isMultipleSheet' => false,
            'models' => $model,
            'columns' => [
                [
                    'header' => Yii::t('agent','Item name'),
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
     * change status
     * @param $id
     * @param $store_uuid
     * @return array|string[]
     * @throws NotFoundHttpException
     * 
     * @api {PATCH} /items/change-status/:id/:store_uuid Change status
     * @apiName ChangeStatus
     * 
     * @apiParam {string} id Item ID.
     * @apiParam {string} store_uuid Store UUID.
     * 
     * @apiGroup Item
     */
    public function actionChangeStatus($id, $store_uuid = null)
    {
        $model = $this->findModel($id, $store_uuid);

        $model->scenario = Item::SCENARIO_UPDATE_STATUS;

        $model->item_status = ($model->item_status == Item::ITEM_STATUS_PUBLISH) ? Item::ITEM_STATUS_UNPUBLISH : Item::ITEM_STATUS_PUBLISH;

        if (!$model->save ()) {
            return $this->itemOperationError($model, __METHOD__, "We've faced a problem while status change of item");
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent',"Item status changed successfully")
        ];
    }

    private function itemOperationError($model, $context, $message, $category = 'agent')
    {
        Yii::error([
            'message' => 'Agent item operation failed',
            'model' => get_class($model),
            'errors' => $model->getErrors()
        ], $context);

        return [
            "operation" => "error",
            "message" => Yii::t($category, $message)
        ];
    }

    /**
     * Finds the Item model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $item_uuid
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($item_uuid, $store_uuid = null)
    {
        $store = Yii::$app->accountManager->getManagedAccount($store_uuid);

        $model = Item::findOne(['item_uuid'=>$item_uuid,'restaurant_uuid' => $store->restaurant_uuid]);

        if ($model !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested record does not exist.');
        }
    }
}
