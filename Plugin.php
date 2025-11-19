<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX for typecho
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.1.3
 * @link https://github.com/GamblerIX/DPlayerMAX
 */
class DPlayerMAX_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('DPlayerMAX_Plugin', 'replacePlayer');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('DPlayerMAX_Plugin', 'replacePlayer');
        Typecho_Plugin::factory('Widget_Archive')->header = array('DPlayerMAX_Plugin', 'playerHeader');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('DPlayerMAX_Plugin', 'playerFooter');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('DPlayerMAX_Plugin', 'addEditorButton');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('DPlayerMAX_Plugin', 'addEditorButton');
        
        // 注册更新路由
        Helper::addRoute('dplayermax_update', '/dplayermax/update', 'DPlayerMAX_Action', 'action');
        
        return _t('插件已激活，请进入设置页面进行配置');
    }

    public static function deactivate()
    {
    }

    public static function playerHeader()
    {
        $url = Helper::options()->pluginUrl . '/DPlayerMAX';
        echo <<<EOF
<link rel="stylesheet" type="text/css" href="$url/assets/DPlayer.min.css" />
EOF;
    }

    public static function playerFooter()
    {
        $url = Helper::options()->pluginUrl . '/DPlayerMAX';
        $config = Helper::options()->plugin('DPlayerMAX');
        
        if (isset($config->hls) && $config->hls) {
            echo "<script type=\"text/javascript\" src=\"$url/plugin/hls.min.js\"></script>\n";
        }
        if (isset($config->flv) && $config->flv) {
            echo "<script type=\"text/javascript\" src=\"$url/plugin/flv.min.js\"></script>\n";
        }
        echo <<<EOF
<script type="text/javascript" src="$url/assets/DPlayer.min.js"></script>
<script type="text/javascript" src="$url/assets/player.js"></script>
EOF;
    }

    public static function replacePlayer($text, $widget, $last)
    {
        $text = empty($last) ? $text : $last;
        if ($widget instanceof Widget_Archive) {
            $pattern = self::get_shortcode_regex(['dplayer']);
            $text = preg_replace_callback("/$pattern/", [__CLASS__, 'parseCallback'], $text);
        }
        return $text;
    }

    public static function parseCallback($matches)
    {
        if ($matches[1] == '[' && $matches[6] == ']') {
            return substr($matches[0], 1, -1);
        }
        $tag = htmlspecialchars_decode($matches[3]);
        $attrs = self::shortcode_parse_atts($tag);
        return self::parsePlayer($attrs);
    }

    public static function parsePlayer($attrs)
    {
        $config = Helper::options()->plugin('DPlayerMAX');
        $theme = (isset($config->theme) && $config->theme) ? $config->theme : '#FADFA3';
        $api = isset($config->api) ? $config->api : '';

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
            'volume' => isset($attrs['volume']) ? $attrs['volume'] : 0.7,
            'mutex' => true,
            'video' => [
                'url' => isset($attrs['url']) ? $attrs['url'] : null,
                'pic' => isset($attrs['pic']) ? $attrs['pic'] : null,
                'type' => isset($attrs['type']) ? $attrs['type'] : 'auto',
                'thumbnails' => isset($attrs['thumbnails']) ? $attrs['thumbnails'] : null,
            ],
        ];
        if (isset($attrs['danmu']) && $attrs['danmu'] == 'true') {
            $config['danmaku'] = [
                'id' => md5(isset($attrs['url']) ? $attrs['url'] : ''),
                'api' => $api,
                'maximum' => isset($attrs['maximum']) ? $attrs['maximum'] : 1000,
                'user' => isset($attrs['user']) ? $attrs['user'] : 'DIYgod',
                'bottom' => isset($attrs['bottom']) ? $attrs['bottom'] : '15%',
                'unlimited' => true,
            ];
        }
        if (isset($attrs['subtitle']) && $attrs['subtitle'] == 'true') {
            $config['subtitle'] = [
                'url' => isset($attrs['subtitleurl']) ? $attrs['subtitleurl'] : null,
                'type' => isset($attrs['subtitletype']) ? $attrs['subtitletype'] : 'webvtt',
                'fontSize' => isset($attrs['subtitlefontsize']) ? $attrs['subtitlefontsize'] : '25px',
                'bottom' => isset($attrs['subtitlebottom']) ? $attrs['subtitlebottom'] : '10%',
                'color' => isset($attrs['subtitlecolor']) ? $attrs['subtitlecolor'] : '#b7daff',
            ];
        }
        $json = json_encode($config);
        return "<div class=\"dplayer\" data-config='{$json}'></div>";
    }

    public static function addEditorButton()
    {
        $dir = Helper::options()->pluginUrl . '/DPlayerMAX/assets/editor.js';
        echo "<script type=\"text/javascript\" src=\"{$dir}\"></script>";
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $theme = new Typecho_Widget_Helper_Form_Element_Text(
            'theme', 
            null, 
            '#FADFA3',
            _t('默认主题颜色'), 
            _t('播放器默认的主题颜色，例如 #372e21、#75c、red、blue，该设定会被[dplayer]标签中的theme属性覆盖，默认为 #FADFA3')
        );
        $form->addInput($theme);
        
        $api = new Typecho_Widget_Helper_Form_Element_Text(
            'api', 
            null, 
            '',
            _t('弹幕服务器地址'), 
            _t('用于保存视频弹幕，例如 https://api.prprpr.me/dplayer/v3/')
        );
        $form->addInput($api);
        
        $hls = new Typecho_Widget_Helper_Form_Element_Radio(
            'hls', 
            array('0' => _t('不开启HLS支持'), '1' => _t('开启HLS支持')), 
            '0', 
            _t('HLS支持'), 
            _t("开启后可解析 m3u8 格式视频")
        );
        $form->addInput($hls);
        
        $flv = new Typecho_Widget_Helper_Form_Element_Radio(
            'flv', 
            array('0' => _t('不开启FLV支持'), '1' => _t('开启FLV支持')), 
            '0', 
            _t('FLV支持'), 
            _t("开启后可解析 flv 格式视频")
        );
        $form->addInput($flv);
        
        $autoUpdate = new Typecho_Widget_Helper_Form_Element_Radio(
            'autoUpdate',
            array('0' => _t('禁用'), '1' => _t('启用')),
            '1',
            _t('自动检查更新'),
            _t('启用后将在访问配置页面时自动检查GitHub上的新版本')
        );
        $form->addInput($autoUpdate);

        // 渲染更新状态组件
        echo self::renderUpdateStatusWidget();
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    private static function shortcode_parse_atts($text)
    {
        $atts = array();
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);
        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
            foreach ($match as $m) {
                if (!empty($m[1]))
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif (!empty($m[3]))
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif (!empty($m[5]))
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif (isset($m[7]) && strlen($m[7]))
                    $atts[] = stripcslashes($m[7]);
                elseif (isset($m[8]))
                    $atts[] = stripcslashes($m[8]);
            }

            foreach ($atts as &$value) {
                if (false !== strpos($value, '<')) {
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

    private static function get_shortcode_regex($tagnames = null)
    {
        $tagregexp = join('|', array_map('preg_quote', $tagnames));

        return
            '\\['
            . '(\\[?)'
            . "($tagregexp)"
            . '(?![\\w-])'
            . '('
            . '[^\\]\\/]*'
            . '(?:'
            . '\\/(?!\\])'
            . '[^\\]\\/]*'
            . ')*?'
            . ')'
            . '(?:'
            . '(\\/)'
            . '\\]'
            . '|'
            . '\\]'
            . '(?:'
            . '('
            . '[^\\[]*+'
            . '(?:'
            . '\\[(?!\\/\\2\\])'
            . '[^\\[]*+'
            . ')*+'
            . ')'
            . '\\[\\/\\2\\]'
            . ')?'
            . ')'
            . '(\\]?)';
    }



    /**
     * 检查更新（代理方法）
     * 
     * @return array 返回更新状态信息
     */
    public static function checkUpdate()
    {
        $updatedFile = __DIR__ . '/ext/Updated.php';
        
        if (!file_exists($updatedFile)) {
            return [
                'success' => false,
                'localVersion' => '1.1.3',
                'remoteVersion' => null,
                'hasUpdate' => false,
                'message' => '更新组件不存在，请重新安装插件'
            ];
        }
        
        require_once $updatedFile;
        return DPlayerMAX_UpdateManager::checkUpdate();
    }

    /**
     * 执行更新（代理方法）
     * 
     * @return array 返回更新结果
     */
    public static function performUpdate()
    {
        $updatedFile = __DIR__ . '/ext/Updated.php';
        
        if (!file_exists($updatedFile)) {
            return [
                'success' => false,
                'message' => '更新组件不存在，请重新安装插件'
            ];
        }
        
        require_once $updatedFile;
        return DPlayerMAX_UpdateManager::performUpdate();
    }

    /**
     * 渲染更新状态组件
     * @return string
     */
    private static function renderUpdateStatusWidget()
    {
        // 获取初始状态（不执行实际的更新检查）
        $updateInfo = self::checkUpdate();
        
        // 确定状态
        $status = 'not-checked';
        if (isset($updateInfo['hasUpdate']) && $updateInfo['hasUpdate']) {
            $status = 'update-available';
        } elseif (isset($updateInfo['success']) && $updateInfo['success']) {
            $status = 'up-to-date';
        } elseif (isset($updateInfo['success']) && !$updateInfo['success']) {
            $status = 'error';
        }
        
        // 渲染CSS样式
        $html = self::renderStyles();
        
        // 开始渲染组件
        $html .= '<div class="dplayermax-update-widget">';
        $html .= '<div class="update-header">';
        $html .= '<h3>插件更新状态' . self::renderStatusLight($status) . '</h3>';
        $html .= '</div>';
        
        // 渲染版本信息
        $html .= self::renderVersionInfo($updateInfo);
        
        // 渲染状态消息
        $html .= '<div class="update-status">';
        $html .= '<p class="status-message">' . htmlspecialchars($updateInfo['message']) . '</p>';
        $html .= '</div>';
        
        // 渲染操作按钮
        $html .= '<div class="update-actions">';
        $html .= '<button type="button" id="dplayermax-check-update-btn" class="btn">检查更新</button>';
        
        if (isset($updateInfo['hasUpdate']) && $updateInfo['hasUpdate']) {
            $html .= '<button type="button" id="dplayermax-perform-update-btn" class="btn primary">立即更新</button>';
            $releaseUrl = 'https://github.com/GamblerIX/DPlayerMAX/releases';
            $html .= '<a href="' . $releaseUrl . '" target="_blank" class="btn">查看更新日志</a>';
        }
        
        $html .= '<span id="dplayermax-update-status" style="margin-left: 10px;"></span>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // 添加JavaScript
        $html .= self::renderJavaScript();
        
        return $html;
    }

    /**
     * 渲染状态指示灯
     * @param string $status 状态
     * @return string
     */
    private static function renderStatusLight($status)
    {
        $titles = [
            'not-checked' => '还没有检查更新',
            'up-to-date' => '已是最新版本',
            'update-available' => '有新版本可用',
            'error' => '检查更新时出错'
        ];
        
        $title = $titles[$status] ?? '未知状态';
        
        return sprintf(
            '<span class="dplayermax-status-light status-%s" title="%s"></span>',
            htmlspecialchars($status),
            htmlspecialchars($title)
        );
    }

    /**
     * 渲染版本信息
     * @param array $updateInfo 更新信息
     * @return string
     */
    private static function renderVersionInfo($updateInfo)
    {
        $html = '<div class="version-info">';
        $html .= '<p><strong>当前版本:</strong> ' . htmlspecialchars($updateInfo['localVersion']) . '</p>';
        
        if ($updateInfo['remoteVersion']) {
            $html .= '<p><strong>最新版本:</strong> ' . htmlspecialchars($updateInfo['remoteVersion']) . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * 渲染CSS样式
     * @return string
     */
    private static function renderStyles()
    {
        return <<<CSS
<style>
.dplayermax-update-widget {
    margin-top: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.dplayermax-update-widget .update-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e1e8ed;
}

.dplayermax-update-widget .update-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: inline-block;
}

.dplayermax-status-light {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-left: 10px;
    box-shadow: 0 0 5px rgba(0,0,0,0.2);
    vertical-align: middle;
}

.dplayermax-status-light.status-not-checked {
    background-color: #6c757d;
    box-shadow: 0 0 5px rgba(108, 117, 125, 0.4);
}

.dplayermax-status-light.status-disabled {
    background-color: #dc3545;
    box-shadow: 0 0 8px rgba(220, 53, 69, 0.6);
}

.dplayermax-status-light.status-up-to-date {
    background-color: #28a745;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.6);
}

.dplayermax-status-light.status-update-available {
    background-color: #ffc107;
    box-shadow: 0 0 8px rgba(255, 193, 7, 0.6);
    animation: dplayermax-pulse 2s infinite;
}

.dplayermax-status-light.status-error {
    background-color: #6c757d;
    box-shadow: 0 0 5px rgba(108, 117, 125, 0.4);
}

@keyframes dplayermax-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.dplayermax-update-widget .version-info {
    margin: 15px 0;
}

.dplayermax-update-widget .version-info p {
    margin: 5px 0;
    font-size: 14px;
}

.dplayermax-update-widget .update-status {
    margin: 15px 0;
}

.dplayermax-update-widget .status-message {
    font-size: 14px;
    margin: 5px 0;
}

.dplayermax-update-widget .last-check-time {
    font-size: 12px;
    color: #6c757d;
    margin: 5px 0;
}

.dplayermax-update-widget .update-actions {
    margin-top: 15px;
}

.dplayermax-update-widget .update-actions .btn {
    margin-right: 10px;
}

.dplayermax-update-widget .loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    vertical-align: middle;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .dplayermax-status-light {
        width: 16px;
        height: 16px;
    }
    
    .dplayermax-update-widget .update-actions .btn {
        display: block;
        margin: 5px 0;
        width: 100%;
    }
}
</style>
CSS;
    }

    /**
     * 渲染JavaScript
     * @return string
     */
    private static function renderJavaScript()
    {
        $updateUrl = Helper::options()->rootUrl . '/dplayermax/update';
        
        return <<<JS
<script>
(function() {
    var checkBtn = document.getElementById('dplayermax-check-update-btn');
    var performBtn = document.getElementById('dplayermax-perform-update-btn');
    var statusSpan = document.getElementById('dplayermax-update-status');
    var lastClickTime = 0;
    
    // 防抖处理
    function debounce(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
    
    // 检查更新
    if (checkBtn) {
        checkBtn.addEventListener('click', debounce(function() {
            var now = Date.now();
            if (now - lastClickTime < 2000) {
                return; // 2秒内不允许重复点击
            }
            lastClickTime = now;
            
            checkBtn.disabled = true;
            checkBtn.textContent = '检查中...';
            statusSpan.innerHTML = '<span class="loading-spinner"></span>';
            
            fetch('{$updateUrl}?do=update&action=check')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    statusSpan.textContent = '检查完成';
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                })
                .catch(function(error) {
                    statusSpan.textContent = '检查失败: ' + error.message;
                    checkBtn.disabled = false;
                    checkBtn.textContent = '检查更新';
                });
        }, 300));
    }
    
    // 立即更新
    if (performBtn) {
        performBtn.addEventListener('click', function() {
            if (!confirm('确定要更新插件吗？\\n\\n建议在更新前手动备份重要数据。')) {
                return;
            }
            
            performBtn.disabled = true;
            performBtn.textContent = '更新中...';
            statusSpan.innerHTML = '<span class="loading-spinner"></span> 正在下载更新包...';
            
            fetch('{$updateUrl}?do=update&action=perform')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        statusSpan.textContent = '✓ ' + data.message;
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        statusSpan.textContent = '✗ ' + data.message;
                        performBtn.disabled = false;
                        performBtn.textContent = '立即更新';
                    }
                })
                .catch(function(error) {
                    statusSpan.textContent = '✗ 更新失败: ' + error.message;
                    performBtn.disabled = false;
                    performBtn.textContent = '立即更新';
                });
        });
    }
})();
</script>
JS;
    }

    /**
     * 记录错误日志
     * @param string $message 错误信息
     * @param string $type 错误类型
     */
    private static function logError($message, $type = 'ERROR')
    {
        $logFile = __DIR__ . '/update_error.log';
        $maxSize = 1024 * 1024; // 1MB
        
        // 如果日志文件过大，清空它
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            @file_put_contents($logFile, '');
        }
        
        // 记录日志
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * 验证用户权限
     * @return bool 是否为管理员
     */
    private static function checkPermission()
    {
        try {
            $user = Typecho_Widget::widget('Widget_User');
            return $user->pass('administrator', true);
        } catch (Exception $e) {
            self::logError('权限验证失败: ' . $e->getMessage(), 'PERMISSION');
            return false;
        }
    }

    /**
     * 验证路径安全性
     * @param string $path 要验证的路径
     * @return bool 路径是否安全
     */
    private static function validatePath($path)
    {
        $pluginDir = realpath(__DIR__);
        $realPath = realpath($path);
        
        // 如果路径不存在或无法解析
        if ($realPath === false) {
            // 对于不存在的路径，检查其父目录
            $parentDir = dirname($path);
            $realParent = realpath($parentDir);
            
            if ($realParent === false) {
                self::logError('无效的路径: ' . $path, 'SECURITY');
                return false;
            }
            
            // 验证父目录在插件目录内
            if (strpos($realParent, $pluginDir) !== 0) {
                self::logError('路径不在插件目录内: ' . $path, 'SECURITY');
                return false;
            }
            
            return true;
        }
        
        // 验证路径在插件目录内
        if (strpos($realPath, $pluginDir) !== 0) {
            self::logError('路径不在插件目录内: ' . $path, 'SECURITY');
            return false;
        }
        
        return true;
    }
}

/**
 * DPlayerMAX Action处理类
 * 处理AJAX更新请求
 */
class DPlayerMAX_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 执行动作
     */
    public function action()
    {
        // 验证用户权限，但不抛出异常
        $user = $this->widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            $this->response->throwJson([
                'success' => false,
                'message' => '权限不足',
                'error' => '只有管理员可以执行更新操作'
            ]);
            return;
        }
        
        $this->on($this->request->is('do=update'))->update();
    }

    /**
     * 处理更新请求
     */
    public function update()
    {
        $action = $this->request->get('action', 'check');

        if ($action === 'check') {
            // 检查更新
            $result = DPlayerMAX_Plugin::checkUpdate();
            $this->response->throwJson($result);
        } elseif ($action === 'perform') {
            // 执行更新
            $result = DPlayerMAX_Plugin::performUpdate();
            $this->response->throwJson($result);
        } else {
            $this->response->throwJson([
                'success' => false,
                'message' => '无效的操作'
            ]);
        }
    }
}
