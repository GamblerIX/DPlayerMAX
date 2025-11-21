/**
 * B站 API 调用封装（客户端）
 * 负责客户端 API 调用
 */
class BilibiliAPI {
    /**
     * 获取视频信息
     * @param {string} videoId BV 号或 AV 号
     * @param {string} type 'bv' 或 'av'
     * @returns {Promise<Object>} 视频信息
     */
    static async getVideoInfo(videoId, type = 'bv') {
        // 尝试从缓存获取
        const cacheKey = 'video_' + videoId;
        const cached = CacheManager.get(cacheKey);
        if (cached) {
            return cached;
        }
        
        // 构建请求参数
        const params = new URLSearchParams();
        if (type === 'bv') {
            params.append('bvid', videoId);
        } else {
            params.append('aid', videoId);
        }
        
        const url = 'https://api.bilibili.com/x/web-interface/view?' + params.toString();
        
        // 发送请求
        const data = await this.request(url);
        
        if (!data || data.code !== 0) {
            throw new Error(data?.message || '获取视频信息失败');
        }
        
        const videoInfo = data.data;
        
        // 缓存 2 小时
        CacheManager.set(cacheKey, videoInfo, 7200);
        
        return videoInfo;
    }
    
    /**
     * 获取播放地址
     * @param {string} videoId BV 号或 AV 号
     * @param {number} cid 分P ID
     * @param {string} type 'bv' 或 'av'
     * @param {number} quality 清晰度
     * @returns {Promise<Object>} 播放数据
     */
    static async getPlayUrl(videoId, cid, type = 'bv', quality = 80) {
        // 尝试从缓存获取
        const cacheKey = 'playurl_' + videoId + '_' + cid + '_' + quality;
        const cached = CacheManager.get(cacheKey);
        if (cached) {
            return cached;
        }
        
        // 构建请求参数
        const params = {
            cid: cid,
            qn: quality,
            fnval: 16,  // DASH 格式
            fnver: 0,
            fourk: 1,
            try_look: 1  // 免登录试看
        };
        
        if (type === 'bv') {
            params.bvid = videoId;
        } else {
            params.avid = videoId;
        }
        
        // 使用 WBI 签名
        const signedParams = await WbiSigner.signParams(params);
        
        const queryString = new URLSearchParams(signedParams).toString();
        const url = 'https://api.bilibili.com/x/player/wbi/playurl?' + queryString;
        
        // 发送请求
        const data = await this.request(url);
        
        if (!data || data.code !== 0) {
            throw new Error(data?.message || '获取播放地址失败');
        }
        
        const playData = data.data;
        
        // 缓存 100 分钟
        CacheManager.set(cacheKey, playData, 6000);
        
        return playData;
    }
    
    /**
     * 发送 HTTP 请求
     * @param {string} url 请求 URL
     * @param {Object} options 请求选项
     * @returns {Promise<Object>} 响应数据
     */
    static async request(url, options = {}) {
        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer': 'https://www.bilibili.com',
                    'Accept': 'application/json, text/plain, */*',
                    ...options.headers
                },
                ...options
            });
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API 请求失败:', error);
            throw error;
        }
    }
}
