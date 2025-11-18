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
     * 获取GitHub远程版本号
     * @return string|false 返回版本号或false（失败时）
     */
    private static function getRemoteVersion()
    {
        $url = 'https://raw.githubusercontent.com/GamblerIX/DPlayerMAX/main/VERSION';
        
        try {
            // 设置超时和错误处理
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            
            // 尝试获取远程VERSION文件
            $version = @file_get_contents($url, false, $context);
            
            if ($version === false) {
                self::logError('无法从GitHub获取VERSION文件', 'NETWORK');
                return false;
            }
            
            // 返回trim后的版本号
            return trim($version);
        } catch (Exception $e) {
            self::logError('获取远程版本异常: ' . $e->getMessage(), 'EXCEPTION');
            return false;
        }
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
     * 检查更新
     * @return array 返回包含状态和信息的数组
     */
    public static function checkUpdate()
    {
        $localVersion = self::getLocalVersion();
        $remoteVersion = self::getRemoteVersion();
        
        // 如果无法获取远程版本
        if ($remoteVersion === false) {
            return [
                'hasUpdate' => false,
                'localVersion' => $localVersion,
                'remoteVersion' => null,
                'message' => '无法连接到GitHub获取版本信息，请检查网络连接',
                'error' => '网络连接失败'
            ];
        }
        
        // 比较版本号
        $compareResult = self::compareVersion($localVersion, $remoteVersion);
        
        if ($compareResult > 0) {
            // 有新版本
            return [
                'hasUpdate' => true,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'message' => '发现新版本 ' . $remoteVersion . '，当前版本 ' . $localVersion,
                'error' => null
            ];
        } elseif ($compareResult === 0) {
            // 版本相同
            return [
                'hasUpdate' => false,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'message' => '当前已是最新版本 ' . $localVersion,
                'error' => null
            ];
        } else {
            // 本地版本更新
            return [
                'hasUpdate' => false,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'message' => '当前版本 ' . $localVersion . ' 高于远程版本 ' . $remoteVersion,
                'error' => null
            ];
        }
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
     * 渲染更新区域
     */
    private static function renderUpdateSection()
    {
        // 检查是否启用自动更新
        try {
            $options = Helper::options()->plugin('DPlayerMAX');
            if (isset($options->autoUpdate) && $options->autoUpdate == '0') {
                return;
            }
        } catch (Exception $e) {
            // 插件配置不存在时（首次激活），默认启用自动更新
        }
        
        // 检查更新
        $updateInfo = self::checkUpdate();
        
        echo '<div class="dplayermax-update-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin-top: 0;">插件更新</h3>';
        
        if ($updateInfo['error']) {
            echo '<p style="color: #d9534f;">' . htmlspecialchars($updateInfo['message']) . '</p>';
        } else {
            echo '<p><strong>当前版本:</strong> ' . htmlspecialchars($updateInfo['localVersion']) . '</p>';
            
            if ($updateInfo['remoteVersion']) {
                echo '<p><strong>最新版本:</strong> ' . htmlspecialchars($updateInfo['remoteVersion']) . '</p>';
            }
            
            echo '<p>' . htmlspecialchars($updateInfo['message']) . '</p>';
            
            if ($updateInfo['hasUpdate']) {
                echo '<button type="button" id="dplayermax-update-btn" class="btn primary" style="margin-top: 10px;">立即更新</button>';
                echo '<span id="dplayermax-update-status" style="margin-left: 10px;"></span>';
            }
        }
        
        echo '</div>';
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
