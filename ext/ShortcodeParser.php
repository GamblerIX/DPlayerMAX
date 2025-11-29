<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX Shortcode 解析器
 * 负责解析 [dplayer] 短代码
 */
class DPlayerMAX_ShortcodeParser
{
    /**
     * B站 URL 匹配模式
     */
    private static $bilibiliPatterns = [
        '/bilibili\.com\/video\/(BV[a-zA-Z0-9]+)/i',
        '/bilibili\.com\/video\/av(\d+)/i',
        '/b23\.tv\/([a-zA-Z0-9]+)/i'
    ];

    /**
     * 解析短代码属性
     */
    public static function parseAtts($text)
    {
        $atts = [];
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);

        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1])) {
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                } elseif (!empty($m[3])) {
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                } elseif (!empty($m[5])) {
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                } elseif (isset($m[7]) && strlen($m[7])) {
                    $atts[] = stripcslashes($m[7]);
                } elseif (isset($m[8])) {
                    $atts[] = stripcslashes($m[8]);
                }
            }

            // 安全过滤
            foreach ($atts as &$value) {
                if (is_string($value) && strpos($value, '<') !== false) {
                    if (1 !== preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value)) {
                        $value = '';
                    }
                }
            }
        } else {
            $atts = ltrim($text);
        }

        return $atts;
    }

    /**
     * 获取短代码正则表达式
     */
    public static function getRegex($tagnames)
    {
        $tagregexp = join('|', array_map('preg_quote', $tagnames));

        return '\\['
            . '(\\[?)'
            . "($tagregexp)"
            . '(?![\\w-])'
            . '([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)'
            . '(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)'
            . '(\\]?)';
    }

    /**
     * 检测是否为 B站视频链接
     *
     * @param string $url URL 地址
     * @return bool
     */
    public static function isBilibiliUrl($url)
    {
        if (empty($url)) {
            return false;
        }

        foreach (self::$bilibiliPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 提取 B站 视频 ID
     *
     * @param string $url URL 地址
     * @return array|null 包含 bvid 或 avid 的数组
     */
    public static function extractBilibiliId($url)
    {
        if (empty($url)) {
            return null;
        }

        // 匹配 BV 号
        if (preg_match('/BV([a-zA-Z0-9]{10})/i', $url, $matches)) {
            return [
                'type' => 'bvid',
                'id' => 'BV' . $matches[1]
            ];
        }

        // 匹配 AV 号
        if (preg_match('/av(\d+)/i', $url, $matches)) {
            return [
                'type' => 'avid',
                'id' => (int)$matches[1]
            ];
        }

        // 匹配短链接
        if (preg_match('/b23\.tv\/([a-zA-Z0-9]+)/i', $url, $matches)) {
            return [
                'type' => 'short',
                'id' => $matches[1]
            ];
        }

        return null;
    }

    /**
     * 解析 B站 URL 参数
     *
     * @param string $url URL 地址
     * @return array 包含 page 和 time 等参数
     */
    public static function parseBilibiliParams($url)
    {
        $params = [
            'page' => 1,
            'time' => 0
        ];

        // 解析分P参数
        if (preg_match('/[?&]p=(\d+)/i', $url, $matches)) {
            $params['page'] = (int)$matches[1];
        }

        // 解析时间参数
        if (preg_match('/[?&]t=(\d+)/i', $url, $matches)) {
            $params['time'] = (int)$matches[1];
        }

        return $params;
    }
}
