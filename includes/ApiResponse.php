<?php
/**
 * ApiResponse 类 - 统一API响应格式
 *
 * 提供标准化的JSON响应格式，确保所有API返回一致的数据结构
 *
 * @package includes
 */
class ApiResponse
{
    /**
     * 发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @return void
     */
    public static function success($data = null, string $message = 'Success'): void
    {
        self::sendResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param int $httpCode HTTP状态码
     * @param mixed $data 附加数据（可选）
     * @return void
     */
    public static function error(string $message, int $httpCode = 400, $data = null): void
    {
        http_response_code($httpCode);
        $response = [
            'success' => false,
            'message' => $message
        ];
        if ($data !== null) {
            $response['data'] = $data;
        }
        self::sendResponse($response);
    }

    /**
     * 验证请求方法
     *
     * @param string $expectedMethod 期望的HTTP方法 (POST, GET等)
     * @return bool 如果方法匹配返回true，否则发送错误响应并终止
     */
    public static function requireMethod(string $expectedMethod): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($expectedMethod)) {
            self::error('Invalid request method', 405);
            return false;
        }
        return true;
    }

    /**
     * 验证必需参数
     *
     * @param array $params 需要验证的参数数组 ['param_name' => $value, ...]
     * @return bool 如果所有参数都存在返回true，否则发送错误响应并终止
     */
    public static function requireParams(array $params): bool
    {
        $missing = [];
        foreach ($params as $name => $value) {
            if ($value === null || $value === '') {
                $missing[] = $name;
            }
        }
        if (!empty($missing)) {
            self::error('Missing required parameters: ' . implode(', ', $missing), 400);
            return false;
        }
        return true;
    }

    /**
     * 发送JSON响应并终止脚本
     *
     * @param array $response 响应数据
     * @return void
     */
    private static function sendResponse(array $response): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 【新增】验证用户登录状态（API专用，返回JSON而不是重定向）
     *
     * @return bool 如果已登录返回true，否则发送JSON错误响应并终止
     */
    public static function requireLogin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            self::error('Please login to access this resource', 401);
            return false;
        }
        return true;
    }

    /**
     * 【新增】验证用户角色（API专用，返回JSON而不是重定向）
     *
     * @param string|array $allowedRoles 允许的角色
     * @return bool 如果角色匹配返回true，否则发送JSON错误响应并终止
     */
    public static function requireRole($allowedRoles): bool
    {
        if (!self::requireLogin()) {
            return false;
        }

        $currentUserRole = $_SESSION['role'] ?? '';

        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        if (!in_array($currentUserRole, $allowedRoles)) {
            error_log("API Access Denied: User ID {$_SESSION['user_id']} with role {$currentUserRole} tried to access " . $_SERVER['PHP_SELF']);
            self::error('Access denied: insufficient permissions', 403);
            return false;
        }
        return true;
    }

    /**
     * 包装异常处理的API执行
     *
     * @param callable $callback API逻辑回调函数
     * @param string $errorPrefix 错误日志前缀
     * @return void
     */
    public static function handle(callable $callback, string $errorPrefix = 'API Error'): void
    {
        try {
            $callback();
        } catch (PDOException $e) {
            error_log("$errorPrefix (Database): " . $e->getMessage());
            self::error('Database error', 500);
        } catch (Exception $e) {
            error_log("$errorPrefix: " . $e->getMessage());
            self::error('Server error', 500);
        }
    }
}
