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
    /**
     * 内存缓存（用于单次请求内的缓存）
     * @var array
     */
    private static $memoryCache = [];

    /**
     * 缓存TTL（秒）
     */
    const CACHE_TTL = 300; // 5分钟

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
     * 获取本地版本号
     * @return string 返回本地版本号
     */
    private static function getLocalVersion()
    {
        $versionFile = __DIR__ . '/VERSION';
        
        try {
            // 如果VERSION文件不存在，返回Plugin.php中的版本号
            if (!file_exists($versionFile)) {
                return '1.1.3';
            }
            
            // 读取VERSION文件
            $version = @file_get_contents($versionFile);
            
            if ($version === false) {
                self::logError('无法读取本地VERSION文件', 'FILE');
                return '1.1.3';
            }
            
            // 返回trim后的版本号
            return trim($version);
        } catch (Exception $e) {
            self::logError('获取本地版本异常: ' . $e->getMessage(), 'EXCEPTION');
            return '1.1.3';
        }
    }

    /**
     * 比较版本号
     * @param string $local 本地版本
     * @param string $remote 远程版本
     * @return int 返回1表示有更新，0表示相同，-1表示本地更新
     */
    private static function compareVersion($local, $remote)
    {
        return version_compare($remote, $local);
    }

    /**
     * 检查更新（增强版，支持缓存和强制刷新）
     * @param bool $forceRefresh 是否强制刷新（忽略缓存）
     * @return array 返回包含状态和信息的数组
     */
    public static function checkUpdate($forceRefresh = false)
    {
        // 1. 检查自动更新是否启用
        if (!self::isAutoUpdateEnabled()) {
            return self::buildDisabledStatus();
        }

        // 2. 如果未强制刷新，检查内存缓存
        if (!$forceRefresh && isset(self::$memoryCache['update_check'])) {
            $cached = self::$memoryCache['update_check'];
            // 检查缓存是否过期
            if (time() - $cached['timestamp'] < self::CACHE_TTL) {
                $cached['fromCache'] = true;
                return $cached;
            }
        }

        // 3. 获取本地版本
        $localVersion = self::getLocalVersion();

        // 4. 获取远程版本（增强版）
        $apiResult = self::fetchRemoteVersion();

        // 5. 处理API请求失败的情况
        if (!$apiResult['success']) {
            $result = self::buildErrorStatus(
                $localVersion,
                $apiResult['error'],
                $apiResult['errorType']
            );
            // 即使失败也缓存结果（短时间），避免频繁请求
            self::$memoryCache['update_check'] = $result;
            return $result;
        }

        // 6. 比较版本号
        $remoteVersion = $apiResult['version'];
        $compareResult = self::compareVersion($localVersion, $remoteVersion);

        // 7. 构建结果
        $result = self::buildSuccessStatus($localVersion, $remoteVersion, $compareResult);

        // 8. 更新缓存
        self::$memoryCache['update_check'] = $result;

        return $result;
    }

    /**
     * 检查自动更新是否启用
     * @return bool
     */
    private static function isAutoUpdateEnabled()
    {
        try {
            $options = Helper::options()->plugin('DPlayerMAX');
            return !isset($options->autoUpdate) || $options->autoUpdate != '0';
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * 获取远程版本（增强版，带详细错误处理）
     * @return array 返回包含成功状态、版本号、错误信息的数组
     */
    private static function fetchRemoteVersion()
    {
        $url = 'https://raw.githubusercontent.com/GamblerIX/DPlayerMAX/main/VERSION';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true,
                    'method' => 'GET',
                    'header' => "User-Agent: DPlayerMAX-Plugin\r\n"
                ]
            ]);

            $startTime = microtime(true);
            $content = @file_get_contents($url, false, $context);
            $duration = microtime(true) - $startTime;

            if ($content === false) {
                return self::handleRequestFailure($http_response_header ?? null, $duration);
            }

            if (isset($http_response_header)) {
                $statusCode = self::parseStatusCode($http_response_header);
                if ($statusCode !== 200) {
                    return self::handleHttpError($statusCode);
                }
            }

            $version = trim($content);
            if (!self::validateVersionFormat($version)) {
                self::logError('版本号格式错误: ' . $version, 'FORMAT');
                return [
                    'success' => false,
                    'version' => null,
                    'error' => '版本号格式不正确',
                    'errorType' => 'FORMAT'
                ];
            }

            return [
                'success' => true,
                'version' => $version,
                'error' => null,
                'errorType' => null
            ];

        } catch (Exception $e) {
            self::logError('获取远程版本异常: ' . $e->getMessage(), 'EXCEPTION');
            return [
                'success' => false,
                'version' => null,
                'error' => $e->getMessage(),
                'errorType' => 'UNKNOWN'
            ];
        }
    }

    /**
     * 处理请求失败
     * @param array|null $headers HTTP响应头
     * @param float $duration 请求耗时
     * @return array
     */
    private static function handleRequestFailure($headers, $duration)
    {
        if ($duration >= 10) {
            self::logError('请求超时 (耗时: ' . $duration . '秒)', 'TIMEOUT');
            return [
                'success' => false,
                'version' => null,
                'error' => '请求超时',
                'errorType' => 'TIMEOUT'
            ];
        }

        self::logError('网络连接失败', 'NETWORK');
        return [
            'success' => false,
            'version' => null,
            'error' => '无法连接到GitHub',
            'errorType' => 'NETWORK'
        ];
    }

    /**
     * 处理HTTP错误
     * @param int $statusCode HTTP状态码
     * @return array
     */
    private static function handleHttpError($statusCode)
    {
        $errorMap = [
            404 => ['error' => '远程版本文件不存在', 'type' => 'NOT_FOUND'],
            403 => ['error' => '无权限访问', 'type' => 'PERMISSION'],
            429 => ['error' => 'API请求过于频繁', 'type' => 'RATE_LIMIT']
        ];

        $errorInfo = $errorMap[$statusCode] ?? ['error' => "HTTP错误: {$statusCode}", 'type' => 'HTTP_ERROR'];
        
        self::logError($errorInfo['error'] . " (HTTP {$statusCode})", $errorInfo['type']);
        
        return [
            'success' => false,
            'version' => null,
            'error' => $errorInfo['error'],
            'errorType' => $errorInfo['type']
        ];
    }

    /**
     * 从响应头中解析HTTP状态码
     * @param array $headers HTTP响应头数组
     * @return int|null
     */
    private static function parseStatusCode($headers)
    {
        if (empty($headers) || !is_array($headers)) {
            return null;
        }

        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * 验证版本号格式
     * @param string $version 版本号
     * @return bool
     */
    private static function validateVersionFormat($version)
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }

    /**
     * 构建禁用状态的返回数据
     * @return array
     */
    private static function buildDisabledStatus()
    {
        return [
            'status' => 'disabled',
            'localVersion' => self::getLocalVersion(),
            'remoteVersion' => null,
            'hasUpdate' => false,
            'message' => '自动检查更新已禁用',
            'error' => null,
            'errorType' => null,
            'timestamp' => time(),
            'fromCache' => false
        ];
    }

    /**
     * 构建错误状态的返回数据
     * @param string $localVersion 本地版本
     * @param string $error 错误信息
     * @param string $errorType 错误类型
     * @return array
     */
    private static function buildErrorStatus($localVersion, $error, $errorType)
    {
        $message = self::getErrorMessage($errorType, $error);

        return [
            'status' => 'error',
            'localVersion' => $localVersion,
            'remoteVersion' => null,
            'hasUpdate' => false,
            'message' => $message,
            'error' => $error,
            'errorType' => $errorType,
            'timestamp' => time(),
            'fromCache' => false
        ];
    }

    /**
     * 构建成功状态的返回数据
     * @param string $localVersion 本地版本
     * @param string $remoteVersion 远程版本
     * @param int $compareResult 版本比较结果
     * @return array
     */
    private static function buildSuccessStatus($localVersion, $remoteVersion, $compareResult)
    {
        if ($compareResult > 0) {
            return [
                'status' => 'update-available',
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => true,
                'message' => "发现新版本 {$remoteVersion}，当前版本 {$localVersion}",
                'error' => null,
                'errorType' => null,
                'timestamp' => time(),
                'fromCache' => false
            ];
        } elseif ($compareResult === 0) {
            return [
                'status' => 'up-to-date',
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => false,
                'message' => "当前已是最新版本 {$localVersion}",
                'error' => null,
                'errorType' => null,
                'timestamp' => time(),
                'fromCache' => false
            ];
        } else {
            return [
                'status' => 'up-to-date',
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => false,
                'message' => "当前版本 {$localVersion} 高于远程版本 {$remoteVersion}",
                'error' => null,
                'errorType' => null,
                'timestamp' => time(),
                'fromCache' => false
            ];
        }
    }

    /**
     * 根据错误类型获取用户友好的错误消息
     * @param string $errorType 错误类型
     * @param string $error 原始错误信息
     * @return string
     */
    private static function getErrorMessage($errorType, $error)
    {
        $messages = [
            'NETWORK' => '无法连接到GitHub，请检查服务器网络设置',
            'TIMEOUT' => '请求超时，请稍后重试',
            'FORMAT' => '远程版本文件格式错误，请联系开发者',
            'RATE_LIMIT' => 'GitHub API请求过于频繁，请1小时后再试',
            'NOT_FOUND' => '远程版本文件不存在',
            'PERMISSION' => '无权限访问GitHub资源'
        ];

        return $messages[$errorType] ?? '发生未知错误：' . $error;
    }

    /**
     * 递归复制文件和目录
     * @param string $source 源目录
     * @param string $dest 目标目录
     * @return bool 成功返回true，失败返回false
     */
    private static function recursiveCopy($source, $dest)
    {
        // 如果源不存在，返回false
        if (!file_exists($source)) {
            return false;
        }
        
        // 如果是文件，直接复制
        if (is_file($source)) {
            return copy($source, $dest);
        }
        
        // 如果是目录，创建目标目录
        if (!is_dir($dest)) {
            if (!@mkdir($dest, 0755, true)) {
                return false;
            }
        }
        
        // 遍历源目录
        $dir = opendir($source);
        if ($dir === false) {
            return false;
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            
            $srcPath = $source . '/' . $file;
            $destPath = $dest . '/' . $file;
            
            // 递归复制
            if (!self::recursiveCopy($srcPath, $destPath)) {
                closedir($dir);
                return false;
            }
        }
        
        closedir($dir);
        return true;
    }

    /**
     * 备份当前插件
     * @param string $backupDir 备份目录路径
     * @return bool 成功返回true，失败返回false
     */
    private static function backupPlugin($backupDir)
    {
        try {
            // 创建备份目录（带时间戳）
            $timestamp = date('YmdHis');
            $backupPath = $backupDir . '/backup_' . $timestamp;
            
            if (!@mkdir($backupPath, 0755, true)) {
                return false;
            }
            
            // 获取当前插件目录
            $pluginDir = __DIR__;
            
            // 递归复制所有文件
            return self::recursiveCopy($pluginDir, $backupPath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 清理备份和临时文件
     * @param string $dir 要清理的目录
     * @return bool 成功返回true，失败返回false
     */
    private static function cleanupBackup($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (is_file($dir)) {
            return @unlink($dir);
        }
        
        // 递归删除目录
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                if (!self::cleanupBackup($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }
        
        return @rmdir($dir);
    }

    /**
     * 从备份恢复插件
     * @param string $backupDir 备份目录路径
     * @return bool 成功返回true，失败返回false
     */
    private static function restorePlugin($backupDir)
    {
        // 验证备份目录存在
        if (!file_exists($backupDir) || !is_dir($backupDir)) {
            return false;
        }
        
        try {
            $pluginDir = __DIR__;
            
            // 删除当前插件文件（除了备份目录）
            $files = array_diff(scandir($pluginDir), ['.', '..', basename($backupDir)]);
            foreach ($files as $file) {
                $path = $pluginDir . '/' . $file;
                if (is_dir($path)) {
                    self::cleanupBackup($path);
                } else {
                    @unlink($path);
                }
            }
            
            // 从备份恢复所有文件
            return self::recursiveCopy($backupDir, $pluginDir);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 下载更新包
     * @param string $tempDir 临时目录路径
     * @return string|false 返回下载文件路径或false
     */
    private static function downloadUpdate($tempDir)
    {
        $url = 'https://github.com/GamblerIX/DPlayerMAX/archive/refs/heads/main.zip';
        
        // 验证URL来源
        if (!self::validateDownloadUrl($url)) {
            self::logError('无效的下载URL: ' . $url, 'SECURITY');
            return false;
        }
        
        $zipFile = $tempDir . '/update.zip';
        
        // 创建临时目录
        if (!file_exists($tempDir)) {
            if (!@mkdir($tempDir, 0755, true)) {
                return false;
            }
        }
        
        // 设置超时和错误处理
        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'ignore_errors' => true
            ]
        ]);
        
        // 下载文件
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            return false;
        }
        
        // 验证文件大小（至少1KB）
        if (strlen($content) < 1024) {
            return false;
        }
        
        // 保存到临时文件
        if (@file_put_contents($zipFile, $content) === false) {
            return false;
        }
        
        return $zipFile;
    }

    /**
     * 验证下载URL的安全性
     * @param string $url 下载URL
     * @return bool URL是否安全
     */
    private static function validateDownloadUrl($url)
    {
        // 只允许从GitHub官方仓库下载
        $allowedDomains = [
            'github.com',
            'raw.githubusercontent.com'
        ];
        
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        // 验证域名
        if (!in_array($parsedUrl['host'], $allowedDomains)) {
            self::logError('不允许的下载域名: ' . $parsedUrl['host'], 'SECURITY');
            return false;
        }
        
        // 验证协议为HTTPS
        if (!isset($parsedUrl['scheme']) || $parsedUrl['scheme'] !== 'https') {
            self::logError('必须使用HTTPS协议', 'SECURITY');
            return false;
        }
        
        return true;
    }

    /**
     * 解压更新包
     * @param string $zipFile zip文件路径
     * @param string $extractDir 解压目录
     * @return string|false 返回解压后的插件目录路径或false
     */
    private static function extractUpdate($zipFile, $extractDir)
    {
        // 检查ZipArchive扩展
        if (!class_exists('ZipArchive')) {
            return false;
        }
        
        $zip = new ZipArchive();
        
        // 打开zip文件
        if ($zip->open($zipFile) !== true) {
            return false;
        }
        
        // 解压到临时目录
        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            return false;
        }
        
        $zip->close();
        
        // GitHub的zip包会包含一个DPlayerMAX-main目录
        $extractedDir = $extractDir . '/DPlayerMAX-main';
        
        if (!file_exists($extractedDir)) {
            return false;
        }
        
        return $extractedDir;
    }

    /**
     * 安装更新（复制文件）
     * @param string $sourceDir 源目录
     * @param string $targetDir 目标目录
     * @return bool 成功返回true，失败返回false
     */
    private static function installUpdate($sourceDir, $targetDir)
    {
        // 跳过的文件和目录
        $skipFiles = ['.git', '.gitignore', '.github'];
        
        try {
            $files = array_diff(scandir($sourceDir), ['.', '..']);
            
            foreach ($files as $file) {
                // 跳过不必要的文件
                if (in_array($file, $skipFiles)) {
                    continue;
                }
                
                $srcPath = $sourceDir . '/' . $file;
                $destPath = $targetDir . '/' . $file;
                
                if (is_dir($srcPath)) {
                    // 递归复制目录
                    if (!self::recursiveCopy($srcPath, $destPath)) {
                        return false;
                    }
                } else {
                    // 复制文件
                    if (!copy($srcPath, $destPath)) {
                        return false;
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 执行完整的更新流程
     * @return array 返回更新结果
     */
    public static function performUpdate()
    {
        $pluginDir = __DIR__;
        $tempDir = $pluginDir . '/temp_update';
        $backupDir = $pluginDir . '/backup';
        $backupPath = null;
        
        try {
            // 1. 创建备份
            if (!self::backupPlugin($backupDir)) {
                self::logError('备份失败', 'BACKUP');
                return [
                    'success' => false,
                    'message' => '备份失败，更新已取消',
                    'error' => '无法创建备份'
                ];
            }
            
            // 获取最新的备份目录
            $backups = glob($backupDir . '/backup_*');
            if (empty($backups)) {
                self::logError('找不到备份目录', 'BACKUP');
                return [
                    'success' => false,
                    'message' => '备份失败，更新已取消',
                    'error' => '找不到备份目录'
                ];
            }
            $backupPath = end($backups);
            
            // 2. 下载更新包
            $zipFile = self::downloadUpdate($tempDir);
            if ($zipFile === false) {
                self::logError('下载更新包失败', 'DOWNLOAD');
                self::cleanupBackup($tempDir);
                return [
                    'success' => false,
                    'message' => '下载更新失败',
                    'error' => '无法从GitHub下载更新包'
                ];
            }
            
            // 3. 解压更新包
            $extractedDir = self::extractUpdate($zipFile, $tempDir);
            if ($extractedDir === false) {
                self::logError('解压更新包失败', 'EXTRACT');
                self::cleanupBackup($tempDir);
                return [
                    'success' => false,
                    'message' => '解压更新失败',
                    'error' => '无法解压更新包或ZipArchive扩展未安装'
                ];
            }
            
            // 4. 安装更新
            if (!self::installUpdate($extractedDir, $pluginDir)) {
                // 安装失败，恢复备份
                self::logError('安装更新失败，正在恢复备份', 'INSTALL');
                self::restorePlugin($backupPath);
                self::cleanupBackup($tempDir);
                return [
                    'success' => false,
                    'message' => '安装更新失败，已恢复到原版本',
                    'error' => '文件复制失败'
                ];
            }
            
            // 5. 清理临时文件和备份
            self::cleanupBackup($tempDir);
            self::cleanupBackup($backupDir);
            
            self::logError('更新成功完成', 'SUCCESS');
            return [
                'success' => true,
                'message' => '更新成功！插件已更新到最新版本',
                'error' => null
            ];
            
        } catch (Exception $e) {
            // 发生异常，尝试恢复备份
            self::logError('更新过程中发生异常: ' . $e->getMessage(), 'EXCEPTION');
            
            if ($backupPath && file_exists($backupPath)) {
                self::logError('正在从备份恢复', 'RESTORE');
                self::restorePlugin($backupPath);
            }
            self::cleanupBackup($tempDir);
            
            return [
                'success' => false,
                'message' => '更新过程中发生错误，已恢复到原版本',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 渲染更新状态组件
     * @return string
     */
    private static function renderUpdateStatusWidget()
    {
        // 获取更新状态
        $updateInfo = self::checkUpdate();
        $status = $updateInfo['status'];
        
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
        
        if (isset($updateInfo['fromCache']) && $updateInfo['fromCache']) {
            $html .= '<p class="cache-notice" style="font-size: 12px; color: #6c757d;">（使用缓存数据）</p>';
        }
        
        if ($updateInfo['timestamp']) {
            $timeStr = date('Y-m-d H:i:s', $updateInfo['timestamp']);
            $html .= '<p class="last-check-time">最后检查: ' . htmlspecialchars($timeStr) . '</p>';
        }
        $html .= '</div>';
        
        // 渲染操作按钮
        $html .= '<div class="update-actions">';
        $html .= '<button type="button" id="dplayermax-check-update-btn" class="btn">检查更新</button>';
        
        if ($updateInfo['hasUpdate']) {
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
            'disabled' => '自动检查更新已禁用',
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
            
            fetch('{$updateUrl}?do=update&action=check&force=1')
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
            if (!confirm('确定要更新插件吗？\\n\\n更新过程中会自动备份当前版本，如果更新失败会自动恢复。\\n建议在更新前手动备份重要数据。')) {
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
        $this->widget('Widget_User')->pass('administrator');
        $this->on($this->request->is('do=update'))->update();
    }

    /**
     * 处理更新请求
     */
    public function update()
    {
        // 验证用户权限
        $user = $this->widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            $this->response->throwJson([
                'success' => false,
                'message' => '权限不足',
                'error' => '只有管理员可以执行更新操作'
            ]);
        }

        $action = $this->request->get('action', 'check');

        if ($action === 'check') {
            // 检查更新，支持强制刷新
            $forceRefresh = $this->request->get('force', '0') == '1';
            $result = DPlayerMAX_Plugin::checkUpdate($forceRefresh);
            $this->response->throwJson($result);
        } elseif ($action === 'status') {
            // 获取更新状态（不强制刷新）
            $result = DPlayerMAX_Plugin::checkUpdate(false);
            $this->response->throwJson($result);
        } elseif ($action === 'perform') {
            // 执行更新
            $result = DPlayerMAX_Plugin::performUpdate();
            $this->response->throwJson($result);
        } else {
            $this->response->throwJson([
                'success' => false,
                'message' => '无效的操作',
                'error' => '不支持的action参数'
            ]);
        }
    }
}
