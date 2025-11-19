<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX 更新管理器
 * 
 * 此文件负责处理插件的版本检查和更新功能。
 * 在插件更新时，此文件不会被覆盖，确保更新功能的持久性。
 *
 * @package DPlayerMAX
 * @author GamblerIX
 * @version 1.0.0
 * @link https://github.com/GamblerIX/DPlayerMAX
 */
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
    const NETWORK_TIMEOUT = 10;
    
    /**
     * 获取本地版本号
     * 
     * @return string 返回本地版本号
     */
    public static function getLocalVersion()
    {
        $versionFile = dirname(__DIR__) . '/VERSION';
        
        // 如果VERSION文件不存在，返回默认版本号
        if (!file_exists($versionFile)) {
            return '1.1.3';
        }
        
        // 读取VERSION文件
        $version = @file_get_contents($versionFile);
        
        if ($version === false) {
            return '1.1.3';
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
        $url = 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::GITHUB_BRANCH . '/VERSION';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::NETWORK_TIMEOUT,
                    'ignore_errors' => true,
                    'method' => 'GET',
                    'header' => "User-Agent: DPlayerMAX-Plugin\r\n"
                ]
            ]);

            $content = @file_get_contents($url, false, $context);

            if ($content === false) {
                return [
                    'success' => false,
                    'version' => null,
                    'error' => '无法连接到GitHub',
                    'errorType' => 'NETWORK'
                ];
            }

            $version = trim($content);
            
            // 验证版本号格式
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
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
            return [
                'success' => false,
                'version' => null,
                'error' => $e->getMessage(),
                'errorType' => 'UNKNOWN'
            ];
        }
    }
    
    /**
     * 下载更新包
     * 
     * @param string $tempDir 临时目录路径
     * @return string|false 返回下载文件路径或false
     */
    private static function downloadUpdate($tempDir)
    {
        $url = 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
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
                'timeout' => self::NETWORK_TIMEOUT,
                'ignore_errors' => true,
                'method' => 'GET',
                'header' => "User-Agent: DPlayerMAX-Plugin\r\n"
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
