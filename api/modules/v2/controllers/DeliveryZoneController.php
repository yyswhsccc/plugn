<?php

namespace api\modules\v2\controllers;

use agent\models\Country;
use api\models\State;
use common\models\Area;
use common\models\OpeningHour;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Exp;
use Yii;
use yii\db\Expression;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use api\models\Item;
use api\models\Category;
use api\models\City;
use api\models\Restaurant;
use common\models\ItemImage;
use common\models\AreaDeliveryZone;
use common\models\DeliveryZone;
use yii\web\NotFoundHttpException;

class DeliveryZoneController extends BaseController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove authentication filter for cors to work
        unset($behaviors['authenticator']);

        // Allow XHR Requests from our different subdomains and dev machines
        $behaviors['corsFilter'] = [
            'class' => Cors::className(),
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
                    'X-Pagination-Total-Count',
                    'Mixpanel-Distinct-ID'
                ],
            ],
        ];

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
     * Return List of countries available for delivery
     * 
     * @api {GET} /delivery-zones/list-of-countries List of countries
     * @apiName ListOfCountries
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionListOfCountries($restaurant_uuid)
    {
        $keyword = Yii::$app->request->get("keyword");
        $onlyCountries = Yii::$app->request->get("onlyCountries");

        $store = $this->findStore($restaurant_uuid);

        $subQuery = $store->getDeliveryZones()
            ->select('delivery_zone.country_id')
            ->distinct();

        $query = Country::find()
            ->andWhere(['IN', 'country.country_id', $subQuery]);

        if($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'country_name', $keyword],
                ['like', 'country_name_ar', $keyword],
            ]);
        }

        $countries = $query
            ->all();

        if($onlyCountries) {
            return $countries;
        }

        $data = [];

        foreach ($countries as $country) {

            $areas = $store->getAreaDeliveryZones()
                ->joinWith(['deliveryZone'])
                ->andWhere(new Expression('state_id IS NOT NULL OR city_id IS NOT NULL OR area_id IS NOT NULL'))
                //->andWhere(['area_delivery_zone.country_id' => $country->country_id])
                ->andWhere(['delivery_zone.country_id' => $country->country_id])
                ->count();

            $deliveryZone = null;

            $deliveryZone = $store->getDeliveryZones()
                ->andWhere(['delivery_zone.country_id' => $country->country_id])
                ->one();

            $data[] = array_merge($country->attributes, [
                'areas' => $areas,
                'deliveryZone' => $deliveryZone,
                'delivery_zone_id' => $deliveryZone ? $deliveryZone->delivery_zone_id : null
            ]);
        }

        return $data;
    }

    /**
     * Return List of business locations that support pickup
     * 
     * @api {GET} /delivery-zones/list-of-pickup-locations List of pickup locations
     * @apiName ListOfPickupLocations
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionListPickupLocations($restaurant_uuid)
    {
        if ($store = Restaurant::findOne($restaurant_uuid)) {

            $pickupLocations = $store->getPickupBusinessLocations()->asArray()->all();

            foreach ($pickupLocations as $key => $pickupLocation) {

                unset($pickupLocations[$key]['mashkor_branch_id']);
                unset($pickupLocations[$key]['armada_api_key']);
            }

            return $pickupLocations;


        } else {
            return [
                'operation' => 'error',
                'message' => 'Store Uuid is invalid'
            ];
        }
    }

    /**
     * Return Delivery zone
     * 
     * @api {GET} /delivery-zones/:delivery_zone_id Delivery zone
     * @apiName DeliveryZone
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionGetDeliveryZone($restaurant_uuid, $delivery_zone_id)
    {

        $area_id = Yii::$app->request->get("area_id");


        if ($store = Restaurant::findOne($restaurant_uuid)) {


            if ($deliveryZone = $store->getDeliveryZones()->where(['delivery_zone_id' => $delivery_zone_id])->asArray()->joinWith(['businessLocation'])->one()) {

                unset($deliveryZone['businessLocation']['armada_api_key']);
                unset($deliveryZone['businessLocation']['mashkor_branch_id']);


                if ($area_id && !AreaDeliveryZone::find()->where(['area_id' => $area_id, 'delivery_zone_id' => $delivery_zone_id])->exists()) {
                    return [
                        'operation' => 'error',
                        'message' => 'delivery zone id is invalid'
                    ];
                }

                $deliveryTime = intval($deliveryZone['delivery_time']);
                $deliveryTimeInMin = intval($deliveryZone['delivery_time']);

                if (DeliveryZone::TIME_UNIT_DAY == $deliveryZone['time_unit'])
                    $deliveryTime = $deliveryTime * 24 * 60 * 60;
                else if (DeliveryZone::TIME_UNIT_HRS == $deliveryZone['time_unit'])
                    $deliveryTime = $deliveryTime * 60 * 60;
                else if (DeliveryZone::TIME_UNIT_MIN == $deliveryZone['time_unit'])
                    $deliveryTime = $deliveryTime * 60;

                if (DeliveryZone::TIME_UNIT_DAY == $deliveryZone['time_unit'])
                    $deliveryTimeInMin = $deliveryTimeInMin * 24 * 60;
                else if (DeliveryZone::TIME_UNIT_HRS == $deliveryZone['time_unit'])
                    $deliveryTimeInMin = $deliveryTimeInMin * 60;

                $deliveryZone['delivery_time_in_min'] = $deliveryTimeInMin;

                $deliveryZone['delivery_time'] = Yii::$app->formatter->asDuration($deliveryTime);

                Yii::$app->formatter->language = 'ar-KW';
                $deliveryZone['delivery_time_ar'] = Yii::$app->formatter->asDuration(intval($deliveryTime));

                $deliveryZone['tax'] = $deliveryZone['delivery_zone_tax'] ? $deliveryZone['delivery_zone_tax'] : $deliveryZone['businessLocation']['business_location_tax'];

                return $deliveryZone;

            } else {
                return [
                    'operation' => 'error',
                    'message' => 'delivery zone id is invalid'
                ];
            }

        } else {
            return [
                'operation' => 'error',
                'message' => 'Store Uuid is invalid'
            ];
        }
    }

    /**
     * @return void
     * 
     * @api {GET} /delivery-zones/city-by-location City by location
     * @apiName CityByLocation
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCityByLocation() {
        $latitude = Yii::$app->request->get('latitude');
        $longitude = Yii::$app->request->get('longitude');
        $postal_code = Yii::$app->request->get("postal_code");

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return [
                'operation' => 'error',
                'message' => 'Latitude and longitude are invalid'
            ];
        }

        // call google api to get country name, lat, long

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'latlng' => $latitude . ',' . $longitude,
        ], '', '&', PHP_QUERY_RFC3986);

        return City::addByGoogleAPIResponse($url, null, null, $postal_code);
    }

    /**
     * return delivery zone for tax + delivery fee details by location provided
     * @return string[]
     * @throws NotFoundHttpException
     * 
     * @api {GET} /delivery-zones/by-location Delivery zone by location
     * @apiName DeliveryZoneByLocation
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionByLocation()
    {
        $area_id = Yii::$app->request->get('area_id');
        $city_id = Yii::$app->request->get('city_id');
        $state_id = Yii::$app->request->get("state_id");

        if (!$state_id && !$area_id && !$city_id) {
            return [
                "operation" => "error",
                "message" => "Location details missing"
            ];
        }

        $query = $this->findStore()
            ->getAreaDeliveryZones();

        if ($area_id) //for kuwait
        {
            $area = Area::find()
                ->with(['city'])
                ->andWhere(['area_id' => $area_id])
                ->one();

            if(!$area || !$area->city) {
                return null;
            }

            //area or whole country, not having states in Kuwait

            $query->andWhere([
                'OR',
                new Expression('area_delivery_zone.country_id="'.$area->city->country_id.'" AND area_delivery_zone.state_id IS NULL 
                    AND area_delivery_zone.city_id IS NULL AND area_delivery_zone.area_id IS NULL'),
                ['area_delivery_zone.area_id' => $area_id]
            ]);
        }
        else if ($city_id)
        {
            $city = \common\models\City::find()
                ->andWhere(['city_id' => $city_id])
                ->one();

            if(!$city) {
                return null;
            }

            //city or whole country

            $conditions = [
                'OR',
                new Expression('area_delivery_zone.country_id="'.$city->country_id.'" AND area_delivery_zone.state_id IS NULL 
                    AND area_delivery_zone.city_id IS NULL AND area_delivery_zone.area_id IS NULL'),
                ['area_delivery_zone.city_id' => $city_id]
            ];

            // or whole state, some cities might not have state, so checking if having state

            if($city->state_id) {
                $conditions[] = new Expression('area_delivery_zone.state_id="'.$city->state_id.'" AND 
                    area_delivery_zone.city_id IS NULL AND 
                    area_delivery_zone.area_id IS NULL');
            }

            $query->andWhere($conditions);
        }
        else if ($state_id)
        {
            $state = \common\models\State::find()
                ->andWhere(['state_id' => $state_id])
                ->one();

            if(!$state) {
                return null;
            }

            //delivering to whole state or whole country

            $query->andWhere([
                "OR",
                new Expression('area_delivery_zone.state_id="'.$state_id.'" AND 
                    area_delivery_zone.city_id IS NULL AND 
                    area_delivery_zone.area_id IS NULL'),
                new Expression('area_delivery_zone.country_id="'.$state->country_id.'" AND 
                    area_delivery_zone.city_id IS NULL AND 
                    area_delivery_zone.area_id IS NULL')
            ]);
        }

        return $query->one();
    }

    /**
     * Return pickup location
     * 
     * @api {GET} /delivery-zones/get-pickup-location Get pickup location
     * @apiName GetPickupLocation
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionGetPickupLocation($restaurant_uuid, $pickup_location_id)
    {
        if ($store = Restaurant::findOne($restaurant_uuid)) {

            if ($pickupLocation = $store->getBusinessLocations()->where(['business_location_id' => $pickup_location_id])->asArray()->one()) {

                unset($pickupLocation['armada_api_key']);
                unset($pickupLocation['mashkor_branch_id']);

                return $pickupLocation;
            } else {
                return [
                    'operation' => 'error',
                    'message' => 'pick up location id is invalid'
                ];
            }

        } else {
            return [
                'operation' => 'error',
                'message' => 'Store Uuid is invalid'
            ];
        }
    }

    /**
     * Return list of areas available for delivery
     * 
     * @api {GET} /delivery-zones/list-of-areas List of areas
     * @apiName ListOfAreas
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionListOfAreas($restaurant_uuid, $country_id)
    {
        if ($store = Restaurant::findOne($restaurant_uuid)) {

            $countryCities = City::find()
                ->andWhere(['country_id' => $country_id])
                ->asArray()
                ->all();

            if ($countryCities) {

                $areaDeliveryZones = $store->getAreaDeliveryZonesForSpecificCountry($country_id)->asArray()->all();

                foreach ($countryCities as $cityKey => $city) {
                    foreach ($areaDeliveryZones as $areaDeliveryZoneKey => $areaDeliveryZone) {

                        if (isset($areaDeliveryZone['area'])) {
                            if ($areaDeliveryZone['area']['city_id'] == $city['city_id']) {
                                $countryCities[$cityKey]['areas'][] = $areaDeliveryZone;
                            }
                        } else {
                            $countryCities[$cityKey]['areas'][] = $areaDeliveryZone;
                        }
                    }
                }
            }

            $citiesData = [];
            foreach ($countryCities as $key => $city) {
                if (isset($city['areas']))
                    $citiesData [] = $city;
            }


            if (!empty($citiesData))
                return $citiesData;


        } else {
            return [
                'operation' => 'error',
                'message' => 'Store Uuid is invalid'
            ];
        }
    }

    /**
     * Return list of states available for delivery
     * 
     * @api {GET} /delivery-zones/states States
     * @apiName States
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionStates($country_id)
    {
        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $keyword = Yii::$app->request->get("keyword");

        $country = Country::findOne($country_id);

        if (!$country) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $country->getStates("\api\models\State");

        if ($store_id) {
            $query->joinWith('areaDeliveryZones', true, 'inner join')
                ->andWhere(['area_delivery_zone.restaurant_uuid' => $store_id]);
        }

        if ($keyword) {
            $query->andWhere(['like', 'name', $keyword]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of areas available for city
     * 
     * @api {GET} /delivery-zones/city-areas City areas
     * @apiName CityAreas
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCityAreas($city_id)
    {
        $keyword = Yii::$app->request->get("keyword");

        $city = City::findOne($city_id);

        if (!$city) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $city->getAreas("\common\models\Area");

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'area_name', $keyword],
                ['like', 'area_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of areas available for country
     * 
     * @api {GET} /delivery-zones/country-areas Country areas
     * @apiName CountryAreas
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCountryAreas($country_id)
    {
        $keyword = Yii::$app->request->get("keyword");

        $country = Country::findOne($country_id);

        if (!$country) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $country->getAreas("\common\models\Area");

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'area_name', $keyword],
                ['like', 'area_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of states available for country
     * 
     * @api {GET} /delivery-zones/country-states Country states
     * @apiName CountryStates
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCountryStates($country_id)
    {
        $keyword = Yii::$app->request->get("keyword");

        $country = Country::findOne($country_id);

        if (!$country) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $country->getStates("\api\models\State");

        if ($keyword) {
            $query->andWhere(['like', 'name', $keyword]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => false
        ]);
    }

    /**
     * Return list of cities available for state
     * 
     * @api {GET} /delivery-zones/country-cities Country cities
     * @apiName CountryCities
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCountryCities($country_id)
    {
        $keyword = Yii::$app->request->get("keyword");

        $country = Country::findOne($country_id);

        if (!$country) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $country->getCities("\api\models\City");

        if($country && $country->iso == "KW") {
            $query->andWhere(new Expression('state_id IS NULL'));
            //hide areas added as city in kuwait by google api
        }

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'city_name', $keyword],
                ['like', 'city_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of cities available for state
     * 
     * @api {GET} /delivery-zones/state-cities State cities
     * @apiName StateCities
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionStateCities($state_id)
    {

        $keyword = Yii::$app->request->get("keyword");

        $state = State::findOne($state_id);

        if (!$state) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $state->getCities("\api\models\City");

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'city_name', $keyword],
                ['like', 'city_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of cities available for delivery
     * 
     * @api {GET} /delivery-zones/cities Cities
     * @apiName Cities
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCities($state_id)
    {

        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $keyword = Yii::$app->request->get("keyword");

        $state = State::findOne($state_id);

        if (!$state) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $query = $state->getCities("\api\models\City");

        if ($store_id) {
            $query->joinWith('areaDeliveryZones', true, 'inner join')
                ->andWhere(['area_delivery_zone.restaurant_uuid' => $store_id]);
        }

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'city_name', $keyword],
                ['like', 'city_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query
        ]);
    }

    /**
     * Return list of Countries available for delivery
     * 
     * @api {GET} /delivery-zones/countries Countries
     * @apiName Countries
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionCountries()
    {

        $keyword = Yii::$app->request->get("keyword");

        $query = Country::find();

        if ($keyword) {
            $query->andWhere([
                'OR',
                ['like', 'country_name', $keyword],
                ['like', 'country_name_ar', $keyword]
            ]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => false
        ]);
    }

    /**
     * @return array|string[]
     * 
     * @api {GET} /delivery-zones/get-delivery-time Get delivery time
     * @apiName GetDeliveryTime
     * @apiGroup DeliveryZone
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionGetDeliveryTime()
    {
        $delivery_zone_id = Yii::$app->request->get("delivery_zone_id");
        $cart = Yii::$app->request->getBodyParam("cart");

        $store = $this->findStore();

        if (!$store->schedule_order) {
            return [
                'operation' => 'error',
                'message' => "Unfortunately we don't currently supporting this feature."
            ];
        }

        $deliveryZone = $store->getDeliveryZones()
            ->where(['delivery_zone_id' => $delivery_zone_id])
            ->one();

        if (!$deliveryZone) {
            return [
                'operation' => 'error',
                'message' => "Unfortunately we don't currently deliver to the selected area."
            ];
        }

        if ($deliveryZone->time_unit == DeliveryZone::TIME_UNIT_MIN)
            $deliveryTime = intval($deliveryZone->delivery_time);
        else if ($deliveryZone->time_unit == DeliveryZone::TIME_UNIT_HRS)
            $deliveryTime = intval($deliveryZone->delivery_time) * 60;
        else if ($deliveryZone->time_unit == DeliveryZone::TIME_UNIT_DAY)
            $deliveryTime = intval($deliveryZone->delivery_time) * 24 * 60;

        $prepTime = 0;

        if ($cart && sizeof($cart) > 0) {
            foreach ($cart as $item) {
                if (isset($item['prep_time_in_min']))//&& $item['prep_time_in_min'] > $prepTime
                    $prepTime += $item['prep_time_in_min'];
            }
        }

        $deliveryData = OpeningHour::getDeliveryTime($deliveryTime, $prepTime, $store);

        return [
            'deliveryData' => $deliveryData
        ];
    }

    /**
     * Finds the Item model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Item the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findStore($id = null)
    {
        if (!$id)
            $id = Yii::$app->request->getHeaders()->get('Store-Id');

        $model = Restaurant::findOne($id);

        if ($model !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
