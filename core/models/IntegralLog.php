<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "{{%integral_log}}".
 *
 * @property string $id
 * @property string $user_id
 * @property string $content
 * @property string $integral
 * @property string $addtime
 * @property string $username
 * @property string $operator
 * @property string $store_id
 * @property string $operator_id
 * @property integer $type
 */
class IntegralLog extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%integral_log}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'content', 'addtime', 'username', 'operator', 'store_id', 'operator_id'], 'required'],
            [['user_id', 'integral', 'addtime', 'store_id', 'operator_id', 'type'], 'integer'],
            [['content'], 'string'],
            [['username', 'operator'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => '用户id',
            'content' => '描述',
            'integral' => '积分',
            'addtime' => '添加时间',
            'username' => '用户名',
            'operator' => '操作者',
            'store_id' => 'Store ID',
            'operator_id' => '分销商id',
            'type' => '数据类型 0--积分修改 1--余额修改',
        ];
    }
}
