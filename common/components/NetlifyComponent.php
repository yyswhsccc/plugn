<?php

namespace common\components;

use common\models\Restaurant;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\httpclient\Client;
use yii\base\InvalidConfigException;
use common\models\PaymentMethod;

/**
 * Netlify  REST API class
 *
 * @author Saoud Al-Turki <saoud@plugn.io>
 * @link http://www.plugn.io
 */
class NetlifyComponent extends Component {

    private $apiEndpoint = 'https://api.netlify.com/api/v1';
    private $accountSlug = 'plugn';
    

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

    /**
     * creates a new site.
     * @param Restaurant $store
     * @param string $store_branch
     */
    public function createSite($store, $store_branch = "main") {

        $createSiteEndpoint = $this->apiEndpoint . "/sites";

       // $domain = str_replace("https://", "", $store->restaurant_domain);

        $url = parse_url($store->restaurant_domain);

        if($store_branch == "main") {
            $repo = "plugnio/plugn-store";
            $dir = "dist";
        } else {
            $repo = "plugnio/plugn-ionic";
            $dir = "www";
        }

        $siteParams = [
            "name" => $store->restaurant_uuid,
            "custom_domain" => $url['host'],
            "build_image" => "focal",
            "repo" => [
                "provider" => "github",
                "id" => 70150125,
                "force_ssl" => true,
                "installation_id" => "11420049",
                "repo" => $repo,
                "private" => true,
                "branch" => $store_branch,
                "cmd" => "export STORE=".$store->restaurant_uuid." && npm run build",
                "dir" => $dir
            ],
            "account_slug" => $this->accountSlug
        ];

        $client = new Client();

        return $client->createRequest()
                ->setMethod('POST')
                ->setUrl($createSiteEndpoint)
                ->setFormat(Client::FORMAT_JSON)
                ->setData($siteParams)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'User-Agent' => 'request',
                ])
                ->send();
    }

    /**
     *returns the specified site.
     * @param integer $page
     * @return type
     */
    public function listSiteData($page, $query = '') {

        $query = trim((string) $query);
        $queryParams = [
            'per_page' => 2,
            'page' => $page,
        ];

        if ($query !== '') {
            $queryParams['name'] = $query;
        }

        $queryParams = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $deploySiteEndpoint = $this->apiEndpoint . "/sites?" . $queryParams;

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($deploySiteEndpoint)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    /**
     *returns the specified site.
     * @param type $site_id
     * @return type
     */
    public function getSiteData($site_id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/sites/" . $site_id ;

        $client = new Client();
        $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($deploySiteEndpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'User-Agent' => 'request',
                ])
                ->send();

        return $response;
    }

    public function getSiteDns($site_id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/sites/" . $site_id . "/dns";

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl($deploySiteEndpoint)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    public function createDnsZone($store) {

        $deploySiteEndpoint = $this->apiEndpoint . "/dns_zones";

        $url = parse_url($store->restaurant_domain);

        $client = new Client();
        return $client->createRequest()
            ->setMethod('POST')
            ->setUrl($deploySiteEndpoint)
            ->setData([
                "account_slug" => "plugn",
                "site_id" => $store->site_id,
                "name" => $url["host"] //$store->name
            ])
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();
    }

    public function configureDNSForSite($site_id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/sites/" . $site_id . "/dns";

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('PUT')
            ->setUrl($deploySiteEndpoint)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    /**
     *  Provision SSL for a site
     * @param type $site_id
     * @return type
     */
    public function provisionSSL($site_id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/sites/" . $site_id . '/ssl';

        $client = new Client();

        $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($deploySiteEndpoint)
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'User-Agent' => 'request',
                ])
                ->send();

        return $response;
    }

    /**
     * delete site from netlify
     * @param $site_id
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function deleteSite($site_id) {

        $apiEndpoint = $this->apiEndpoint . "/sites/" . $site_id;

        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('DELETE')
            ->setUrl($apiEndpoint)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    /**
     * update site from netlify
     * @param $site_id
     * @param $params
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function updateSite($site_id, $params) {

        $apiEndpoint = $this->apiEndpoint . "/sites/" . $site_id;

        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('PATCH')
            ->setData($params)
            ->setUrl($apiEndpoint)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    /**
     * upgrade site to newer version
     * @param $store
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function upgradeSite($store) {

        if(!$store) {
            return false;
        }

        $params = [
            "build_image" => "focal",
            "repo" => [
                "provider" => "github",
                "id" => 70150125,
                "force_ssl" => true,
                "installation_id" => "11420049",
                "repo" => "plugnio/plugn-store",
                "private" => true,
                "branch" => "main",
                "cmd" => "export STORE=".$store->restaurant_uuid." && npm run build",
                "dir" => "dist"
            ],
            "account_slug" => $this->accountSlug
        ];

        return $this->updateSite($store->site_id, $params);
    }

    /**
     * downgrade site to older version
     * @param $store
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function downgradeSite($store) {

        if(!$store) {
            return false;
        }

        $params = [
            "build_image" => "focal",
            "repo" => [
                "provider" => "github",
                "id" => 70150125,
                "force_ssl" => true,
                "installation_id" => "11420049",
                "repo" => "plugnio/plugn-ionic",
                "private" => true,
                "branch" => "master",
                "cmd" => "export STORE=".$store->restaurant_uuid." && npm run build",
                "dir" => "www"
            ],
        ];

        return $this->updateSite($store->site_id, $params);
    }
}
