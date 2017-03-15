<?php
namespace BAE\Entity\Order;

/**
 *@author  翁茂意<wengmaoyi@baobeigezi.com>
 *@version  1.0
 *@copyright  Bbgz Tech Inc.
 *@date  2015-07-08 08:17
 */
 
 
use BAE\Entity\Entity;

class Coupon extends Entity
{
    const STATUS_YES = 2;
    const STATUS_NO = 1;
    const DELETE = 1;
    const NORMAL = 0;
	
	const ONLY_NOT_BONDED = 0;//非保税可用 
	const ONLY_BONDED = 1;//仅保税可用 
	const ONLY_ABROAD = 2;//仅海外可用 
	const ONLY_HOME = 3;//仅国内可用
	const ALL_COUNTRY = 4;//随便用
	

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
    protected $table = 'coupon';
    

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
        return 'coupon';
    }
}
