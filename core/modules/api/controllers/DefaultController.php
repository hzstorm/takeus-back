<?php
/**
 * Created by IntelliJ IDEA.
 * User: luwei
 * Date: 2017/6/19
 * Time: 15:15
 */

namespace app\modules\api\controllers;


use app\hejiang\ApiResponse;
use app\hejiang\BaseApiResponse;
use app\models\AppNavbar;
use app\models\Article;
use app\models\Banner;
use app\models\Cat;
use app\models\CouponMsg;
use app\models\FormId;
use app\models\Goods;
use app\models\Option;
use app\models\OrderPic;
use app\models\Setting;
use app\models\Store;
use app\models\SyUploadForm;
use app\models\UploadConfig;
use app\models\UploadForm;
use app\modules\api\behaviors\LoginBehavior;
use app\modules\api\models\CatListForm;
use app\modules\api\models\CommentListForm;
use app\modules\api\models\CouponListForm;
use app\modules\api\models\DistrictForm;
use app\modules\api\models\GoodsAttrInfoForm;
use app\modules\api\models\GoodsForm;
use app\modules\api\models\GoodsListForm;
use app\modules\api\models\GoodsQrcodeForm;
use app\modules\api\models\IndexForm;
use app\modules\api\models\ShareQrcodeForm;
use app\modules\api\models\ShopListForm;
use app\modules\api\models\TopicForm;
use app\modules\api\models\TopicListForm;
use app\modules\api\models\VideoForm;
use app\modules\api\models\ShopForm;
use Curl\Curl;
use yii\data\Pagination;
use yii\helpers\VarDumper;
use app\modules\api\models\TopicTypeForm;

class DefaultController extends Controller
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
        ]);
    }

    /**
     * 首页接口
     */
    public function actionIndex()
    {
        $form = new IndexForm();
        $form->store_id = $this->store->id;
        return $form->search();
    }

    /**
     * 分类列表
     */
    public function actionCatList()
    {
        $form = new CatListForm();
        $form->attributes = \Yii::$app->request->get();
        $form->store_id = $this->store->id;
        return $form->search();
    }

    /**
     * 购买数据
     */
    public function actionBuyData()
    {
        $key = "buy_data";
        $cache = \Yii::$app->cache;
        $data = $cache->get($key);
        $cha_time = time() - (int)json_decode($data)->time;
        $data = json_decode($data);
        unset($data->time);

        if ($data && $data->store_id == $this->store_id) {
            return new BaseApiResponse([
                'code' => 0,
                'data' => $data,
                'cha_time' => $cha_time,
                'md5' => md5(json_encode($data)),
            ]);
        } else {
            return new ApiResponse(1, 'Null');
        }
    }

    /**
     * 商品列表
     */
    public function actionGoodsList()
    {
        $form = new GoodsListForm();
        $form->attributes = \Yii::$app->request->get();
        $form->store_id = $this->store->id;
        return $form->search();
    }


    /**
     * 商品推荐
     */
    public function actionGoodsRecommend()
    {
        if (!$this->store->is_recommend) {
            return new ApiResponse(1, 'error');
        }
        $form = new GoodsListForm();
        $form->attributes = \Yii::$app->request->get();
        $form->goods_id = \Yii::$app->request->get('goods_id');
        $form->recommend_count = $this->store->recommend_count;
        $form->limit = 6;
        $form->store_id = $this->store->id;
        return $form->recommend();
    }

    /**
     * 商品详情
     */
    public function actionGoods()
    {
        $form = new GoodsForm();
        $form->attributes = \Yii::$app->request->get();
        if (!\Yii::$app->user->isGuest) {
            $form->user_id = \Yii::$app->user->id;
        }
        $form->store_id = $this->store->id;
        return $form->search();
    }

    /**
     * 省市区数据
     */
    public function actionDistrict()
    {
        $form = new DistrictForm();
        return $form->search();
    }


    public function actionGoodsAttrInfo()
    {
        $form = new GoodsAttrInfoForm();
        $form->attributes = \Yii::$app->request->get();
        return $form->search();
    }

    public function actionStore()
    {
        //分销设置
        $share_setting = Setting::find()->alias('s')
            ->where(['s.store_id' => $this->store_id])
            ->leftJoin('{{%qrcode}} q', 'q.store_id=s.store_id and q.is_delete=0')
            ->select(['s.*', 'q.qrcode_bg'])
            ->asArray()->one();
        if (is_null($share_setting)) {
            $share_setting = new Setting();
            $share_setting->store_id = $this->store_id;
            $share_setting->save();
            $share_setting = Setting::find()->alias('s')
                ->where(['s.store_id' => $this->store_id])
                ->leftJoin('{{%qrcode}} q', 'q.store_id=s.store_id and q.is_delete=0')
                ->select(['s.*', 'q.qrcode_bg'])
                ->asArray()->one();
        }
        $data = [
            'store_name' => $this->store->name,
            'contact_tel' => $this->store->contact_tel,
            'show_customer_service' => $this->store->show_customer_service,
            'share_setting' => $share_setting,
            'store' => (object)[
                'id' => $this->store->id,
                'name' => $this->store->name,
                'copyright' => $this->store->copyright,
                'copyright_pic_url' => $this->store->copyright_pic_url,
                'copyright_url' => $this->store->copyright_url,
                'contact_tel' => $this->store->contact_tel,
                'show_customer_service' => $this->store->show_customer_service,
                'cat_style' => $this->store->cat_style,
                'address' => $this->store->address,
                'is_offline' => $this->store->is_offline,
                'is_coupon' => $this->store->is_coupon,
                'service' => Option::get('service', $this->store->id, 'admin', '/images/icon-service.png'),
            ],
        ];
        return new ApiResponse(0, 'success', $data);
    }

    public function actionUploadImage()
    {
        $form = new UploadForm();
        $upload_config = UploadConfig::findOne(['store_id' => $this->store->id]);
        $form->upload_config = $upload_config;
        $data = $form->saveImage('image');
        return new BaseApiResponse($data);
    }

    public function actionUploadImageWithOrder()
    {
        $form = new SyUploadForm();

        $upload_config = UploadConfig::findOne(['store_id' => $this->store->id]);
        $form->upload_config = $upload_config;
        $data = $form->saveImage('image');

        $url = $data['data']['url'];
        $order_id = \Yii::$app->request->post('order_id');
        $orderPic = new OrderPic();
        $orderPic->url = $url;
        $orderPic->order_id = $order_id;
        $orderPic->is_delete = 0;
        $orderPic->addtime = time();
        $orderPic->store_id = $this->store_id;
        $orderPic->is_compress = 0;
        $orderPic->save();
        $data_compress = $form->saveImageCompress('image');
        $data = $data_compress;
        $url = $data['data']['url'];
        $order_id = \Yii::$app->request->post('order_id');
        $orderPic = new OrderPic();
        $orderPic->url = $url;
        $orderPic->order_id = $order_id;
        $orderPic->is_delete = 0;
        $orderPic->addtime = time();
        $orderPic->store_id = $this->store_id;
        $orderPic->is_compress = 1;
        $orderPic->save();


        return new BaseApiResponse($data);
    }

    //商品评价列表
    public function actionCommentList()
    {
        $form = new CommentListForm();
        $form->attributes = \Yii::$app->request->get();
        return $form->search();
    }

    //文章列表
    public function actionArticleList()
    {
        $list = Article::find()->where([
            'store_id' => $this->store->id,
            'is_delete' => 0,
            'article_cat_id' => \Yii::$app->request->get('cat_id'),
        ])->orderBy('sort DESC,addtime DESC')
            ->select('id,title')->asArray()->all();
        return new ApiResponse(0, 'success', ['list' => $list]);
    }

    //文章详情
    public function actionArticleDetail()
    {
        $id = \Yii::$app->request->get('id');
        if ($id == 'about_us') {
            $model = Article::findOne([
                'store_id' => $this->store->id,
                'article_cat_id' => 1,
            ]);
            if (!$model)
                $model = new Article();

            $data = [
                'id' => $model->id,
                'title' => $model->title,
                'content' => $model->content,
            ];
            return new ApiResponse(0, 'success', $data);
        } else {
            $model = Article::find()->where([
                'is_delete' => 0,
                'id' => $id,
            ])->select('id,title,content')->asArray()->one();
            if (empty($model)) {
                return new ApiResponse(1, '内容不存在');
            }
            return new ApiResponse(0, 'success', $model);
        }
    }

    //核销二维码  已废弃
    public function actionQrcode($path)
    {
        include \Yii::$app->basePath . '/extensions/phpqrcode/phpqrcode.php';
        \QRcode::png($path);
    }

    public function actionVideoList()
    {
        $form = new VideoForm();
        $form->store_id = $this->store_id;
        $form->attributes = \Yii::$app->request->get();
        $form->limit = 10;
        return $form->getList();
    }

    public function actionCouponList()
    {
        $form = new CouponListForm();
        $form->store_id = $this->store_id;
        $form->user_id = \Yii::$app->user->identity->id;
        $list = $form->getList();

        $coupon_msg = CouponMsg::findOne(['user_id' => $form->user_id, 'is_read' => 0]);
        if ($coupon_msg) {
            $coupon_msg->is_read = 1;
            $coupon_msg->save();
        }

        return new ApiResponse(0, 'success', ['list' => $list]);
    }

    //获取商品二维码海报
    public function actionGoodsQrcode()
    {
        $form = new ShareQrcodeForm();
        $form->attributes = \Yii::$app->request->get();

        $form->store_id = $this->store_id;
        $form->type = 0;
        if (!\Yii::$app->user->isGuest) {
            $form->user = \Yii::$app->user->identity;
            $form->user_id = \Yii::$app->user->id;
        }
        return new BaseApiResponse($form->search());
    }

    //专题列表
    public function actionTopicList()
    {
        $form = new TopicListForm();
        $form->attributes = \Yii::$app->request->get();
        $form->store_id = $this->store_id;
        $form->user_id = \Yii::$app->user->identity->id;

        return $form->search();
    }

    //专题详情
    public function actionTopic()
    {
        $form = new TopicForm();
        $form->attributes = \Yii::$app->request->get();
        $form->store_id = $this->store_id;
        if (!\Yii::$app->user->isGuest) {
            $form->user_id = \Yii::$app->user->id;
        }
        return $form->search();
    }

    //专题分类
    public function actionTopicType()
    {
        $form = new TopicTypeForm();
        $form->store_id = $this->store_id;
        return $form->search();
    }

    //底部导航栏
    public function actionNavbar()
    {
        $navbar = AppNavbar::getNavbar($this->store->id);
        return new ApiResponse(0, 'success', $navbar);
    }

    //顶部导航栏颜色
    public function actionNavigationBarColor()
    {
        $navigation_bar_color = Option::get('navigation_bar_color', $this->store->id, 'app', [
            'frontColor' => '#000000',
            'backgroundColor' => '#ffffff',
        ]);
        return new ApiResponse(0, 'success', $navigation_bar_color);
    }

    //门店列表
    public function actionShopList()
    {
        $form = new ShopListForm();
        $form->store_id = $this->store->id;
        $form->user = \Yii::$app->user->identity;
        $form->attributes = \Yii::$app->request->get();
        return $form->search();
    }

    //门店详情
    public function actionShopDetail()
    {
        $form = new ShopForm();
        $form->store_id = $this->store->id;
        $form->user = \Yii::$app->user->identity;
        $form->attributes = \Yii::$app->request->get();
        return $form->search();
    }
}