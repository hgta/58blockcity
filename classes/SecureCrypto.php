<?php
/**
 * API Key 等敏感值加密工具（AES-256-GCM）
 * change: help-center-ai-assistant (task 1.3)
 *
 * - 密钥文件: config/ai-master.key（64位hex = 32字节，自动生成，不入Git）
 * - 密文格式: base64( iv(12) || tag(16) || cipher )，自带版本前缀 v1:
 * - 用法:
 *     SecureCrypto::encrypt('sk-xxx');           // → 'v1:AAAA...'
 *     SecureCrypto::decrypt($cipher);            // → 'sk-xxx' 或 null
 *     SecureCrypto::mask('sk-xxx');              // → 'sk-••••xxx' 后台掩码展示
 */

class SecureCrypto
{
    const KEY_FILE = __DIR__ . '/../config/ai-master.key';
    const PREFIX   = 'v1:';

    /** @var string|null 32字节主密钥缓存 */
    private static $key = null;

    /** 获取主密钥（不存在则生成） */
    public static function key(): string
    {
        if (self::$key !== null) return self::$key;

        if (is_file(self::KEY_FILE)) {
            $hex = trim(file_get_contents(self::KEY_FILE));
            if (preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
                return self::$key = hex2bin($hex);
            }
            throw new RuntimeException('密钥文件格式非法: ' . self::KEY_FILE);
        }

        // 自动生成（仅首次）
        $hex = bin2hex(random_bytes(32));
        $dir = dirname(self::KEY_FILE);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('无法创建密钥目录: ' . $dir . '（' . self::lastError() . '）');
        }
        if (file_put_contents(self::KEY_FILE, $hex) === false) {
            throw new RuntimeException(
                '无法写入密钥文件: ' . self::KEY_FILE . '（' . self::lastError()
                . '）请检查目录属主/权限（如 www 用户可写），或手动生成该文件'
            );
        }
        chmod(self::KEY_FILE, 0600);
        return self::$key = hex2bin($hex);
    }

    /** 最近一次 PHP 错误信息（用于异常提示） */
    private static function lastError(): string
    {
        $e = error_get_last();
        return ($e['message'] ?? '未知错误') . (isset($e['file']) ? ' @ ' . $e['file'] . ':' . $e['line'] : '');
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new RuntimeException('AES加密失败');
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** 解密失败返回 null（容忍密钥更换/密文损坏） */
    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') return null;
        if (strpos($payload, self::PREFIX) !== 0) return null; // 兼容：非本工具密文不处理
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) return null; // iv12+tag16 最小长度
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /** 后台展示用掩码：保留前3后4 */
    public static function mask(?string $plain): string
    {
        if ($plain === null || $plain === '') return '';
        $len = strlen($plain);
        if ($len <= 8) return str_repeat('•', $len);
        return substr($plain, 0, 3) . str_repeat('•', min(12, $len - 7)) . substr($plain, -4);
    }
}
