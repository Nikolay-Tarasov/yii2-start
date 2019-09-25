<?php

use yii\db\Migration;

/**
 * Class m190925_121316_update_table_user
 */
class m190925_121316_update_table_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user', 'vk_id', $this->integer(20));
        $this->addColumn('user', 'main_photo', $this->string(255)->defaultValue('upload/images/no-avatar.png'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m190925_121316_update_table_user cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m190925_121316_update_table_user cannot be reverted.\n";

        return false;
    }
    */
}
