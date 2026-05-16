<?php

namespace agent\modules\v1\controllers;

use agent\models\AgentToken;
use agent\models\Currency;
use agent\models\Restaurant;
use common\models\RestaurantByCampaign;
use common\models\AgentEmailVerifyAttempt;
use Yii;
use yii\filters\auth\HttpBasicAuth;
use agent\models\Agent;
use agent\models\PasswordResetRequestForm;
use yii\web\NotFoundHttpException;


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
                    'X-Error-Email',
                    'X-Error-Password',
                    'Mixpanel-Distinct-ID'
                ],
            ],
        ];

        // Basic Auth accepts Base64 encoded username/password and decodes it for you
        $behaviors['authenticator'] = [
            'class' => HttpBasicAuth::className(),
            'except' => ['options'],
            'auth' => function ($email, $password) {

                $agent = Agent::findByEmail($email);

                if(!$agent) {
                    Yii::$app->response->headers->set (
                        'X-Error-Email', 
                        Yii::t('agent', 'Email not found')
                    );
                    
                    return null;
                }

                if(empty($password)) {
                    Yii::$app->response->headers->set (
                        'X-Error-Password',
                        Yii::t('agent', 'Password not provided')
                    );

                    return null;
                }

                if ($agent->validatePassword($password)) {
                    return $agent;
                }

                Yii::$app->response->headers->set (
                    'X-Error-Password', 
                    Yii::t('agent', 'Password not matching')
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
            'signup-step-one',
            'update-email',
            'resend-verification-email',
            'verify-email',
            'is-email-verified',
            'login-auth0',
            'locate',
            'login-by-apple',
            'login-by-google',
            'login-by-key'
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
     * Perform validation on the agent account (check if he's allowed login to platform)
     * If everything is alright,
     * Returns the BEARER access token required for futher requests to the API
     * @return array
     * 
     * @api {GET} /auth/login Login to the platform
     * @apiHeader {string} md5 hash of email:password.
     * @apiName Login
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionLogin() {

        $agent = Yii::$app->user->identity;

        // Email and password are correct, check if his email has been verified
        // If agent email has been verified, then allow him to log in
        if($agent->agent_email_verification != \common\models\Agent::EMAIL_VERIFIED) {

            return [
                "operation" => "error",
                "errorType" => "email-not-verified",
                "message" => Yii::t('agent',"Please click the verification link sent to you by email to activate your account"),
                "unVerifiedToken" => $this->_loginResponse($agent)
            ];
        }

        Yii::$app->eventManager->track('Log In', [
            "login_method" => "Email",
        ]);

        return $this->_loginResponse($agent);
    }

    /**
     * login with auth0 token
     * @return array
     * 
     * @api {post} /auth/login-auth0 Login with auth0 token
     * @apiHeader {string} accessToken Auth0 access token.
     * @apiName LoginAuth0
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionLoginAuth0()
    {
        $accessToken = Yii::$app->request->getBodyParam('accessToken');

        $response = Yii::$app->auth0->getUserInfo($accessToken);

        if(!$response->isOk) {
            return [
                "operation" => "error",
                "message" => Yii::t('agent',"Invalid access token")
            ];
        }

        $userInfo = $response->data;

        if(!$userInfo || !$userInfo['email'])
        {
            return [
                "operation" => "error",
                "message" => Yii::t('agent',"We've faced a problem creating your account, please contact us for assistance.")
            ];
        }

        $agent = Agent::find()
            ->andWhere(['agent_email' => $userInfo['email']])
            ->one();

        /**
         * redirect to signup page if no account
         */
        if(!$agent)
        {
            return [
                "operation" => "error",
                "code" => 1,
                "message" => Yii::t('agent',"Account not found")
            ];
        }

        // Email and password are correct, check if his email has been verified
        // If email has been verified, then allow him to log in
        /*if ($agent->contact_email_verification != Candidate::EMAIL_VERIFIED) {

            //$agent->generateOtp();
            //$agent->save(false);

            return [
                "operation" => "error",
                "errorType" => "email-not-verified",
                "message" => Yii::t('agent', "Please click the verification link sent to you by email to activate your account"),
                "unVerifiedToken" => $this->_loginResponse($agent)
            ];
        }*/

        Yii::$app->eventManager->track('Log In', [
            "login_method" => "Auth0"
        ]);

        return $this->_loginResponse($agent);
    }

    /**
     * @return array
     * @throws NotFoundHttpException
     * 
     * @api {post} /auth/login-by-key Login with key    
     * @apiHeader {string} auth_key Auth key.
     * @apiName LoginByKey
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.  
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionLoginByKey() {

        $auth_key = Yii::$app->request->getBodyParam('auth_key');
        $store_id = Yii::$app->request->getBodyParam('store_id');

        $model = Agent::find()
            ->andWhere([
                'agent_auth_key' => $auth_key,
             //   'agent_email_verification' => true,
            ])

            //->andWhere(['deleted' => 0])
            ->one();

        if (!$model) {
            throw new NotFoundHttpException('The requested page does not exist.');

            /*return [
                "operation" => "error",
                "code" => 1,
                "message" => Yii::t('agent',"Account not found")
            ];*/
        }

        if($model->agent_status != \common\models\Agent::STATUS_ACTIVE) {

            return [
                "operation" => "error",
                "errorType" => "account-deactivated",
                "message" => Yii::t('agent',"This account is not active, please contact us if you think this is error, Thank you!"),
            ];
        }

        // Email and password are correct, check if his email has been verified
        // If agent email has been verified, then allow him to log in
        if($model->agent_email_verification != \common\models\Agent::EMAIL_VERIFIED) {

            return [
                "operation" => "error",
                "errorType" => "email-not-verified",
                "message" => Yii::t('agent',"Please click the verification link sent to you by email to activate your account"),
                "unVerifiedToken" => $this->_loginResponse($model)
            ];
        }

        Yii::$app->eventManager->track('Log In', [
            "login_method" => "Admin"
        ]);

        $model->agent_auth_key = "";
        $model->save(false);

        return $this->_loginResponse($model, $store_id);
    }

    /**
     * Sign up with google login
     * 
     * @api {post} /auth/login-by-google Login with google
     * @apiHeader {string} idToken Google ID token.
     * @apiName LoginByGoogle
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.  
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionLoginByGoogle() {

        $token = Yii::$app->request->getBodyParam("idToken");

        if (!is_string($token) || trim($token) === '') {
            return $this->invalidGoogleAccessTokenResponse();
        }

        $response = $this->fetchGoogleTokenInfo($token);

        if (!is_object($response) || empty($response->email) || !$this->isGoogleTokenAudienceValid($response)) {
            return $this->invalidGoogleAccessTokenResponse();
        }

        $model = Agent::find()
            ->andWhere(['agent_email' => $response->email])
            ->one();


        if (!$model) {
            return [
                "operation" => "error",
                "code" => 1,
                "message" => Yii::t('agent',"Account not found")
            ];
        }

        Yii::$app->eventManager->track('Log In', [
            "login_method" => "Google"
        ]);

        return $this->_loginResponse($model);
    }

    private function fetchGoogleTokenInfo($token) {
        $ch = curl_init();

        if ($ch === false) {
            return null;
        }

        $query = http_build_query(['id_token' => $token], '', '&', PHP_QUERY_RFC3986);

        curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/oauth2/v3/tokeninfo?" . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $body = curl_exec($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            return null;
        }

        $response = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $response;
    }

    private function isGoogleTokenAudienceValid($response) {
        if (empty($response->aud)) {
            return false;
        }

        $clientId = $this->getGoogleOAuthClientId();

        return $clientId !== '' && (string) $response->aud === $clientId;
    }

    private function getGoogleOAuthClientId() {
        $clientId = Yii::$app->params['googleOAuthClientId'] ?? null;

        if (!is_string($clientId) || trim($clientId) === '') {
            $clientId = getenv('GOOGLE_OAUTH_CLIENT_ID') ?: getenv('GOOGLE_CLIENT_ID');
        }

        return is_string($clientId) ? trim($clientId) : '';
    }

    private function invalidGoogleAccessTokenResponse() {
        return [
            'operation' => 'error',
            "code" => 1,
            'message' => Yii::t('agent',"Invalid access token")
        ];
    }

    /**
     *
     * Sign up with apple login
     * 
     * @api {post} /auth/login-by-apple Login with apple    
     * @apiHeader {string} identityToken Apple identity token.
     * @apiName LoginByApple
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.  
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.     
     */
    public function actionLoginByApple() {

        try {

            $jwt = Yii::$app->request->getBodyParam("identityToken");

            //will throw error on invalid token

            $payload = Yii::$app->jwt->decode($jwt);

        } catch(\ErrorException $e) {

            return [
                'operation' => 'error',
                'message' => $e->getMessage()
            ];

        }

        if(empty($payload->email)) {
            return [
                'operation' => 'error',
                'message' => Yii::t('agent',"Invalid access token")
            ];
        }

        $email = $payload->email;

        //$familyName = Yii::$app->request->getBodyParam("familyName");
        //$givenName = Yii::$app->request->getBodyParam("givenName");

        $model = Agent::find()
            ->andWhere(['agent_email' => $email])
            ->one();


        if (!$model) {
            return [
                "operation" => "error",
                "code" => 1,
                "message" => Yii::t('agent',"Account not found")
            ];
        }

        Yii::$app->eventManager->track('Log In', [
            "login_method" => "Apple"
        ]);

        return $this->_loginResponse($model);
    }

    /**
     * signup agent
     * @return array|string[]
     * 
     * @api {post} /auth/signup-step-one Signup agent step one
     * @apiName SignupStepOne
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.  
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionSignupStepOne()
    {
        $accessToken = Yii::$app->request->getBodyParam('accessToken');
        $token = Yii::$app->request->getBodyParam('token');

        //TODO: make token as required field once we update android app

        if(YII_ENV == 'prod') {
            $response = Yii::$app->reCaptcha->verify($token);

            if (!$response->data || !$response->data['success']) {
                return [
                    "operation" => "error",
                    "code" => 0,
                    "message" => Yii::t('agent', "Invalid captcha validation")
                ];
            }
        }

        $agent = new Agent();
        $agent->setScenario(Agent::SCENARIO_CREATE_NEW_AGENT);
        $agent->utm_uuid = Yii::$app->request->getBodyParam('utm_uuid');
        $agent->agent_name = Yii::$app->request->getBodyParam('name');
        $agent->agent_email = Yii::$app->request->getBodyParam('email');
        $agent->agent_number = Yii::$app->request->getBodyParam ('owner_number');
        $agent->agent_phone_country_code = Yii::$app->request->getBodyParam ('owner_phone_country_code');

        $agent->tempPassword = Yii::$app->request->getBodyParam ('password');

        if($accessToken) {

            $response = Yii::$app->auth0->getUserInfo ($accessToken);

            if ($response->isOk && $response->data['email']) {
                $agent->agent_email = $response->data['email'];
                $agent->agent_email_verification = Agent::EMAIL_VERIFIED;
            }
        }

        if (!$agent->save()) {
            return [
                "operation" => "error",
                "message" => $agent->errors
            ];
        }

        if (YII_ENV == 'prod') {

            $param = [
                'email' => Yii::$app->request->getBodyParam('email'),
                'password' => Yii::$app->request->getBodyParam('password')
            ];

            Yii::$app->auth0->createUser($param);
        }

        $full_name = explode(' ', $agent->agent_name);
        $firstname = $full_name[0];
        $lastname = array_key_exists(1, $full_name) ? $full_name[1] : null;

        Yii::$app->eventManager->setUser($agent->agent_id, [
            'name' => trim($agent->agent_name),
            'email' => $agent->agent_email,
        ]);

        Yii::$app->eventManager->track('Agent Signup', [
            'first_name' => trim($firstname),
            'last_name' => $lastname ? trim($lastname) : null,
            'email' => $agent->agent_email,
            "campaign" => $agent->campaign ? $agent->campaign->utm_campaign : null,
            "utm_medium" => $agent->campaign ? $agent->campaign->utm_medium : null,
            "profile_status" => "Active",
            "user_id" => $agent->agent_id
        ]);

        if($agent->agent_email_verification == Agent::EMAIL_NOT_VERIFIED)
        {
            $agent->sendVerificationEmail();

            return [
                "operation" => "success",
                "agent_id" => $agent->agent_id,
                "message" => Yii::t('agent', "Please click on the link sent to you by email to verify your account"),
                "unVerifiedToken" => $this->_loginResponse($agent)
            ];
        }

        return $this->_loginResponse ($agent);
    }

    /**
     * register user with store
     * @return mixed
     * 
     * @api {post} /auth/signup Signup agent
     * @apiName Signup
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.  
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionSignup() {

        $currencyCode = Yii::$app->request->getBodyParam('currency');
        $accessToken = Yii::$app->request->getBodyParam('accessToken');
        $utm_id = Yii::$app->request->getBodyParam('utm_uuid');
        $token = Yii::$app->request->getBodyParam('token');

        //TODO: make token as required field once we update android app

        if(YII_ENV == 'prod') {
            $response = Yii::$app->reCaptcha->verify($token);

            if (!$response->data || !$response->data['success']) {
                return [
                    "operation" => "error",
                    "code" => 0,
                    "message" => Yii::t('agent', "Invalid captcha validation")
                ];
            }
        }

        $currency = Currency::findOne(['code' => $currencyCode]);
 
        $agent = new Agent();
        $agent->setScenario(Agent::SCENARIO_CREATE_NEW_AGENT);
        $agent->utm_uuid = Yii::$app->request->getBodyParam('utm_uuid');
        $agent->agent_name = Yii::$app->request->getBodyParam ('name');
        $agent->agent_email = Yii::$app->request->getBodyParam ('email');
        //$agent->setPassword(Yii::$app->request->getBodyParam ('password'));
        $agent->tempPassword = Yii::$app->request->getBodyParam ('password');

        if($accessToken) {

            $response = Yii::$app->auth0->getUserInfo ($accessToken);

            if ($response->isOk && $response->data['email']) {
                $agent->agent_email = $response->data['email'];
                $agent->agent_email_verification = Agent::EMAIL_VERIFIED;
            }
        }

        $store = new Restaurant();
        $store->version = Yii::$app->params['storeVersion'];
        $store->setScenario(Restaurant::SCENARIO_CREATE_STORE_BY_AGENT);
        $store->owner_number = Yii::$app->request->getBodyParam ('owner_number');
        $store->owner_phone_country_code= Yii::$app->request->getBodyParam ('owner_phone_country_code');
        $store->meta_description = Yii::$app->request->getBodyParam("meta_description");
        $store->meta_description_ar = Yii::$app->request->getBodyParam("meta_description_ar");

        $store->name = Yii::$app->request->getBodyParam ('restaurant_name');
        $store->business_type = Yii::$app->request->getBodyParam ('account_type');
        $store->restaurant_domain = Yii::$app->request->getBodyParam ('restaurant_domain');
        $store->country_id = Yii::$app->request->getBodyParam ('country_id');
        $store->currency_id = Yii::$app->request->getBodyParam('currency');
        $store->accept_order_247 = Yii::$app->request->getBodyParam('accept_order_247');
        
        $store->annual_revenue= Yii::$app->request->getBodyParam ('annual_revenue');

        $store->restaurant_email = $agent->agent_email;
        $store->owner_first_name = $agent->agent_name;
        $store->name_ar = $store->name;

        $transaction = Yii::$app->db->beginTransaction();

            if (!$agent->save()) {
                $transaction->rollBack();
                return [
                    "operation" => "error",
                    "message" => $agent->errors
                ];
            }

            $full_name = explode(' ', $agent->agent_name);
            $firstname = $full_name[0];
            $lastname = array_key_exists(1, $full_name) ? $full_name[1] : null;

            Yii::$app->eventManager->track('Agent Signup', [
                'first_name' => trim($firstname),
                'last_name' => $lastname ? trim($lastname) : null,
                'store_name' => $store->name,
                'phone_number' => $store->owner_number,
                'email' => $agent->agent_email,
                'store_url' => $store->restaurant_domain,
                "country" => $store->country ? $store->country->country_name : null,
                "campaign" => $agent->campaign ? $agent->campaign->utm_campaign : null,
                "utm_medium" => $agent->campaign ? $agent->campaign->utm_medium : null,
                "profile_status" => "Active",
                "user_id" => $agent->agent_id
            ]);

            if (!$store->save()) {
                return [
                    "operation" => "error",
                    "message" => $store->errors
                ];
            }

            $response = $store->setupStore($agent);

            if($response['operation'] != 'success') {
                $transaction->rollBack();

                return $response;
            }

            if($utm_id) {
                $rbc = new RestaurantByCampaign();
                $rbc->restaurant_uuid = $store->restaurant_uuid;
                $rbc->utm_uuid = $utm_id;

                if (!$rbc->save()) {
                    $transaction->rollBack();
                    return [
                        "operation" => "error",
                        "message" => $rbc->errors
                    ];
                }
            }

            $transaction->commit();

            if($agent->agent_email_verification == Agent::EMAIL_NOT_VERIFIED)
            {
                $agent->sendVerificationEmail();

                return [
                    "operation" => "success",
                    "agent_id" => $agent->agent_id,
                    "message" => Yii::t('agent', "Please click on the link sent to you by email to verify your account"),
                    "unVerifiedToken" => $this->_loginResponse($agent)
                ];
            }

        /*} catch (\Exception $e) {
            $transaction->rollBack();
            return [
                "operation" => 'error',
                "message" => $e->getMessage()
            ];
        }*/

        return $this->_loginResponse ($agent);
    }
    
    /**
     * Update email address
     * @return array
     * 
     * @api {post} /auth/update-email Update email address
     * @apiName UpdateEmail
     * @apiGroup Auth
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionUpdateEmail() {

        $unVerifiedToken = Yii::$app->request->getBodyParam("unVerifiedToken");
        $new_email = Yii::$app->request->getBodyParam("newEmail");
        $token = Yii::$app->request->getBodyParam('token');

        //TODO: make token as required field once we update android app

        if(YII_ENV == 'prod') {
            $response = Yii::$app->reCaptcha->verify($token);

            if (!$response->data || !$response->data['success']) {
                return [
                    "operation" => "error",
                    "code" => 0,
                    "message" => Yii::t('agent', "Invalid captcha validation")
                ];
            }
        }
        
        $agent = Agent::findIdentityByUnVerifiedTokenToken($unVerifiedToken);

        if (!$agent) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if (!$new_email) {
            return [
                "operation" => "error",
                "message" => Yii::t('agent', "Agent new email address required")
            ];
        }

        if ($new_email == $agent->agent_email || $new_email == $agent->agent_new_email) {
            return [
                "operation" => "error",
                "message" => Yii::t('agent', "Agent new email address is same as old email")
            ];
        }

        /**
         * Opt will expiry after 60 minutes, so user have to login back to update
         * email
         *
        if (!$agent->findByOtp($agent->otp, 60)) {
        return [
        "operation" => "error-session-expired",
        "message" => Yii::t('employer', "Session expired, please log back in")
        ];
        }*/

        $agent->scenario = AGENT::SCENARIO_UPDATE_EMAIL;

        if ($agent->agent_email_verification == Agent::EMAIL_VERIFIED) {
            $agent->agent_new_email = $new_email;
        } else  {
            $agent->agent_email = $new_email;
            $agent->agent_new_email = null;
        }

        if ($agent->save()) {

            //extend otp to fix: https://www.pivotaltracker.com/story/show/169037267

            //$agent->generateOtp();

            //to verify new email address 

            $agent->sendVerificationEmail();

            return [
                "operation" => "success",
                "message" => Yii::t('agent', "Agent Account Info Updated Successfully, please check email to verify new email address"),
                "unVerifiedToken" => $this->_loginResponse($agent)
            ];
        } else {
            return [
                "operation" => "error",
                "message" => $agent->errors
            ];
        }
    }
    
    /**
     * Re-send manual verification email to agent
     * @return array
     * 
     * @api {post} /auth/resend-verification-email Re-send manual verification email to agent
     * @apiName ResendVerificationEmail
     * @apiGroup Auth
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionResendVerificationEmail()
    {
        $emailInput = Yii::$app->request->getBodyParam("email");
        $token = Yii::$app->request->getBodyParam('token');

        //TODO: make token as required field once we update android app

        if(YII_ENV == 'prod') {
            $response = Yii::$app->reCaptcha->verify($token);

            if (!$response->data || !$response->data['success']) {
                return [
                    "operation" => "error",
                    "code" => 0,
                    "message" => Yii::t('agent', "Invalid captcha validation")
                ];
            }
        }

        $agent = Agent::find()
            ->andWhere(['deleted' => 0])
            ->andWhere([
                'OR',
                ['agent_email' => $emailInput],
                ['agent_new_email' => $emailInput],
            ])->one();

        $errors = false;
        $errorCode = null; //error code

        if ($agent) {

            if (empty($agent->agent_new_email) && $agent->agent_email_verification == Agent::EMAIL_VERIFIED) {
                return [
                    'operation' => 'error',
                    'errorCode' => 1,
                    'message' => Yii::t('agent', 'You have verified your email')
                ];
            }

            //Check if this user sent an email in past few minutes (to limit email spam)
            $emailLimitDatetime = null;
            $currentDatetime = new \DateTime();

            if ($agent->agent_limit_email) {
                $emailLimitDatetime = new \DateTime($agent->agent_limit_email);
                date_add($emailLimitDatetime, date_interval_create_from_date_string('1 minutes'));
            }
            
            if ($agent->agent_limit_email && $currentDatetime < $emailLimitDatetime) {

                $difference = $currentDatetime->diff($emailLimitDatetime);
                $minuteDifference = (int) $difference->i;
                $secondDifference = (int) $difference->s;

                $errorCode = 2;

                $errors = Yii::t('agent', "Email was sent previously, you may request another one in {numMinutes, number} minutes and {numSeconds, number} seconds", [
                    'numMinutes' => $minuteDifference,
                    'numSeconds' => $secondDifference,
                ]);
            } else if ($agent->agent_email_verification == Agent::EMAIL_NOT_VERIFIED) {
                $agent->sendVerificationEmail();
            }


        } else {
            $errorCode = 3;
            $errors['email'] = [Yii::t('agent', 'Account not found')];
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
            'message' => Yii::t('agent', 'Please click on the link sent to you by email to verify your account'),
        ];
    }

    /**
     * Check if agent email already verified
     * 
     * @api {post} /auth/is-email-verified Check if agent email already verified
     * @apiName IsEmailVerified
     * @apiGroup Auth
     *
     * @apiSuccess {string} status 0|1.
     */
    public function actionIsEmailVerified() {

        $token = Yii::$app->request->getBodyParam("token");

        $model = AgentToken::find()
            ->andWhere(['token_value' => $token])
            ->one();

        if (!$model || !$model->agent) {
            return [
                'status' => 0
            ];
        }

        return [
            'status' => $model->agent->agent_new_email ? 0 : $model->agent->agent_email_verification
        ];
    }

    /**
     * Process email verification
     * @return array
     * 
     * @api {post} /auth/verify-email Process email verification    
     * @apiName VerifyEmail
     * @apiGroup Auth
     *
     * @apiSuccess {string} token Access token.
     * @apiSuccess {string} id Agent ID.
     * @apiSuccess {string} agent_name Agent name.
     * @apiSuccess {string} agent_email Agent email.
     * @apiSuccess {string} agent_new_email Agent new email.
     * @apiSuccess {string} language_pref Language preference.
     * @apiSuccess {Role} role Role.
     * @apiSuccess {string} created_at Created at.
     * @apiSuccess {Store} selectedStore Selected store.
     * @apiSuccess {Array} stores Stores.
     */
    public function actionVerifyEmail() {

        $code = Yii::$app->request->getBodyParam("code");
        $email = Yii::$app->request->getBodyParam("email");

        //check limit reached

        $totalInvalidAttempts = AgentEmailVerifyAttempt::find()
            ->andWhere([
                'agent_email' => $email,
                'ip_address' => Yii::$app->getRequest()->getUserIP()
            ])
            ->andWhere(new \yii\db\Expression("created_at >= DATE_SUB(NOW(),INTERVAL 1 HOUR)"))//last 1 hour
            ->count();

        if ($totalInvalidAttempts > 4) {
            return [
                'operation' => 'error',
                'message' => Yii::t('agent', 'You reached your limit to verify email. Please try again after an hour.')
            ];
        }

        $response = Agent::verifyEmail($email, $code);

        if (!$response['success']) {
            //add entry for invalid attempt

            $model = new AgentEmailVerifyAttempt;
            $model->code = $code;
            $model->agent_email = $email;
            $model->ip_address = Yii::$app->getRequest()->getUserIP();
            $model->save();

            /*return [
                'operation' => 'error',
                'message' => Yii::t('agent', 'Invalid email verification code.')
            ];*/

            return [
                'operation' => 'error',
                'message' => $response['message']
            ];
        }

            //remove old email verification attempts

            AgentEmailVerifyAttempt::deleteAll([
                'agent_email' => $email,
                'ip_address' => Yii::$app->getRequest()->getUserIP()
            ]);

            //remove otp

            //$agent->otp = null;
            //$agent->save(false);

            return $this->_loginResponse($response['data']);
    }

    /**
     * Sends password reset email to user
     * @return array
     * 
     * @api {post} /auth/request-reset-password Request password reset
     * @apiName RequestResetPassword
     * @apiGroup Auth
     *
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionRequestResetPassword() {

        $emailInput = Yii::$app->request->getBodyParam("email");
        $token = Yii::$app->request->getBodyParam('token');

        //TODO: make token as required field once we update android app

        if(YII_ENV == 'prod') {
            $response = Yii::$app->reCaptcha->verify($token);

            if (!$response->data || !$response->data['success']) {
                return [
                    "operation" => "error",
                    "code" => 0,
                    "message" => Yii::t('agent', "Invalid captcha validation")
                ];
            }
        }

        $errors = false;
        $model = new PasswordResetRequestForm();
        $model->email = $emailInput;

        if (!$model->validate()) {
            return [
                'operation' => 'error',
                'message' => $model->getErrors()
            ];
        }

        $agent = Agent::findOne([
           'agent_email' => $model->email,
        ]);

        //Check if this user sent an email in past few minutes (to limit email spam)

        $emailLimitDatetime = null;
        $currentDatetime = new \DateTime('now');
        
        if ($agent->agent_limit_email) {
        $emailLimitDatetime = new \DateTime($agent->agent_limit_email);
        date_add($emailLimitDatetime, date_interval_create_from_date_string('1 minutes'));
        }

        if ($agent->agent_limit_email && $currentDatetime < $emailLimitDatetime) {
            $difference = $currentDatetime->diff($emailLimitDatetime);
            $minuteDifference = (int) $difference->i;
            $secondDifference = (int) $difference->s;

            $errors = Yii::t('agent', "Email was sent previously, you may request another one in {numMinutes, number} minutes and {numSeconds, number} seconds", [
                'numMinutes' => $minuteDifference,
                'numSeconds' => $secondDifference,
            ]);
        } else if (!$model->sendEmail()) {
            $errors = Yii::t('agent', 'Sorry, we are unable to reset a password for email provided.');
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
            'message' => Yii::t ('agent', 'Please check the link sent to you on your email to set new password.')
        ];
    }

    /**
     * Updates password based on passed token
     * @return array
     * 
     * @api {PATCH} /auth/update-password Update password
     * @apiName UpdatePassword
     * @apiGroup Auth
     *
     * @apiParam {string} token Token.
     * @apiParam {string} newPassword New password.
     * @apiParam {string} cPassword Confirm password.
     * 
     * @apiSuccess {string} operation success|error.
     * @apiSuccess {string} message Message.
     */
    public function actionUpdatePassword() {

        $token = Yii::$app->request->getBodyParam("token");
        $newPassword = Yii::$app->request->getBodyParam("newPassword");
        //$cPassword = Yii::$app->request->getBodyParam("cPassword");

        $agent = Agent::findByPasswordResetToken($token);

        if (!$agent) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('agent', 'Invalid password reset token.')
            ];
        }
        if (!$newPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('agent', 'Password field required')
            ];
        }

        /*if (!$cPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('agent', 'Confirm Password field required')
            ];
        }

        if ($cPassword != $newPassword) {
            return [
                'operation' => 'error',
                'message' => Yii::t ('agent', 'Password & Confirm Password does not match')
            ];
        }*/

        $agent->setPassword($newPassword);
        $agent->removePasswordResetToken();

        /**
         * as password reset token will be sent to email and user will update password
         * from that link so if user have token he have valid email
         */

        $agent->agent_email_verification = Agent::EMAIL_VERIFIED;

        $agent->save(false);

        return $this->_loginResponse($agent);
    }

    /**
     * return user location detail by user ip address
     * @return array
     * 
     * @api {post} /auth/locate Return user location detail by user ip address
     * @apiName Locate
     * @apiGroup Auth
     *
     * @apiSuccess {string} ip IP address.
     * @apiSuccess {string} hostname Hostname.
     * @apiSuccess {string} city City.
     * @apiSuccess {string} region Region.
     * @apiSuccess {string} country Country.
     * @apiSuccess {string} loc Location.
     * @apiSuccess {string} org Organization.
     * @apiSuccess {string} postal Postal code.
     * @apiSuccess {string} timezone Timezone.
     */
    public function actionLocate() {
        return Yii::$app->ipstack->locate();
    }

    /**
     * Return agent data after successful login
     * @param  Agent $agent
     * @return array
     */
    private function _loginResponse($agent, $store_id = null)
    {
        // Return Agent access token if everything valid

        $accessToken = $agent->accessToken->token_value;

        $assignmentQuery = $agent->getAgentAssignments();

        if($store_id) {
            $assignmentQuery->andWhere(['agent_assignment.restaurant_uuid' => $store_id]);
        }

        $assignment = $assignmentQuery->one();

        /*if(!$assignment) {
          return [
              "operation" => "error",
              'message' => Yii::t ('agent', "You're not assigned to any store")
          ];
        }*/

        $selectedStore = null;

        if($assignment)
        {
            $selectedStore = $assignment->getRestaurant()
                ->select([
                    'restaurant_uuid',
                    'name',
                    'name_ar',
                    'restaurant_domain'
                ])
                ->one();
        }

        $stores = $agent->getAccountsManaged()
            ->select([
                'restaurant_uuid',
                'name',
                'name_ar',
                'restaurant_domain'
            ])
            ->all();

        return [
            "operation" => "success",
            "token" => $accessToken,
            "id" => $agent->agent_id,
            "username" => $agent->agent_id,
            "agent_name" => $agent->agent_name,
            "agent_email" => $agent->agent_email,
            "agent_new_email" => $agent->agent_new_email,
            "language_pref" => $agent->agent_language_pref,
            "role" =>  $assignment? (int) $assignment->role: null,
            "created_at" => strtotime($agent->agent_created_at),
            "selectedStore" => $selectedStore,
            "stores" => $stores
        ];
    }
}
