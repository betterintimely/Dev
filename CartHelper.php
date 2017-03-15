<?php
namespace BAE\Helper\Cart;
use BAE\Model\Cart\CartModel;
use Config;
use BAE\Helper\FormatHelper;
use BAE\Model\User\UserModel;

/**
 * 购物车辅助类
 * 
 * @author 翁茂意<wengmaoyi@baobeigezi.com>
 * @date 2015/8/25
 * @time 17:00
 */

class CartHelper {
	private static $_currentCartId;

	/**
	 * 清除当前购物车id
	 */
	public static function destroyCurrentCartId($flushCart = false){
		$cookieName = self::getCartCookieName();
		$cookieDomain = Config::get('session.domain');
		if($flushCart){
			CartModel::flushCart(self::getCurrentCartId(FALSE));
		}
		return setcookie($cookieName, '', time()-1000, '/', $cookieDomain, null, true);
	}

	/**
	 * 
	 * @return type
	 * @desc 优先从用户信息里获取，如果用户信息里没有再从cookie里获取，如果还是没有就生成并保存到用户信息及cookie
	 */
	public static function getCurrentCartId($needGenerate = true){
		if(self::$_currentCartId){
			return self::$_currentCartId;
		}
		//优先从用户信息里获取，如果没有走cookie里的信息
		$userInfo = UserModel::getCurrentUser();
		$cartId = '';
		if(isset($userInfo['shopping_cart_id']) && $userInfo['shopping_cart_id']){
			$cartId = $userInfo['shopping_cart_id'];
		}
		
		if(!$cartId){
			$cartId = isset($_COOKIE[self::getCartCookieName()]) ? $_COOKIE[self::getCartCookieName()] : '';
		}
		
		if(!$cartId && $needGenerate){
			$cartId = CartModel::generateCartId();
			self::setCurrentCartId($cartId);
		}
		self::$_currentCartId = $cartId;
		return $cartId;
	}
	
	/**
	 * 购物车商品全选
	 * @return boolean
	 */
	public static function checkAllGoods(){
		$goodsIds = array_keys(CartModel::getCart(self::getCurrentCartId()));
		return self::checkGoods($goodsIds);
	}
	
	/**
	 * 购物车商品全不选
	 * @return boolean
	 */
	
	public static function uncheckAllGoods(){
		$goodsIds = array_keys(CartModel::getCart(self::getCurrentCartId()));
		return self::uncheckGoods($goodsIds);
	}

	/**
	 * 选中购物车商品
	 * @param type $cartId
	 * @param type $goodsId
	 * @return type
	 */
	public static function checkGoods($goodsId){
		$formatedId = FormatHelper::toInt($goodsId, true);
		if(!$formatedId){
			return false;
		}
		return CartModel::switchGoodsCheck(self::getCurrentCartId(), $formatedId);
	}
	
	/**
	 * 取消选中物车商品
	 * @param type $cartId
	 * @param type $goodsId
	 * @return type
	 */
	public static function uncheckGoods($goodsId){
		$formatedId = FormatHelper::toInt($goodsId, true);
		if(!$formatedId){
			return false;
		}
		return CartModel::switchGoodsCheck(self::getCurrentCartId(), $formatedId, false);
	}
	
	/**
	 * 设置当前用户的购物车id
	 * @param type $cartId
	 * @return boolean
	 */
	public static function setCurrentCartId($cartId, $mergeCart = true){
		$cookieName = self::getCartCookieName();
		$cookieExpire = Config::get('cart.cookieExpire');
		$cookieDomain = Config::get('session.domain');
		$currentCartId = self::getCurrentCartId(false);
		if($mergeCart && $currentCartId && $currentCartId != $cartId){
			CartModel::mergeCart($cartId, $currentCartId);
		}
		self::$_currentCartId = $cartId;
		
		//如果是登录状态不写cookie并清掉已有cookie
		$userInfo = UserModel::getCurrentUser();
		if(isset($userInfo['uid']) && $userInfo['uid']){
			return setcookie($cookieName, $cartId, time()-1, '/', $cookieDomain, null, true);
		}
		return setcookie($cookieName, $cartId, time() + $cookieExpire, '/', $cookieDomain, null, true);
	}
	
	/**
	 * 获取购物车cookie名
	 * 
	 * @return type
	 * @throws \Exception | string
	 */
	public static function getCartCookieName(){
		$cookieName = Config::get('cart.cookieName');
		if(!$cookieName){
			throw new \Exception('Please set cart cookiename');
		}
		return $cookieName;
	}
	
	/**
	 * 当前购物车信息
	 * @return type
	 */
	public static function getCurrentCart(){
		return CartModel::getCart(self::getCurrentCartId());
	}
	
	/**
	 * 获取当前购物车商品总数量
	 * @return int
	 */
	public static function getCurrentCartGoodsNum(){
		$ret = self::getCurrentCart();
		if(!$ret){
			return 0;
		}
		return array_sum($ret);
	}
	
	/**
	 * 添加商品到购物车或者增加商品数量
	 * @param type $goodsId
	 * @param type $goodsNum
	 * @return boolean
	 */
	public static function incrOrDecrCart($goodsId, $goodsNum){
		//检测商品有效性
		$ret = CartGoodsHelper::isAvailable($goodsId, $goodsNum);
		if(!$ret['flag']){
			return $ret;
		}
		$cartId = self::getCurrentCartId();
		self::checkGoods($goodsId);
		
		$flag = CartModel::incrOrDecrCart($cartId, $goodsId, $goodsNum);
		if(!$flag){
			return ['flag' => false, 'msg' => '添加失败'];
		}
		return ['flag' => true, 'msg' => '添加成功'];
	}
	
	/**
	 * 删除购物车商品信息
	 * @param type $goodsId
	 * @return boolean
	 */
	public static function delCart($goodsId){
		$formatedId = FormatHelper::toInt($goodsId, true);
		if(!$formatedId){
			return false;
		}
		$cartId = self::getCurrentCartId();
		self::uncheckGoods($formatedId);
		return CartModel::delCart($cartId, $formatedId);
	}
	
	/**
	 * 设置购物车商品数量
	 * @param int $goodsId
	 * @param int $goodsNum
	 * @return boolean
	 */
	public static function setCart($goodsId, $goodsNum){
		//检测商品有效性
		$ret = CartGoodsHelper::isAvailable($goodsId, $goodsNum);
		if(!$ret['flag']){
			return $ret;
		}
		$flag = CartModel::setCart(self::getCurrentCartId(), $goodsId, $goodsNum);
		if(!$flag){
			return ['flag' => false, 'msg' => '操作失败'];
		}
		return ['flag' => true, 'msg' => '添加成功'];
	}
}
