<?php
/**
 * 调试版本 - 用于查看具体错误信息
 * 使用方法：访问 /usr/plugins/DPlayerMAX/ext/debug_update.php?action=check
 */

// 显示所有错误
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "开始调试...<br>";

// 1. 检查路径
$currentDir = __DIR__;
echo "当前目录: $currentDir<br>";

$rootDir = dirname(dirname(dirname(dirname(__FILE__))));
echo "Typecho 根目录: $rootDir<br>";

// 2. 检查配置文件
$configFile = $rootDir . '/config.inc.php';
echo "配置文件路径: $configFile<br>";
echo "配置文件存在: " . (file_exists($configFile) ? '是' : '否') . "<br>";

if (!file_exists($configFile)) {
    die("配置文件不存在！");
}

// 3. 加载 Typecho
echo "加载 Typecho...<br>";
define('__TYPECHO_ROOT_DIR__', $rootDir);
require_once $configFile;
echo "Typecho 加载成功<br>";

// 4. 检查用户权限
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
echo "Session 已启动<br>";

try {
    $user = Typecho_Widget::widget('Widget_User');
    echo "用户对象创建成功<br>";
    echo "是否登录: " . ($user->hasLogin() ? '是' : '否') . "<br>";
    
    if ($user->hasLogin()) {
        echo "用户名: " . $user->name . "<br>";
        echo "是否管理员: " . ($user->pass('administrator', true) ? '是' : '否') . "<br>";
    }
} catch (Exception $e) {
    echo "用户验证错误: " . $e->getMessage() . "<br>";
}

// 5. 加载更新管理器
echo "<br>加载更新管理器...<br>";
$updatedFile = __DIR__ . '/Updated.php';
echo "Updated.php 路径: $updatedFile<br>";
echo "Updated.php 存在: " . (file_exists($updatedFile) ? '是' : '否') . "<br>";

if (file_exists($updatedFile)) {
    require_once $updatedFile;
    echo "Updated.php 加载成功<br>";
    
    if (class_exists('DPlayerMAX_UpdateManager')) {
        echo "DPlayerMAX_UpdateManager 类存在<br>";
        
        // 6. 测试检查更新
        if (isset($_GET['action']) && $_GET['action'] === 'check') {
            echo "<br>执行检查更新...<br>";
            try {
                $result = DPlayerMAX_UpdateManager::checkUpdate();
                echo "<pre>";
                print_r($result);
                echo "</pre>";
            } catch (Exception $e) {
                echo "检查更新错误: " . $e->getMessage() . "<br>";
                echo "错误追踪: <pre>" . $e->getTraceAsString() . "</pre>";
            }
        }
    } else {
        echo "DPlayerMAX_UpdateManager 类不存在<br>";
    }
}

echo "<br>调试完成！";
