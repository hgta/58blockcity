<?php
class System {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // 获取所有系统设置
    public function getSystemSettings() {
        $stmt = $this->pdo->query("SELECT * FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
    
    // 更新系统设置
    public function updateSettings($data) {
        $this->pdo->beginTransaction();
        try {
            foreach ($data as $key => $value) {
                $stmt = $this->pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                           VALUES (?, ?) 
                                           ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // 发送邮件
    public function sendEmail($to, $subject, $message) {
        $settings = $this->getSystemSettings();
        
        if (empty($settings['smtp_host']) || empty($settings['smtp_username'])) {
            return false;
        }
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // 服务器设置
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'];
            $mail->Password = $settings['smtp_password'];
            $mail->SMTPSecure = $settings['smtp_secure'] ?? 'ssl';
            $mail->Port = $settings['smtp_port'] ?? 465;
            
            // 收件人
            $mail->setFrom($settings['email_from'], $settings['email_from_name']);
            $mail->addAddress($to);
            
            // 内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("发送邮件失败: " . $e->getMessage());
            return false;
        }
    }
}
?>