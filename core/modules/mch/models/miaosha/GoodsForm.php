<?php
/**
 * Created by PhpStorm.
 * User: peize
 * Date: 2018/3/7
 * Time: 15:49
 */

namespace app\modules\mch\models\miaosha;


use app\models\Attr;
use app\models\AttrGroup;
use app\models\MsGoods;
use app\models\MsGoodsPic;
use app\modules\mch\models\Model;
use yii\data\Pagination;

class GoodsForm extends Model
{
    public $goods;

    public $goods_pic_list;

    public $name;
    public $store_id;
    public $original_price;
    public $detail;
    public $service;
    public $sort;
    public $virtual_sales;
    public $cover_pic;
    public $sales;
    public $video_url;
    public $unit;
    public $weight;
    public $freight;
    public $full_cut;
    public $integral;
    public $use_attr;
    public $attr;
    public $coupon;
    public $is_discount;
    public $payment;
    public $individual_share;
    public $share_type;
    public $share_commission_first;
    public $share_commission_second;
    public $share_commission_third;

    public $goods_num;
    public $rebate;


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'original_price', 'detail', 'store_id', 'goods_pic_list'], 'required'],
            [[ 'weight'], 'number'],
            [['detail', 'cover_pic', 'video_url'], 'string'],
            [['sort', 'virtual_sales', 'store_id', 'freight', 'use_attr', 'is_discount', 'coupon'], 'integer'],
            [['name', 'unit'], 'string', 'max' => 255],
            [['service'], 'string', 'max' => 2000],
            [['attr', 'full_cut', 'integral'], 'safe',],
            [['goods_num','individual_share','share_type'], 'integer', 'min' => 0,],
            [['sort'], 'default', 'value' => 1000],
            [['payment'],'safe'],
            [['share_commission_first','share_commission_second','share_commission_third','rebate',],'default','value'=>0],
            [['share_commission_first','share_commission_second','share_commission_third','rebate',],'number','min'=>0],
            [['original_price'],'number','min'=>0.01]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '商品名称',
            'original_price' => '原价',
            'detail' => '商品详情，图文',
            'status' => '上架状态【1=> 上架，2=> 下架】',
            'service' => '服务选项',
            'sort' => '商品排序 升序',
            'virtual_sales' => '虚拟销量',
            'cover_pic' => '商品缩略图',
            'addtime' => '添加时间',
            'is_delete' => '是否删除',
            'store_id' => 'Store ID',
            'video_url' => '视频',
            'unit' => '单位',
            'weight' => '重量',
            'freight' => '运费模板ID',
            'full_cut' => '满减',
            'integral' => '积分设置',
            'use_attr' => '是否使用规格：0=不使用，1=使用',
            'attr' => '规格的库存及价格',
            'goods_num' => '商品库存',
            'coupon' => '是否支持优惠劵',
            'is_discount' => '是否支持会员折扣',
            'share_commission_first' => '一级分销比例',
            'share_commission_second' => '二级分销比例',
            'share_commission_third' => '三级分销比例',
            'rebate' => '自购返利',
        ];
    }

    /**
     * @return array
     * 秒杀商品列表
     */
    public function getList()
    {
        $query = MsGoods::find()
            ->andWhere(['is_delete' => 0, 'store_id' => $this->store_id]);

        $keyword = \Yii::$app->request->get('keyword');
        if (trim($keyword)) {
            $query->andWhere(['LIKE', 'name', $keyword]);
        }
        $count = $query->count();
        $p = new Pagination(['totalCount' => $count, 'pageSize' => 20]);

        $list = $query
            ->orderBy('sort ASC')
            ->offset($p->offset)
            ->limit($p->limit)
            ->all();
        return [$list, $p];
    }

    /**
     * @return array
     * 秒杀商品编辑
     */
    public function save()
    {
        if ($this->validate()) {
            if (!is_array($this->goods_pic_list) || empty($this->goods_pic_list) || count($this->goods_pic_list) == 0)
                return [
                    'code' => 1,
                    'msg' => '商品图片不能为空',
                ];

            if (!$this->use_attr && ($this->goods_num === null || $this->goods_num === '')) {
                return [
                    'code' => 1,
                    'msg' => '请填写商品库存',
                ];
            }
            if ($this->original_price > 99999999.99){
                return [
                    'code' => 1,
                    'msg' => '商品原价超过限制',
                ];
            }

            $goods = $this->goods;
            if ($goods->isNewRecord) {
                $goods->is_delete = 0;
                $goods->addtime = time();
                $goods->status = 0;
                $goods->sales = 0;
                $goods->coupon = 1;
                $goods->is_discount = 1;
                $goods->attr = \Yii::$app->serializer->encode([]);
            }

            $this->full_cut = \Yii::$app->serializer->encode($this->full_cut);
            if (!isset($this->integral['more'])) {
                $this->integral['more'] = '';
            }

            $this->integral = \Yii::$app->serializer->encode($this->integral);
            $this->payment = \Yii::$app->serializer->encode($this->payment);
            $_this_attributes = $this->attributes;
            unset($_this_attributes['attr']);
            $goods->attributes = $_this_attributes;
            $goods->use_attr = $this->use_attr ? 1 : 0;
            if ($goods->save()) {
                MsGoodsPic::updateAll(['is_delete' => 1], ['goods_id' => $goods->id]);
                foreach ($this->goods_pic_list as $pic_url) {
                    $goods_pic = new MsGoodsPic();
                    $goods_pic->goods_id = $goods->id;
                    $goods_pic->pic_url = $pic_url;
                    $goods_pic->is_delete = 0;
                    $goods_pic->save();
                }
                $this->setAttr($goods);
                return [
                    'code' => 0,
                    'msg' => '保存成功',
                ];
            } else {
                return $this->getErrorResponse($goods);
            }
        } else {
            return $this->errorResponse;
        }
    }

    /**
     * @param Goods $goods
     */
    private function setAttr($goods)
    {
        if (!$this->use_attr) {
            list($default_attr, $default_attr_group) = $this->getDefaultAttr();
            $this->attr = [
                [
                    'attr_list' => [
                        [
                            'attr_group_name' => $default_attr_group->attr_group_name,
                            'attr_id' => $default_attr->id,
                            'attr_name' => $default_attr->attr_name,
                        ],
                    ],
                    'num' => intval($this->goods_num) ? intval($this->goods_num) : 0,
                    'price' => 0,
                ],
            ];
        }
        if (empty($this->attr) || !is_array($this->attr))
            return;
        $new_attr = [];
        foreach ($this->attr as $i => $item) {
            $new_attr_item = [
                'attr_list' => [],
                'num' => intval($item['num']),
                'price' => doubleval($item['price']),
                'no' => $item['no'] ? $item['no'] : '',
                'pic' => $item['pic'] ? $item['pic'] : '',
            ];
            foreach ($item['attr_list'] as $a) {
                $attr_group_model = AttrGroup::findOne(['store_id' => $this->store_id, 'attr_group_name' => $a['attr_group_name'], 'is_delete' => 0]);
                if (!$attr_group_model) {
                    $attr_group_model = new AttrGroup();
                    $attr_group_model->attr_group_name = $a['attr_group_name'];
                    $attr_group_model->store_id = $this->store_id;
                    $attr_group_model->is_delete = 0;
                    $attr_group_model->save();
                }
                $attr_model = Attr::findOne(['attr_group_id' => $attr_group_model->id, 'attr_name' => $a['attr_name'], 'is_delete' => 0]);
                if (!$attr_model) {
                    $attr_model = new Attr();
                    $attr_model->attr_name = $a['attr_name'];
                    $attr_model->attr_group_id = $attr_group_model->id;
                    $attr_model->is_delete = 0;
                    $attr_model->save();
                }
                $new_attr_item['attr_list'][] = [
                    'attr_id' => $attr_model->id,
                    'attr_name' => $attr_model->attr_name,
                ];
            }
            $new_attr[] = $new_attr_item;
        }
        $goods->attr = \Yii::$app->serializer->encode($new_attr);
        $goods->save();
    }

    /**
     * @return array
     */
    private function getDefaultAttr()
    {
        $default_attr_name = '默认';
        $default_attr_group_name = '规格';
        $attr = Attr::findOne([
            'attr_name' => $default_attr_name,
            'is_delete' => 0,
            'is_default' => 1,
        ]);
        $attr_group = null;
        if (!$attr) {
            $attr_group = AttrGroup::findOne([
                'attr_group_name' => $default_attr_group_name,
                'is_delete' => 0,
            ]);
            if (!$attr_group) {
                $attr_group = new AttrGroup();
                $attr_group->store_id = $this->store_id;
                $attr_group->attr_group_name = $default_attr_group_name;
                $attr_group->is_delete = 0;
                $attr_group->save(false);
            }
            $attr = new Attr();
            $attr->attr_group_id = $attr_group->id;
            $attr->attr_name = $default_attr_name;
            $attr->is_delete = 0;
            $attr->is_default = 1;
            $attr->save(false);
        } else {
            $attr_group = AttrGroup::findOne($attr->attr_group_id);
        }
        return [$attr, $attr_group];
    }

}