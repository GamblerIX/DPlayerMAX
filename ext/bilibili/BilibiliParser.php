<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/WbiSigner.php';

/**
 * B站视频解析器
 * 负责解析 B站链接并获取视频播放信息
 */
class DPlayerMAX_Bilibili_Parser
{
    // BV/AV 转换常量
    const XOR_CODE = 23442827791579;
    const MASK_CODE = 2251799813685247;
    const MAX_AID = 2251799813685248; // 1 << 51
    const BASE = 58;
    const TABLE = 'FcwAPNKTMug3GV5Lj7EJnHpWsx4tb8haYeviqBz6rkCy12mUSDQX9RdoZf';

    // 清晰度映射
    private static $qualityMap = [
        '4k' => 120,
        '1080p60' => 116,
        '1080p+' => 112,
        '1080p' => 80,
        '720p60' => 74,
        '720p' => 64,
        '480p' => 32,
        '360p' => 16
    ];

    // 缓存目录
    private static $cacheDir = null;

    /**
     * 获取缓存目录
     */
    private static function getCacheDir()
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }

    /**
     * 从缓存获取数据
     */
    private static function getFromCache($key, $maxAge = 7200)
    {
        $cacheFile = self::getCacheDir() . '/' . md5($key) . '.json';
        if (file_exists($cacheFile)) {
            $data = @json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['time']) && isset($data['data'])) {
                if (time() - $data['time'] < $maxAge) {
                    return $data['data'];
                }
            }
        }
        return null;
    }

    /**
     * 保存数据到缓存
     */
    private static function saveToCache($key, $data)
    {
        $cacheFile = self::getCacheDir() . '/' . md5($key) . '.json';
        $cacheData = [
            'time' => time(),
            'data' => $data
        ];
        @file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * BV 号转 AV 号
     */
    public static function bv2av($bvid)
    {
        if (strlen($bvid) < 12) {
            return 0;
        }

        // 确保以 BV 开头
        if (strpos($bvid, 'BV') !== 0) {
            $bvid = 'BV' . $bvid;
        }

        $bvidArr = str_split($bvid);

        // 交换位置
        list($bvidArr[3], $bvidArr[9]) = [$bvidArr[9], $bvidArr[3]];
        list($bvidArr[4], $bvidArr[7]) = [$bvidArr[7], $bvidArr[4]];

        // 去掉前3个字符
        $bvidArr = array_slice($bvidArr, 3);

        $tmp = 0;
        foreach ($bvidArr as $char) {
            $idx = strpos(self::TABLE, $char);
            if ($idx === false) {
                return 0;
            }
            $tmp = $tmp * self::BASE + $idx;
        }

        return ($tmp & self::MASK_CODE) ^ self::XOR_CODE;
    }

    /**
     * AV 号转 BV 号
     */
    public static function av2bv($avid)
    {
        $bytes = str_split('BV1000000000');
        $bvIndex = 11;

        $tmp = (self::MAX_AID | $avid) ^ self::XOR_CODE;

        while ($tmp > 0) {
            $bytes[$bvIndex] = self::TABLE[$tmp % self::BASE];
            $tmp = intdiv($tmp, self::BASE);
            $bvIndex--;
        }

        // 交换位置
        list($bytes[3], $bytes[9]) = [$bytes[9], $bytes[3]];
        list($bytes[4], $bytes[7]) = [$bytes[7], $bytes[4]];

        return implode('', $bytes);
    }

    /**
     * 检测是否为 B站视频链接
     */
    public static function isBilibiliUrl($url)
    {
        // 支持的 B站 URL 格式
        $patterns = [
            '/bilibili\.com\/video\/(BV[a-zA-Z0-9]+)/i',
            '/bilibili\.com\/video\/av(\d+)/i',
            '/b23\.tv\/([a-zA-Z0-9]+)/i',
            '/^(BV[a-zA-Z0-9]{10})$/i',
            '/^av(\d+)$/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析 B站 URL，提取视频 ID 和参数
     */
    public static function parseUrl($url)
    {
        $result = [
            'bvid' => null,
            'avid' => null,
            'page' => 1,
            'time' => 0
        ];

        // 解析 BV 号
        if (preg_match('/BV([a-zA-Z0-9]{10})/i', $url, $matches)) {
            $result['bvid'] = 'BV' . $matches[1];
            $result['avid'] = self::bv2av($result['bvid']);
        }
        // 解析 AV 号
        elseif (preg_match('/av(\d+)/i', $url, $matches)) {
            $result['avid'] = (int)$matches[1];
            $result['bvid'] = self::av2bv($result['avid']);
        }
        // 短链接处理
        elseif (preg_match('/b23\.tv\/([a-zA-Z0-9]+)/i', $url, $matches)) {
            // 需要先获取重定向后的真实 URL
            $realUrl = self::resolveShortUrl('https://b23.tv/' . $matches[1]);
            if ($realUrl) {
                return self::parseUrl($realUrl);
            }
        }

        // 解析分P参数
        if (preg_match('/[?&]p=(\d+)/i', $url, $matches)) {
            $result['page'] = (int)$matches[1];
        }

        // 解析时间参数
        if (preg_match('/[?&]t=(\d+)/i', $url, $matches)) {
            $result['time'] = (int)$matches[1];
        }

        return $result;
    }

    /**
     * 解析短链接
     */
    private static function resolveShortUrl($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        $response = curl_exec($ch);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        return $redirectUrl ?: null;
    }

    /**
     * 获取视频信息
     */
    public static function getVideoInfo($bvid)
    {
        // 检查缓存
        $cacheKey = 'video_info_' . $bvid;
        $cached = self::getFromCache($cacheKey, 7200);
        if ($cached) {
            return $cached;
        }

        $url = 'https://api.bilibili.com/x/web-interface/view';
        $params = ['bvid' => $bvid];

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://www.bilibili.com/',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!$data || $data['code'] !== 0) {
            return null;
        }

        $videoInfo = $data['data'];
        $result = [
            'bvid' => $videoInfo['bvid'],
            'avid' => $videoInfo['aid'],
            'title' => $videoInfo['title'],
            'desc' => $videoInfo['desc'],
            'pic' => $videoInfo['pic'],
            'duration' => $videoInfo['duration'],
            'owner' => [
                'mid' => $videoInfo['owner']['mid'],
                'name' => $videoInfo['owner']['name'],
                'face' => $videoInfo['owner']['face']
            ],
            'cid' => $videoInfo['cid'],
            'pages' => []
        ];

        // 解析分P
        if (isset($videoInfo['pages']) && is_array($videoInfo['pages'])) {
            foreach ($videoInfo['pages'] as $page) {
                $result['pages'][] = [
                    'page' => $page['page'],
                    'cid' => $page['cid'],
                    'part' => $page['part'],
                    'duration' => $page['duration']
                ];
            }
        }

        // 保存到缓存
        self::saveToCache($cacheKey, $result);

        return $result;
    }

    /**
     * 获取视频播放地址
     */
    public static function getPlayUrl($bvid, $cid, $quality = 80)
    {
        // 检查缓存 (播放地址缓存时间较短，因为可能过期)
        $cacheKey = "play_url_{$bvid}_{$cid}_{$quality}";
        $cached = self::getFromCache($cacheKey, 6000); // 100分钟
        if ($cached) {
            return $cached;
        }

        $url = 'https://api.bilibili.com/x/player/wbi/playurl';
        $params = [
            'bvid' => $bvid,
            'cid' => $cid,
            'qn' => $quality,
            'fnval' => 1, // 请求 MP4 格式 (不使用DASH)
            'fnver' => 0,
            'fourk' => 1,
            'try_look' => 1 // 免登录试看
        ];

        // 使用 WBI 签名
        $signedParams = DPlayerMAX_Bilibili_WbiSigner::sign($params);

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://www.bilibili.com/video/' . $bvid,
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($signedParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!$data || $data['code'] !== 0) {
            return null;
        }

        $playData = $data['data'];
        $result = [
            'quality' => $playData['quality'] ?? $quality,
            'format' => $playData['format'] ?? 'mp4',
            'timelength' => $playData['timelength'] ?? 0,
            'accept_quality' => $playData['accept_quality'] ?? [],
            'accept_description' => $playData['accept_description'] ?? [],
            'video_url' => null,
            'audio_url' => null,
            'durl' => null
        ];

        // 处理 DASH 格式
        if (isset($playData['dash'])) {
            $dash = $playData['dash'];

            // 获取视频流 (选择最高质量)
            if (isset($dash['video']) && !empty($dash['video'])) {
                $video = $dash['video'][0];
                $result['video_url'] = $video['baseUrl'] ?? $video['base_url'];
                $result['video_codecs'] = $video['codecs'] ?? '';
                $result['video_bandwidth'] = $video['bandwidth'] ?? 0;
            }

            // 获取音频流
            if (isset($dash['audio']) && !empty($dash['audio'])) {
                $audio = $dash['audio'][0];
                $result['audio_url'] = $audio['baseUrl'] ?? $audio['base_url'];
                $result['audio_codecs'] = $audio['codecs'] ?? '';
            }

            $result['type'] = 'dash';
        }
        // 处理 FLV/MP4 格式
        elseif (isset($playData['durl']) && !empty($playData['durl'])) {
            $result['durl'] = [];
            foreach ($playData['durl'] as $segment) {
                $result['durl'][] = [
                    'url' => $segment['url'],
                    'size' => $segment['size'],
                    'length' => $segment['length']
                ];
            }
            $result['video_url'] = $playData['durl'][0]['url'];
            $result['type'] = 'mp4';
        }

        // 保存到缓存
        if ($result['video_url']) {
            self::saveToCache($cacheKey, $result);
        }

        return $result;
    }

    /**
     * 解析清晰度字符串
     */
    public static function parseQuality($qualityStr)
    {
        $qualityStr = strtolower(trim($qualityStr));
        return self::$qualityMap[$qualityStr] ?? 80;
    }

    /**
     * 完整解析 B站 视频
     *
     * @param string $url B站视频链接
     * @param int $page 分P序号
     * @param string $quality 清晰度
     * @return array|null 解析结果
     */
    public static function parse($url, $page = 1, $quality = '1080p')
    {
        // 解析 URL
        $urlInfo = self::parseUrl($url);
        if (!$urlInfo['bvid']) {
            return [
                'success' => false,
                'error' => '无法解析视频链接'
            ];
        }

        // 获取视频信息
        $videoInfo = self::getVideoInfo($urlInfo['bvid']);
        if (!$videoInfo) {
            return [
                'success' => false,
                'error' => '获取视频信息失败'
            ];
        }

        // 确定分P
        $pageNum = $page > 0 ? $page : ($urlInfo['page'] > 0 ? $urlInfo['page'] : 1);
        $cid = $videoInfo['cid'];

        if ($pageNum > 1 && isset($videoInfo['pages'][$pageNum - 1])) {
            $cid = $videoInfo['pages'][$pageNum - 1]['cid'];
        }

        // 获取播放地址
        $qualityNum = self::parseQuality($quality);
        $playUrl = self::getPlayUrl($urlInfo['bvid'], $cid, $qualityNum);

        if (!$playUrl || !$playUrl['video_url']) {
            return [
                'success' => false,
                'error' => '获取播放地址失败'
            ];
        }

        return [
            'success' => true,
            'bvid' => $videoInfo['bvid'],
            'avid' => $videoInfo['avid'],
            'title' => $videoInfo['title'],
            'pic' => $videoInfo['pic'],
            'duration' => $videoInfo['duration'],
            'owner' => $videoInfo['owner'],
            'page' => $pageNum,
            'cid' => $cid,
            'quality' => $playUrl['quality'],
            'type' => $playUrl['type'],
            'video_url' => $playUrl['video_url'],
            'audio_url' => $playUrl['audio_url'] ?? null,
            'accept_quality' => $playUrl['accept_quality'],
            'accept_description' => $playUrl['accept_description']
        ];
    }

    /**
     * 清理过期缓存
     */
    public static function cleanCache($maxAge = 86400)
    {
        $cacheDir = self::getCacheDir();
        $files = glob($cacheDir . '/*.json');
        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAge) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
