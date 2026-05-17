<?php

namespace frontend\controllers;

use Yii;
use frontend\models\Category;
use frontend\models\CategorySearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\components\FileUploader;

/**
 * CategoryController implements the CRUD actions for Category model.
 */
class CategoryController extends Controller {

    public $enableCsrfValidation = false;

    /**
     * {@inheritdoc}
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'delete-category-image' => ['POST'],
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
     *
     * @param type $storeUuid
     * @return type
     */
    public function actionIndex($storeUuid) {

        $restaurant = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $searchModel = new CategorySearch();

        $count = $searchModel->search([], $restaurant->restaurant_uuid)->getCount();

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, $restaurant->restaurant_uuid);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
                    'count' => $count,
                    'restaurant' => $restaurant
        ]);
    }


    /**
     * Delete category image
     * @param type $storeUuid
     * @param type $itemUuid
     * @return boolean
     */
    public function actionDeleteCategoryImage($storeUuid, $categoryId) {


        $model = $this->findModel($categoryId, $storeUuid);


        $file_name = Yii::$app->request->getBodyParam("file");

        if ($model && $model->category_image == $file_name) {
          $model->deleteCategoryImage();

            $model->category_image = null;
            $model->save(false);

            return true;
        }
        return false;
    }


    /**
     * Displays a single Category model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id, $storeUuid) {
        return $this->render('view', [
                    'model' => $this->findModel($id, $storeUuid),
                    'storeUuid' => $storeUuid
        ]);
    }

    /**
     * Creates a new Category model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($storeUuid) {
        $model = new Category();
        $model->restaurant_uuid = Yii::$app->accountManager->getManagedAccount($storeUuid)->restaurant_uuid;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {

            // $categoryImage = \yii\web\UploadedFile::getInstances($model, 'image');



            // initialize FileUploader
            $FileUploader = new FileUploader('category_image', array(
                'limit' => 1,
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

            // get the fileList
            $categoryImage = $FileUploader->getFileList();



            if ($categoryImage)
                $model->uploadCategoryImage($categoryImage[0]['file']);


            return $this->redirect(['index', 'storeUuid' => $storeUuid]);
        }

        return $this->render('create', [
                    'model' => $model,
                    'storeUuid' => $storeUuid
        ]);
    }

    /**
     * Updates an existing Category model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id, $storeUuid) {
        $model = $this->findModel($id, $storeUuid);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {


            // $categoryImage = \yii\web\UploadedFile::getInstances($model, 'image');

            // initialize FileUploader
            $FileUploader = new FileUploader('category_image', array(
                'limit' => 1,
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

            // get the fileList
            $categoryImage = $FileUploader->getFileList();



            if ($categoryImage)
            $model->uploadCategoryImage($categoryImage[0]['file']);

            return $this->redirect(['index', 'storeUuid' => $storeUuid]);
        }

        return $this->render('update', [
                    'model' => $model,
                    'storeUuid' => $storeUuid
        ]);
    }

    /**
     * Deletes an existing Category model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id, $storeUuid) {
        $this->findModel($id, $storeUuid)->delete();

        return $this->redirect(['index', 'storeUuid' => $storeUuid]);
    }

    /**
     * Finds the Category model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Category the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id, $storeUuid)
    {
        $store = Yii::$app->accountManager->getManagedAccount($storeUuid);

        $model = Category::find()
            ->where([
                'category.category_id' => $id,
                'category.restaurant_uuid' => $store->restaurant_uuid
            ])
            ->one();

        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
