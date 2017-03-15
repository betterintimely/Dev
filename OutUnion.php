<?php
namespace BAE\Entity\Order;

/**
 *@author  翁茂意<wengmaoyi@baobeigezi.com>
 *@version  1.0
 *@copyright  Bbgz Tech Inc.
 *@date  2015-07-08 08:17
 */
 
 
use BAE\Entity\Entity;

class OutUnion extends Entity
{
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
    protected $table = 'out_union';
    

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
        return 'out_union';
    }
}
