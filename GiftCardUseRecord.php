<?php
namespace BAE\Entity\Order;

/**
 *@author  翁茂意<wengmaoyi@baobeigezi.com>
 *@version  1.0
 *@copyright  Bbgz Tech Inc.
 *@date  2015-07-08 08:17
 */
 
 
use BAE\Entity\Entity;

class GiftCardUseRecord extends Entity
{
	//使用类型
	const USE_TYPE_PAY = 1;//使用
	const USE_TYPE_REFUND = 2;//退款
    /**
     * 主建
     *
     */
    protected $primaryKey = 'id';
    
    /**
     *数据连接名
     *
     */
    protected $connection = 'order';

    /**
     * Indicates if the model should be timestamped. 不使用自己维护插入及更新时间
     *
     * @var  bool
     */
    public $timestamps = false;

    /**
     * The database table used by the model.
     *
     * @var  string
     */
    protected $table = 'gift_card_use_record';
    

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var  array
     */
    protected $hidden = [];
    
    /**
     *获取表名
     *
     */
    public static function tableName(){
        return 'gift_card_use_record';
    }
}
