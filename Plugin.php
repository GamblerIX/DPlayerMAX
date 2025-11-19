<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX for typecho
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.1.4
 * @link https://github.com/GamblerIX/DPlayerMAX
 */
class DPlayerMAX_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 静态初始化 - 在类加载时立即执行
     */
    public static function init()
    {
        // 检查是否是 AJAX 更新请求
        if (isset($_POST['dplayermax_action']) && 
            isset($_GET['config']) && 
            strpos($_SERVER['REQUEST_URI'], 'DPlayerMAX') !== false) {
            self::handleAjaxRequest();
        }
    }
    
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('DPlayerMAX_Plugin', 'replacePlayer');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('DPlayerMAX_Plugin', 'replacePlayer');
        Typecho_Plugin::factory('Widget_Archive')->header = array('DPlayerMAX_Plugin', 'playerHeader');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('DPlayerMAX_Plugin', 'playerFooter');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('DPlayerMAX_Plugin', 'addEditorButton');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('DPlayerMAX_Plugin', 'addEditorButton');
        
        return _t('插件已激活，请进入设置页面进行配置');
    }

    public static function deactivate()
    {
    }

    public static function playerHeader()
    {
        $url = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX';
        echo <<<EOF
<link rel="stylesheet" type="text/css" href="$url/assets/DPlayer.min.css" />
EOF;
    }

    public static function playerFooter()
    {
        $url = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX';
        $config = \Utils\Helper::options()->plugin('DPlayerMAX');
        
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
        $config = \Utils\Helper::options()->plugin('DPlayerMAX');
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
        $dir = \Utils\Helper::options()->pluginUrl . '/DPlayerMAX/assets/editor.js';
        echo "<script type=\"text/javascript\" src=\"{$dir}\"></script>";
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 必须在最开始处理 AJAX 请求，在任何输出之前
        if (isset($_POST['dplayermax_action'])) {
            self::handleAjaxRequest();
            // handleAjaxRequest 会调用 exit，不会执行到这里
        }
        
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
     * 处理 AJAX 请求
     */
    private static function handleAjaxRequest()
    {
        // 清除之前可能的输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 设置响应头
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        // 验证权限
        $user = Typecho_Widget::widget('Widget_User');
        
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            echo json_encode([
                'success' => false,
                'message' => '权限不足'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $action = $_POST['dplayermax_action'];
        
        try {
            if ($action === 'check') {
                $result = self::checkUpdate();
            } elseif ($action === 'perform') {
                $result = self::performUpdate();
            } else {
                $result = [
                    'success' => false,
                    'message' => '无效的操作'
                ];
            }
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => '错误: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }

    /**
     * 检查更新（代理方法）
     * 
     * @return array 返回更新状态信息
     */
    public static function checkUpdate()
    {
        try {
            // 确保 __TYPECHO_ROOT_DIR__ 常量已定义
            if (!defined('__TYPECHO_ROOT_DIR__')) {
                define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__DIR__))));
            }
            
            $updatedFile = __DIR__ . '/ext/Updated.php';
            
            if (!file_exists($updatedFile)) {
                return [
                    'success' => false,
                    'localVersion' => '1.1.4',
                    'remoteVersion' => null,
                    'hasUpdate' => false,
                    'message' => '更新组件不存在，请重新安装插件'
                ];
            }
            
            require_once $updatedFile;
            
            if (!class_exists('DPlayerMAX_UpdateManager')) {
                return [
                    'success' => false,
                    'localVersion' => '1.1.4',
                    'remoteVersion' => null,
                    'hasUpdate' => false,
                    'message' => '更新管理器类不存在'
                ];
            }
            
            return DPlayerMAX_UpdateManager::checkUpdate();
            
        } catch (Exception $e) {
            self::logError('检查更新失败: ' . $e->getMessage(), 'CHECK_UPDATE');
            return [
                'success' => false,
                'localVersion' => '1.1.4',
                'remoteVersion' => null,
                'hasUpdate' => false,
                'message' => '检查更新时发生错误: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行更新（代理方法）
     * 
     * @return array 返回更新结果
     */
    public static function performUpdate()
    {
        try {
            // 确保 __TYPECHO_ROOT_DIR__ 常量已定义
            if (!defined('__TYPECHO_ROOT_DIR__')) {
                define('__TYPECHO_ROOT_DIR__', dirname(dirname(dirname(__DIR__))));
            }
            
            $updatedFile = __DIR__ . '/ext/Updated.php';
            
            if (!file_exists($updatedFile)) {
                return [
                    'success' => false,
                    'message' => '更新组件不存在，请重新安装插件'
                ];
            }
            
            require_once $updatedFile;
            
            if (!class_exists('DPlayerMAX_UpdateManager')) {
                return [
                    'success' => false,
                    'message' => '更新管理器类不存在'
                ];
            }
            
            return DPlayerMAX_UpdateManager::performUpdate();
            
        } catch (Exception $e) {
            self::logError('执行更新失败: ' . $e->getMessage(), 'PERFORM_UPDATE');
            return [
                'success' => false,
                'message' => '执行更新时发生错误: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 渲染更新状态组件
     * @return string
     */
    private static function renderUpdateStatusWidget()
    {
        // 设置初始状态（不执行实际的更新检查）
        $updateInfo = [
            'success' => true,
            'localVersion' => '1.1.4',
            'remoteVersion' => null,
            'hasUpdate' => false,
            'message' => '点击"检查更新"按钮来检查新版本'
        ];
        
        // 设置初始状态为未检查
        $status = 'not-checked';
        
        // 渲染CSS样式
        $html = self::renderStyles();
        
        // 开始渲染组件
        $html .= '<div class="dplayermax-update-widget">';
        $html .= '<div class="update-header">';
        $html .= '<h3>插件更新状态' . self::renderStatusLight($status) . '</h3>';
        $html .= '</div>';
        
        // 检查版本号是否有错误
        if (strpos($updateInfo['localVersion'], 'ERROR:') === 0) {
            $html .= '<div class="version-info" style="color: red;">';
            $html .= '<p><strong>⚠ ' . htmlspecialchars(substr($updateInfo['localVersion'], 7)) . '</strong></p>';
            $html .= '</div>';
        } else {
            // 渲染版本信息
            $html .= self::renderVersionInfo($updateInfo);
        }
        
        // 渲染状态消息
        $html .= '<div class="update-status">';
        $html .= '<p class="status-message">' . htmlspecialchars($updateInfo['message']) . '</p>';
        $html .= '</div>';
        
        // 渲染操作按钮
        $html .= '<div class="update-actions">';
        $html .= '<button type="button" id="dplayermax-check-update-btn" class="btn">检查更新</button>';
        $html .= '<button type="button" id="dplayermax-perform-update-btn" class="btn primary" style="display:none;">立即更新</button>';
        $html .= '<a id="dplayermax-release-link" href="https://github.com/GamblerIX/DPlayerMAX/tree/main/Changelog" target="_blank" class="btn" style="display:none;">查看更新日志</a>';
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
        $cssUrl = Helper::options()->pluginUrl . '/DPlayerMAX/plugin/update-widget.css';
        return '<link rel="stylesheet" type="text/css" href="' . $cssUrl . '" />';
    }

    /**
     * 渲染JavaScript
     * @return string
     */
    private static function renderJavaScript()
    {
        return <<<JS
<script>
(function() {
    var checkBtn = document.getElementById('dplayermax-check-update-btn');
    var performBtn = document.getElementById('dplayermax-perform-update-btn');
    var releaseLink = document.getElementById('dplayermax-release-link');
    var statusSpan = document.getElementById('dplayermax-update-status');
    var statusLight = document.querySelector('.dplayermax-status-light');
    var lastClickTime = 0;
    
    // 更新状态指示灯
    function updateStatusLight(status) {
        if (!statusLight) return;
        
        // 移除所有状态类
        statusLight.className = 'dplayermax-status-light';
        
        // 添加新状态类
        statusLight.classList.add('status-' + status);
        
        // 更新 title
        var titles = {
            'not-checked': '还没有检查更新',
            'up-to-date': '已是最新版本',
            'update-available': '有新版本可用',
            'error': '检查更新时出错'
        };
        statusLight.title = titles[status] || '未知状态';
    }
    
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
            
            // 使用 POST 请求到当前页面
            var formData = new FormData();
            formData.append('dplayermax_action', 'check');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(function(response) {
                    // 检查响应状态
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': 请求失败');
                    }
                    
                    // 检查内容类型
                    var contentType = response.headers.get('content-type');
                    if (!contentType || contentType.indexOf('application/json') === -1) {
                        throw new Error('服务器返回了非JSON格式的响应，可能是权限不足或登录已过期');
                    }
                    
                    return response.json();
                })
                .then(function(data) {
                    checkBtn.disabled = false;
                    checkBtn.textContent = '检查更新';
                    
                    if (data.success === false) {
                        statusSpan.innerHTML = '<span style="color:red;">✗ ' + data.message + '</span>';
                        updateStatusLight('error');
                    } else {
                        var message = data.message || '检查完成';
                        if (data.hasUpdate) {
                            // 有新版本可用
                            statusSpan.innerHTML = '<span style="color:orange;">⚠ ' + message + '</span>';
                            updateStatusLight('update-available');
                            
                            // 显示更新按钮和查看日志链接
                            if (performBtn) {
                                performBtn.style.display = 'inline-block';
                            }
                            if (releaseLink) {
                                releaseLink.style.display = 'inline-block';
                            }
                        } else {
                            // 已是最新版本
                            statusSpan.innerHTML = '<span style="color:green;">✓ ' + message + '</span>';
                            updateStatusLight('up-to-date');
                            
                            // 隐藏更新按钮
                            if (performBtn) {
                                performBtn.style.display = 'none';
                            }
                            if (releaseLink) {
                                releaseLink.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(function(error) {
                    statusSpan.textContent = '✗ 检查失败: ' + error.message;
                    checkBtn.disabled = false;
                    checkBtn.textContent = '检查更新';
                    console.error('更新检查错误:', error);
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
            
            // 使用 POST 请求到当前页面
            var formData = new FormData();
            formData.append('dplayermax_action', 'perform');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(function(response) {
                    // 检查响应状态
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': 请求失败');
                    }
                    
                    // 检查内容类型
                    var contentType = response.headers.get('content-type');
                    if (!contentType || contentType.indexOf('application/json') === -1) {
                        throw new Error('服务器返回了非JSON格式的响应，可能是权限不足或登录已过期');
                    }
                    
                    return response.json();
                })
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
                    console.error('更新执行错误:', error);
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




// 初始化插件，处理 AJAX 请求
DPlayerMAX_Plugin::init();
