<?php

namespace common\components;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\base\InvalidConfigException;

/**
 * Google map REST API class
 *
 * @author Saoud Al-Turki <saoud@plugn.io>
 * @link http://www.plugn.io
 */
class GoogleMapComponent extends Component {

    private $apiEndpoint = 'https://maps.google.com/maps/api';

    public $token;


    /**
     * @inheritdoc
     */
    public function init() {
        // Fields required by default
        $requiredAttributes = ['token'];

        // Process Validation
        foreach ($requiredAttributes as $attribute) {
            if ($this->$attribute === null) {
                throw new InvalidConfigException(strtr('"{class}::{attribute}" cannot be empty.', [
                    '{class}' => static::className(),
                    '{attribute}' => '$' . $attribute
                ]));
            }
        }


        parent::init();
    }


    public function getReverseGeocodeing($lat,$lng) {

      $url = $this->apiEndpoint . '/geocode/json?' . http_build_query([
          'latlng' => $lat . ',' . $lng,
          'key' => $this->token,
          'language' => 'en',
      ], '', '&', PHP_QUERY_RFC3986);

        $client = new Client();
        $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->addHeaders([
                    'content-type' => 'application/json',
                ])
                ->send();

        return $response;
    }

}
