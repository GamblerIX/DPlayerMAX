<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * B站 WBI 签名器
 * 实现 B站 WBI 签名机制
 */
class DPlayerMAX_Bilibili_WbiSigner
{
    // WBI 密钥混淆映射表
    private static $mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];

    // 缓存的 WBI 密钥
    private static $cachedKeys = null;
    private static $cacheTime = 0;
    private static $cacheFile = null;

    /**
     * 获取缓存文件路径
     */
    private static function getCacheFile()
    {
        if (self::$cacheFile === null) {
            $cacheDir = __DIR__ . '/cache';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            self::$cacheFile = $cacheDir . '/wbi_keys.json';
        }
        return self::$cacheFile;
    }

    /**
     * 从缓存加载 WBI 密钥
     */
    private static function loadFromCache()
    {
        $cacheFile = self::getCacheFile();
        if (file_exists($cacheFile)) {
            $data = @json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['keys']) && isset($data['time'])) {
                // 缓存有效期 1 小时
                if (time() - $data['time'] < 3600) {
                    self::$cachedKeys = $data['keys'];
                    self::$cacheTime = $data['time'];
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 保存 WBI 密钥到缓存
     */
    private static function saveToCache($keys)
    {
        $cacheFile = self::getCacheFile();
        $data = [
            'keys' => $keys,
            'time' => time()
        ];
        @file_put_contents($cacheFile, json_encode($data));
        self::$cachedKeys = $keys;
        self::$cacheTime = time();
    }

    /**
     * 获取 WBI 密钥 (img_key 和 sub_key)
     */
    public static function getWbiKeys()
    {
        // 检查内存缓存
        if (self::$cachedKeys && (time() - self::$cacheTime < 3600)) {
            return self::$cachedKeys;
        }

        // 检查文件缓存
        if (self::loadFromCache()) {
            return self::$cachedKeys;
        }

        // 从 B站 API 获取
        $keys = self::fetchWbiKeys();
        if ($keys) {
            self::saveToCache($keys);
            return $keys;
        }

        return null;
    }

    /**
     * 从 B站 API 获取 WBI 密钥
     */
    private static function fetchWbiKeys()
    {
        $url = 'https://api.bilibili.com/x/web-interface/nav';

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://www.bilibili.com/',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['data']['wbi_img'])) {
            return null;
        }

        $imgUrl = $data['data']['wbi_img']['img_url'] ?? '';
        $subUrl = $data['data']['wbi_img']['sub_url'] ?? '';

        if (empty($imgUrl) || empty($subUrl)) {
            return null;
        }

        // 提取密钥 (文件名不含扩展名)
        $imgKey = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_FILENAME);
        $subKey = pathinfo(parse_url($subUrl, PHP_URL_PATH), PATHINFO_FILENAME);

        return [
            'img_key' => $imgKey,
            'sub_key' => $subKey
        ];
    }

    /**
     * 生成混淆密钥 (mixin_key)
     */
    public static function getMixinKey($imgKey, $subKey)
    {
        $rawWbiKey = $imgKey . $subKey;
        $mixinKey = '';

        foreach (self::$mixinKeyEncTab as $index) {
            if (isset($rawWbiKey[$index])) {
                $mixinKey .= $rawWbiKey[$index];
            }
        }

        return substr($mixinKey, 0, 32);
    }

    /**
     * 对请求参数进行 WBI 签名
     *
     * @param array $params 请求参数
     * @return array 签名后的参数
     */
    public static function sign(array $params)
    {
        $keys = self::getWbiKeys();
        if (!$keys) {
            return $params;
        }

        $mixinKey = self::getMixinKey($keys['img_key'], $keys['sub_key']);

        // 添加时间戳
        $params['wts'] = time();

        // 按 key 排序
        ksort($params);

        // 过滤特殊字符并编码
        $filtered = [];
        foreach ($params as $key => $value) {
            // 过滤 !'()* 字符
            $value = preg_replace("/[!'()*]/", '', (string)$value);
            $filtered[$key] = $value;
        }

        // 构建查询字符串
        $query = http_build_query($filtered);

        // 计算签名
        $wrid = md5($query . $mixinKey);

        $filtered['w_rid'] = $wrid;

        return $filtered;
    }

    /**
     * 构建带签名的 URL
     *
     * @param string $baseUrl 基础 URL
     * @param array $params 请求参数
     * @return string 带签名的完整 URL
     */
    public static function buildSignedUrl($baseUrl, array $params)
    {
        $signedParams = self::sign($params);
        return $baseUrl . '?' . http_build_query($signedParams);
    }
}
