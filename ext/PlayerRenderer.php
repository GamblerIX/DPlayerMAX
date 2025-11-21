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
    var hasInteracted=false;
    function markInteraction(){
        hasInteracted=true;
        document.removeEventListener('click',markInteraction);
        document.removeEventListener('keydown',markInteraction);
        document.removeEventListener('touchstart',markInteraction);
    }
    document.addEventListener('click',markInteraction);
    document.addEventListener('keydown',markInteraction);
    document.addEventListener('touchstart',markInteraction);
    function init(){
        players.forEach(function(p){try{p.destroy()}catch(e){}});
        players=[];
        document.querySelectorAll('.dplayer').forEach(function(el,i){
            setTimeout(function(){
                try{
                    var cfg=JSON.parse(el.dataset.config);
                    cfg.container=el;
                    cfg.mutex=false;
                    var shouldAutoplay=cfg.autoplay;
                    if(shouldAutoplay&&!hasInteracted){
                        var tryPlay=function(){
                            if(hasInteracted&&p){
                                p.play().catch(function(){});
                                document.removeEventListener('click',tryPlay);
                                document.removeEventListener('keydown',tryPlay);
                                document.removeEventListener('touchstart',tryPlay);
                            }
                        };
                        document.addEventListener('click',tryPlay);
                        document.addEventListener('keydown',tryPlay);
                        document.addEventListener('touchstart',tryPlay);
                    }
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
    
    /**
     * 解析 B 站视频并生成播放器
     */
    public static function parseBilibiliUrl($url, $options = [])
    {
        // 加载 B 站解析器
        require_once __DIR__ . '/bilibili/server/CacheManager.php';
        require_once __DIR__ . '/bilibili/server/WbiSigner.php';
        require_once __DIR__ . '/bilibili/server/BilibiliAPI.php';
        require_once __DIR__ . '/bilibili/server/BilibiliParser.php';
        
        // 解析视频
        $result = DPlayerMAX_BilibiliParser::getVideoData($url, $options);
        
        if (!$result['success']) {
            return self::renderError($result['error']['message']);
        }
        
        return self::generateBilibiliPlayer($result['data'], $options);
    }
    
    /**
     * 生成 B 站视频播放器配置
     */
    private static function generateBilibiliPlayer($videoData, $options = [])
    {
        $pluginConfig = \Utils\Helper::options()->plugin('DPlayerMAX');
        $theme = isset($pluginConfig->theme) && $pluginConfig->theme ? $pluginConfig->theme : '#FADFA3';
        
        $videoInfo = $videoData['video_info'];
        $playData = $videoData['play_data'];
        
        // 选择最佳视频流和音频流
        $videoUrl = null;
        $audioUrl = null;
        
        if (isset($playData['dash'])) {
            // DASH 格式
            $dash = $playData['dash'];
            
            // 选择视频流（优先选择 1080P）
            if (isset($dash['video']) && is_array($dash['video'])) {
                foreach ($dash['video'] as $video) {
                    if ($video['id'] == 80) {  // 1080P
                        $videoUrl = $video['baseUrl'];
                        break;
                    }
                }
                
                // 如果没有 1080P，选择第一个
                if (!$videoUrl && count($dash['video']) > 0) {
                    $videoUrl = $dash['video'][0]['baseUrl'];
                }
            }
            
            // 选择音频流
            if (isset($dash['audio']) && is_array($dash['audio']) && count($dash['audio']) > 0) {
                $audioUrl = $dash['audio'][0]['baseUrl'];
            }
        } elseif (isset($playData['durl']) && is_array($playData['durl']) && count($playData['durl']) > 0) {
            // MP4 格式
            $videoUrl = $playData['durl'][0]['url'];
        }
        
        if (!$videoUrl) {
            return self::renderError('无法获取视频播放地址');
        }
        
        $config = [
            'live' => false,
            'autoplay' => isset($options['autoplay']) && $options['autoplay'] == 'true',
            'theme' => isset($options['theme']) ? $options['theme'] : $theme,
            'loop' => isset($options['loop']) && $options['loop'] == 'true',
            'screenshot' => true,
            'hotkey' => true,
            'preload' => 'metadata',
            'lang' => 'zh-cn',
            'volume' => isset($options['volume']) ? (float)$options['volume'] : 0.7,
            'video' => [
                'url' => $videoUrl,
                'pic' => $videoInfo['pic'],
                'type' => 'customHls',  // 使用自定义类型处理 B 站视频
                'customType' => [
                    'customHls' => 'function(video, player) {
                        video.src = "' . $videoUrl . '";
                        video.addEventListener("loadedmetadata", function() {
                            player.play();
                        });
                    }'
                ]
            ],
        ];
        
        // 如果有音频流，需要特殊处理
        if ($audioUrl) {
            $config['video']['audioUrl'] = $audioUrl;
        }
        
        // 添加标题
        if (isset($videoInfo['title'])) {
            $config['video']['title'] = $videoInfo['title'];
        }
        
        $json = htmlspecialchars(json_encode($config), ENT_QUOTES, 'UTF-8');
        return "<div class=\"dplayer\" data-config='{$json}'></div>";
    }
    
    /**
     * 渲染错误信息
     */
    private static function renderError($message)
    {
        return '<div class="dplayer-error" style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; color: #666;">'
            . '<strong>播放器加载失败：</strong>' . htmlspecialchars($message)
            . '</div>';
    }
}