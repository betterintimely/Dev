<?php
namespace BAE\Entity\Order;

/**
 *@author  翁茂意<wengmaoyi@baobeigezi.com>
 *@version  1.0
 *@copyright  Bbgz Tech Inc.
 *@date  2015-07-08 08:17
 */
 
 
use BAE\Entity\Entity;

class GiftCard extends Entity
{
	
	const USED = 1;
	const UNUSED = 0;
	
	const NORMAL = 0;
	const DELETED = 1;
	
	const STATUS_OFF = 1;
	const STATUS_ON = 2;
	
	//是否已送出
	const SENDED = 1;//已送出
	const NOT_SEND = 0;//未送出
	
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
    protected $table = 'gift_card';
    

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
        return 'gift_card';
    }
}
