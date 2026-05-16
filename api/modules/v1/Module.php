<?php

namespace api\modules\v1;

use common\models\BlockedIp;
use Yii;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = 'api\modules\v1\controllers';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();


        if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return true;
        }

        
        $ip = $this->resolveClientIp();

        //check if ip is blocked

        $isBlocked = $ip && BlockedIp::find()->andWhere(['ip_address' => $ip])->exists();

        if($isBlocked) {
            header('Access-Control-Allow-Origin: *');
            throw new \yii\web\HttpException(403, 'ILLEGAL USAGE');
        }
    }

    private function resolveClientIp()
    {
        $ip = trim((string) Yii::$app->request->getRemoteIP());

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $IParray = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));

            if (!empty($IParray) && filter_var($IParray[0], FILTER_VALIDATE_IP) !== false) {
                $ip = $IParray[0];
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

}
