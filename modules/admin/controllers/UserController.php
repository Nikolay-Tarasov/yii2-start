<?php

namespace app\modules\admin\controllers;

use VK\OAuth\VKOAuth;
use VK\Client\VKApiClient;
use VK\OAuth\VKOAuthDisplay;
use VK\OAuth\Scopes\VKOAuthGroupScope;
use VK\OAuth\Scopes\VKOAuthUserScope;
use VK\OAuth\VKOAuthResponseType;

use app\modules\admin\components\UserStatus;
use app\modules\admin\models\form\ChangePassword;
use app\modules\admin\models\form\Login;
use app\modules\admin\models\form\PasswordResetRequest;
use app\modules\admin\models\form\ResetPassword;
use app\modules\admin\models\form\Signup;
use app\modules\admin\models\searchs\User as UserSearch;
use app\modules\admin\models\User;
use Yii;
use yii\base\InvalidParamException;
use yii\base\UserException;
use yii\filters\VerbFilter;
use yii\mail\BaseMailer;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * User controller
 */
class UserController extends Controller
{
    private $_oldMailPath;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'logout' => ['post'],
                    'activate' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            if (Yii::$app->has('mailer') && ($mailer = Yii::$app->getMailer()) instanceof BaseMailer) {
                /* @var $mailer BaseMailer */
                $this->_oldMailPath = $mailer->getViewPath();
                $mailer->setViewPath('@mdm/admin/mail');
            }
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        if ($this->_oldMailPath !== null) {
            Yii::$app->getMailer()->setViewPath($this->_oldMailPath);
        }
        return parent::afterAction($action, $result);
    }

    /**
     * Lists all User models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new UserSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single User model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
                'model' => $this->findModel($id),
        ]);
    }

    /**
     * Deletes an existing User model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Login
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->getUser()->isGuest) {
            return $this->goHome();
        }

        $model = new Login();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->login()) {
            return $this->goBack();
        } else {
            $oauth = new VKOAuth();
            $client_id = \Yii::$app->vk->client_id;
            $redirect_uri = \Yii::$app->vk->redirect_uri;
            $display = VKOAuthDisplay::POPUP;
            $client_secret = \Yii::$app->vk->client_secret;
            $scope = array(VKOAuthUserScope::FRIENDS, VKOAuthUserScope::EMAIL, VKOAuthUserScope::OFFLINE,);
            $state = \Yii::$app->vk->state;
            $url = $oauth->getAuthorizeUrl(VKOAuthResponseType::CODE, $client_id, $redirect_uri, $display, $scope, $state);
            if(\Yii::$app->request->get('code') and !$code){
                $code = \Yii::$app->request->get('code');
                $response = $oauth->getAccessToken($client_id, $client_secret, $redirect_uri, $code);
                $vk = new VKApiClient();
                $info = $vk->users()->get($response['access_token'], array(
                    'user_ids' => array($response['user_id']),
                    'fields' => array('city', 'photo', 'email'),
                ));
                $vk_id = User::findOne(['vk_id' => $response['user_id']]);
                $email = User::findOne(['email' => $response['email']]);
                if(!$vk_id){
                        if(User::findOne(['email' => $response['email']])){
                            Yii::$app->session->setFlash('email_error', "Пользователь с почтой $email->email уже зарегистрирован");
                            return $this->redirect(['user/login']);  
                        }
                        $class = Yii::$app->getUser()->identityClass ? : 'app\modules\admin\models\User';
                        $signup = new $class();
                        $signup->vk_id = $response['user_id'];
                        $signup->username = $info[0]['first_name'].' '.$info[0]['last_name'];
                        $signup->email = $response['email'];
                        $signup->main_photo = $info[0]['photo'];
                        $signup->setPassword(NULL);
                        $signup->generateAuthKey();
                        $signup->save();
                        if(Yii::$app->user->login(User::findOne(['vk_id' => $response['user_id']]))){
                            return $this->goHome();
                        }
                } else {
                    Yii::$app->user->login($vk_id);
                    return $this->goHome();
                }
            }
            return $this->render('login', [
                    'model' => $model,
                    'url' => $url,
                    'code' => $code,
                    'info' => $info,
            ]);
        }
    }
    
    /**
     * Logout
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->getUser()->logout();

        return $this->goHome();
    }

    /**
     * Signup new user
     * @return string
     */
    public function actionSignup()
    {
        $model = new Signup();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                return $this->goHome();
            }
        }

        return $this->render('signup', [
                'model' => $model,
        ]);
    }

    /**
     * Request reset password
     * @return string
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequest();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                Yii::$app->getSession()->setFlash('success', 'Check your email for further instructions.');

                return $this->goHome();
            } else {
                Yii::$app->getSession()->setFlash('error', 'Sorry, we are unable to reset password for email provided.');
            }
        }

        return $this->render('requestPasswordResetToken', [
                'model' => $model,
        ]);
    }

    /**
     * Reset password
     * @return string
     */
    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPassword($token);
        } catch (InvalidParamException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate() && $model->resetPassword()) {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }

        return $this->render('resetPassword', [
                'model' => $model,
        ]);
    }

    /**
     * Reset password
     * @return string
     */
    public function actionChangePassword()
    {
        $model = new ChangePassword();
        if ($model->load(Yii::$app->getRequest()->post()) && $model->change()) {
            return $this->goHome();
        }

        return $this->render('change-password', [
                'model' => $model,
        ]);
    }

    /**
     * Activate new user
     * @param integer $id
     * @return type
     * @throws UserException
     * @throws NotFoundHttpException
     */
    public function actionActivate($id)
    {
        /* @var $user User */
        $user = $this->findModel($id);
        if ($user->status == UserStatus::INACTIVE) {
            $user->status = UserStatus::ACTIVE;
            if ($user->save()) {
                return $this->goHome();
            } else {
                $errors = $user->firstErrors;
                throw new UserException(reset($errors));
            }
        }
        return $this->goHome();
    }

    /**
     * Finds the User model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return User the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
