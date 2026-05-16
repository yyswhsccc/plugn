<?php

namespace common\components;

use common\models\Country;
use common\models\Currency;
use GuzzleHttp\Exception\ClientException;
use Yii;

class Ipstack {

    // Prefix to the IP cache object
//    public $ipPrefix = "client_ip:";
    // IP Cache duration
//    public $cacheDuration = 1 * 60 * 60; //1 hours
    public $accessKey;

    public function locate($populateData = true) {

        if (!isset(Yii::$app->request)) {
            return null;
        }

        // Get initial IP address of requester
        $ip = method_exists(Yii::$app->request, "getRemoteIP")?
            trim((string) Yii::$app->request->getRemoteIP()): null;

        // Check if request is forwarded via load balancer or cloudfront on behalf of user
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];

            // as "X-Forwarded-For" is usually a list of IP addresses that have routed
            $IParray = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));

            // Get the first ip from forwarded array to get original requester
            if (!empty($IParray)) {
                $ip = $IParray[0];
            }
        }

        if (!$ip || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        // Build url used for ip check
        //$url = 'https://api.ipstack.com/' . $ip . '?access_key=' . $this->accessKey;
        $url = 'https://ipinfo.io/' . rawurlencode($ip) . '/json?' . http_build_query([
            'token' => $this->accessKey,
        ], '', '&', PHP_QUERY_RFC3986);

        // Check if calling from localhost
        if ($ip == '::1' || $ip == '127.0.0.1') {
            //$url = 'https://api.ipstack.com/check?access_key=' . $this->accessKey;
            $url = 'https://ipinfo.io/json?' . http_build_query([
                'token' => $this->accessKey,
            ], '', '&', PHP_QUERY_RFC3986);
        }

        // Return IP info from cache OR make a request for new data then cache
        //return Yii::$app->cache->getOrSet($this->ipPrefix.$ip, function () use ($url) {
        // Initiate the request object
        $http = new \GuzzleHttp\Client(['base_uri' => $url]);

        try {
            $responseObj = $http->request('GET');
        } catch (ClientException $e) {
            Yii::error($e);
            return false;
        } catch (\Exception $e) {
            Yii::error($e);
            return false;
        }

        $result = null;

        try {
            $result = \GuzzleHttp\json_decode($responseObj->getBody()->getContents());
        }catch (\Exception $e) {
            Yii::error($e);
            return null;
        }

        if($populateData) {

            // save latest records from our table

            if (isset($result->currency->code)) {

                $currency = Currency::find()
                    ->where(['code' => $result->currency->code])
                    ->one();

                if($currency) {
                    $result->currency = $currency;
                }
            }

            //Fix: https://www.pivotaltracker.com/story/show/165662472

            if (!isset($result->city)) {
                if (!empty($result->region_name))
                    $result->city = $result->region_name;
                else if (!empty($result->location) && !empty($result->location->capital))
                    $result->city = $result->location->capital;
                else if (!empty($result->continent_name))
                    $result->city = $result->continent_name;
            }

            if (isset($result->country_code)) {

                $country = Country::find()
                    ->where(['iso' => $result->country_code])
                    ->one();

                $result->country = $country;

            //for ipinfo
            } else if (isset($result->country) && is_string($result->country)) {

                $country = Country::find()
                    ->where(['iso' => $result->country])
                    ->one();

                if(!$country) {
                    Yii::error("Country not found with iso code: " . $result->country);
                }

                $result->country = $country;

                if(empty($result->currency)) {
                    if ($country) {
                        $result->currency = $country->currency;
                    } else {
                        $result->currency = Currency::findOne(['code' => "KWD"]);
                    }
                }
            }
        }

        return $result;

        //}, $this->cacheDuration);
    }
}
