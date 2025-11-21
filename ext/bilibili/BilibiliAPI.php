<?php
/**
 * B站 API 调用封装
 * 负责处理 HTTP 请求和响应
 */
class DPlayerMAX_BilibiliAPI
{
    /**
     * 获取视频基本信息
     * @param string $videoId BV 号或 AV 号
     * @param string $type 'bv' 或 'av'
     * @return array|null 视频信息
     */
    public static function getVideoInfo($videoId, $type = 'bv')
    {
        // 尝试从缓存获取
        $cacheKey = 'bilibili_video_' . $videoId;
        $cached = DPlayerMAX_CacheManager::get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        // 构建请求 URL
        $params = [];
        if ($type === 'bv') {
            $params['bvid'] = $videoId;
        } else {
            $params['aid'] = $videoId;
        }
        
        $url = 'https://api.bilibili.com/x/web-interface/view?' . http_build_query($params);
        
        // 发送请求
        $data = self::httpGet($url);
        
        if (!$data || $data['code'] != 0) {
            return null;
        }
        
        $videoInfo = $data['data'];
        
        // 缓存 2 小时
        DPlayerMAX_CacheManager::set($cacheKey, $videoInfo, 7200);
        
        return $videoInfo;
    }
    
    /**
     * 获取播放地址
     * @param string $videoId BV 号或 AV 号
     * @param int $cid 分P的 CID
     * @param string $type 'bv' 或 'av'
     * @param int $quality 清晰度 (80=1080P, 64=720P, 32=480P, 16=360P)
     * @return array|null 播放数据
     */
    public static function getPlayUrl($videoId, $cid, $type = 'bv', $quality = 80)
    {
        // 尝试从缓存获取
        $cacheKey = 'bilibili_playurl_' . $videoId . '_' . $cid . '_' . $quality;
        $cached = DPlayerMAX_CacheManager::get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        // 构建请求参数
        $params = [
            'cid' => $cid,
            'qn' => $quality,
            'fnval' => 16,  // DASH 格式
            'fnver' => 0,
            'fourk' => 1,
            'try_look' => 1  // 免登录试看
        ];
        
        if ($type === 'bv') {
            $params['bvid'] = $videoId;
        } else {
            $params['avid'] = $videoId;
        }
        
        // 使用 WBI 签名
        $signedParams = DPlayerMAX_WbiSigner::signParams($params);
        
        $url = 'https://api.bilibili.com/x/player/wbi/playurl?' . http_build_query($signedParams);
        
        // 发送请求
        $data = self::httpGet($url);
        
        if (!$data || $data['code'] != 0) {
            return null;
        }
        
        $playData = $data['data'];
        
        // 缓存 100 分钟（视频流 URL 有效期约 2 小时）
        DPlayerMAX_CacheManager::set($cacheKey, $playData, 6000);
        
        return $playData;
    }
    
    /**
     * 发送 HTTP GET 请求
     * @param string $url 请求 URL
     * @param array $headers 请求头
     * @return array|null 响应数据
     */
    private static function httpGet($url, $headers = [])
    {
        try {
            $client = Typecho_Http_Client::get();
            
            // 设置默认请求头
            $client->setHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $client->setHeader('Referer', 'https://www.bilibili.com');
            $client->setHeader('Accept', 'application/json, text/plain, */*');
            
            // 设置自定义请求头
            foreach ($headers as $key => $value) {
                $client->setHeader($key, $value);
            }
            
            // 设置超时
            $client->setTimeout(10);
            
            // 发送请求
            $response = $client->send($url);
            
            if (!$response) {
                return null;
            }
            
            // 解析 JSON
            $data = json_decode($response, true);
            
            return $data;
        } catch (Exception $e) {
            return null;
        }
    }
}
