<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX for typecho
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.2.0
 * @link https://github.com/GamblerIX/DPlayerMAX
 */
class DPlayerMAX_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 插件初始化
     */
    public static function init()
    {
        // 处理 AJAX 更新请求
        if (isset($_POST['dplayermax_action']) && 
            isset($_GET['config']) && 
            strpos($_SERVER['REQUEST_URI'], 'DPlayerMAX') !== false) {
            self::handleAjaxRequest();
        }
    }
    
    /**
     * 激活插件
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = ['DPlayerMAX_Plugin', 'replacePlayer'];
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = ['DPlayerMAX_Plugin', 'replacePlayer'];
        Typecho_Plugin::factory('Widget_Archive')->header = ['DPlayerMAX_Plugin', 'playerHeader'];
        Typecho_Plugin::factory('Widget_Archive')->footer = ['DPlayerMAX_Plugin', 'playerFooter'];
        Typecho_Plugin::factory('admin/write-post.php')->bottom = ['DPlayerMAX_Plugin', 'addEditorButton'];
        Typecho_Plugin::factory('admin/write-page.php')->bottom = ['DPlayerMAX_Plugin', 'addEditorButton'];
        
        // 注册 B 站代理 Action
        \Utils\Helper::addAction('bilibili-proxy', 'DPlayerMAX_Action');
        
        return _t('插件已激活，请进入设置页面进行配置');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
    }

    /**
     * 输出头部资源
     */
    public static function playerHeader()
    {
        require_once __DIR__ . '/ext/PlayerRenderer.php';
        DPlayerMAX_PlayerRenderer::renderHeader();
    }

    /**
     * 输出底部脚本
     */
    public static function playerFooter()
    {
        require_once __DIR__ . '/ext/PlayerRenderer.php';
        DPlayerMAX_PlayerRenderer::renderFooter();
    }

    /**
     * 替换短代码为播放器
     */
    public static function replacePlayer($text, $widget, $last)
    {
        $text = empty($last) ? $text : $last;
        if ($widget instanceof Widget_Archive) {
            require_once __DIR__ . '/ext/ShortcodeParser.php';
            $pattern = DPlayerMAX_ShortcodeParser::getRegex(['dplayer']);
            $text = preg_replace_callback("/$pattern/", [__CLASS__, 'parseCallback'], $text);
        }
        return $text;
    }

    /**
     * 短代码回调
     */
    public static function parseCallback($matches)
    {
        if ($matches[1] == '[' && $matches[6] == ']') {
            return substr($matches[0], 1, -1);
        }
        
        require_once __DIR__ . '/ext/ShortcodeParser.php';
        require_once __DIR__ . '/ext/PlayerRenderer.php';
        
        $tag = htmlspecialchars_decode($matches[3]);
        $attrs = DPlayerMAX_ShortcodeParser::parseAtts($tag);
        
        // 检查是否启用 B 站解析
        $config = \Utils\Helper::options()->plugin('DPlayerMAX');
        $bilibiliEnable = isset($config->bilibili_enable) && $config->bilibili_enable == '1';
        
        // 检查是否为 B 站链接
        $url = isset($attrs['url']) ? $attrs['url'] : null;
        $isBilibili = isset($attrs['bilibili']) && $attrs['bilibili'] == 'true';
        
        if ($bilibiliEnable && ($isBilibili || DPlayerMAX_ShortcodeParser::isBilibiliUrl($url))) {
            // 使用 B 站解析器
            $options = [
                'quality' => isset($attrs['quality']) ? self::parseQuality($attrs['quality']) : 80,
                'page' => isset($attrs['page']) ? (int)$attrs['page'] : 1,
                'autoplay' => isset($attrs['autoplay']) ? $attrs['autoplay'] : 'false',
                'loop' => isset($attrs['loop']) ? $attrs['loop'] : 'false',
                'theme' => isset($attrs['theme']) ? $attrs['theme'] : null,
                'volume' => isset($attrs['volume']) ? $attrs['volume'] : null,
            ];
            
            return DPlayerMAX_PlayerRenderer::parseBilibiliUrl($url, $options);
        }
        
        // 使用默认播放器
        return DPlayerMAX_PlayerRenderer::parsePlayer($attrs);
    }
    
    /**
     * 解析清晰度参数
     */
    private static function parseQuality($quality)
    {
        $qualityMap = [
            '1080p' => 80,
            '720p' => 64,
            '480p' => 32,
            '360p' => 16,
        ];
        
        return isset($qualityMap[$quality]) ? $qualityMap[$quality] : 80;
    }

    /**
     * 添加编辑器按钮
     */
    public static function addEditorButton()
    {
        $url = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX/assets/editor.js';
        echo '<script src="' . $url . '"></script>';
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 处理 AJAX 请求
        if (isset($_POST['dplayermax_action'])) {
            self::handleAjaxRequest();
        }
        
        // 主题颜色
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'theme', null, '#FADFA3',
            _t('默认主题颜色'), 
            _t('播放器默认的主题颜色，例如 #372e21、#75c、red、blue')
        ));
        
        // 弹幕服务器
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'api', null, '',
            _t('弹幕服务器地址'), 
            _t('用于保存视频弹幕，例如 https://api.prprpr.me/dplayer/v3/')
        ));
        
        // HLS 支持
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'hls', 
            ['0' => _t('不开启'), '1' => _t('开启')], 
            '0', _t('HLS支持'), _t("开启后可解析 m3u8 格式视频")
        ));
        
        // FLV 支持
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'flv', 
            ['0' => _t('不开启'), '1' => _t('开启')], 
            '0', _t('FLV支持'), _t("开启后可解析 flv 格式视频")
        ));
        
        // B站视频解析
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'bilibili_enable',
            ['0' => _t('禁用'), '1' => _t('启用')],
            '1',
            _t('B站视频解析'),
            _t('开启后可自动解析 B 站视频链接，支持免登录 1080P 播放')
        ));
        
        // 解析模式
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'bilibili_mode',
            [
                'server' => _t('服务端代理模式（推荐）'),
                'client' => _t('客户端直连模式'),
                'hybrid' => _t('混合模式（智能）')
            ],
            'server',
            _t('B站解析模式'),
            _t('<ul style="margin-top:10px;padding-left:20px;">
                <li><strong>服务端代理模式</strong>：安全性高，算法不暴露，适合注重安全的场景</li>
                <li><strong>客户端直连模式</strong>：速度快，服务器无压力，适合追求性能的场景</li>
                <li><strong>混合模式</strong>：优先客户端直连，失败时自动降级到服务端代理</li>
            </ul>')
        ));
        
        // 默认清晰度
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'bilibili_quality',
            [
                '80' => _t('1080P（高清）'),
                '64' => _t('720P'),
                '32' => _t('480P'),
                '16' => _t('360P（流畅）')
            ],
            '80',
            _t('B站默认清晰度'),
            _t('选择视频的默认播放清晰度')
        ));
        
        // 缓存功能
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'bilibili_cache',
            ['0' => _t('禁用'), '1' => _t('启用')],
            '1',
            _t('B站缓存功能'),
            _t('开启后会缓存视频信息和 WBI 密钥，提高加载速度')
        ));
        
        // 缓存时长
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'bilibili_cache_ttl',
            null,
            '7200',
            _t('B站缓存时长（秒）'),
            _t('视频信息的缓存时长，默认 7200 秒（2 小时）')
        ));
        
        // 请求超时
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'bilibili_timeout',
            null,
            '10',
            _t('B站请求超时（秒）'),
            _t('API 请求的超时时间，默认 10 秒')
        ));
        
        // 限流设置
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'bilibili_rate_limit',
            null,
            '60',
            _t('B站限流阈值（次/分钟）'),
            _t('服务端代理模式下，每个 IP 每分钟最多请求次数，0 表示不限制')
        ));
        
        // 自动识别
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'bilibili_auto_detect',
            ['0' => _t('禁用'), '1' => _t('启用')],
            '1',
            _t('自动识别 B 站链接'),
            _t('开启后会自动识别文章中的 B 站链接并转换为播放器')
        ));

        // 渲染更新组件
        require_once __DIR__ . '/ext/UpdateUI.php';
        echo DPlayerMAX_UpdateUI::render();
    }

    /**
     * 个人配置
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 处理 AJAX 请求
     */
    private static function handleAjaxRequest()
    {
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        // 验证权限
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            self::sendJson(['success' => false, 'message' => '权限不足']);
        }
        
        // 执行操作
        $action = $_POST['dplayermax_action'];
        try {
            $result = ($action === 'check') ? self::checkUpdate() : 
                     (($action === 'perform') ? self::performUpdate() : 
                     ['success' => false, 'message' => '无效操作']);
            self::sendJson($result);
        } catch (Exception $e) {
            self::sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 检查更新
     */
    public static function checkUpdate()
    {
        try {
            if (!defined('__TYPECHO_ROOT_DIR__')) {
                define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__DIR__))));
            }
            
            $file = __DIR__ . '/ext/Updated.php';
            if (!file_exists($file)) {
                return [
                    'success' => false,
                    'localVersion' => '1.2.0',
                    'remoteVersion' => null,
                    'hasUpdate' => false,
                    'message' => '更新组件不存在'
                ];
            }
            
            require_once $file;
            return class_exists('DPlayerMAX_UpdateManager') ? 
                   DPlayerMAX_UpdateManager::checkUpdate() : 
                   ['success' => false, 'message' => '更新管理器加载失败'];
                   
        } catch (Exception $e) {
            return [
                'success' => false,
                'localVersion' => '1.2.0',
                'remoteVersion' => null,
                'hasUpdate' => false,
                'message' => '检查更新失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行更新
     */
    public static function performUpdate()
    {
        try {
            if (!defined('__TYPECHO_ROOT_DIR__')) {
                define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__DIR__))));
            }
            
            $file = __DIR__ . '/ext/Updated.php';
            if (!file_exists($file)) {
                return ['success' => false, 'message' => '更新组件不存在'];
            }
            
            require_once $file;
            return class_exists('DPlayerMAX_UpdateManager') ? 
                   DPlayerMAX_UpdateManager::performUpdate() : 
                   ['success' => false, 'message' => '更新管理器加载失败'];
                   
        } catch (Exception $e) {
            return ['success' => false, 'message' => '执行更新失败: ' . $e->getMessage()];
        }
    }

    /**
     * 发送 JSON 响应
     */
    private static function sendJson($data)
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 加载 B 站代理 Action
if (file_exists(__DIR__ . '/ext/bilibili/Action.php')) {
    require_once __DIR__ . '/ext/bilibili/Action.php';
}

// 初始化插件
DPlayerMAX_Plugin::init();
