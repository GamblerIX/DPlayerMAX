<?php
/**
 * WBI 签名器
 * 实现 B 站的 WBI 签名算法
 */
class DPlayerMAX_WbiSigner
{
    /**
     * 混淆密钥编码表
     */
    private static $mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];
    
    /**
     * 对请求参数进行 WBI 签名
     * @param array $params 请求参数
     * @return array 添加了 w_rid 和 wts 的参数
     */
    public static function signParams($params)
    {
        $keys = self::getWbiKeys();
        if (!$keys) {
            return $params;
        }
        
        return self::encWbi($params, $keys['img_key'], $keys['sub_key']);
    }
    
    /**
     * 获取 WBI 密钥（带缓存）
     * @return array|null ['img_key' => string, 'sub_key' => string]
     */
    private static function getWbiKeys()
    {
        // 尝试从缓存获取
        $cached = DPlayerMAX_CacheManager::get('bilibili_wbi_keys');
        if ($cached) {
            return $cached;
        }
        
        // 从 B 站 API 获取
        try {
            $client = Typecho_Http_Client::get();
            $client->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $client->setHeader('Referer', 'https://www.bilibili.com');
            
            $response = $client->send('https://api.bilibili.com/x/web-interface/nav');
            $data = json_decode($response, true);
            
            if (!$data || $data['code'] != 0) {
                return null;
            }
            
            $wbiImg = $data['data']['wbi_img'];
            $imgUrl = $wbiImg['img_url'];
            $subUrl = $wbiImg['sub_url'];
            
            // 提取文件名作为密钥
            preg_match('/\/([^\/]+)\.png$/', $imgUrl, $imgMatches);
            preg_match('/\/([^\/]+)\.png$/', $subUrl, $subMatches);
            
            if (!$imgMatches || !$subMatches) {
                return null;
            }
            
            $keys = [
                'img_key' => $imgMatches[1],
                'sub_key' => $subMatches[1]
            ];
            
            // 缓存 1 小时
            DPlayerMAX_CacheManager::set('bilibili_wbi_keys', $keys, 3600);
            
            return $keys;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 生成混合密钥
     * @param string $orig 原始密钥（img_key + sub_key）
     * @return string 32位混合密钥
     */
    private static function getMixinKey($orig)
    {
        $mixinKey = '';
        foreach (self::$mixinKeyEncTab as $index) {
            if (isset($orig[$index])) {
                $mixinKey .= $orig[$index];
            }
        }
        return substr($mixinKey, 0, 32);
    }
    
    /**
     * WBI 编码
     * @param array $params 请求参数
     * @param string $imgKey img_key
     * @param string $subKey sub_key
     * @return array 签名后的参数
     */
    private static function encWbi($params, $imgKey, $subKey)
    {
        // 添加时间戳
        $params['wts'] = time();
        
        // 过滤特殊字符
        $filteredParams = [];
        foreach ($params as $key => $value) {
            $strValue = (string)$value;
            // 过滤 !'()*
            $strValue = str_replace(['!', "'", '(', ')', '*'], '', $strValue);
            $filteredParams[$key] = $strValue;
        }
        
        // 按键名排序
        ksort($filteredParams);
        
        // 生成查询字符串
        $query = http_build_query($filteredParams);
        
        // 生成混合密钥
        $mixinKey = self::getMixinKey($imgKey . $subKey);
        
        // 计算签名
        $wRid = md5($query . $mixinKey);
        
        // 添加签名参数
        $params['w_rid'] = $wRid;
        
        return $params;
    }
}
