<?php
/**
 * 代理控制器
 * 处理客户端的代理请求
 */
class DPlayerMAX_ProxyController
{
    /**
     * 处理代理请求
     * @param string $action 操作类型：'video_info' | 'play_url' | 'wbi_keys' | 'parse_video'
     * @param array $params 请求参数
     * @return array JSON 响应
     */
    public static function handleRequest($action, $params)
    {
        // 验证请求
        if (!self::validateRequest()) {
            return ['success' => false, 'error' => ['code' => -403, 'message' => '请求来源不合法']];
        }
        
        // 限流检查
        $ip = self::getClientIp();
        if (!self::checkRateLimit($ip)) {
            return ['success' => false, 'error' => ['code' => -429, 'message' => '请求过于频繁']];
        }
        
        try {
            switch ($action) {
                case 'video_info':
                    return self::handleVideoInfo($params);
                    
                case 'play_url':
                    return self::handlePlayUrl($params);
                    
                case 'wbi_keys':
                    return self::handleWbiKeys();
                    
                case 'parse_video':
                    return self::handleParseVideo($params);
                    
                default:
                    return ['success' => false, 'error' => ['code' => -1, 'message' => '未知的操作类型']];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => $e->getMessage()]];
        }
    }
    
    /**
     * 处理视频信息请求
     */
    private static function handleVideoInfo($params)
    {
        $videoId = isset($params['video_id']) ? $params['video_id'] : null;
        $type = isset($params['type']) ? $params['type'] : 'bv';
        
        if (!$videoId) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => '缺少 video_id 参数']];
        }
        
        $videoInfo = DPlayerMAX_BilibiliAPI::getVideoInfo($videoId, $type);
        
        if (!$videoInfo) {
            return ['success' => false, 'error' => ['code' => -404, 'message' => '视频不存在']];
        }
        
        return ['success' => true, 'data' => $videoInfo];
    }
    
    /**
     * 处理播放地址请求
     */
    private static function handlePlayUrl($params)
    {
        $videoId = isset($params['video_id']) ? $params['video_id'] : null;
        $cid = isset($params['cid']) ? $params['cid'] : null;
        $type = isset($params['type']) ? $params['type'] : 'bv';
        $quality = isset($params['quality']) ? $params['quality'] : 80;
        
        if (!$videoId || !$cid) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => '缺少必要参数']];
        }
        
        $playData = DPlayerMAX_BilibiliAPI::getPlayUrl($videoId, $cid, $type, $quality);
        
        if (!$playData) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => '无法获取播放地址']];
        }
        
        return ['success' => true, 'data' => $playData];
    }
    
    /**
     * 处理 WBI 密钥请求
     */
    private static function handleWbiKeys()
    {
        // 这个方法不应该暴露给客户端，返回错误
        return ['success' => false, 'error' => ['code' => -403, 'message' => '不支持的操作']];
    }
    
    /**
     * 处理完整视频解析请求
     */
    private static function handleParseVideo($params)
    {
        $url = isset($params['url']) ? $params['url'] : null;
        $options = isset($params['options']) ? $params['options'] : [];
        
        if (!$url) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => '缺少 url 参数']];
        }
        
        return DPlayerMAX_BilibiliParser::getVideoData($url, $options);
    }
    
    /**
     * 获取客户端 IP
     */
    private static function getClientIp()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * 验证请求来源
     * @return bool 是否合法
     */
    private static function validateRequest()
    {
        // 检查 Referer
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $siteUrl = Typecho_Widget::widget('Widget_Options')->siteUrl;
            
            // 允许来自本站的请求
            if (strpos($referer, $siteUrl) === 0) {
                return true;
            }
        }
        
        // 如果没有 Referer，也允许（某些情况下浏览器不发送 Referer）
        return true;
    }
    
    /**
     * 限流检查
     * @param string $ip 客户端 IP
     * @return bool 是否允许请求
     */
    private static function checkRateLimit($ip)
    {
        // 获取配置的限流阈值
        $config = \Utils\Helper::options()->plugin('DPlayerMAX');
        $rateLimit = isset($config->bilibili_rate_limit) ? (int)$config->bilibili_rate_limit : 60;
        
        // 如果设置为 0，表示不限流
        if ($rateLimit <= 0) {
            return true;
        }
        
        // 使用缓存记录请求次数
        $cacheKey = 'rate_limit_' . md5($ip);
        $requests = DPlayerMAX_CacheManager::get($cacheKey);
        
        if ($requests === null) {
            // 第一次请求
            DPlayerMAX_CacheManager::set($cacheKey, 1, 60);
            return true;
        }
        
        if ($requests >= $rateLimit) {
            return false;
        }
        
        // 增加请求计数
        DPlayerMAX_CacheManager::set($cacheKey, $requests + 1, 60);
        return true;
    }
}
