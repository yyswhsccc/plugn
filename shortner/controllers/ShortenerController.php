<?php
namespace shortner\controllers;

use Yii;
use yii\web\Controller;
use common\models\Order;

/**
 * Shortener controller
 * For shorten and redirect to payment link
 */
class ShortenerController extends Controller
{
    private const FALLBACK_URL = 'https://www.plugn.io';
    private const SAFE_REDIRECT_SCHEMES = ['http', 'https'];

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionRedirect($orderId)
    {
        $model = Order::findOne($orderId);

        if (!$model || !$model->restaurant || !$model->restaurant->restaurant_domain) {
            return $this->redirect(self::FALLBACK_URL);
        }

        $targetBaseUrl = $this->normalizeRestaurantDomain($model->restaurant->restaurant_domain);
        if (!$targetBaseUrl) {
            Yii::warning('Unsafe shortener redirect domain for order ' . $orderId, __METHOD__);
            return $this->redirect(self::FALLBACK_URL);
        }

        return $this->redirect($targetBaseUrl . '/order-status/' . rawurlencode((string)$orderId));
    }

    private function normalizeRestaurantDomain($domain)
    {
        $domain = trim((string)$domain);
        if ($domain === '') {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $domain)) {
            return null;
        }

        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }

        $parts = parse_url($domain);
        if (!$parts) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, self::SAFE_REDIRECT_SCHEMES, true) || empty($parts['host'])) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $baseUrl = $scheme . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }
        if (!empty($parts['path'])) {
            $baseUrl .= '/' . trim($parts['path'], '/');
        }

        return rtrim($baseUrl, '/');
    }

}
