<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "city".
 *
 * @property int $city_id
 * @property int $state_id
 * @property int $country_id
 * @property string $city_name
 * @property string $city_name_ar
 * @property boolean $is_deleted
 *
 * @property Area[] $areas
  * @property Country $country
 */
class City extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'city';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['city_name', 'city_name_ar', 'country_id'], 'required'],
            [['city_name', 'city_name_ar'], 'string', 'max' => 255],
            [['state_id'], 'exist', 'skipOnError' => true, 'targetClass' => State::className (), 'targetAttribute' => ['state_id' => 'state_id']],
            [['country_id'], 'exist', 'skipOnError' => true, 'targetClass' => Country::className (), 'targetAttribute' => ['country_id' => 'country_id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'city_id' => Yii::t('app','City ID'),
            'state_id' => Yii::t('app','State ID'),
            'country_id' => Yii::t('app','Country ID'),
            'city_name' => Yii::t('app','City Name'),
            'city_name_ar' => Yii::t('app','City Name in Arabic'),
        ];
    }


    /**
     * Get country object from Google API response
     * @param type $response
     * @return type
     */
    public static function getGoogleAPICountryObject($response)
    {
        foreach($response->results[0]->address_components as $component) {
            if(in_array('country', $component->types)) {
                return $component;
            }
        }
    }

    /**
     * @param $response
     * @return void
     */
    public static function getGoogleAPIStateObject($response)
    {
        foreach ($response->results as $key => $address_component) {
            foreach($address_component->address_components as $component) {
                if(in_array('administrative_area_level_1', $component->types)) {
                    return $component;
                }
            }
        }
    }

    /**
     * @param $response
     * @return mixed|void
     */
    public static function getGoogleAPIAreaObject($response)
    {
        foreach ($response->results as $key => $address_component) {
            foreach($address_component->address_components as $component) {
                if(in_array('sublocality_level_1', $component->types)) {
                    return $component;
                }
            }
        }
    }

    /**
     * Get city object from Google API response
     * @param type $response
     * @return type
     */
    public static function getGoogleAPICityObject($response)
    {
        foreach ($response->results as $key => $address_component) {
            foreach($address_component->address_components as $component) {
                if(in_array('locality', $component->types)) {
                    return $component;
                }
            }
        }

        foreach ($response->results as $key => $address_component) {
            foreach($address_component->address_components as $component) {
                if(in_array('administrative_area_level_3', $component->types)) {
                    return $component;
                }
            }
        }

        foreach ($response->results as $key => $address_component) {
            foreach($address_component->address_components as $component) {
                if(in_array('administrative_area_level_1', $component->types)) {
                    return $component;
                }
            }
        }

//        foreach($response->results[0]->address_components as $component) {
//            if(in_array('locality', $component->types)) {
//                return $component;
//            }
//        }

        //in case not able to find city, return political area

//        foreach($response->results[0]->address_components as $component) {
//            if(in_array('administrative_area_level_1', $component->types)) {
//                return $component;
//            }
//        }
    }

    /**
     * Add city by info we got in ipinfo api response in app
     * @param type $city_name
     * @param type $country_name
     * @param type $country_code
     * @param type $latitude
     * @param type $longitude
     */
    public static function addByIpInfo($city_name, $country_name, $country_code, $latitude, $longitude, $postal_code = null)
    {
        //add country if not available

        $country = Country::find()
            ->where(['country_name' => $country_name])
            ->one();

        if(!$country)
        {
            $countryInfo = json_decode(file_get_contents('https://restcountries.eu/rest/v2/alpha/' . $country_code));

            $country = new Country;
            $country->country_name = $country_name;
            $country->country_name_ar = $country_name;
            $country->iso = strtolower($country_code);
            $country->country_code = isset($countryInfo->callingCodes[0]) ? $countryInfo->callingCodes[0]: null;
            $country->save();
        }

        //todo: add state

        $model = new City;
        //todo: state_id
        $model->country_id = $country->country_id;
        $model->city_name = $city_name;
        $model->city_name_ar = $city_name;
        $model->save();

        $city = City::find()
            ->with('country')
            ->where(['city_uuid' => $model->city_id])
            ->asArray()
            ->one();

        //todo: add area
        /*$model->city_latitude = $latitude;
        $model->city_longitude = $longitude;
        $model->city_postal_code = $postal_code;
        */

        return [
            'operation' => 'success',
            'city' => $city
        ];
    }

    public static function addByGoogleAPIResponseForOther($response, $country) {

        $objState = self::getGoogleAPIStateObject($response);
        $cityObject = self::getGoogleAPICityObject($response);
        //$areaObject = self::getGoogleAPIAreaObject($response);

        if(!$cityObject) {
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }

        $city_name = $cityObject->long_name;

        //$area_name = $areaObject? $areaObject->long_name: null;

        $city = City::find()
            //->with('country')
            ->andWhere(['city_name' => $city_name, 'country_id' => $country->country_id])
            //->asArray()
            ->one();

        //if city already available

        if($city) {

            $state = $city->getState()->one();

            // check if area already available

            $area = null;

            /*if($areaObject) {
                $area = self::_isGooglePlaceIsArea($area_name, $city, $response->results[0]);
            }*/

            return [
                'operation' => 'success',
                "area" => $area,
                'city' => $city,
                "state" => $state,
                "country" => $city->country,
            ];
        }

        //add area + city + state as not available

        //add state

        $state = null;

        // make sure state name not same as city (as some country might not have states)

        if ($objState && $objState->long_name != $city_name) {

            $state = State::find()
                ->andWhere([
                    'name' => $objState->long_name,
                    "country_id" => $country->country_id])
                ->one();

            if(!$state) {
                $state = new State();
                $state->country_id = $country->country_id;
                $state->name = $objState->long_name;
                $state->save();
            }
        }

        //add city

        $city = new City;
        $city->state_id = $state? $state->state_id: null;
        $city->country_id = $country->country_id;
        $city->city_name = $city_name;
        $city->city_name_ar = $city_name;
        $city->save();

        // check if area already available

        /*$area = null;

        if($areaObject) {
            $area = self::_isGooglePlaceIsArea($area_name, $city, $response->results[0]);
        }*/

        return [
            'operation' => 'success',
            //"area" => $area,
            'city' => $city,
            "state" => $state,
            "country" => $city->country
        ];
    }

    public static function addByGoogleAPIResponseForKuwait($response, $country) {

        $objState = self::getGoogleAPIStateObject($response);
        $cityObject = self::getGoogleAPICityObject($response);
        $areaObject = self::getGoogleAPIAreaObject($response);

        if(!$areaObject || !$cityObject) {
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }

        $city_name = $cityObject->long_name;

        $area_name = $areaObject? $areaObject->long_name: null;

        $city = City::find()
            //->with('country')
            ->andWhere([
                'city_name' => $city_name,
                'country_id' => $country->country_id
            ])
            //->asArray()
            ->one();

        //if city already available

        if($city) {

            $state = $city->getState()->one();

            // check if area already available

            $area = null;

            if($areaObject) {
                $area = self::_isGooglePlaceIsArea($area_name, $city, $response->results[0]);
            }

            return [
                'operation' => 'success',
                "area" => $area,
                'city' => $city,
                "state" => $state,
                "country" => $city->country,
            ];
        }

        //add area + city + state as not available

        //add state

        $state = null;

        // make sure state name not same as city (as some country might not have states)

        if ($objState && $objState->long_name != $city_name) {

            $state = State::find()
                ->andWhere([
                    'name' => $objState->long_name,
                    "country_id" => $country->country_id])
                ->one();

            if(!$state) {
                $state = new State();
                $state->country_id = $country->country_id;
                $state->name = $objState->long_name;
                $state->save();
            }
        }

        //add city

        $city = new City;
        $city->state_id = $state? $state->state_id: null;
        $city->country_id = $country->country_id;
        $city->city_name = $city_name;
        $city->city_name_ar = $city_name;
        $city->save();

        //for kuwait sub-locality = area

        $area = null;

        if ($areaObject) {//$country_name == "Kuwait"

            $area = self::_isGooglePlaceIsArea($area_name, $city, $response->results[0]);

            //add area
/*
            $area = new Area;
            $area->city_id = $city->city_id;
            $area->area_name = $area_name;
            $area->area_name_ar = $area_name;
            $area->latitude = $response->results[0]->geometry->location->lat;
            $area->longitude= $response->results[0]->geometry->location->lng;
            $area->save();*/
        }

        // if no area then return city, state, country

        return [
            'operation' => 'success',
            'area' => $area,
            'city' => $city,
            "state" => $state,
            "country" => $city->country
        ];
    }

    /**
     * todo:
     * 1) fix adding cities without states
     * 2) fix adding duplicate cities on providing different areas of same cities
     * 3) test function by providing different lacation (unknown)
     * Add city if not available by Google API response
     * https://maps.googleapis.com/maps/api/geocode/json?latlng=22.260728864087454,73.13252570265993&key=AIzaSyCGCusw5MJ_aJwyzIi4q7pJY71k2CNXAbA&location_type=APPROXIMATE
     * @param string $url
     * @param type $city_name
     * @param type $area_name
     * @return type
     */
    public static function addByGoogleAPIResponse($url, $city_name = null, $area_name = null, $postal_code = null)
    {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query([
            'key' => Yii::$app->params['google_api_key'],
            'location_type' => 'APPROXIMATE',
        ], '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, str_replace(' ', '+', $url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseContent = curl_exec($ch);
        if ($responseContent === false) {
            Yii::error('Unable to fetch Google geocode response: ' . curl_error($ch), __METHOD__);
            curl_close($ch);
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }
        curl_close($ch);

        $response = json_decode($responseContent);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error('Invalid Google geocode response: ' . json_last_error_msg(), __METHOD__);
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }

        if(isset($response->result))
            $response->results = [$response->result];

        if(!$response || empty($response->results))
        {
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }

        $objCountry = self::getGoogleAPICountryObject($response);

        if(empty($objCountry->long_name) || empty($response->results[0]->geometry->location->lat)) {
            return [
                'operation' => 'error',
                'message' => Yii::t('app', 'Sorry not able to find your city!')
            ];
        }

        $country_name = $objCountry->long_name;
        $country_code = $objCountry->short_name;

        //add country if not available

        $country = Country::find()
            ->where(['country_name' => $country_name])
            ->one();

        if(!$country)
        {
            $countryInfo = json_decode(file_get_contents('https://restcountries.eu/rest/v2/alpha/' . $country_code));

            $country = new Country;
            $country->country_name = $country_name;
            $country->country_name_ar = $country_name;
            $country->iso = strtolower($country_code);
            $country->country_code = isset($countryInfo->callingCodes[0]) ? $countryInfo->callingCodes[0]: null;
            $country->save();
        }

        if($country->iso == "KW") {
            return self::addByGoogleAPIResponseForKuwait($response, $country);
        }

        return self::addByGoogleAPIResponseForOther($response, $country);
    }

    /**
     * Check if it is area the place we getting by place_id from google
     * @param type $area_name
     * @param type $city
     * @param type $response
     * @return \common\models\Area
     */
    static function _isGooglePlaceIsArea($area_name, $city, $response) {

        if(
            $area_name &&
            !in_array('locality', $response->types) &&
            !in_array('administrative_area_level_1', $response->types)
        ) {

            $area = Area::find()
                ->andWhere([
                    'OR',
                    [
                        'area_name' => $area_name
                    ],
                    [
                        'area_name_ar' => $area_name
                    ],
                ])
                ->one();

            if(!$area) {
                $area = new Area;
                $area->area_name = $area_name;
                $area->area_name_ar = $area_name;
                $area->city_id = $city['city_id'];
                $area->latitude = $response->geometry->location->lat;
                $area->longitude = $response->geometry->location->lng;
                $area->save();
            }

            return $area;
        }
    }

    /**
     * @inheritdoc
     */
    public function extraFields() {
        return [
           'areas',
           'state',
           'country'
        ];
    }

    /**
     * Gets query for [[Country]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCountry($modelClass = "\common\models\Country")
    {
        return $this->hasOne($modelClass::className(), ['country_id' => 'country_id']);
    }

    /**
     * Gets query for [[State]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getState($modelClass = "\common\models\State")
    {
        return $this->hasMany($modelClass::className(), ['state_id' => 'state_id']);
    }

    /**
     * Gets query for [[Areas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAreas($modelClass = "\common\models\Area")
    {
        return $this->hasMany($modelClass::className(), ['city_id' => 'city_id']);
    }

    /**
     * Gets query for [[Areas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAreaDeliveryZones($modelClass = "\common\models\AreaDeliveryZone")
    {
        return $this->hasMany($modelClass::className(), ['city_id' => 'city_id']);
    }

        /**
     * Gets query for [[RestaurantDeliveryAreas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurantDeliveryAreas($modelClass = "\common\models\RestaurantDelivery")
    {
        return $this->hasMany ($modelClass::className (), ['area_id' => 'area_id'])->via ('areas')->with ('area');
    }

    /**
     * @return query\CityQuery
     */
    public static function find()
    {
        return new query\CityQuery(get_called_class());
    }
}
