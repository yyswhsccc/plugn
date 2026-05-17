<?php

namespace api\modules\v2\controllers;

use Yii;
use yii\rest\Controller;


class BaseController extends Controller
{
    private const PAYMENT_RETURN_FALLBACK_URL = 'https://www.plugn.io';

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // remove authentication filter for cors to work
        unset($behaviors['authenticator']);

        // Allow XHR Requests from our different subdomains and dev machines
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::className(),
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

        // Bearer Auth checks for Authorize: Bearer <Token> header to login the user
        $behaviors['authenticator'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::className(),
        ];

        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
        $behaviors['authenticator']['except'] = ['options'];

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
     * @param \yii\base\Action $action
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {
        if(!parent::beforeAction($action)) {
            return false;
        }

        if(Yii::$app->user->identity) {
            Yii::$app->eventManager->setUser(Yii::$app->user->getId(), [
                'name' => trim(Yii::$app->user->identity->customer_name),
                'email' => Yii::$app->user->identity->customer_email,
            ]);
        }

        return true;
    }

    protected function buildRestaurantReturnUrl($restaurant, $path)
    {
        $domain = $restaurant && !empty($restaurant->restaurant_domain)
            ? trim($restaurant->restaurant_domain)
            : '';

        if ($domain === '' || preg_match('/[\s\x00-\x1F\x7F]/', $domain)) {
            Yii::warning('Missing or malformed restaurant payment return domain.', __METHOD__);

            return self::PAYMENT_RETURN_FALLBACK_URL;
        }

        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . ltrim($domain, '/');
        }

        $parts = @parse_url($domain);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : null;

        if (!$parts || empty($parts['host']) || !in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            Yii::warning('Unsafe restaurant payment return domain rejected: ' . $domain, __METHOD__);

            return self::PAYMENT_RETURN_FALLBACK_URL;
        }

        $baseUrl = $scheme . '://' . $parts['host'];

        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        if (!empty($parts['path'])) {
            $baseUrl .= '/' . trim($parts['path'], '/');
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
