<?php
/**
 * DPlayerMAX 更新管理器
 * 
 * 此文件负责处理插件的版本检查和更新功能。
 * 在插件更新时，此文件不会被覆盖，确保更新功能的持久性。
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.2.0
 * @link https://github.com/GamblerIX/DPlayerMAX
 */

// 处理 HTTP 请求（当直接访问此文件时）
if (isset($_GET['action']) && !defined('__TYPECHO_ROOT_DIR__')) {
    // 调试模式：添加 &debug=1 参数启用
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($debugMode) {
        // 调试模式：显示所有错误
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        header('Content-Type: text/html; charset=utf-8');
        echo "<h2>DPlayerMAX 更新调试模式</h2>";
        echo "<p>当前文件: " . __FILE__ . "</p>";
    } else {
        // 正常模式：关闭错误显示
        ini_set('display_errors', 0);
        error_reporting(0);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    try {
        // 尝试不同的路径层级找到 Typecho 根目录
        $possibleRoots = [
            dirname(dirname(dirname(dirname(__FILE__)))),  // 4层: ext -> DPlayerMAX -> plugins -> usr -> root
            dirname(dirname(dirname(__FILE__))),           // 3层: ext -> DPlayerMAX -> plugins -> root
            dirname(dirname(dirname(dirname(dirname(__FILE__))))), // 5层
        ];
        
        $foundRoot = null;
        foreach ($possibleRoots as $testRoot) {
            if ($debugMode) {
                echo "<p>测试路径: $testRoot</p>";
            }
            if (file_exists($testRoot . '/config.inc.php')) {
                $foundRoot = $testRoot;
                if ($debugMode) {
                    echo "<p style='color:green;'><b>✓ 找到配置文件: {$testRoot}/config.inc.php</b></p>";
                }
                break;
            }
        }
        
        if (!$foundRoot) {
            if ($debugMode) {
                echo "<p style='color:red;'><b>✗ 无法找到 Typecho 配置文件</b></p>";
                echo "<p>尝试的路径:</p><ul>";
                foreach ($possibleRoots as $path) {
                    echo "<li>$path/config.inc.php</li>";
                }
                echo "</ul>";
                exit;
            }
            echo json_encode([
                'success' => false,
                'message' => '无法找到 Typecho 配置文件，请检查插件安装路径'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 定义 Typecho 根目录
        define('__TYPECHO_ROOT_DIR__', $foundRoot);
        $configFile = __TYPECHO_ROOT_DIR__ . '/config.inc.php';
        
        if ($debugMode) {
            echo "<p>加载配置文件: $configFile</p>";
        }
        
        // 加载 Typecho
        require_once $configFile;
        
        if ($debugMode) {
            echo "<p style='color:green;'>✓ Typecho 加载成功</p>";
        }
        
        // 验证用户权限 - 使用 Cookie 验证
        if (session_status() == PHP_SESSION_NONE) {
            @session_start();
        }
        
        if ($debugMode) {
            echo "<p>Session 已启动</p>";
            echo "<p>Session ID: " . session_id() . "</p>";
            echo "<p>Cookies: <pre>" . print_r($_COOKIE, true) . "</pre></p>";
        }
        
        // 初始化数据库连接
        $db = Typecho_Db::get();
        
        // 获取 Cookie 前缀
        $cookiePrefix = Typecho_Cookie::getPrefix();
        
        if ($debugMode) {
            echo "<p>Cookie 前缀: " . htmlspecialchars($cookiePrefix) . "</p>";
        }
        
        $user = Typecho_Widget::widget('Widget_User');
        
        if ($debugMode) {
            echo "<p>用户登录状态: " . ($user->hasLogin() ? '已登录' : '未登录') . "</p>";
            if ($user->hasLogin()) {
                echo "<p>用户名: {$user->name}</p>";
                echo "<p>用户组: {$user->group}</p>";
                echo "<p>是否管理员: " . ($user->pass('administrator', true) ? '是' : '否') . "</p>";
            } else {
                // 尝试手动验证 Cookie
                echo "<p>尝试手动验证 Cookie...</p>";
                $uidCookie = $cookiePrefix . '__typecho_uid';
                $authCookie = $cookiePrefix . '__typecho_authCode';
                
                if (isset($_COOKIE[$uidCookie])) {
                    echo "<p>找到 UID Cookie: " . $_COOKIE[$uidCookie] . "</p>";
                    
                    // 尝试手动登录
                    $uid = intval($_COOKIE[$uidCookie]);
                    if ($uid > 0) {
                        try {
                            $user = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));
                            if ($user && $user['group'] == 'administrator') {
                                echo "<p style='color:green;'><b>✓ 通过 Cookie 验证为管理员</b></p>";
                                echo "<p>用户名: {$user['name']}</p>";
                                // 跳过权限检查，直接执行操作
                                goto skip_permission_check;
                            }
                        } catch (Exception $e) {
                            echo "<p>手动验证失败: " . $e->getMessage() . "</p>";
                        }
                    }
                }
                if (isset($_COOKIE[$authCookie])) {
                    echo "<p>找到 AuthCode Cookie</p>";
                }
            }
        }
        
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            if ($debugMode) {
                echo "<p style='color:red;'><b>✗ 权限不足</b></p>";
                echo "<p>提示：直接访问此文件时，Typecho 的 Session 可能无法正确识别。请使用插件设置页面的按钮进行操作。</p>";
                exit;
            }
            echo json_encode([
                'success' => false,
                'message' => '权限不足，只有管理员可以执行更新操作'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        skip_permission_check:
        
        // 获取操作类型
        $action = $_GET['action'];
        
        if ($debugMode) {
            echo "<p>执行操作: $action</p>";
            echo "<hr>";
        }
        
        if ($action === 'check') {
            $result = DPlayerMAX_UpdateManager::checkUpdate();
        } elseif ($action === 'perform') {
            $result = DPlayerMAX_UpdateManager::performUpdate();
        } else {
            $result = [
                'success' => false,
                'message' => '无效的操作类型'
            ];
        }
        
        if ($debugMode) {
            echo "<h3>结果:</h3>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        if ($debugMode) {
            echo "<h3 style='color:red;'>异常错误:</h3>";
            echo "<p><b>消息:</b> " . $e->getMessage() . "</p>";
            echo "<p><b>文件:</b> " . $e->getFile() . "</p>";
            echo "<p><b>行号:</b> " . $e->getLine() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            echo json_encode([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Error $e) {
        if ($debugMode) {
            echo "<h3 style='color:red;'>PHP 错误:</h3>";
            echo "<p><b>消息:</b> " . $e->getMessage() . "</p>";
            echo "<p><b>文件:</b> " . $e->getFile() . "</p>";
            echo "<p><b>行号:</b> " . $e->getLine() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'PHP 错误: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}

// 如果不是 HTTP 请求，确保在 Typecho 环境中
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class DPlayerMAX_UpdateManager
{
    /**
     * GitHub 仓库信息
     */
    const GITHUB_REPO = 'GamblerIX/DPlayerMAX';
    const GITHUB_BRANCH = 'main';

    /**
     * 网络请求超时时间（秒）
     */
    const NETWORK_TIMEOUT = 30;

    /**
     * GitHub 镜像列表（按优先级排序）
     */
    private static $mirrors = [
        'raw' => [
            'https://raw.githubusercontent.com/',
            'https://ghfast.top/https://raw.githubusercontent.com/',
            'https://raw.gitmirror.com/',
            'https://raw.fgit.cf/',
        ],
        'zip' => [
            'https://github.com/',
            'https://ghfast.top/https://github.com/',
            'https://hub.gitmirror.com/https://github.com/',
            'https://gh.ddlc.top/https://github.com/',
        ]
    ];
    
    /**
     * 获取本地版本号
     * 
     * @return string 返回本地版本号，如果出错返回 ERROR: 开头的错误信息
     */
    public static function getLocalVersion()
    {
        $versionFile = dirname(__DIR__) . '/VERSION';
        
        // 如果VERSION文件不存在，返回错误提示
        if (!file_exists($versionFile)) {
            return 'ERROR: VERSION文件不存在，请重新下载插件';
        }
        
        // 读取VERSION文件
        $version = @file_get_contents($versionFile);
        
        if ($version === false) {
            return 'ERROR: 无法读取VERSION文件';
        }
        
        // 返回trim后的版本号
        return trim($version);
    }
    
    /**
     * 检查更新
     * 
     * @return array 返回更新状态信息
     */
    public static function checkUpdate()
    {
        // 获取本地版本
        $localVersion = self::getLocalVersion();
        
        // 检查是否有错误
        if (strpos($localVersion, 'ERROR:') === 0) {
            return [
                'success' => false,
                'localVersion' => $localVersion,
                'remoteVersion' => null,
                'hasUpdate' => false,
                'message' => substr($localVersion, 7) // 移除 "ERROR: " 前缀
            ];
        }

        // 获取远程版本
        $apiResult = self::fetchRemoteVersion();

        // 处理API请求失败的情况
        if (!$apiResult['success']) {
            return [
                'success' => false,
                'localVersion' => $localVersion,
                'remoteVersion' => null,
                'hasUpdate' => false,
                'message' => self::getFriendlyErrorMessage($apiResult['errorType'])
            ];
        }

        // 比较版本号
        $remoteVersion = $apiResult['version'];
        $compareResult = self::compareVersion($localVersion, $remoteVersion);

        // 构建结果
        if ($compareResult > 0) {
            return [
                'success' => true,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => true,
                'message' => "发现新版本 {$remoteVersion}，当前版本 {$localVersion}"
            ];
        } elseif ($compareResult === 0) {
            return [
                'success' => true,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => false,
                'message' => "当前已是最新版本 {$localVersion}"
            ];
        } else {
            return [
                'success' => true,
                'localVersion' => $localVersion,
                'remoteVersion' => $remoteVersion,
                'hasUpdate' => false,
                'message' => "当前版本 {$localVersion} 高于远程版本 {$remoteVersion}"
            ];
        }
    }
    
    /**
     * 执行更新
     * 
     * @return array 返回更新结果
     */
    public static function performUpdate()
    {
        $pluginDir = dirname(__DIR__);
        $tempDir = $pluginDir . '/temp_update';
        
        try {
            // 1. 下载更新包
            $zipFile = self::downloadUpdate($tempDir);
            if ($zipFile === false) {
                self::cleanupTemp($tempDir);
                return [
                    'success' => false,
                    'message' => self::getFriendlyErrorMessage('DOWNLOAD')
                ];
            }
            
            // 2. 解压更新包
            $extractedDir = self::extractUpdate($zipFile, $tempDir);
            if ($extractedDir === false) {
                self::cleanupTemp($tempDir);
                return [
                    'success' => false,
                    'message' => self::getFriendlyErrorMessage('EXTRACT')
                ];
            }
            
            // 3. 获取远程版本号用于验证
            $remoteVersionResult = self::fetchRemoteVersion();
            $expectedVersion = $remoteVersionResult['success'] ? $remoteVersionResult['version'] : null;
            
            // 4. 安装更新
            if (!self::installUpdate($extractedDir, $pluginDir)) {
                self::cleanupTemp($tempDir);
                return [
                    'success' => false,
                    'message' => self::getFriendlyErrorMessage('INSTALL')
                ];
            }
            
            // 5. 验证版本号
            if ($expectedVersion && !self::verifyVersion($expectedVersion)) {
                self::cleanupTemp($tempDir);
                return [
                    'success' => false,
                    'message' => '更新完成但版本验证失败，请手动检查'
                ];
            }
            
            // 6. 清理临时文件
            self::cleanupTemp($tempDir);
            
            return [
                'success' => true,
                'message' => '更新成功！插件已更新到最新版本，请刷新页面'
            ];
            
        } catch (Exception $e) {
            // 发生异常，清理临时文件
            self::cleanupTemp($tempDir);
            
            return [
                'success' => false,
                'message' => self::getFriendlyErrorMessage('UNKNOWN')
            ];
        }
    }
    
    /**
     * 比较版本号
     * 
     * @param string $local 本地版本
     * @param string $remote 远程版本
     * @return int 返回1表示有更新，0表示相同，-1表示本地更新
     */
    private static function compareVersion($local, $remote)
    {
        return version_compare($remote, $local);
    }
    
    /**
     * 获取远程版本号
     *
     * @return array 返回包含成功状态、版本号、错误信息的数组
     */
    private static function fetchRemoteVersion()
    {
        $path = self::GITHUB_REPO . '/' . self::GITHUB_BRANCH . '/VERSION';

        // 尝试所有镜像
        foreach (self::$mirrors['raw'] as $mirror) {
            $url = $mirror . $path;

            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => self::NETWORK_TIMEOUT,
                        'ignore_errors' => true,
                        'method' => 'GET',
                        'header' => "User-Agent: DPlayerMAX-Plugin\r\n"
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);

                $content = @file_get_contents($url, false, $context);

                if ($content !== false) {
                    $version = trim($content);

                    // 验证版本号格式
                    if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                        return [
                            'success' => true,
                            'version' => $version,
                            'error' => null,
                            'errorType' => null,
                            'mirror' => $mirror
                        ];
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return [
            'success' => false,
            'version' => null,
            'error' => '无法连接到GitHub',
            'errorType' => 'NETWORK'
        ];
    }
    
    /**
     * 下载更新包
     *
     * @param string $tempDir 临时目录路径
     * @return string|false 返回下载文件路径或false
     */
    private static function downloadUpdate($tempDir)
    {
        $path = self::GITHUB_REPO . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
        $zipFile = $tempDir . '/update.zip';

        // 创建临时目录
        if (!file_exists($tempDir)) {
            if (!@mkdir($tempDir, 0755, true)) {
                return false;
            }
        }

        // 尝试所有镜像
        foreach (self::$mirrors['zip'] as $mirror) {
            $url = $mirror . $path;

            // 设置超时和错误处理
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::NETWORK_TIMEOUT,
                    'ignore_errors' => true,
                    'method' => 'GET',
                    'header' => "User-Agent: DPlayerMAX-Plugin\r\n",
                    'follow_location' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            // 下载文件
            $content = @file_get_contents($url, false, $context);

            if ($content !== false && strlen($content) > 1024) {
                // 保存到临时文件
                if (@file_put_contents($zipFile, $content) !== false) {
                    return $zipFile;
                }
            }
        }

        return false;
    }
    
    /**
     * 解压更新包
     * 
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
        $extractedDir = $extractDir . '/DPlayerMAX-' . self::GITHUB_BRANCH;
        
        if (!file_exists($extractedDir)) {
            return false;
        }
        
        return $extractedDir;
    }
    
    /**
     * 安装更新
     * 
     * @param string $sourceDir 源目录
     * @param string $targetDir 目标目录
     * @return bool 成功返回true，失败返回false
     */
    private static function installUpdate($sourceDir, $targetDir)
    {
        try {
            $files = @scandir($sourceDir);
            if ($files === false) {
                return false;
            }
            
            $files = array_diff($files, ['.', '..']);
            
            foreach ($files as $file) {
                // 检查是否应该跳过此文件
                if (self::shouldSkipFile($file)) {
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
                    if (!@copy($srcPath, $destPath)) {
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
     * 递归复制文件和目录
     * 
     * @param string $source 源路径
     * @param string $dest 目标路径
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
            return @copy($source, $dest);
        }
        
        // 如果是目录，创建目标目录
        if (!is_dir($dest)) {
            if (!@mkdir($dest, 0755, true)) {
                return false;
            }
        }
        
        // 遍历源目录
        $dir = @opendir($source);
        if ($dir === false) {
            return false;
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
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
     * 判断文件是否应该跳过
     * 
     * @param string $relativePath 相对路径
     * @return bool 应该跳过返回true
     */
    private static function shouldSkipFile($relativePath)
    {
        $skipFiles = [
            'ext/Updated.php',
            '.git',
            '.github',
            '.gitignore',
            '.gitattributes'
        ];
        
        foreach ($skipFiles as $skipFile) {
            if ($relativePath === $skipFile || strpos($relativePath, $skipFile . '/') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 清理临时目录
     * 
     * @param string $dir 要清理的目录
     * @return bool 成功返回true，失败返回false
     */
    private static function cleanupTemp($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (is_file($dir)) {
            return @unlink($dir);
        }
        
        // 递归删除目录
        $files = @scandir($dir);
        if ($files === false) {
            return false;
        }
        
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                if (!self::cleanupTemp($path)) {
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
     * 验证更新后的版本号
     * 
     * @param string $expectedVersion 期望的版本号
     * @return bool 版本号正确返回true
     */
    private static function verifyVersion($expectedVersion)
    {
        $currentVersion = self::getLocalVersion();
        return $currentVersion === $expectedVersion;
    }
    
    /**
     * 获取用户友好的错误消息
     * 
     * @param string $errorType 错误类型
     * @return string 用户友好的中文错误消息
     */
    private static function getFriendlyErrorMessage($errorType)
    {
        $messages = [
            'NETWORK' => '无法连接到 GitHub，请检查网络连接',
            'DOWNLOAD' => '下载更新包失败，请稍后重试',
            'EXTRACT' => '解压更新包失败，请确保服务器已安装 ZipArchive 扩展',
            'INSTALL' => '安装更新失败，请检查文件权限',
            'FORMAT' => '版本号格式错误',
            'UNKNOWN' => '更新过程中发生错误，请稍后重试'
        ];

        return isset($messages[$errorType]) ? $messages[$errorType] : '检查更新失败，请稍后重试';
    }
}
