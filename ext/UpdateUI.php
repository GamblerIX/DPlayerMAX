<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * DPlayerMAX 更新界面渲染器
 * 负责更新检查和更新UI的渲染
 */
class DPlayerMAX_UpdateUI
{
    /**
     * 渲染更新状态组件
     */
    public static function render()
    {
        $cssUrl = Helper::options()->pluginUrl . '/DPlayerMAX/plugin/update-widget.css';
        
        $html = '<link rel="stylesheet" href="' . $cssUrl . '" />';
        $html .= '<div class="dplayermax-update-widget">';
        $html .= '<div class="update-header">';
        $html .= '<h3>插件更新状态<span class="dplayermax-status-light status-not-checked"></span></h3>';
        $html .= '</div>';
        $html .= '<div class="version-info"><p><strong>当前版本:</strong> 1.2.0</p></div>';
        $html .= '<div class="update-status"><p class="status-message">点击"检查更新"按钮来检查新版本</p></div>';
        $html .= '<div class="update-actions">';
        $html .= '<button type="button" id="dplayermax-check-update-btn" class="btn">检查更新</button>';
        $html .= '<button type="button" id="dplayermax-perform-update-btn" class="btn primary" style="display:none;">立即更新</button>';
        $html .= '<a id="dplayermax-release-link" href="https://github.com/GamblerIX/DPlayerMAX/tree/main/Changelog" target="_blank" class="btn" style="display:none;">查看更新日志</a>';
        $html .= '<span id="dplayermax-update-status" style="margin-left:10px;"></span>';
        $html .= '</div></div>';
        $html .= self::renderScript();
        
        return $html;
    }

    /**
     * 渲染更新检查脚本
     */
    private static function renderScript()
    {
        return <<<'JS'
<script>
(function(){
    var checkBtn=document.getElementById('dplayermax-check-update-btn');
    var performBtn=document.getElementById('dplayermax-perform-update-btn');
    var releaseLink=document.getElementById('dplayermax-release-link');
    var statusSpan=document.getElementById('dplayermax-update-status');
    var statusLight=document.querySelector('.dplayermax-status-light');
    var lastClick=0;
    
    function updateLight(s){
        if(!statusLight)return;
        statusLight.className='dplayermax-status-light status-'+s;
    }
    
    function doFetch(action,onSuccess){
        var fd=new FormData();
        fd.append('dplayermax_action',action);
        fetch(window.location.href,{method:'POST',body:fd})
            .then(function(r){
                if(!r.ok)throw new Error('请求失败');
                var ct=r.headers.get('content-type');
                if(!ct||ct.indexOf('application/json')===-1)throw new Error('响应格式错误');
                return r.json();
            })
            .then(onSuccess)
            .catch(function(e){
                statusSpan.textContent='✗ '+e.message;
                checkBtn.disabled=false;
                checkBtn.textContent='检查更新';
                performBtn.disabled=false;
                performBtn.textContent='立即更新';
            });
    }
    
    if(checkBtn){
        checkBtn.addEventListener('click',function(){
            var now=Date.now();
            if(now-lastClick<2000)return;
            lastClick=now;
            checkBtn.disabled=true;
            checkBtn.textContent='检查中...';
            statusSpan.innerHTML='<span class="loading-spinner"></span>';
            
            doFetch('check',function(d){
                checkBtn.disabled=false;
                checkBtn.textContent='检查更新';
                if(d.success===false){
                    statusSpan.innerHTML='<span style="color:red;">✗ '+d.message+'</span>';
                    updateLight('error');
                }else if(d.hasUpdate){
                    statusSpan.innerHTML='<span style="color:orange;">⚠ '+d.message+'</span>';
                    updateLight('update-available');
                    performBtn.style.display='inline-block';
                    releaseLink.style.display='inline-block';
                }else{
                    statusSpan.innerHTML='<span style="color:green;">✓ '+d.message+'</span>';
                    updateLight('up-to-date');
                    performBtn.style.display='none';
                    releaseLink.style.display='none';
                }
            });
        });
    }
    
    if(performBtn){
        performBtn.addEventListener('click',function(){
            if(!confirm('确定要更新插件吗？\n\n建议在更新前手动备份重要数据。'))return;
            performBtn.disabled=true;
            performBtn.textContent='更新中...';
            statusSpan.innerHTML='<span class="loading-spinner"></span> 正在更新...';
            
            doFetch('perform',function(d){
                if(d.success){
                    statusSpan.textContent='✓ '+d.message;
                    setTimeout(function(){location.reload()},2000);
                }else{
                    statusSpan.textContent='✗ '+d.message;
                    performBtn.disabled=false;
                    performBtn.textContent='立即更新';
                }
            });
        });
    }
})();
</script>
JS;
    }
}
