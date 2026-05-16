<?php

namespace common\components;

use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;

class BlogManager extends BaseObject
{
    public $apiEndpoint = 'http://localhost:8080/v1';

    public $token;

    /**
     * @inheritdoc
     */
    public function init() {
        // Fields required by default
        $requiredAttributes = ['apiEndpoint', 'token'];

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

    public function listPost($page, $query = '', $limit = 10) {

        $queryParams = http_build_query([
            'limit' => $limit,
            'page' => $page,
            'query' => $query,
        ], '', '&', PHP_QUERY_RFC3986);

        $deploySiteEndpoint = $this->apiEndpoint . "/post?" . $queryParams;

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

    public function viewPost($id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/post/" . $id;

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
     * creates blog post
     * ---------------------------
    {
    "post_image": null,
    "post_video": null,
    "sort_number": 1,
    "slug": "fashion2",
    "blogPostCategories" : [{
    "blog_category_id": 1
    }],
    "blogPostDescriptions" : [{
    "language_code": "en",
    "title": "Fashion 75",
    "description": "fashion items 22"
    }]
    }
     */
    public function createPost($params) {

        $endpoint = $this->apiEndpoint . "/post";

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpoint)
            ->setFormat(Client::FORMAT_JSON)
            ->setData($params)
            //->setContent(json_encode($params))
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
                'Content-Type' => "application/json"
            ])
            ->send();

        return $response;
    }

    /**
     * update blog post
     * ---------------------------
     {
        "post_image": null,
        "post_video": null,
        "sort_number": 1,
        "slug": "fashion2",
        "blogPostCategories" : [{
            "blog_category_id": 1
        }],
        "blogPostDescriptions" : [{
            "language_code": "en",
            "title": "Fashion 75",
            "description": "fashion items 22"
        }]
     }
     */
    public function updatePost($id, $params) {

        $endpoint = $this->apiEndpoint . "/post/" . $id;

        $client = new Client();

        return $client->createRequest()
            ->setMethod('PATCH')
            ->setUrl($endpoint)
            ->setFormat(Client::FORMAT_JSON)
            ->setData($params)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
                'Content-Type' => "application/json"
            ])
            ->send();
    }

    /**
     * delete blog post
     * @param $id
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function deletePost($id) {

        $apiEndpoint = $this->apiEndpoint . "/post/" . $id;

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
     * list categories
     * @param $page
     * @param $query
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function listCategory($page, $query = '', $limit = 10) {

        $queryParams = http_build_query([
            'limit' => $limit,
            'page' => $page,
            'query' => $query,
        ], '', '&', PHP_QUERY_RFC3986);

        $deploySiteEndpoint = $this->apiEndpoint . "/category?" . $queryParams;

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
     * return category details
     * @param $id
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function viewCategory($id) {

        $deploySiteEndpoint = $this->apiEndpoint . "/category/" . $id;

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
     * creates blog category
     * ---------------------------
    {
        "category_image": null,
        "parent_category_id": null,
        "sort_number": 1,
        "slug": "fashion2",
        "blogCategoryDescriptions" : [{
            "id": 19,
            "language_code": "en",
            "title": "Fashion 75",
            "description": "fashion items 22"
        }]
    }
     */
    public function createCategory($params) {

        $endpoint = $this->apiEndpoint . "/category";

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpoint)
            ->setFormat(Client::FORMAT_JSON)
            ->setData($params)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();

        return $response;
    }

    /**
     * update blog category
     * ---------------------------
    {
        "category_image": null,
        "parent_category_id": null,
        "sort_number": 1,
        "slug": "fashion2",
        "blogCategoryDescriptions" : [{
            "id": 19,
            "language_code": "en",
            "title": "Fashion 75",
            "description": "fashion items 22"
        }]
    }
     */
    public function updateCategory($id, $params) {

        $endpoint = $this->apiEndpoint . "/category/" . $id;

        $client = new Client();

        return $client->createRequest()
            ->setMethod('PATCH')
            ->setUrl($endpoint)
            ->setFormat(Client::FORMAT_JSON)
            ->setData($params)
            ->addHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => 'request',
            ])
            ->send();
    }

    /**
     * delete blog category
     * @param $id
     * @return \yii\httpclient\Response
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function deleteCategory($id) {

        $apiEndpoint = $this->apiEndpoint . "/category/" . $id;

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

}
