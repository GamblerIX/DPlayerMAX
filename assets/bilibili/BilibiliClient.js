/**
 * B站客户端解析器
 * 客户端解析入口，根据配置选择解析模式
 */
class BilibiliClient {
    /**
     * 初始化客户端
     * @param {Object} config 配置对象
     * @param {string} config.mode 'server' | 'client' | 'hybrid'
     * @param {string} config.proxyUrl 服务端代理 URL
     * @param {number} config.timeout 超时时间（毫秒）
     */
    constructor(config) {
        this.config = {
            mode: 'server',
            proxyUrl: '',
            timeout: 10000,
            quality: 80,
            ...config
        };
        
        if (this.config.mode === 'hybrid') {
            this.hybridController = new HybridController(this.config);
        }
    }
    
    /**
     * 解析 B 站链接
     * @param {string} url B 站视频链接
     * @returns {Object|null} 解析结果
     */
    parseUrl(url) {
        const result = {
            type: null,
            id: null,
            page: 1,
            time: 0
        };
        
        // 提取 BV 号
        const bvMatch = url.match(/BV([a-zA-Z0-9]+)/);
        if (bvMatch) {
            result.type = 'bv';
            result.id = 'BV' + bvMatch[1];
        }
        // 提取 AV 号
        else {
            const avMatch = url.match(/av(\d+)/);
            if (avMatch) {
                result.type = 'av';
                result.id = avMatch[1];
            } else {
                return null;
            }
        }
        
        // 解析分P参数
        const pageMatch = url.match(/[?&]p=(\d+)/);
        if (pageMatch) {
            result.page = parseInt(pageMatch[1]);
        }
        
        // 解析时间戳参数
        const timeMatch = url.match(/[?&]t=(\d+)/);
        if (timeMatch) {
            result.time = parseInt(timeMatch[1]);
        }
        
        return result;
    }
    
    /**
     * 解析 B 站视频
     * @param {string} url B 站视频链接
     * @param {Object} options 选项
     * @returns {Promise<Object>} 视频数据
     */
    async parseVideo(url, options = {}) {
        const parsed = this.parseUrl(url);
        if (!parsed) {
            throw new Error('无效的 B 站链接');
        }
        
        const quality = options.quality || this.config.quality;
        const page = options.page || parsed.page;
        
        // 根据模式选择解析方式
        if (this.config.mode === 'server') {
            return await this.parseVideoByServer(url, { quality, page });
        } else if (this.config.mode === 'client') {
            return await this.parseVideoByClient(parsed, { quality, page });
        } else {
            return await this.hybridController.getVideoData(url, { quality, page });
        }
    }
    
    /**
     * 通过服务端代理解析
     * @param {string} url B 站视频链接
     * @param {Object} options 选项
     * @returns {Promise<Object>} 视频数据
     */
    async parseVideoByServer(url, options) {
        const response = await fetch(this.config.proxyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'parse_video',
                url: url,
                options: options
            })
        });
        
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error?.message || '解析失败');
        }
        
        return data.data;
    }
    
    /**
     * 通过客户端直连解析
     * @param {Object} parsed 解析后的链接信息
     * @param {Object} options 选项
     * @returns {Promise<Object>} 视频数据
     */
    async parseVideoByClient(parsed, options) {
        // 获取视频信息
        const videoInfo = await this.getVideoInfo(parsed.id, parsed.type);
        
        // 获取对应分P的 CID
        let cid = null;
        if (videoInfo.pages && Array.isArray(videoInfo.pages)) {
            for (const p of videoInfo.pages) {
                if (p.page === options.page) {
                    cid = p.cid;
                    break;
                }
            }
        }
        
        if (!cid && videoInfo.cid) {
            cid = videoInfo.cid;
        }
        
        if (!cid) {
            throw new Error('无法获取视频 CID');
        }
        
        // 获取播放地址
        const playData = await this.getPlayUrl(parsed.id, cid, parsed.type, options.quality);
        
        return {
            video_info: videoInfo,
            play_data: playData,
            parsed: parsed,
            page: options.page,
            cid: cid
        };
    }
    
    /**
     * 获取视频信息
     * @param {string} videoId BV 号或 AV 号
     * @param {string} type 'bv' 或 'av'
     * @returns {Promise<Object>} 视频信息
     */
    async getVideoInfo(videoId, type = 'bv') {
        return await BilibiliAPI.getVideoInfo(videoId, type);
    }
    
    /**
     * 获取播放地址
     * @param {string} videoId BV 号或 AV 号
     * @param {number} cid 分P ID
     * @param {string} type 'bv' 或 'av'
     * @param {number} quality 清晰度
     * @returns {Promise<Object>} 播放数据
     */
    async getPlayUrl(videoId, cid, type = 'bv', quality = 80) {
        return await BilibiliAPI.getPlayUrl(videoId, cid, type, quality);
    }
}
