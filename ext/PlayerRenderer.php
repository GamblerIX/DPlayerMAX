<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX 播放器渲染器
 * 负责播放器的HTML和JavaScript渲染
 */
class DPlayerMAX_PlayerRenderer
{
    /**
     * 渲染播放器头部资源
     */
    public static function renderHeader()
    {
        $url = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX';
        echo '<link rel="stylesheet" type="text/css" href="' . $url . '/assets/DPlayer.min.css" />' . "\n";
    }

    /**
     * 渲染播放器脚本
     */
    public static function renderFooter()
    {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;
        
        $url = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX';
        $config = \Utils\Helper::options()->plugin('DPlayerMAX');
        
        // 加载可选的格式支持
        if (isset($config->hls) && $config->hls) {
            echo '<script src="' . $url . '/plugin/hls.min.js"></script>' . "\n";
        }
        if (isset($config->flv) && $config->flv) {
            echo '<script src="' . $url . '/plugin/flv.min.js"></script>' . "\n";
        }
        
        // 加载播放器和初始化脚本
        echo '<script src="' . $url . '/assets/DPlayer.min.js"></script>' . "\n";
        echo self::renderInitScript();
    }

    /**
     * 渲染播放器初始化脚本
     */
    private static function renderInitScript()
    {
        return <<<'JS'
<script>
(function(){
    var players=[];
    function init(){
        players.forEach(function(p){try{p.destroy()}catch(e){}});
        players=[];
        document.querySelectorAll('.dplayer').forEach(function(el,i){
            setTimeout(function(){
                try{
                    var cfg=JSON.parse(el.dataset.config);
                    cfg.container=el;
                    cfg.mutex=false;
                    var p=new DPlayer(cfg);
                    players.push(p);
                    p.on('error',function(){p.pause()});
                }catch(e){console.error('DPlayer:',e)}
            },i*100);
        });
    }
    document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();
</script>
JS;
    }

    /**
     * 解析播放器配置并生成HTML
     */
    public static function parsePlayer($attrs)
    {
        $pluginConfig = \Utils\Helper::options()->plugin('DPlayerMAX');
        $theme = isset($pluginConfig->theme) && $pluginConfig->theme ? $pluginConfig->theme : '#FADFA3';
        $api = isset($pluginConfig->api) ? $pluginConfig->api : '';

        $config = [
            'live' => false,
            'autoplay' => isset($attrs['autoplay']) && $attrs['autoplay'] == 'true',
            'theme' => isset($attrs['theme']) ? $attrs['theme'] : $theme,
            'loop' => isset($attrs['loop']) && $attrs['loop'] == 'true',
            'screenshot' => isset($attrs['screenshot']) && $attrs['screenshot'] == 'true',
            'hotkey' => true,
            'preload' => 'metadata',
            'lang' => isset($attrs['lang']) ? $attrs['lang'] : 'zh-cn',
            'logo' => isset($attrs['logo']) ? $attrs['logo'] : null,
            'volume' => isset($attrs['volume']) ? (float)$attrs['volume'] : 0.7,
            'video' => [
                'url' => isset($attrs['url']) ? $attrs['url'] : null,
                'pic' => isset($attrs['pic']) ? $attrs['pic'] : null,
                'type' => isset($attrs['type']) ? $attrs['type'] : 'auto',
                'thumbnails' => isset($attrs['thumbnails']) ? $attrs['thumbnails'] : null,
            ],
        ];
        
        // 弹幕配置
        if (isset($attrs['danmu']) && $attrs['danmu'] == 'true') {
            $config['danmaku'] = [
                'id' => md5($attrs['url'] ?? ''),
                'api' => $api,
                'maximum' => isset($attrs['maximum']) ? (int)$attrs['maximum'] : 1000,
                'user' => isset($attrs['user']) ? $attrs['user'] : 'DIYgod',
                'bottom' => isset($attrs['bottom']) ? $attrs['bottom'] : '15%',
                'unlimited' => true,
            ];
        }
        
        // 字幕配置
        if (isset($attrs['subtitle']) && $attrs['subtitle'] == 'true') {
            $config['subtitle'] = [
                'url' => isset($attrs['subtitleurl']) ? $attrs['subtitleurl'] : null,
                'type' => isset($attrs['subtitletype']) ? $attrs['subtitletype'] : 'webvtt',
                'fontSize' => isset($attrs['subtitlefontsize']) ? $attrs['subtitlefontsize'] : '25px',
                'bottom' => isset($attrs['subtitlebottom']) ? $attrs['subtitlebottom'] : '10%',
                'color' => isset($attrs['subtitlecolor']) ? $attrs['subtitlecolor'] : '#b7daff',
            ];
        }
        
        $json = htmlspecialchars(json_encode($config), ENT_QUOTES, 'UTF-8');
        return "<div class=\"dplayer\" data-config='{$json}'></div>";
    }
}
