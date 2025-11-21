<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * B站代理 Action
 * 处理前端的 B 站 API 代理请求
 */
class DPlayerMAX_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 执行 Action
     */
    public function action()
    {
        // 设置响应头
        $this->response->setContentType('application/json');
        
        // 获取请求数据
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            $this->sendJson(['success' => false, 'error' => ['code' => -1, 'message' => '无效的请求数据']]);
        }
        
        $action = isset($data['action']) ? $data['action'] : null;
        $params = isset($data['params']) ? $data['params'] : [];
        
        if (!$action) {
            $this->sendJson(['success' => false, 'error' => ['code' => -1, 'message' => '缺少 action 参数']]);
        }
        
        // 加载代理控制器
        require_once __DIR__ . '/CacheManager.php';
        require_once __DIR__ . '/WbiSigner.php';
        require_once __DIR__ . '/BilibiliAPI.php';
        require_once __DIR__ . '/BilibiliParser.php';
        require_once __DIR__ . '/ProxyController.php';
        
        // 处理请求
        $result = DPlayerMAX_ProxyController::handleRequest($action, $params);
        
        $this->sendJson($result);
    }
    
    /**
     * 发送 JSON 响应
     */
    private function sendJson($data)
    {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
