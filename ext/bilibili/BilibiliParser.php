<?php
/**
 * B站视频解析器
 * 负责解析B站链接并协调各模块完成视频解析
 */
class DPlayerMAX_BilibiliParser
{
    /**
     * 解析 B 站链接
     * @param string $url B 站视频链接
     * @return array|null ['type' => 'bv'|'av', 'id' => string, 'page' => int, 'time' => int]
     */
    public static function parseUrl($url)
    {
        $result = [
            'type' => null,
            'id' => null,
            'page' => 1,
            'time' => 0
        ];
        
        // 提取 BV 号
        if (preg_match('/BV([a-zA-Z0-9]+)/', $url, $matches)) {
            $result['type'] = 'bv';
            $result['id'] = 'BV' . $matches[1];
        }
        // 提取 AV 号
        elseif (preg_match('/av(\d+)/', $url, $matches)) {
            $result['type'] = 'av';
            $result['id'] = $matches[1];
        }
        else {
            return null;
        }
        
        // 解析分P参数
        if (preg_match('/[?&]p=(\d+)/', $url, $matches)) {
            $result['page'] = (int)$matches[1];
        }
        
        // 解析时间戳参数
        if (preg_match('/[?&]t=(\d+)/', $url, $matches)) {
            $result['time'] = (int)$matches[1];
        }
        
        return $result;
    }
    
    /**
     * 获取视频完整信息（包含播放地址）
     * @param string $url B 站视频链接
     * @param array $options 可选参数 ['quality' => 80, 'page' => 1]
     * @return array|null 视频信息和播放数据
     */
    public static function getVideoData($url, $options = [])
    {
        // 解析链接
        $parsed = self::parseUrl($url);
        if (!$parsed) {
            return [
                'success' => false,
                'error' => ['code' => -1, 'message' => '无效的 B 站链接']
            ];
        }
        
        // 合并选项
        $quality = isset($options['quality']) ? $options['quality'] : 80;
        $page = isset($options['page']) ? $options['page'] : $parsed['page'];
        
        // 获取视频信息
        $videoInfo = DPlayerMAX_BilibiliAPI::getVideoInfo($parsed['id'], $parsed['type']);
        if (!$videoInfo) {
            return [
                'success' => false,
                'error' => ['code' => -404, 'message' => '视频不存在或已被删除']
            ];
        }
        
        // 获取对应分P的 CID
        $cid = null;
        if (isset($videoInfo['pages']) && is_array($videoInfo['pages'])) {
            foreach ($videoInfo['pages'] as $p) {
                if ($p['page'] == $page) {
                    $cid = $p['cid'];
                    break;
                }
            }
        }
        
        if (!$cid && isset($videoInfo['cid'])) {
            $cid = $videoInfo['cid'];
        }
        
        if (!$cid) {
            return [
                'success' => false,
                'error' => ['code' => -1, 'message' => '无法获取视频 CID']
            ];
        }
        
        // 获取播放地址
        $playData = DPlayerMAX_BilibiliAPI::getPlayUrl($parsed['id'], $cid, $parsed['type'], $quality);
        if (!$playData) {
            return [
                'success' => false,
                'error' => ['code' => -1, 'message' => '无法获取播放地址']
            ];
        }
        
        // 组合返回数据
        return [
            'success' => true,
            'data' => [
                'video_info' => $videoInfo,
                'play_data' => $playData,
                'parsed' => $parsed,
                'page' => $page,
                'cid' => $cid
            ]
        ];
    }
}
