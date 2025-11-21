<?php
/**
 * 缓存管理器
 * 负责管理服务端缓存数据
 */
class DPlayerMAX_CacheManager
{
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed|null 缓存值
     */
    public static function get($key)
    {
        $cachePath = self::getCachePath($key);
        
        if (!file_exists($cachePath)) {
            return null;
        }
        
        $content = file_get_contents($cachePath);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        if (!$data) {
            return null;
        }
        
        // 检查是否过期
        if (isset($data['expire']) && $data['expire'] < time()) {
            self::delete($key);
            return null;
        }
        
        return isset($data['value']) ? $data['value'] : null;
    }
    
    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool 是否成功
     */
    public static function set($key, $value, $ttl = 3600)
    {
        $cachePath = self::getCachePath($key);
        $cacheDir = dirname($cachePath);
        
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                return false;
            }
        }
        
        $data = [
            'value' => $value,
            'expire' => time() + $ttl,
            'created' => time()
        ];
        
        $content = json_encode($data);
        return file_put_contents($cachePath, $content) !== false;
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public static function delete($key)
    {
        $cachePath = self::getCachePath($key);
        
        if (file_exists($cachePath)) {
            return unlink($cachePath);
        }
        
        return true;
    }
    
    /**
     * 清空所有缓存
     * @return bool 是否成功
     */
    public static function clear()
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/bilibili/';
        
        if (!is_dir($cacheDir)) {
            return true;
        }
        
        $files = glob($cacheDir . '*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * 获取缓存文件路径
     * @param string $key 缓存键
     * @return string 缓存文件路径
     */
    private static function getCachePath($key)
    {
        $cacheDir = __TYPECHO_ROOT_DIR__ . '/usr/cache/bilibili/';
        return $cacheDir . md5($key) . '.cache';
    }
}
