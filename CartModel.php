<?php
namespace BAE\Model\Cart;

use BAE\Helper\FuncHelper;
use Illuminate\Support\Facades\Redis;
/**
 * Description of CartModel
 *
 * @author Freeloop
 */
class CartModel {

	private static $userCartKey = 'USER::CART::KEY::%s';//用户购物车KEY
	private static $userCartCheckKey = 'USER::CART::CHECKED::KEY::%s';//用户购物车KEY
	private static $redisInstance;
	public static function getCartRedisInstance(){
		if(!self::$redisInstance){
			self::$redisInstance = Redis::connection('cart');
		}
		return self::$redisInstance;
	}

	private static function getCartKey($cartId){
		return sprintf(self::$userCartKey, $cartId);
	}
	
	private static function getCartCheckKey($cartId){
		return sprintf(self::$userCartCheckKey, $cartId);
	}
	
	/**
	 * 设置商品选中状态
	 * @param type $cartId
	 * @param type $goodsId
	 * @param boolean $status true 选中 false取消选中
	 * @return boolean
	 */
	public static function switchGoodsCheck($cartId, $goodsId, $status = true){
		if (empty($cartId) || !$goodsId) {
            return false;
        }
		$goodsIds = $goodsId;
		if(!is_array($goodsId)){
			$goodsIds = [$goodsId];
		}
        $checkedKey = self::getCartCheckKey($cartId);
		$Redis = self::getCartRedisInstance();
		if($status){
			foreach($goodsIds as $id){
				$Redis->hset($checkedKey, $id, 1);
			}
		}else{
			foreach($goodsIds as $id){
				$Redis->hdel($checkedKey, $id);
			}
		}
		/**
		 * 校验有没有成功
		 */
		$currentCheckedGoods = array_keys(self::getCheckedGoods($cartId));
		if($status){
			foreach($goodsIds as $id){
				if(!in_array($id, $currentCheckedGoods)){
					return false;
				}
			}
		}else{
			foreach($goodsIds as $id){
				if(in_array($id, $currentCheckedGoods)){
					return false;
				}
			}
		}
		return true;
	}

	
	public static function getCheckedGoods($cartId){
		if (empty($cartId)) {
            return [];
        }
		
        $cartCheckedRedisKey = self::getCartCheckKey($cartId);
		$Redis = Redis::connection('cart');
		return $Redis->hgetall($cartCheckedRedisKey);
	}

	/**
     * 创建购物车唯一ID
     *
     */
	public static function generateCartId(){
		return FuncHelper::generateUuidV4();
	}
	 
    /**
	 * 添加购物车
	 * 
	 * @param type $cartId
	 * @param type $goodsId
	 * @param type $goodsNum
	 * @return boolean
	 */
    public static function setCart ($cartId, $goodsId, $goodsNum) {
        if (empty($cartId) || $goodsId <= 0 || !is_int($goodsId) || $goodsNum <= 0 || !is_int($goodsNum)) {
            return false;
        }

        $cartKey = self::getCartKey($cartId);
		$Redis = self::getCartRedisInstance();
        $Redis->hset($cartKey, $goodsId, $goodsNum);
		$currentNum = $Redis->hget($cartKey, $goodsId);
		if($currentNum == $goodsNum){
			return true;
		}
		return false;
    }

    /**
     * 删除购物车中的商品
     *
     * @param $cartId
     * @param $goodsId array() || id
     * @return boolean
     */
    public static function delCart ($cartId, $goodsId) {
        if (empty($cartId) || !$goodsId) {
            return false;
        }
        $cartKey = self::getCartKey($cartId);
		$Redis = self::getCartRedisInstance();
		if(is_array($goodsId) && $goodsId){
			foreach ($goodsId as $id) {
				$ret = $Redis->hdel($cartKey, $id);
				if (!$ret) {
					return false;
				}
			}
			return true;
		}else{
			return $Redis->hdel($cartKey, $goodsId);
		}
		return false;
    }

    /**
     * 修改购物车商品购买数量
     *
     * @param $cartId
     * @param $goodsId
     * @param $goodsNum
     * @return boolean
     */
    public static function incrOrDecrCart ($cartId, $goodsId, $goodsNum) {
        if (!$cartId || !is_numeric($goodsId) || !$goodsId || !$goodsNum || !is_numeric($goodsNum)) {
            return false;
        }

        $cartKey = self::getCartKey($cartId);
		$Redis = self::getCartRedisInstance();
		//获取当前数量
		$oldNum = $Redis->hget($cartKey, $goodsId);
		$currentNum = intval($oldNum + $goodsNum);
		if($currentNum <= 0){
			$ret = self::delCart($cartId, $goodsId);
		}else{
			$ret = self::setCart($cartId, $goodsId, $currentNum);
		}
        return $ret;
    }


    /**
     * 清空购物车
     *
     * @param $cartId
     */
    public static function flushCart ($cartId) {
        if (empty($cartId)) {
            return false;
        }

        /*
         * 读取全部信息然后进行删除
         */
		$checkedGoodsIdArr = array_keys(self::getCheckedGoods($cartId));
		self::switchGoodsCheck($cartId, $checkedGoodsIdArr, FALSE);
		$goodsIdArr = array_keys(self::getCart($cartId));
        return self::delCart($cartId, $goodsIdArr);
    }

    /**
	 * 
	 * @param type $cartId
	 * @return array 
	 */
    public static function getCart ($cartId) {
        if (empty($cartId)) {
            return [];
        }
		
        $cartRedisKey = self::getCartKey($cartId);
		$Redis = Redis::connection('cart');
		return $Redis->hgetall($cartRedisKey);
    }

	public function setFlashBuyLimit($uid){
		$Redis = Redis::connection('cart');
	    return $Redis->save('limit_flash_buy_'.$uid,1,15);
    }
    
    public function checkFlashBuyLimit($uid){
		$Redis = Redis::connection('cart');
	    return $Redis->get('limit_flash_buy_'.$uid);
    }
    
    
    /**
     * 统计购物车商品数量
     *
     * @param $cartId
     * @return int
     */
    public static  function countCart ($cartId) {
        if (empty($cartId)) {
            return 0;
        }

        $cartKey = self::getCartKey($cartId);
		$Redis = self::getCartRedisInstance();
        $list = $Redis->hvals($cartKey);
        $num = 0;
        if (is_array($list) && !empty($list)) {
            $num = array_sum($list);
        }
        return $num;
    }

    /**
     * 合并两个购物车的信息
     *
     * @param $masterCartId 主购物车id
	 * @param $tempCartId 其它购物车id
     */
    public static function mergeCart ($masterCartId, $tempCartId) {
        if (!$masterCartId || !$tempCartId || $tempCartId == $masterCartId) {
            return false;
        }
        $tempCart = self::getCart($tempCartId);
        $masterCart = self::getCart($masterCartId);
		
		foreach($tempCart as $goodsId => $goodsNum){
			if(!isset($masterCart[$goodsId])){
				$masterCart[$goodsId] = 0;
			}
			$currentGoodsNum = $masterCart[$goodsId] + $goodsNum;
			$results = self::setCart($masterCartId, $goodsId, $currentGoodsNum);
			if(!$results){
				return false;
			}
			//删除被合并的购物车信息
			self::delCart($tempCartId, $goodsId);
		}
		return true;
    }
}
