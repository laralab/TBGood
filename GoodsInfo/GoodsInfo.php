<?php
/**
 * Created by PhpStorm.
 * User: lee
 * Date: 15/9/4
 * Time: 下午11:02
 */

namespace Laralab\GoodsInfo;

interface GoodsInfo
{
    // 获取商品原始链接
    public function getOriginUrl();

    // 获取商品简化链接
    public function getSimpleUrl();

    // 获取商品 ID
    public function getGoodId();

    // 获取商品 Item ID
    public function getGoodItemId();

    // 获取商品卖家 ID
    public function getGoodSellerId();

    // 获取卖家名称
    public function getGoodSellerName();

}
