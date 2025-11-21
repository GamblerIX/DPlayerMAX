/**
 * 缓存管理器（客户端）
 * 负责浏览器缓存管理
 */
class CacheManager {
    /**
     * 获取缓存
     * @param {string} key 缓存键
     * @returns {any|null} 缓存值
     */
    static get(key) {
        try {
            const cacheKey = 'bilibili_' + key;
            const item = localStorage.getItem(cacheKey);
            
            if (!item) {
                return null;
            }
            
            const data = JSON.parse(item);
            
            // 检查是否过期
            if (data.expire && data.expire < Date.now()) {
                this.delete(key);
                return null;
            }
            
            return data.value;
        } catch (error) {
            console.error('缓存读取失败:', error);
            return null;
        }
    }
    
    /**
     * 设置缓存
     * @param {string} key 缓存键
     * @param {any} value 缓存值
     * @param {number} ttl 过期时间（秒）
     * @returns {boolean} 是否成功
     */
    static set(key, value, ttl = 3600) {
        try {
            const cacheKey = 'bilibili_' + key;
            const data = {
                value: value,
                expire: Date.now() + (ttl * 1000),
                created: Date.now()
            };
            
            localStorage.setItem(cacheKey, JSON.stringify(data));
            return true;
        } catch (error) {
            console.error('缓存写入失败:', error);
            return false;
        }
    }
    
    /**
     * 删除缓存
     * @param {string} key 缓存键
     * @returns {boolean} 是否成功
     */
    static delete(key) {
        try {
            const cacheKey = 'bilibili_' + key;
            localStorage.removeItem(cacheKey);
            return true;
        } catch (error) {
            console.error('缓存删除失败:', error);
            return false;
        }
    }
    
    /**
     * 清空所有缓存
     * @returns {boolean} 是否成功
     */
    static clear() {
        try {
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('bilibili_')) {
                    keys.push(key);
                }
            }
            
            keys.forEach(key => localStorage.removeItem(key));
            return true;
        } catch (error) {
            console.error('缓存清空失败:', error);
            return false;
        }
    }
}
