<?php

namespace Laralab\GoodsInfo;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use App\Http\Util\Http;

/**
 * Class TaoBaoGoodsInfo
 * @package App\GoodsInfo
 *
 * 普通淘宝店测试: https://item.taobao.com/item.htm?id=19946228971
 * 天猫店测试: https://detail.tmall.com/item.htm?id=538008363955
 */
class TaoBaoGoodsInfo implements GoodsInfo
{
    private $baseUrl = 'https://item.taobao.com/item.htm';

    private $originUrl;

    private $simpleUrl;

    private $goodId;

    private $goodItemId;

    private $goodSellerId;

    private $goodSellerName;

    private $goodSellerTrueName;

    private $goodLongTitle;

    private $goodShortTitle;

    private $goodPage;

    private $shopId;

    private $shopName;

    private $shopUrl;

    /**
     * TaoBaoGoodsInfo constructor.
     *
     * @param $url
     */
    public function __construct($url)
    {
        $this->originUrl = $url;
        $this->setGoodIdFromUrl($this->originUrl);
        $this->simpleUrl = $this->baseUrl . "?id=" . $this->goodId;
        $this->setGoodPage();

        $this->setGoodItemId();
        $this->setGoodSellerId();
        $this->setGoodSellerName();
        $this->setGoodSellerTrueName();

        $this->setShopId();
        $this->setShopName();
        $this->setShopUrl();

        $this->setGoodLongTitle();
        $this->setGoodShortTitle();

        Log::info($this->setLogInfo());
    }

    private function setGoodIdFromUrl($url)
    {
        if (preg_match("/id=\d+/i", $url, $matches)) {
            $this->goodId = explode("=", $matches[0])[1];
        } else {
            Log::error($this->setLogError());
            throw new \Exception('URL 中没有商品 ID');
        }
    }

    private function setGoodPage()
    {
        $http = new Http(true);

        $this->goodPage = $http->get($this->baseUrl, [ "id" => $this->goodId ]);

        if ( ! $this->goodPage) {
            Log::error($this->setLogError());
            throw new \Exception('获取商品信息页面失败');
        }
    }

    private function setGoodItemId()
    {

        if (preg_match("/itemId=\d+/i", $this->goodPage['data'], $matches)) {
            $this->goodItemId = explode("=", $matches[0])[1];
        }

        if ( ! $this->goodItemId) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 商品 Item ID 信息');
        }

    }

    private function setShopId()
    {
        if (preg_match("/shopId=\d+/i", $this->goodPage['data'], $matches)) {
            $this->shopId = explode("=", $matches[0])[1];
        }

        if ( ! $this->shopId) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 ShopID 信息');
        }
    }

    private function setGoodSellerTrueName()
    {
        // 淘宝店
        if (preg_match("/sellerNickGBK\s+:\s+'.*,/i", $this->goodPage['data'], $matches)) {
            $this->goodSellerTrueName = urldecode(explode("'", $matches[0])[1]);
        }

        // 天猫店
        if ( ! $this->goodSellerTrueName) {
            if (preg_match("/sellerNickName:.*,/i", $this->goodPage['data'], $matches)) {
                $this->goodSellerTrueName = urldecode(explode('"', $matches[0])[1]);
            }
        }

        if ( ! $this->goodSellerTrueName) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 卖家旺旺 信息');
        }
    }

    private function setShopName()
    {
        $this->shopName = $this->goodSellerName;
    }

    private function setShopUrl()
    {
        //xpath     '//*[@id="J_ShopInfo"]/div/div[3]/a[1]';
        //selector  '#J_ShopInfo > div > div.tb-shop-info-ft > a:nth-child(1)';
        $crawler = new Crawler($this->goodPage['data']);

        // 淘宝页面
        $node = $crawler->filter('#J_ShopInfo > div > div.tb-shop-info-ft > a:nth-child(1)');

        if ( ! count($node)) {
            // 企业店
            if (preg_match("/\surl\s+:\s+'\/\/.*taobao.com/i", $this->goodPage['data'], $matches)) {
                $this->shopUrl = 'https:' .  explode("'", $matches[0])[1];
            }
        } else {
            $shopUrl       = preg_split("/[\r\n\t]+/", $node->attr('href'));
            $this->shopUrl = 'http:' . $shopUrl[0];
        }

        // 天猫店
        if ( ! $this->shopUrl) {
            $node = $crawler->filter('#shopExtra > div.slogo > a');

            if ( count($node)) {
                $shopUrl       = preg_split("/[\r\n\t]+/", $node->attr('href'));
                $this->shopUrl = 'http:' . $shopUrl[0];
            }
        }

        if ( ! $this->shopUrl) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 ShopUrl 信息');
        }
    }

    private function setGoodSellerId()
    {
        if (preg_match("/sellerId=\d+/i", $this->goodPage['data'], $matches)) {
            $this->goodSellerId = explode("=", $matches[0])[1];
        }

        if ( ! $this->goodSellerId) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 卖家ID 信息');
        }
    }

    private function setGoodSellerName()
    {
        $crawler = new Crawler($this->goodPage['data']);

        // 淘宝页面
        $node = $crawler->filter('div#J_ShopInfo > div.tb-shop-info-wrap > div.tb-shop-info-hd > div.tb-shop-name > dl > dd > strong > a');

        if ( ! count($node)) {
            // 天猫页面
            $node = $crawler->filter('div#shopExtra > div.slogo > a.slogo-shopname > strong');

            if (count($node)) {
                $sellerName           = $node->text();
                $this->goodSellerName = $sellerName;
            }
        } else {
            $sellerName           = preg_split("/[\r\n\t]+/", $node->text());
            $this->goodSellerName = trim($sellerName[1]);
        }

        // 企业店
        if ( ! $this->goodSellerName) {
            if (preg_match("/(?<key>shopName\s+):\s+'(?<name>.+)',/i", $this->goodPage['data'], $matches)) {
                if (array_key_exists('name', $matches)) {
                    $this->goodSellerName = $this->unicode_decode($matches['name']);
                }

            }
        }

        if ( ! $this->goodSellerName) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 卖家名称 信息');
        }

    }

    private function setGoodLongTitle()
    {
        $crawler = new Crawler($this->goodPage['data']);

        // 淘宝页面
        $node = $crawler->filter('div#J_Title > h3');

        if ( ! count($node)) {
            //天猫页面
            $node = $crawler->filter('div.tb-detail-hd > h1');

            if (count($node)) {
                $goodLongTitle       = trim($node->text());
                $this->goodLongTitle = $goodLongTitle;
            }
        } else {
            $goodLongTitle       = $node->attr('data-title');
            $this->goodLongTitle = $goodLongTitle;
        }

        // 企业店
        if ( ! $this->goodLongTitle) {
            $title = $crawler->filter('div#J_Title > h3.tb-main-title')->extract([ 'data-title' ])[0];
            if ($title != '') {
                $this->goodLongTitle = $title;
            }
        }

        if ( ! $this->goodLongTitle) {
            Log::error($this->setLogError());
            throw new \Exception('没有找到 宝贝标题 信息');
        }

    }

    private function setGoodShortTitle($page = 1)
    {
        $search_url = 'https://s.m.taobao.com/search.json';

        $http = new Http(true);

        for($index = 0; $index <= 2; $index++ )
        {
            $search_results = $http->get($search_url, [ "q" => $this->goodLongTitle, 'n' => 100, 'page' => $page + $index ]);

            if ( ! $search_results) {
                Log::error($this->setLogError());
                throw new \Exception('获取商品短标题数据失败');
            }

            $data = json_decode($search_results['data']);

            foreach ($data->itemsArray as $item) {
                if ($item->item_id == $this->goodId) {
                    $shortTitle = html_entity_decode($item->title);
                    return $this->goodShortTitle = $shortTitle;
                }
            }
        }
    }

    public function getKeyWordOrder($keyWords, $query)
    {
        $search_url = 'https://s.m.taobao.com/search.json';

        $query_array = [
            "q"      => $keyWords,
            'n'      => 20,
            'sst'    => 1,
            'buying' => 'buyitnow',
            'm'      => 'api4h5',
            'abtest' => 14,
            'wlsort' => 14
        ];

        // 排序类型
        $searchOrder = $this->preSearchOrder($query['sortOrder']);

        if ($searchOrder != '') {
            $query_array = array_add($query_array, 'sort', $searchOrder);
        }

        // 限价区间
        if ($query['end_price'] != 0) {
            $query_array = array_add($query_array, 'start_price', $query['start_price']);
            $query_array = array_add($query_array, 'end_price', $query['end_price']);
        }

        // 发货地
        if ($query['loc'] != '所有地区') {
            $query_array = array_add($query_array, 'loc', $query['loc']);
        }

        // 卡位
        $searchFilter = $this->preSearchFilter($query['filter']);

        if ($searchFilter != '') {
            $query_array = array_add($query_array, 'filter', $searchFilter);
        }

        $http = new Http(true);

        for ($i = 1; $i < 6; $i++) {
            $query_array_new = array_add($query_array, 'page', $i);
            $search_results = $http->get($search_url, $query_array_new);

            if ( ! $search_results) {
                Log::error($this->setLogError());
                throw new \Exception('获取商品排名数据失败');
            }

            $data = json_decode($search_results['data']);

            // 去除直通车商品
            $ztc_index = 0;

            foreach ($data->listItem as $key => $item) {

                if( str_contains($item->url, "mclick.simba")){
                    $ztc_index = $ztc_index + 1;
                }elseif($item->item_id == $this->goodId){
                    return [ 'page' => $i, 'order' => $key + 1 - $ztc_index];
                }
            }
        }

        return [ 'page' => 0, 'order' => 0 ];

    }

    public function getZTCKeyWordOrder($keyWords, $query)
    {
        // https://s.m.taobao.com/search.json 这个接口返回的数据里，"isP4p": "true" 的就是直通车
        $search_url = 'https://s.m.taobao.com/search.json';

        $query_array = [
            "q"      => $keyWords,
            'n'      => 20,
            'sst'    => 1,
            'buying' => 'buyitnow',
            'm'      => 'api4h5',
            'abtest' => 14,
            'wlsort' => 14
        ];

        // 排序类型
        $searchOrder = $this->preSearchOrder($query['sortOrder']);

        if ($searchOrder != '') {
            $query_array = array_add($query_array, 'sort', $searchOrder);
        }

        // 限价区间
        if ($query['end_price'] != 0) {
            $query_array = array_add($query_array, 'start_price', $query['start_price']);
            $query_array = array_add($query_array, 'end_price', $query['end_price']);
        }

        // 发货地
        if ($query['loc'] != '所有地区') {
            $query_array = array_add($query_array, 'loc', $query['loc']);
        }

        // 卡位
        $searchFilter = $this->preSearchFilter($query['filter']);

        if ($searchFilter != '') {
            $query_array = array_add($query_array, 'filter', $searchFilter);
        }

        $http = new Http(true);

        for ($i = 1; $i < 6; $i++) {
            $query_array_new = array_add($query_array, 'page', $i);
            $search_results = $http->get($search_url, $query_array_new);

            if ( ! $search_results) {
                Log::error($this->setLogError());
                throw new \Exception('获取商品排名数据失败');
            }

            $data = json_decode($search_results['data']);

            // 计算直通车商品
            $ztc_index = 0;

            foreach ($data->listItem as $key => $item) {
                if($item->isP4p == "true"){
                    $ztc_index = $ztc_index + 1;
                    if($item->item_id == $this->goodId){
                        return [ 'page' => $i, 'order' => $ztc_index];
                    }
                }
            }
        }

        return [ 'page' => 0, 'order' => 0 ];

    }

    private function preSearchOrder($order_string)
    {
        switch ($order_string) {
            case '销量优先':
                return '_sale';
            case '价格从低到高':
                return 'bid';
            case '价格从高到低':
                return '_bid';
            case '信用排序':
                return '_ratesum';
            default:
                return '';
        }
    }

    private function preSearchFilter($filters)
    {
        $filter = '';

        foreach ($filters as $filter_key => $filter_value) {
            if ($filter_value == 'true') {
                $filter = $filter . $filter_key . ';';
            }
        }

        return $filter;
    }

    private function setLogError()
    {
        return [
            'originUrl'   => $this->originUrl,
            'minifiedUrl' => $this->simpleUrl,
            //'goodPage'    => $this->goodPage,
        ];
    }

    private function setLogInfo()
    {
        return [
            'info'           => '获取宝贝信息',
            'originUrl'      => $this->originUrl,
            'minifiedUrl'    => $this->simpleUrl,
            'goodItemId'     => $this->goodItemId,
            'goodSellerId'   => $this->goodSellerId,
            'goodSellerName' => $this->goodSellerName,
            'curl_info'      => $this->goodPage['curl_info'],
        ];
    }

    // From: http://www.cnblogs.com/xiangxiaodong/archive/2012/10/25/2739307.html
    private function unicode_decode($name)
    {
        // 转换编码，将Unicode编码转换成可以浏览的utf-8编码
        $pattern = '/([\w|\s]+)|(\\\u([\w]{4}))/i';
        preg_match_all($pattern, $name, $matches);
        if ( ! empty( $matches )) {
            $name = '';
            for ($j = 0; $j < count($matches[0]); $j++) {
                $str = $matches[0][$j];
                if (strpos($str, '\\u') === 0) {
                    $code  = base_convert(substr($str, 2, 2), 16, 10);
                    $code2 = base_convert(substr($str, 4), 16, 10);
                    $c     = chr($code) . chr($code2);
                    $c     = iconv('UCS-2BE', 'UTF-8', $c);
                    $name .= $c;
                } else {
                    $name .= $str;
                }
            }
        }

        return $name;
    }

    public function getOriginUrl()
    {
        return $this->originUrl;
    }

    public function getSimpleUrl()
    {
        return $this->simpleUrl;
    }

    public function getGoodId()
    {
        return $this->goodId;
    }

    public function getGoodItemId()
    {
        return $this->goodItemId;
    }

    public function getGoodSellerId()
    {
        return $this->goodSellerId;
    }

    public function getGoodSellerName()
    {
        return $this->goodSellerName;
    }

    public function getGoodLongTitle()
    {
        return $this->goodLongTitle;
    }

    public function getGoodShortTitle()
    {
        return $this->goodShortTitle;
    }

    public function getShopId()
    {
        return $this->shopId;
    }

    public function getShopName()
    {
        return $this->shopName;
    }

    public function getShopUrl()
    {
        return $this->shopUrl;
    }

    public function getGoodSellerTrueName()
    {
        return $this->goodSellerTrueName;
    }

}
