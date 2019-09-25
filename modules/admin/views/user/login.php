<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model \mdm\admin\models\form\Login */

$this->title = Yii::t('rbac-admin', 'Вход');
$this->params['breadcrumbs'][] = $this->title;
?>

<?php if ($url and !$code): ?>
    <a href="<?= $url ?>"><?= Html::img('@web/upload/images/vk-login.png')?> Вход через VK</a>
<?php endif; ?>
    
<div class="site-login">
    <h1><?= Html::encode($this->title) ?></h1>

    <p>Для входа заполните поля:</p>

    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
                <?= $form->field($model, 'username') ?>
                <?= $form->field($model, 'password')->passwordInput() ?>
                <?= $form->field($model, 'rememberMe')->checkbox() ?>
                <div style="color:#999;margin:1em 0">
                    Если вы забыли пароль, то можете <?= Html::a('Восстановить пароль', ['user/request-password-reset']) ?>.
                    Если вы новый пользователь, то можете <?= Html::a('Зарегистрироваться', ['user/signup']) ?>.
                </div>
                <div class="form-group">
                    <?= Html::submitButton(Yii::t('rbac-admin', 'Вход'), ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
                </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
