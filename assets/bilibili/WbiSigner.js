/**
 * WBI 签名器（客户端）
 * 实现 B 站的 WBI 签名算法
 */
class WbiSigner {
    /**
     * 混淆密钥编码表
     */
    static mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];
    
    /**
     * 获取混合密钥
     * @param {string} orig 原始密钥
     * @returns {string} 混合密钥
     */
    static getMixinKey(orig) {
        let mixinKey = '';
        for (const index of this.mixinKeyEncTab) {
            if (orig[index]) {
                mixinKey += orig[index];
            }
        }
        return mixinKey.substring(0, 32);
    }
    
    /**
     * WBI 编码
     * @param {Object} params 请求参数
     * @param {string} imgKey img_key
     * @param {string} subKey sub_key
     * @returns {string} 签名后的查询字符串
     */
    static encWbi(params, imgKey, subKey) {
        // 添加时间戳
        params.wts = Math.floor(Date.now() / 1000);
        
        // 过滤特殊字符
        const filteredParams = {};
        for (const key in params) {
            let value = String(params[key]);
            // 过滤 !'()*
            value = value.replace(/[!'()*]/g, '');
            filteredParams[key] = value;
        }
        
        // 按键名排序
        const sortedKeys = Object.keys(filteredParams).sort();
        const queryPairs = sortedKeys.map(key => 
            `${encodeURIComponent(key)}=${encodeURIComponent(filteredParams[key])}`
        );
        const query = queryPairs.join('&');
        
        // 生成混合密钥
        const mixinKey = this.getMixinKey(imgKey + subKey);
        
        // 计算签名（需要 MD5 库）
        const wRid = this.md5(query + mixinKey);
        
        return query + '&w_rid=' + wRid;
    }
    
    /**
     * 对参数进行签名
     * @param {Object} params 请求参数
     * @returns {Promise<Object>} 签名后的参数
     */
    static async signParams(params) {
        const keys = await this.getWbiKeys();
        if (!keys) {
            return params;
        }
        
        const queryString = this.encWbi(params, keys.imgKey, keys.subKey);
        
        // 解析查询字符串回对象
        const signedParams = {};
        queryString.split('&').forEach(pair => {
            const [key, value] = pair.split('=');
            signedParams[decodeURIComponent(key)] = decodeURIComponent(value);
        });
        
        return signedParams;
    }
    
    /**
     * 获取 WBI 密钥（带缓存）
     * @returns {Promise<Object>} {imgKey, subKey}
     */
    static async getWbiKeys() {
        // 尝试从缓存获取
        const cached = CacheManager.get('bilibili_wbi_keys');
        if (cached) {
            return cached;
        }
        
        try {
            const response = await fetch('https://api.bilibili.com/x/web-interface/nav', {
                headers: {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer': 'https://www.bilibili.com'
                }
            });
            
            const data = await response.json();
            
            if (!data || data.code !== 0) {
                return null;
            }
            
            const wbiImg = data.data.wbi_img;
            const imgUrl = wbiImg.img_url;
            const subUrl = wbiImg.sub_url;
            
            // 提取文件名作为密钥
            const imgMatch = imgUrl.match(/\/([^\/]+)\.png$/);
            const subMatch = subUrl.match(/\/([^\/]+)\.png$/);
            
            if (!imgMatch || !subMatch) {
                return null;
            }
            
            const keys = {
                imgKey: imgMatch[1],
                subKey: subMatch[1]
            };
            
            // 缓存 1 小时
            CacheManager.set('bilibili_wbi_keys', keys, 3600);
            
            return keys;
        } catch (error) {
            console.error('获取 WBI 密钥失败:', error);
            return null;
        }
    }
    
    /**
     * MD5 哈希函数
     * @param {string} string 输入字符串
     * @returns {string} MD5 哈希值
     */
    static md5(string) {
        // 简化的 MD5 实现
        function rotateLeft(value, shift) {
            return (value << shift) | (value >>> (32 - shift));
        }
        
        function addUnsigned(x, y) {
            const lsw = (x & 0xFFFF) + (y & 0xFFFF);
            const msw = (x >> 16) + (y >> 16) + (lsw >> 16);
            return (msw << 16) | (lsw & 0xFFFF);
        }
        
        function md5cmn(q, a, b, x, s, t) {
            return addUnsigned(rotateLeft(addUnsigned(addUnsigned(a, q), addUnsigned(x, t)), s), b);
        }
        
        function md5ff(a, b, c, d, x, s, t) {
            return md5cmn((b & c) | ((~b) & d), a, b, x, s, t);
        }
        
        function md5gg(a, b, c, d, x, s, t) {
            return md5cmn((b & d) | (c & (~d)), a, b, x, s, t);
        }
        
        function md5hh(a, b, c, d, x, s, t) {
            return md5cmn(b ^ c ^ d, a, b, x, s, t);
        }
        
        function md5ii(a, b, c, d, x, s, t) {
            return md5cmn(c ^ (b | (~d)), a, b, x, s, t);
        }
        
        function convertToWordArray(string) {
            const wordArray = [];
            for (let i = 0; i < string.length * 8; i += 8) {
                wordArray[i >> 5] |= (string.charCodeAt(i / 8) & 0xFF) << (i % 32);
            }
            return wordArray;
        }
        
        function utf8Encode(string) {
            return unescape(encodeURIComponent(string));
        }
        
        const x = convertToWordArray(utf8Encode(string));
        let a = 0x67452301;
        let b = 0xEFCDAB89;
        let c = 0x98BADCFE;
        let d = 0x10325476;
        
        x[string.length * 8 >> 5] |= 0x80 << (string.length * 8 % 32);
        x[(((string.length * 8 + 64) >>> 9) << 4) + 14] = string.length * 8;
        
        for (let i = 0; i < x.length; i += 16) {
            const olda = a;
            const oldb = b;
            const oldc = c;
            const oldd = d;
            
            a = md5ff(a, b, c, d, x[i], 7, 0xD76AA478);
            d = md5ff(d, a, b, c, x[i + 1], 12, 0xE8C7B756);
            c = md5ff(c, d, a, b, x[i + 2], 17, 0x242070DB);
            b = md5ff(b, c, d, a, x[i + 3], 22, 0xC1BDCEEE);
            a = md5ff(a, b, c, d, x[i + 4], 7, 0xF57C0FAF);
            d = md5ff(d, a, b, c, x[i + 5], 12, 0x4787C62A);
            c = md5ff(c, d, a, b, x[i + 6], 17, 0xA8304613);
            b = md5ff(b, c, d, a, x[i + 7], 22, 0xFD469501);
            a = md5ff(a, b, c, d, x[i + 8], 7, 0x698098D8);
            d = md5ff(d, a, b, c, x[i + 9], 12, 0x8B44F7AF);
            c = md5ff(c, d, a, b, x[i + 10], 17, 0xFFFF5BB1);
            b = md5ff(b, c, d, a, x[i + 11], 22, 0x895CD7BE);
            a = md5ff(a, b, c, d, x[i + 12], 7, 0x6B901122);
            d = md5ff(d, a, b, c, x[i + 13], 12, 0xFD987193);
            c = md5ff(c, d, a, b, x[i + 14], 17, 0xA679438E);
            b = md5ff(b, c, d, a, x[i + 15], 22, 0x49B40821);
            
            a = md5gg(a, b, c, d, x[i + 1], 5, 0xF61E2562);
            d = md5gg(d, a, b, c, x[i + 6], 9, 0xC040B340);
            c = md5gg(c, d, a, b, x[i + 11], 14, 0x265E5A51);
            b = md5gg(b, c, d, a, x[i], 20, 0xE9B6C7AA);
            a = md5gg(a, b, c, d, x[i + 5], 5, 0xD62F105D);
            d = md5gg(d, a, b, c, x[i + 10], 9, 0x02441453);
            c = md5gg(c, d, a, b, x[i + 15], 14, 0xD8A1E681);
            b = md5gg(b, c, d, a, x[i + 4], 20, 0xE7D3FBC8);
            a = md5gg(a, b, c, d, x[i + 9], 5, 0x21E1CDE6);
            d = md5gg(d, a, b, c, x[i + 14], 9, 0xC33707D6);
            c = md5gg(c, d, a, b, x[i + 3], 14, 0xF4D50D87);
            b = md5gg(b, c, d, a, x[i + 8], 20, 0x455A14ED);
            a = md5gg(a, b, c, d, x[i + 13], 5, 0xA9E3E905);
            d = md5gg(d, a, b, c, x[i + 2], 9, 0xFCEFA3F8);
            c = md5gg(c, d, a, b, x[i + 7], 14, 0x676F02D9);
            b = md5gg(b, c, d, a, x[i + 12], 20, 0x8D2A4C8A);
            
            a = md5hh(a, b, c, d, x[i + 5], 4, 0xFFFA3942);
            d = md5hh(d, a, b, c, x[i + 8], 11, 0x8771F681);
            c = md5hh(c, d, a, b, x[i + 11], 16, 0x6D9D6122);
            b = md5hh(b, c, d, a, x[i + 14], 23, 0xFDE5380C);
            a = md5hh(a, b, c, d, x[i + 1], 4, 0xA4BEEA44);
            d = md5hh(d, a, b, c, x[i + 4], 11, 0x4BDECFA9);
            c = md5hh(c, d, a, b, x[i + 7], 16, 0xF6BB4B60);
            b = md5hh(b, c, d, a, x[i + 10], 23, 0xBEBFBC70);
            a = md5hh(a, b, c, d, x[i + 13], 4, 0x289B7EC6);
            d = md5hh(d, a, b, c, x[i], 11, 0xEAA127FA);
            c = md5hh(c, d, a, b, x[i + 3], 16, 0xD4EF3085);
            b = md5hh(b, c, d, a, x[i + 6], 23, 0x04881D05);
            a = md5hh(a, b, c, d, x[i + 9], 4, 0xD9D4D039);
            d = md5hh(d, a, b, c, x[i + 12], 11, 0xE6DB99E5);
            c = md5hh(c, d, a, b, x[i + 15], 16, 0x1FA27CF8);
            b = md5hh(b, c, d, a, x[i + 2], 23, 0xC4AC5665);
            
            a = md5ii(a, b, c, d, x[i], 6, 0xF4292244);
            d = md5ii(d, a, b, c, x[i + 7], 10, 0x432AFF97);
            c = md5ii(c, d, a, b, x[i + 14], 15, 0xAB9423A7);
            b = md5ii(b, c, d, a, x[i + 5], 21, 0xFC93A039);
            a = md5ii(a, b, c, d, x[i + 12], 6, 0x655B59C3);
            d = md5ii(d, a, b, c, x[i + 3], 10, 0x8F0CCC92);
            c = md5ii(c, d, a, b, x[i + 10], 15, 0xFFEFF47D);
            b = md5ii(b, c, d, a, x[i + 1], 21, 0x85845DD1);
            a = md5ii(a, b, c, d, x[i + 8], 6, 0x6FA87E4F);
            d = md5ii(d, a, b, c, x[i + 15], 10, 0xFE2CE6E0);
            c = md5ii(c, d, a, b, x[i + 6], 15, 0xA3014314);
            b = md5ii(b, c, d, a, x[i + 13], 21, 0x4E0811A1);
            a = md5ii(a, b, c, d, x[i + 4], 6, 0xF7537E82);
            d = md5ii(d, a, b, c, x[i + 11], 10, 0xBD3AF235);
            c = md5ii(c, d, a, b, x[i + 2], 15, 0x2AD7D2BB);
            b = md5ii(b, c, d, a, x[i + 9], 21, 0xEB86D391);
            
            a = addUnsigned(a, olda);
            b = addUnsigned(b, oldb);
            c = addUnsigned(c, oldc);
            d = addUnsigned(d, oldd);
        }
        
        function wordToHex(value) {
            let hex = '';
            for (let i = 0; i < 4; i++) {
                hex += ((value >> (i * 8)) & 0xFF).toString(16).padStart(2, '0');
            }
            return hex;
        }
        
        return wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);
    }
}
