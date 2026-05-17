<?php

namespace api\modules\v2\controllers;

use api\models\Customer;
use api\models\CustomerToken;
use api\models\PasswordResetRequestForm;
use api\models\Restaurant;
use common\models\CustomerEmailVerifyAttempt;
use Yii;
use yii\rest\Controller;
use yii\filters\auth\HttpBasicAuth;


/**
 * Auth controller provides the initial access token that is required for further requests
 * It initially authorizes via Http Basic Auth using a base64 encoded username and password
 */
class AuthController extends BaseController {

    public function behaviors() {

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
                    'X-Empty-Password',
                    'X-Error-Email',
                    'X-Error-Password'
                ],
            ],
        ];

        // Basic Auth accepts Base64 encoded username/password and decodes it for you
        $behaviors['authenticator'] = [
            'class' => HttpBasicAuth::className(),
            'except' => ['options'],
            'auth' => function ($email, $password) {

                $customer = Customer::findByEmail($email);

                if(!$customer) {
                    Yii::$app->response->headers->set (
                        'X-Error-Email',
                        Yii::t('api', 'Email not found')
                    );

                    return null;
                }

                if(empty($customer->customer_password_hash)) {

                    //Check if this user sent an email in past few minutes (to limit email spam)
                    $emailLimitDatetime = null;
                    $currentDatetime = new \DateTime();

                    if ($customer->customer_limit_email) {
                        $emailLimitDatetime = new \DateTime($customer->customer_limit_email);
                        date_add($emailLimitDatetime, date_interval_create_from_date_string('60 minutes'));
                    }

                    if ($customer->customer_limit_email && $currentDatetime < $emailLimitDatetime) {

                        $difference = $currentDatetime->diff($emailLimitDatetime);
                        $minuteDifference = (int)$difference->i;
                        $secondDifference = (int)$difference->s;

                        $errors = Yii::t('agent', "Email was sent previously, you may request another one in {numMinutes, number} minutes and {numSeconds, number} seconds", [
                            'numMinutes' => $minuteDifference,
                            'numSeconds' => $secondDifference,
                        ]);

                        Yii::$app->response->headers->set(
                            'X-Empty-Password',
                            Yii::t('api', $errors)
                        );

                        return null;

                    } else {

                        //send mail with password form page link

                        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

                        $store = Restaurant::find()->andWhere(['restaurant_uuid' => $store_id])->one();

                        $model = new PasswordResetRequestForm;
                        $model->email = $customer->customer_email;
                        $model->sendEmail($customer, $store);

                        Yii::$app->response->headers->set(
                            'X-Empty-Password',
                            Yii::t('agent', 'Please check the link sent to you on your email to set new password.')
                        );

                        return null;
                    }
                }

                if ($customer->validatePassword($password)) {
                    return $customer;
                }

                Yii::$app->response->headers->set (
                    'X-Error-Password',
                    Yii::t('api', 'Password not matching')
                );

                return null;
            }
        ];

        // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
        // also avoid for public actions like registration and password reset
        $behaviors['authenticator']['except'] = [
            'options',
            'request-reset-password',
            'update-password',
            'signup',
            'update-email',
            'resend-verification-email',
            'verify-email',
            'is-email-verified',
            'locate'
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actions() {
        $actions = parent::actions();

        // Return Header explaining what options are available for next request
        $actions['options'] = [
            'class' => 'yii\rest\OptionsAction',
            // optional:
            'collectionOptions' => ['GET', 'POST', 'HEAD', 'OPTIONS'],
            'resourceOptions' => ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        ];

        return $actions;
    }

    /**
     * Perform validation on the customer account (check if he's allowed login to platform)
     * If everything is alright,
     * Returns the BEARER access token required for futher requests to the API
     * @return array
     * 
     * @api {GET} /login Login
     * @apiName Login
     * @apiGroup Auth
     * 
     * @header {string} Authorization Basic Auth username and password (md5 hash of username:password).
     * @apiSuccess {string} message Message.
     */
    public function actionLogin() {

        $customer = Yii::$app->user->identity;

        // Email and password are correct, check if his email has been verified
        // If customer email has been verified, then allow him to log in
        if($customer->customer_email_verification != Customer::EMAIL_VERIFIED) {

            return [
                "operation" => "error",
                "errorType" => "email-not-verified",
                "message" => Yii::t('agent',"Please click the verification link sent to you by email to activate your account"),
                "unVerifiedToken" => $this->_loginResponse($customer)
            ];
        }

        return $this->_loginResponse($customer);
    }

    /**
     * todo: register user with store
     * @return mixed
     * 
     * @api {POST} /signup Signup
     * @apiName Signup
     * @apiGroup Auth
     * 
     * @apiParam {string} first_name First name.
     * @apiParam {string} last_name Last name.
     * @apiParam {string} email Email.
     * @apiParam {string} password Password.
     * @apiParam {string} phone_number Phone number.
     * @apiParam {string} country_code Country code.
     * @apiSuccess {string} message Message.
     * 
     */
    public function actionSignup() {
        
        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $model = new Customer();

        $model->customer_name = Yii::$app->request->getBodyParam("first_name") . ' '
                . Yii::$app->request->getBodyParam("last_name");

        $model->customer_email = Yii::$app->request->getBodyParam("email");
        $model->setPassword(Yii::$app->request->getBodyParam("password"));
        $model->customer_phone_number = Yii::$app->request->getBodyParam("phone_number");
        $model->country_code = Yii::$app->request->getBodyParam("country_code");

        if($model->country_code && $model->customer_phone_number) {
            $model->customer_phone_number = "+" . $model->country_code . $model->customer_phone_number;
        }

        $model->customer_language_pref = Yii::$app->language == "ar" ? "ar": "en";
        $model->restaurant_uuid = $store_id;

        if(!$model->save()) {
            $this->logAuthValidationErrors('signup', $model->errors, [
                'store_uuid' => $store_id,
            ]);

            return [
                "operation" => "error",
                "message" => Yii::t('api', 'Unable to create customer account.')
            ];
        }

        $model->sendVerificationEmail();

        return [
            "operation" => "success",
            "customer_id" => $model->customer_id,
            "message" => Yii::t('agent', "Please click on the link sent to you by email to verify your account"),
            "unVerifiedToken" => $this->_loginResponse($model)
        ];
    }

    /**
     * Update email address
     * @return array
     * 
     * @api {POST} /update-email Update email
     * @apiName UpdateEmail
     * @apiGroup Auth
     * 
     * @apiParam {string} unVerifiedToken Unverified token.
     * @apiParam {string} newEmail New email.
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionUpdateEmail() {

        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $unVerifiedToken = Yii::$app->request->getBodyParam("unVerifiedToken");
        $new_email = Yii::$app->request->getBodyParam("newEmail");

        $customer = Customer::findIdentityByUnVerifiedTokenToken($unVerifiedToken);

        if (!$customer) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if (!$new_email) {
            return [
                "operation" => "error",
                "message" => Yii::t('api', "Customer new email address required")
            ];
        }

        if ($new_email == $customer->customer_email || $new_email == $customer->customer_new_email) {
            return [
                "operation" => "error",
                "message" => Yii::t('api', "Customer new email address is same as old email")
            ];
        }

        /**
         * Opt will expiry after 60 minutes, so user have to login back to update
         * email
         *
        if (!$customer->findByOtp($customer->otp, 60)) {
        return [
        "operation" => "error-session-expired",
        "message" => Yii::t('employer', "Session expired, please log back in")
        ];
        }*/

        $customer->scenario = Customer::SCENARIO_UPDATE_EMAIL;

        if ($customer->customer_email_verification == Customer::EMAIL_VERIFIED) {
            $customer->customer_new_email = $new_email;
        } else  {
            $customer->customer_email = $new_email;
            $customer->customer_new_email = null;
        }

        if ($customer->save()) {

            //extend otp to fix: https://www.pivotaltracker.com/story/show/169037267

            //$customer->generateOtp();

            //to verify new email address 

            $customer->sendVerificationEmail($store_id);

            return [
                "operation" => "success",
                "message" => Yii::t('api', "Customer Account Info Updated Successfully, please check email to verify new email address"),
                "unVerifiedToken" => $this->_loginResponse($customer)
            ];
        } else {
            $this->logAuthValidationErrors('update-email', $customer->errors, [
                'customer_id' => $customer->customer_id,
                'store_uuid' => $store_id,
            ]);

            return [
                "operation" => "error",
                "message" => Yii::t('api', 'Unable to update customer email address.')
            ];
        }
    }

    /**
     * Re-send manual verification email to customer
     * @return array
     * 
     * @api {POST} /resend-verification-email Resend verification email
     * @apiName ResendVerificationEmail
     * @apiGroup Auth
     * 
     * @apiParam {string} email Email.
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionResendVerificationEmail()
    {
        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $emailInput = Yii::$app->request->getBodyParam("email");

        $filter = $store_id ? [
            'AND',
            [
                'restaurant_uuid' => $store_id
            ],
            [
                'OR',
                ['customer_email' => $emailInput],
                ['customer_new_email' => $emailInput],
            ]
        ]: [
            'OR',
            ['customer_email' => $emailInput],
            ['customer_new_email' => $emailInput],
        ];

        $customer = Customer::find()->andWhere($filter)->one();

        $errors = false;
        $errorCode = null; //error code

        if ($customer) {

            if ($customer->customer_email_verification == Customer::EMAIL_VERIFIED) {
                return [
                    'operation' => 'error',
                    'errorCode' => 1,
                    'message' => Yii::t('api', 'You have verified your email')
                ];
            }

            //Check if this user sent an email in past few minutes (to limit email spam)

            $currentDatetime = new \DateTime();
            $emailLimitDatetime = null;
            if ($customer->customer_limit_email) {
                $emailLimitDatetime = new \DateTime($customer->customer_limit_email);
                date_add($emailLimitDatetime, date_interval_create_from_date_string('1 minutes'));
            }

            if ($customer->customer_limit_email && $currentDatetime < $emailLimitDatetime) {

                $difference = $currentDatetime->diff($emailLimitDatetime);
                $minuteDifference = (int) $difference->i;
                $secondDifference = (int) $difference->s;

                $errorCode = 2;

                $errors = Yii::t('api', "Email was sent previously, you may request another one in {numMinutes, number} minutes and {numSeconds, number} seconds", [
                    'numMinutes' => $minuteDifference,
                    'numSeconds' => $secondDifference,
                ]);
            } else if ($customer->customer_email_verification == Customer::EMAIL_NOT_VERIFIED) {
                $customer->sendVerificationEmail($store_id);
            }
        } else {
            $errorCode = 3;
            $errors['email'] = [Yii::t('api', 'Account not found')];
        }

        // If errors exist show them

        if ($errors) {
            return [
                'errorCode' => $errorCode,
                'operation' => 'error',
                'message' => $errors
            ];
        }

        // Otherwise return success
        return [
            'operation' => 'success',
            'message' => Yii::t('api', 'Please click on the link sent to you by email to verify your account'),
        ];
    }

    /**
     * Check if candidate email already verified
     * 
     * @api {POST} /is-email-verified Is email verified
     * @apiName IsEmailVerified
     * @apiGroup Auth
     * 
     * @apiParam {string} token Token.
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionIsEmailVerified() {

        $token = Yii::$app->request->getBodyParam("token");

        $model = CustomerToken::find()
            ->andWhere(['token_value' => $token])
            ->one();

        if (!$model || !$model->customer) {
            return [
                'status' => 0
            ];
        }

        return [
            'status' => $model->customer->customer_new_email ? 0 : $model->customer->customer_email_verification
        ];
    }

    /**
     * Process email verification
     * @return array
     * 
     * @api {POST} /verify-email Verify email
     * @apiName VerifyEmail
     * @apiGroup Auth
     * 
     * @apiParam {string} code Code.
     * @apiParam {string} email Email.
     * 
     * @apiSuccess {string} message Message.
     */
    public function actionVerifyEmail() {

        $code = Yii::$app->request->getBodyParam("code");
        $email = Yii::$app->request->getBodyParam("email");

        //check limit reached

        $totalInvalidAttempts = CustomerEmailVerifyAttempt::find()
            ->andWhere([
                'customer_email' => $email,
                'ip_address' => Yii::$app->getRequest()->getUserIP()
            ])
            ->andWhere(new \yii\db\Expression("created_at >= DATE_SUB(NOW(),INTERVAL 1 HOUR)"))//last 1 hour
            ->count();

        if ($totalInvalidAttempts > 4) {
            return [
                'operation' => 'error',
                'message' => Yii::t('api', 'You reached your limit to verify email. Please try again after an hour.')
            ];
        }

        $response = Customer::verifyEmail($email, $code);

        if ($response['success'] == false) {
            return [
                'operation' => 'error',
                'message' => $response['message']
            ];
        }

        if ($response['success'] == true) {
            //remove old email verification attempts

            CustomerEmailVerifyAttempt::deleteAll([
                'customer_email' => $email,
                'ip_address' => Yii::$app->getRequest()->getUserIP()
            ]);

            //remove otp

            //$customer->otp = null;
            //$customer->save(false);

            return $this->_loginResponse($response['data']);

        } else {
            //add entry for invalid attempt

            $model = new CustomerEmailVerifyAttempt;
            $model->code = $code;
            $model->customer_email = $email;
            $model->ip_address = Yii::$app->getRequest()->getUserIP();
            $model->save();

            return [
                'operation' => 'error',
                'message' => Yii::t('api', 'Invalid email verification code.')
            ];
        }
    }

    /**
     * Sends password reset email to user
     * @return array
     * 
     * @api {POST} /request-reset-password Request reset password
     * @apiName RequestResetPassword
     * @apiGroup Auth
     * 
     * @apiParam {string} email Email.
     * @apiSuccess {string} message Message.
     */
    public function actionRequestResetPassword() {

        $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

        $store = Restaurant::find()->andWhere(['restaurant_uuid' => $store_id])->one();

        $emailInput = Yii::$app->request->getBodyParam("email");
        $errors = false;
        $model = new PasswordResetRequestForm();
        $model->email = $emailInput;

        if (!$model->validate()) {
            return [
                'operation' => 'error',
                'message' => $model->getErrors()
            ];
        }

        $filter = $store_id? [
            'customer_email' => $model->email,
            'restaurant_uuid' => $store_id
        ]: [
            'customer_email' => $model->email,
        ];

        $customer = Customer::findOne($filter);

        if(!$customer) {
            return [
                'operation' => 'error',
                'message' => Yii::t('api', 'Account not found')
            ];
        }

        //Check if this user sent an email in past few minutes (to limit email spam)
        $currentDatetime = new \DateTime('now');
        $emailLimitDatetime = null;
        if ($customer->customer_limit_email) {
            $emailLimitDatetime = new \DateTime($customer->customer_limit_email);
            date_add($emailLimitDatetime, date_interval_create_from_date_string('1 minutes'));
        }

        if ($customer->customer_limit_email && $currentDatetime < $emailLimitDatetime) {

            $difference = $currentDatetime->diff($emailLimitDatetime);
            $minuteDifference = (int) $difference->i;
            $secondDifference = (int) $difference->s;

            $errors = Yii::t('api', "Email was sent previously, you may request another one in {numMinutes, number} minutes and {numSeconds, number} seconds", [
                'numMinutes' => $minuteDifference,
                'numSeconds' => $secondDifference,
            ]);

        } else if (!$model->sendEmail($customer, $store)) {
            $errors = Yii::t('api', 'Sorry, we are unable to reset a password for email provided.');
        }

        if($errors) {
            return [
                'operation' => 'error',
                'message' => $errors
            ];
        }

        // Otherwise return success
        return [
            'operation' => 'success',
            'message' => Yii::t ('customer', 'Please check the link sent to you on your email to set new password.')
        ];
    }

    /**
     * Updates password based on passed token
     * @return array
     * 
     * @api {POST} /update-password Update password
     * @apiName UpdatePassword
     * @apiGroup Auth
     * 
     * @apiParam {string} token Token.
     */
    public function actionUpdatePassword() {

        $token = Yii::$app->request->getBodyParam("token");
        $newPassword = Yii::$app->request->getBodyParam("newPassword");
        //$cPassword = Yii::$app->request->getBodyParam("cPassword");

        $customer = Customer::findByPasswordResetToken($token);

        if (!$customer) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('customer', 'Invalid password reset token.')
            ];
        }
        if (!$newPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('customer', 'Password field required')
            ];
        }

        /*if (!$cPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('customer', 'Confirm Password field required')
            ];
        }

        if ($cPassword != $newPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('customer', 'Password & Confirm Password does not match')
            ];
        }*/

        $customer->setPassword($newPassword);
        $customer->removePasswordResetToken();

        /**
         * as password reset token will be sent to email and user will update password
         * from that link so if user have token he have valid email
         */

        $customer->customer_email_verification = Customer::EMAIL_VERIFIED;

        $customer->save(false);

        return $this->_loginResponse($customer);
    }
    
    /**
     * Return customer data after successful login
     * @param Customer $customer
     * @param int $new_user
     * @return array
     */
    private function _loginResponse($customer, $new_user = 0) {

            $store_id = Yii::$app->request->getHeaders()->get('Store-Id');

            Yii::$app->eventManager->track('Customer Logged In',  [
                "login_method" => "Email"
            ],
                null,
                $store_id
            );

        // Return Customer access token if everything valid

        $accessToken = $customer->accessToken->token_value;

        return [
            "operation" => "success",
            "token" => $accessToken,
            "id" => $customer->customer_id ,
            "name" => $customer->customer_name,
            "email" => $customer->customer_email,
        ];
    }

    /**
     * Keep detailed validation diagnostics in logs while returning generic API errors.
     * @param string $context
     * @param array $errors
     * @param array $metadata
     */
    private function logAuthValidationErrors($context, $errors, $metadata = []) {
        Yii::error('[API Auth] ' . $context . ' validation failed: ' . json_encode([
            'errors' => $errors,
            'metadata' => $metadata,
        ]), __METHOD__);
    }

    /**
     * return user location detail by user ip address
     * @return array 
     * 
     * @api {GET} /locate Locate
     * @apiName Locate
     * @apiGroup Auth
     * 
     * @apiSuccess {ipstackResponse} ipstackResponse Ipstack response.
     */
    public function actionLocate() {
        return Yii::$app->ipstack->locate();
    }

    /**
     * Finds the Restaurant model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $domain
     * @return \common\models\Restaurant the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findByDomain($domain)
    {
        $model = Restaurant::findOne(['restaurant_domain' => 'https://'. $domain]);

        if ($model !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested record does not exist.');
        }
    }
}
