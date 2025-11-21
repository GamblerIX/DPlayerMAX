/**
 * 混合模式控制器
 * 处理客户端直连和服务端代理的智能降级
 */
class HybridController {
    /**
     * 初始化混合控制器
     * @param {Object} config 配置对象
     */
    constructor(config) {
        this.config = config;
        this.failureLog = [];
        this.maxFailureLog = 10;
        
        // 尝试从缓存加载推荐模式
        const cachedMode = CacheManager.get('recommended_mode');
        this.recommendedMode = cachedMode || 'client';
    }
    
    /**
     * 智能获取视频数据
     * @param {string} url B 站视频链接
     * @param {Object} options 选项
     * @returns {Promise<Object>} 视频数据
     */
    async getVideoData(url, options = {}) {
        const client = new BilibiliClient({
            ...this.config,
            mode: 'client'
        });
        
        // 根据推荐模式选择优先尝试的方式
        if (this.recommendedMode === 'server') {
            // 直接使用服务端模式
            return await this.fallbackToServerMode(async () => {
                const serverClient = new BilibiliClient({
                    ...this.config,
                    mode: 'server'
                });
                return await serverClient.parseVideo(url, options);
            });
        }
        
        // 尝试客户端直连
        try {
            return await this.tryClientMode(async () => {
                return await client.parseVideo(url, options);
            });
        } catch (error) {
            console.warn('客户端直连失败，降级到服务端代理:', error);
            this.logFailure('client_failed', error);
            
            // 降级到服务端代理
            return await this.fallbackToServerMode(async () => {
                const serverClient = new BilibiliClient({
                    ...this.config,
                    mode: 'server'
                });
                return await serverClient.parseVideo(url, options);
            });
        }
    }
    
    /**
     * 尝试客户端直连
     * @param {Function} apiCall API 调用函数
     * @returns {Promise<Object>} 响应数据
     */
    async tryClientMode(apiCall) {
        const timeout = this.config.timeout || 10000;
        
        return await Promise.race([
            apiCall(),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('客户端请求超时')), timeout)
            )
        ]);
    }
    
    /**
     * 降级到服务端代理
     * @param {Function} apiCall API 调用函数
     * @returns {Promise<Object>} 响应数据
     */
    async fallbackToServerMode(apiCall) {
        try {
            return await apiCall();
        } catch (error) {
            this.logFailure('server_failed', error);
            throw error;
        }
    }
    
    /**
     * 记录失败原因
     * @param {string} reason 失败原因
     * @param {Error} error 错误对象
     */
    logFailure(reason, error) {
        this.failureLog.push({
            reason: reason,
            error: error.message,
            timestamp: Date.now()
        });
        
        // 只保留最近的失败记录
        if (this.failureLog.length > this.maxFailureLog) {
            this.failureLog.shift();
        }
        
        // 更新推荐模式
        this.updateRecommendedMode();
    }
    
    /**
     * 更新推荐模式
     */
    updateRecommendedMode() {
        // 统计最近的失败情况
        const recentFailures = this.failureLog.filter(log => 
            Date.now() - log.timestamp < 300000  // 5分钟内
        );
        
        const clientFailures = recentFailures.filter(log => 
            log.reason === 'client_failed'
        ).length;
        
        // 如果客户端失败率超过 50%，切换到服务端模式
        if (recentFailures.length >= 4 && clientFailures / recentFailures.length > 0.5) {
            this.recommendedMode = 'server';
            CacheManager.set('recommended_mode', 'server', 300);  // 缓存 5 分钟
        } else {
            this.recommendedMode = 'client';
            CacheManager.delete('recommended_mode');
        }
    }
    
    /**
     * 获取推荐模式
     * @returns {string} 'client' | 'server'
     */
    getRecommendedMode() {
        return this.recommendedMode;
    }
}
