<?php

namespace agent\modules\v1\controllers;

use common\models\Campaign;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;


class CampaignController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
        $behaviors['authenticator']['except'] = ['options', 'click'];

        return $behaviors;
    }

    /**
     * Get all store's categories
     * @param string $store_uuid
     * @param string $keyword
     * @return ActiveDataProvider
     * 
     * @api {get} /campaign List campaigns
     * @apiName ListCampaigns
     * @apiParam {string} keyword Keyword.
     * @apiGroup Campaign
     *
     * @apiSuccess {Array} campaigns List of campaigns.
     */
    public function actionList($store_uuid = null)
    {
        $keyword = Yii::$app->request->get('keyword');

        $store = Yii::$app->accountManager->getManagedAccount($store_uuid);

        $query = $store->getCampaigns();

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'utm_source', $keyword],
                ['like', 'utm_medium', $keyword],
                ['like', 'utm_campaign', $keyword],
            ]);
        }

        $query->orderBy([new \yii\db\Expression('created_at DESC')]);

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Create category
     * @return array
     * 
     * @api {post} /campaign Create campaign
     * @apiName CreateCampaign
     * 
     * @apiParam {string} source Source.
     * @apiParam {string} medium Medium.
     * @apiParam {string} campaign Campaign.
     * @apiParam {string} content Content.
     * @apiParam {string} term Term.
     * 
     * @apiGroup Campaign
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     * @apiSuccess {Array} model Campaign.
     */
    public function actionCreate()
    {
        $store = Yii::$app->accountManager->getManagedAccount();

        $model = new Campaign();
        $model->restaurant_uuid = $store->restaurant_uuid;
        $model->utm_source = Yii::$app->request->getBodyParam("source");
        $model->utm_medium = Yii::$app->request->getBodyParam("medium");
        $model->utm_campaign = Yii::$app->request->getBodyParam("campaign");
        $model->utm_content = Yii::$app->request->getBodyParam("content");
        $model->utm_term = Yii::$app->request->getBodyParam("term");

        if (!$model->save()) {
            return $this->campaignSaveFailedResponse(
                $model,
                "We've faced a problem creating the campaign"
            );
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Campaign created successfully"),
            "model" => Campaign::findOne($model->utm_uuid)
        ];
    }

    /**
     * Update campaign
     * @return array
     * 
     * @api {PATCH} /campaign/:id Update campaign
     * @apiName UpdateCampaign
     * 
     * @apiParam {string} id Campaign ID.
     * @apiParam {string} source Source.
     * @apiParam {string} medium Medium.
     * @apiParam {string} campaign Campaign.
     * @apiParam {string} content Content.
     * @apiParam {string} term Term.
     * 
     * @apiGroup Campaign
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     * @apiSuccess {Array} model Campaign.
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $model->utm_source = Yii::$app->request->getBodyParam("source");
        $model->utm_medium = Yii::$app->request->getBodyParam("medium");
        $model->utm_campaign = Yii::$app->request->getBodyParam("campaign");
        $model->utm_content = Yii::$app->request->getBodyParam("content");
        $model->utm_term = Yii::$app->request->getBodyParam("term");

        if (!$model->save()) {
            return $this->campaignSaveFailedResponse(
                $model,
                "We've faced a problem updating the campaign"
            );
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Campaign updated successfully"),
            "model" => $model
        ];
    }

    /**
     * Delete Campaign
     * @return array
     * 
     * @api {DELETE} /campaign/:id Delete campaign
     * @apiName DeleteCampaign
     * 
     * @apiParam {string} id Campaign ID.
     * 
     * @apiGroup Campaign
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);

        if (!$model->delete()) {
            return $this->campaignSaveFailedResponse(
                $model,
                "We've faced a problem deleting the campaign"
            );
        }

        return [
            "operation" => "success",
            "message" => Yii::t('agent', "Campaign deleted successfully")
        ];
    }

    /**
     * Return Campaign detail
     * @param string $store_uuid
     * @param string $utm_uuid
     * @return Campaign
     * 
     * @api {get} /campaigns/:id Get campaign detail
     * @apiName GetCampaignDetail
     * @apiGroup Campaign
     *
     * @apiSuccess {Array} campaign Campaign.
     */
    public function actionDetail($id)
    {
        return $this->findModel($id);
    }

    /**
     * @param $id
     * @return array|string[]
     * 
     * @api {post} /campaigns/click Click campaign
     * @apiName ClickCampaign
     * @apiGroup Campaign
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionClick($id)
    {
        $model = Campaign::find()->where([
            'utm_uuid' => $id
        ])->one();

        if ($model === null) {
            throw new NotFoundHttpException('The requested record does not exist.');
        }

        $model->no_of_clicks = $model->no_of_clicks + 1;

        if(!$model->save()) {
            return $this->campaignSaveFailedResponse(
                $model,
                "We've faced a problem tracking the campaign click"
            );
        }

        return [
            "operation" => "success"
        ];
    }

    private function campaignSaveFailedResponse($model, $message)
    {
        Yii::error([
            'message' => $message,
            'campaign_uuid' => isset($model->utm_uuid) ? $model->utm_uuid : null,
            'restaurant_uuid' => isset($model->restaurant_uuid) ? $model->restaurant_uuid : null,
            'errors' => $model->errors,
        ], __METHOD__);

        return [
            "operation" => "error",
            "message" => Yii::t('agent', $message)
        ];
    }

    /**
     * Finds the Campaign model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $utm_uuid
     * @return Campaign the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($utm_uuid)
    {
        $store = Yii::$app->accountManager->getManagedAccount();

        $model = $store->getCampaigns()->where([
            'utm_uuid' => $utm_uuid
        ])->one();

        if ($model !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested record does not exist.');
        }
    }
}
