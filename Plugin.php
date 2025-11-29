<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX for typecho
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.3.0
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
        return DPlayerMAX_PlayerRenderer::parsePlayer($attrs);
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

        // ========== 基本设置 ==========
        echo '<h3 style="margin-top:0;padding-bottom:10px;border-bottom:1px solid #ddd;">基本设置</h3>';

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

        // ========== B站解析设置 ==========
        echo '<h3 style="margin-top:30px;padding-bottom:10px;border-bottom:1px solid #ddd;">B站视频解析</h3>';

        // B站解析开关
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'bilibili',
            ['0' => _t('不开启'), '1' => _t('开启')],
            '0',
            _t('B站视频解析'),
            _t('开启后可直接使用B站视频链接，支持免登录1080P播放。用法：[dplayer url="https://www.bilibili.com/video/BVxxxxx/"]')
        ));

        // 默认清晰度
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'bilibili_quality',
            [
                '1080p' => _t('1080P 高清'),
                '720p' => _t('720P'),
                '480p' => _t('480P'),
                '360p' => _t('360P 流畅')
            ],
            '1080p',
            _t('B站默认清晰度'),
            _t('选择B站视频的默认播放清晰度')
        ));

        // ========== 更新设置 ==========
        echo '<h3 style="margin-top:30px;padding-bottom:10px;border-bottom:1px solid #ddd;">插件更新</h3>';

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
                    'localVersion' => '1.3.0',
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
                'localVersion' => '1.3.0',
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

// 初始化插件
DPlayerMAX_Plugin::init();
