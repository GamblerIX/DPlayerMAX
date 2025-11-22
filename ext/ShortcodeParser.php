<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX Shortcode 解析器
 * 负责解析 [dplayer] 短代码
 */
class DPlayerMAX_ShortcodeParser
{
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
}
