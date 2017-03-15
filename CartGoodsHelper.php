<?php
namespace BAE\Helper\Cart;
use BAE\Model\Cart\CartModel;
use BAE\Model\Goods\GoodsModel;
use BAE\Entity\Product\Product;
use BAE\Entity\Product\Goods;
use BAE\Helper\ArrayHelper;
use BAE\Model\Activity\ActivityCalculateModel;
use BAE\Model\Product\StoreHouseModel;
use URL;
use Input;
use BAE\Helper\StockHelper;
use BAE\Model\Product\ProductModel;
use BAE\Model\Activity\ActivityModel;
use BAE\Helper\Cart\CartHelper;
use BAE\Helper\TaxHelper;
use BAE\Helper\Order\OrderCheckHelper;
use BAE\Model\Category\CategorySizeModel;
use BAE\Model\PlatformModel;
use BAE\Helper\DomainHelper;
use BAE\Entity\Fx\ProductCommission;
/**
 * 购物车商品信息辅助类
 * 
 * @author 翁茂意<wengmaoyi@baobeigezi.com>
 * @date 2015/8/25
 * @time 17:00
 */

class CartGoodsHelper {
	private static $_currentCartId;
	
	public static function getCurrentToOrderList($platform = ''){
		$cartId = CartHelper::getCurrentCartId();
		$checkedGoodsId = array_keys(CartModel::getCheckedGoods($cartId));
		if(!$checkedGoodsId){
			return [];
		}
		$goodsNums = CartModel::getCart($cartId);
		$goodsInfo = CartGoodsHelper::getGoodsInfo($checkedGoodsId, $goodsNums);
		
		$stockMap = StockHelper::getStockByGoodsInfo($goodsInfo);
		
		//把可以一起下单的商品归类在一起
		$grouped = [];
		foreach($goodsInfo as $item){
			if(!isset($stockMap[$item['goods_id']]) || !isset($goodsNums[$item['goods_id']]) || $goodsNums[$item['goods_id']] <= 0 || $stockMap[$item['goods_id']] < $goodsNums[$item['goods_id']] || !$item['is_onshelf'] || $item['is_delete'] == Product::DELETED){
				continue;
			}
			$outGoodsItem = [
				'goods_id' => $item['goods_id'],
				'product_id' => $item['product_id'],
				'goods_name' => $item['goods_name'],
				'market_price' => $item['market_price'],
				'store_price' => $item['store_price'],
				'product_name' => $item['product_name'],
				'image_100' => $item['image_100'],
				'image_250' => $item['image_250'],
				'image_400' => $item['image_400'],
				'image_raw' => $item['image_raw'],
				'goods_num' => $item['goods_num'],
				'goods_amount' => $item['goods_amount'],
				'pay_price' => $item['pay_price'],
				'discount_price' => $item['discount_price'],
				'discount_amount' => $item['discount_amount'],
				'tax_amount' => $item['tax_amount'],
				'coupon_amount' => $item['coupon_amount'],
				'limit_buy' => $item['limit_buy'],
				'max_buy' => $item['max_buy'],
			];
			$groupKey = $item['shop_id'].'_'.$item['is_oversea'].'_'.$item['sellcountry_id'];
			if(!isset($grouped[$groupKey])){
				$grouped[$groupKey] = [
					'is_oversea' => $item['is_oversea'], 
					'sellcountry_id' => $item['sellcountry_id'],
					'shop_id' => $item['shop_id'],
					'num' => $goodsNums[$item['goods_id']], 
					'amount' => $item['goods_amount'],
					'items'  => [$outGoodsItem['goods_id'] => $outGoodsItem],
					'discountAmount' => $item['discount_amount'],
						];
			}else{
				$grouped[$groupKey]['num'] += $goodsNums[$item['goods_id']];
				$grouped[$groupKey]['amount'] += $item['goods_amount'];
				
				$grouped[$groupKey]['items'][$outGoodsItem['goods_id']] = $outGoodsItem;
				$grouped[$groupKey]['discountAmount'] += $item['discount_amount'];
				
			}
			
		}
		if(!$grouped){
			return [];
		}
		if(!$platform){
			$platform = config('app.platform');
		}
		foreach($grouped as $item){
			$prefix = '';
			
			if($item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] != 0){//保税
				$prefix .= '5';
			}else{
				$prefix .= '0';
			}
			$groupKey = $prefix.'_';
			if(!($item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] != 0)){//非保税
				$groupKey .= '_0';
			}else{
				$groupKey .= '_'.$item['shop_id'];
			}
			
			//宝贝格子特殊处理
			if($item['is_oversea'] == Product::ABROAD || ($item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] == 0)){
				$groupKey .= '_0';
			}else{
				$groupKey .= '_'.$item['is_oversea'];
			}
			if($item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] != 0){
				$groupKey .= '_'.$item['sellcountry_id'];
				$storeInfo = StoreHouseModel::getStoreHouseInfo($item['sellcountry_id'], true);
				if(!isset($list[$groupKey])){
					if($platform == PlatformModel::H5){
						$orderUrl = URL::route('order.confirm', ['type' => $item['sellcountry_id']]);
					}else if($platform == PlatformModel::PC){
						$orderUrl = DomainHelper::get('trade').'order/'.$item['sellcountry_id'];
					}
					$list[$groupKey] = [
						'name' => isset($storeInfo['stockName']) ? $storeInfo['stockName'] : '',
						'orderUrl' => $orderUrl,
						'num' => $item['num'],
						'valid' => true,
						'items' => $item['items'],
						'discountAmount' => $item['discountAmount'],
						'amount' => $item['amount'],
							];
				}else{
					$list[$groupKey]['num'] += $item['num'];
					$list[$groupKey]['items'] += $item['items'];
					$list[$groupKey]['discountAmount'] += $item['discountAmount'];
					$list[$groupKey]['amount'] += $item['amount'];
				}
				
				if(!OrderCheckHelper::checkBondedLimit($item['sellcountry_id'], $item['amount'], $item['num'])){
					$list[$groupKey]['valid'] = false;
					$list[$groupKey]['err'] = '商品金额超过海关规定购买多件总价不能超过￥2000的限制，请分次购买！';
				}
			}else{
				$groupKey .= '_self';
				if(!isset($list[$groupKey])){
					if($platform == PlatformModel::H5){
						$orderUrl = URL::route('order.confirm', ['type' => 'self']);
					}else if($platform == PlatformModel::PC){
						$orderUrl = DomainHelper::get('trade').'order/self';
					}
					$list[$groupKey] = [
						'name' => '宝贝格子及其他商品',
						'orderUrl' => $orderUrl,
						'num' => $item['num'],
						'valid' => true,
						'items' => $item['items'],
						'discountAmount' => $item['discountAmount'],
						'amount' => $item['amount'],
						];
				}else{
					$list[$groupKey]['num'] += $item['num'];
					$list[$groupKey]['items'] += $item['items'];
					$list[$groupKey]['discountAmount'] += $item['discountAmount'];
					$list[$groupKey]['amount'] += $item['amount'];
				}
			}
		}
		//检测商品数量限制有没有满足
		foreach ($list as $groupKey => $item){
			if(!$item['valid']){//已经有错误的不在验证
				continue;
			}
			$goodsLimitError = [];
			foreach($item['items'] as $goodsItem){
				if($goodsItem['limit_buy'] >0 && $goodsItem['limit_buy'] > $goodsItem['goods_num']){
					$goodsLimitError[] = $goodsItem['goods_name'].$goodsItem['limit_buy'].'件起订';
				}

				if($goodsItem['max_buy'] >0 && $goodsItem['max_buy'] < $goodsItem['goods_num']){//超过最多限制
					$goodsLimitError[] = $goodsItem['goods_name'].'最多只能购买'.$goodsItem['max_buy'].'件';
				}
			}
			if($goodsLimitError){
				$list[$groupKey]['valid'] = false;
				$list[$groupKey]['err'] = implode(',', $goodsLimitError);
			}
		}
		ksort($list);
		$outArr = [
			'popList' => true,
			'list' => $list,
		];
		$theOnly = [];
		if(count($list) == 1){
			$theOnly = array_pop($list);
			//当选中只有一个时
			if($theOnly['valid']){
				$outArr['popList'] = false;
				$outArr['toUrl'] = $theOnly['orderUrl'];
			}
		}
		return $outArr;
	}


	public static function getCurrentCheckedGoods($type = ''){
		$checkedGoodsIds = array_keys(CartModel::getCheckedGoods(CartHelper::getCurrentCartId()));
		
		$goodsToProduct = ArrayHelper::mapByKey(Goods::whereIn('id', $checkedGoodsIds)
				->where('is_onshelf', Goods::ON_SHELF)
				->select(['id', 'product_id'])
				->get()
				->toArray(), 'id', 'product_id');
		if(!$goodsToProduct){
			return [];
		}
		//取相应地区的产品id
		$Product = Product::arrWhere(['is_delete' => Product::UN_DELETED,'is_onshelf' => Product::ON_SHELF,'id' => array_values($goodsToProduct)]);
		
		if($type == 'self'){//所有非保税的商品
			$Product = $Product->whereRaw('!(is_oversea = ? and sellcountry_id != 0)', [Product::BONDED]);
		}else{//保税区商品
			$Product = $Product->whereRaw('(is_oversea = ? and sellcountry_id = ?)', [Product::BONDED, $type]);
		}
		
		$matchedProductIds = ArrayHelper::arrayKeyValues($Product->select('id')->get()->toArray(), 'id');
		if(!$matchedProductIds){
			return [];
		}
		$matchedGoodsId = [];
		foreach($goodsToProduct as $goodsId => $productId){
			if(in_array($productId, $matchedProductIds)){
				$matchedGoodsId[] = $goodsId;
			}
		}
		return $matchedGoodsId;
		
		
	}
	

	public static function getCurrentCheckedGoodsInfo($type = ''){
		
		if($type == 'flushbuy'){
			$goodsId = (int)Input::get('goodsId');
			$goodsNum = (int)Input::get('goodsNum');
			if(!$goodsNum || !$goodsId){
				return [];
			}
			$goodsIds = [$goodsId];
			$goodsNums[$goodsId] = $goodsNum;
		}else{
			$goodsIds = self::getCurrentCheckedGoods($type);
			$goodsNums = CartModel::getCart(CartHelper::getCurrentCartId());
		}
		if(!$goodsIds){
			return [];
		}
		$goodsInfo = self::getGoodsInfo($goodsIds, $goodsNums, false, true);
		return $goodsInfo;
	}
	
	public static function sumCartGoods($goodsInfo, $goodsNums){
		if(!$goodsInfo){
			return [];
		}
		
		$groupedGoodsList = [];
		$totalNum = 0;
		$totalPrice = $totalDiscount = 0.00;
		foreach ($goodsInfo as $item){
			if($item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] != 0){//保税
				//如果有活动
				$groupKey = 'normal';
				if(isset($item['activity']) && $item['activity']){					
					$groupKey = $item['activity']['activity_id'];
					$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['items'][$item['activity']['activity_id']]['activity']= $item['activity'];
				}
				$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['items'][$groupKey]['items'][] = $item;
				$storeInfo = StoreHouseModel::getStoreHouseInfo($item['sellcountry_id'], true);
				$storeName = isset($storeInfo['stockName']) ? $storeInfo['stockName'] : '';
				$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['shopInfo'] = [
					'name' => $storeName,
				];
				//初始化变量
				if(!isset($groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum'])){
					$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum'] = [
						'type' => 'bonded',
						'totalDiscount' => 0.0,
						'totalAmount' => 0.0,
					];
				}
				//排除无货与没有选中的商品
				if($item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']] && $item['is_onshelf'] && $item['is_delete'] != Product::DELETED){
					@($groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['sum'][$groupKey]['totalNum'] += $item['goods_num']);
					@($groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['sum'][$groupKey]['totalPrice'] += $item['goods_amount']);
					@($groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['sum'][$groupKey]['discountPrice'] += $item['discount_amount']);
					$totalPrice += $item['goods_amount'];
					$totalNum += $item['goods_num'];
					if(isset($item['discount_amount'])){
						$totalDiscount += $item['discount_amount'];
					}
					if(!isset($groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum']['checkedAll'])){
						$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum']['checkedAll'] = true;
					}
					if(!isset($checkedAll)){
						$checkedAll = true;
					}
					$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum']['totalDiscount'] += $item['discount_amount'];
					$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum']['totalAmount'] += $item['goods_amount'];
				}
				//有一个正常未被选中则设置为非全选
				if(!$item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']]){
					$groupedGoodsList['5_'.$item['shop_id']][$item['sellcountry_id']]['globalSum']['checkedAll'] = false;
					$checkedAll = false;
				}
				
			}else if($item['shop_id'] > 0){//联营商家
				//如果有活动
				$groupKey = 'normal';
				if(isset($item['activity']) && $item['activity']){					
					$groupKey = $item['activity']['activity_id'];
					$groupedGoodsList['9_'.$item['shop_id']][0]['items'][$groupKey]['activity']= $item['activity'];
				}
				$groupedGoodsList['9_'.$item['shop_id']][0]['items'][$groupKey]['items'][] = $item;
				//初始化变量
				if(!isset($groupedGoodsList['9_'.$item['shop_id']][0]['globalSum'])){
					$groupedGoodsList['9_'.$item['shop_id']][0]['globalSum'] = [
						'type' => 'merchant',
						'totalDiscount' => 0.0,
						'totalAmount' => 0.0,
					];
				}
				
				if(!isset($groupedGoodsList['9_'.$item['shop_id']][0]['shopInfo'])){
					$shopInfo = ProductModel::getShopInfo(['ac_id' => $item['shop_id']], 'ac_name', 0, 1);
					$groupedGoodsList['9_'.$item['shop_id']][0]['shopInfo'] = [
						'name' => isset($shopInfo['ac_name']) ? $shopInfo['ac_name'] : '宝贝格子联营商家',
					];
				}
				//排除无货与没有选中的商品
				if($item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']] && $item['is_onshelf'] && $item['is_delete'] != Product::DELETED){
					@($groupedGoodsList['9_'.$item['shop_id']][0]['sum'][$groupKey]['totalNum'] += $item['goods_num']);
					@($groupedGoodsList['9_'.$item['shop_id']][0]['sum'][$groupKey]['totalPrice'] += $item['goods_amount']);
					@($groupedGoodsList['9_'.$item['shop_id']][0]['sum'][$groupKey]['discountPrice'] += $item['discount_amount']);
					$totalPrice += $item['goods_amount'];
					$totalNum += $item['goods_num'];
					if(isset($item['discount_amount'])){
						$totalDiscount += $item['discount_amount'];
					}
					if(!isset($groupedGoodsList['9_'.$item['shop_id']][0]['globalSum']['checkedAll'])){
						$groupedGoodsList['9_'.$item['shop_id']][0]['globalSum']['checkedAll'] = true;
					}
					if(!isset($checkedAll)){
						$checkedAll = true;
					}
					
					$groupedGoodsList['9_'.$item['shop_id']][0]['globalSum']['totalDiscount'] += $item['discount_amount'];
					$groupedGoodsList['9_'.$item['shop_id']][0]['globalSum']['totalAmount'] += $item['goods_amount'];
				}
				//有一个正常未被选中则设置为非全选
				if(!$item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']]){
					$groupedGoodsList['9_'.$item['shop_id']][0]['globalSum']['checkedAll'] = false;
					$checkedAll = false;
				}
			}else{
				//初始化变量
				if(!isset($groupedGoodsList['0_'.$item['shop_id']][0]['globalSum'])){
					$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum'] = [
						'type' => 'self',
						'totalDiscount' => 0.0,
						'totalAmount' => 0.0,
					];
				}
				$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['type'] = 'self';
				//如果有活动
				$groupKey = 'normal';
				if(isset($item['activity']) && $item['activity']){					
					$groupKey = $item['activity']['activity_id'];
					$groupedGoodsList['0_'.$item['shop_id']][0]['items'][$groupKey]['activity']= $item['activity'];
				}
				$groupedGoodsList['0_'.$item['shop_id']][0]['items'][$groupKey]['items'][] = $item;
				
				$groupedGoodsList['0_'.$item['shop_id']][0]['shopInfo'] = [
					'name' => '宝贝格子',
				];
				//排除无货与没有选中的商品
				if($item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']] &&  $item['is_onshelf']  && $item['is_delete'] != Product::DELETED){
					@($groupedGoodsList['0_'.$item['shop_id']][0]['sum'][$groupKey]['totalNum'] += $item['goods_num']);
					@($groupedGoodsList['0_'.$item['shop_id']][0]['sum'][$groupKey]['totalPrice'] += $item['goods_amount']);
					@($groupedGoodsList['0_'.$item['shop_id']][0]['sum'][$groupKey]['discountPrice'] += $item['discount_amount']);
					$totalPrice += $item['goods_amount'];
					$totalNum += $item['goods_num'];
					if(isset($item['discount_amount'])){
						$totalDiscount += $item['discount_amount'];
					}
					if(!isset($groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['checkedAll'])){
						$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['checkedAll'] = true;
					}
					if(!isset($checkedAll)){
						$checkedAll = true;
					}
					$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['totalDiscount'] += $item['discount_amount'];
					$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['totalAmount'] += $item['goods_amount'];
				}
				//有一个正常未被选中则设置为非全选
				if(!$item['checked'] && $item['stock_num'] >= $goodsNums[$item['goods_id']]){
					$groupedGoodsList['0_'.$item['shop_id']][0]['globalSum']['checkedAll'] = false;
					$checkedAll = false;
				}
			}
		}
		//排序
		ksort($groupedGoodsList);
		foreach ($groupedGoodsList as &$shopGroup){
			krsort($shopGroup);
			foreach($shopGroup as &$countryGroup){
				krsort($countryGroup['items']);
			}
		}
		//超限验证
		$crossChcekRet = OrderCheckHelper::ifCrossBondedLimit($goodsInfo, false);
		$tips = '';
		if(!$crossChcekRet['flag']){
			$tips = '抱歉，'.  implode(',', $crossChcekRet['crossList']).'商品金额超过海关规定购买多件总价不能超过￥2000的限制，请分次购买！';
		}
		//组合信息
		return [
			'groupedGoods' => $groupedGoodsList,
			'totalNum' => $totalNum,
			'totalPrice' => $totalPrice,
			'totalDiscount' => $totalDiscount,
			'checkedAll' => isset($checkedAll) ? $checkedAll : false,
			'tips' => $tips,
				];
	}

	/**
	 * 1.计算活动信息等相关信息
	 * 2.计算单个商品的价格信息
	 * @param type $goodsIds
	 * @return type
	 */
	public static function getGoodsInfo($goodsIds, $goodsNums, $onlyChecked = false, $filter = false){
		if(!$goodsIds){
			return [];
		}
		$goodsInfo = GoodsModel::getGoodsListById($goodsIds);
		if(!$goodsInfo){
			return [];
		}
		
		$productIds = ArrayHelper::arrayKeyValues($goodsInfo, 'product_id');
		$productEffectPriceActivity = ActivityModel::getActivityList($productIds, config('app.platform'));//活动信息是以product为单位的

		$productPinkageActivity = ActivityModel::getActivityList($productIds, config('app.platform'), ActivityModel::TYPE_PINKAGE);//活动信息是以product为单位的
		if($onlyChecked){
			$checkedGoods = CartModel::getCheckedGoods(CartHelper::getCurrentCartId());
		}
		$stockMap = StockHelper::getStockByGoodsInfo($goodsInfo);
		
		//佣金比较
		$prductCommission = ArrayHelper::mapByKey(ProductCommission::whereIn('product_id', $productIds)->where('status', ProductCommission::ENABLE)
				->where('is_delete', ProductCommission::NORMAL)
				->get()
				->toArray(), 'product_id');
		//把可以一起下单的商品归类在一起
		$groupedGoods = [];
		//颜色尺寸
		$sizeMap = CategorySizeModel::getCategorySizeById(array_filter(array_unique(ArrayHelper::arrayKeyValues($goodsInfo, 'size_id'))));
		$colorMap = CategorySizeModel::getCategorySizeById(array_filter(array_unique(ArrayHelper::arrayKeyValues($goodsInfo, 'color_id'))));
		foreach($goodsInfo as $item){
			//过滤数量不对的商品
			if(!isset($goodsNums[$item['goods_id']]) || $goodsNums[$item['goods_id']] < 0){
				continue;
			}
			
			//过滤下架及删除的商品
			if($filter && ($item['is_delete'] || !$item['is_onshelf'])){
				continue;
			}

			//过滤无库存商品
			$item['stock_num'] = isset($stockMap[$item['goods_id']]) ? $stockMap[$item['goods_id']] : 0;
			if($filter &&   $goodsNums[$item['goods_id']] > $item['stock_num']){
				continue;
			}
			
			$item['checked'] = true;
			if($onlyChecked){
				$item['checked'] = isset($checkedGoods[$item['goods_id']]) ? true : false;
			}
			
			$item['fx_commission_rate'] = isset($prductCommission[$item['product_id']]['rate']) ? $prductCommission[$item['product_id']]['rate'] : 0;
			//最低佣金为0.02
			if($item['fx_commission_rate'] < 0.02){
				$item['fx_commission_rate'] = 0.02;
			}
			$item['goods_num'] = $goodsNums[$item['goods_id']];
			$item['goods_amount'] = $item['goods_num'] * $item['store_price'];//单个商品支付金额的纯商品金额
			$item['sell_price'] = $item['store_price'];//不使用优惠券的价格，包含活动 
			$item['sell_goods_amount'] = $item['goods_num'] * $item['sell_price'];//单个商品支付金额的纯商品金额
			$item['pay_price'] = $item['store_price'];//单个商品支付单价
			$item['discount_price'] = 0.00;//单件优惠金额
			$item['discount_amount'] = 0.00;//本件商品总记优惠金额
			//原始税
			$item['tax_amount'] = round($item['goods_amount'] * $item['bonded_tax'], 2);//税收金额
			//重量转换
			$item['weight_g'] = $item['gross_weight'];//克
			
			$item['weight_lb'] = $item['weight_g'];
			$item['total_weight_lb'] = $item['weight_g'] * $item['goods_num'];//总磅
			$item['total_weight_g'] = $item['weight_g'] * $item['goods_num'];
			$item['coupon_amount']	 = 0;//代金券金额
			//颜色&尺寸
			$item['color_str']	 = isset($colorMap[$item['color_id']]['size_name']) ? $colorMap[$item['color_id']]['size_name'] : '';
			$item['size_str']	 = isset($sizeMap[$item['size_id']]['size_name']) ? $sizeMap[$item['size_id']]['size_name'] : '';
			//活动
			if(isset($productEffectPriceActivity[$item['product_id']])){
				$item['activity'] = $productEffectPriceActivity[$item['product_id']];
			}
			
			$item['pinkage'] = false;
			if(isset($productPinkageActivity[$item['product_id']])){
				$item['pinkage'] = true;
				$item['pinkage_activity'] = $productPinkageActivity[$item['product_id']];
			}
			//保税先拆出来
			if(isset($item['is_oversea']) && $item['is_oversea'] == Product::BONDED && $item['sellcountry_id'] && $item['sellcountry_id'] != 0){
				if(isset($item['activity'])){
					$groupedGoods[$item['sellcountry_id']]['activity'][$item['activity']['activity_id']]['goods'][$item['goods_id']] = $item;
					$groupedGoods[$item['sellcountry_id']]['activity'][$item['activity']['activity_id']]['activityInfo'] = $item['activity'];
				}else{
					$groupedGoods[$item['sellcountry_id']]['normal'][$item['goods_id']] = $item;
				}
			}else{
				if(isset($item['activity'])){
					$groupedGoods[0]['activity'][$item['activity']['activity_id']]['goods'][$item['goods_id']] = $item;
					$groupedGoods[0]['activity'][$item['activity']['activity_id']]['activityInfo'] = $item['activity'];
				}else{
					$groupedGoods[0]['normal'][$item['goods_id']] = $item;
				}
			}
		}
		
		$lastGoodsInfo = [];
		foreach($groupedGoods as $groups){
			if(!$groups){
				continue;
			}
			foreach($groups as $type => $subGroup){
				
				if($type != 'activity'){
					$lastGoodsInfo += $subGroup;
					continue;
				}
				foreach($subGroup as $activityGroup){
					$currentActivityInfo = ActivityCalculateModel::calculateActivity($activityGroup['goods'], $activityGroup['activityInfo']);
					if(!$currentActivityInfo){
						continue;
					}
					
					$activityGroup['activityInfo'] = isset($currentActivityInfo['activity']) ? $currentActivityInfo['activity'] : $activityGroup['activityInfo'];
					$lastGoodsInfo += $currentActivityInfo['goods'];
				}
			}
		}
		ksort($lastGoodsInfo);
		return self::calculatedGoodsTax($lastGoodsInfo);
	}

	public static function getCurrentCartGoodsInfo(){
		$cartId = CartHelper::getCurrentCartId();
		$cart = CartModel::getCart($cartId);		
		$goodsIds = array_keys($cart);
		if(!$goodsIds){
			return [];
}
		$goodsNums = CartModel::getCart($cartId);
		$goodsInfo = self::getGoodsInfo($goodsIds, $goodsNums, true);
		if(!$goodsInfo){
			return [];
		}
		return self::sumCartGoods($goodsInfo, $goodsNums);
	}
	
	
	/**
	 * 
	 * @param type $goodsInfo
	 */
	public static function calculatedGoodsTax($goodsInfo){
		if(!$goodsInfo){
			return $goodsInfo;
		}
		//按商家及国家分组计算关税
		$grouped = [];
		foreach($goodsInfo as $item){
			$grouped[$item['shop_id']][$item['sellcountry_id']]['items'][] = $item;
			@$grouped[$item['shop_id']][$item['sellcountry_id']]['totalTax'] += $item['tax_amount'];
			@$grouped[$item['shop_id']][$item['sellcountry_id']]['is_oversea'] = $item['is_oversea'];
		}
		
		if(!$grouped){
			return $goodsInfo;
		}
		$mapedGoods = ArrayHelper::mapByKey($goodsInfo, 'goods_id');
		foreach($grouped as $group){
			foreach($group as $sellcountryId => $subGroup){
				$ret = TaxHelper::ifHasTax($subGroup['totalTax'], $sellcountryId, $subGroup['is_oversea']);
				if($ret){//有税直接返回
					continue;
				}
				
				foreach($subGroup['items'] as $gitem){
					$mapedGoods[$gitem['goods_id']]['origin_tax_amount'] = $mapedGoods[$gitem['goods_id']]['tax_amount'];//记录原始的税
					$mapedGoods[$gitem['goods_id']]['tax_amount'] = 0.00;
				}
			}
		}
	
		return $mapedGoods;
	}
	
	/**
	 * 
	 * @param type $goodsId
	 * @param type $goodsNum
	 */
	public static function isAvailable($goodsId, $goodsNum){
		if($goodsNum < 0){
			return ['flag' => false, 'msg' => '无效的商品数量'];
		}
		
		$goodsInfo = GoodsModel::getGoodsListById($goodsId);
		
		if(!$goodsInfo){
			return ['flag' => false, 'msg' => '商品不存在'];
		}
		
		//已删除
		if($goodsInfo['is_delete']){
			return ['flag' => false, 'msg' => '商品无效'];
		}
		
		//已下架
		if(!$goodsInfo['is_onshelf']){
			return ['flag' => false, 'msg' => '商品已下架'];
		}
		
		//库存校验
		$stockMap = StockHelper::getStockByGoodsInfo([$goodsInfo]);
		if(!isset($stockMap[$goodsId]) || $stockMap[$goodsId] < $goodsNum){
			return ['flag' => false, 'msg' => '库存不足'];
		}
		
		return ['flag' => true, 'msg' => ''];
	}
}
