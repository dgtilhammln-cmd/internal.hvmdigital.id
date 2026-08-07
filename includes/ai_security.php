<?php
class AISecurity {
    private $blocked_patterns = [
        '/password|katasandi|login|credential/i',
        '/admin.?privilege|super.?admin.?access/i',
        '/bypass|hack|exploit|injection/i',
        '/financial.?data|salary|gaji|omzet.?details/i',
        '/contract.?value|client.?pricing/i',
        '/delete.?data|drop.?table|truncate/i',
        '/system.?file|config.?access/i',
        '/sex|porn|nude|explicit/i',
        '/hate|racist|discriminat/i',
        '/illegal|black.?market/i'
    ];
    
    private $allowed_mime_types = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp'
    ];
    
    public function isManipulativePrompt($message) {
        // Check for manipulative patterns
        foreach($this->blocked_patterns as $pattern) {
            if(preg_match($pattern, $message)) {
                return true;
            }
        }
        
        // Check for social engineering attempts
        if($this->isSocialEngineering($message)) {
            return true;
        }
        
        // Check for prompt injection attempts
        if($this->isPromptInjection($message)) {
            return true;
        }
        
        return false;
    }
    
    private function isSocialEngineering($message) {
        $red_flags = [
            '/please.?ignore.?previous.?instructions/i',
            '/act.?as.?if.?you.?are/i',
            '/disregard.?your.?guidelines/i',
            '/forget.?what.?i.?said/i',
            '/from.?now.?on.?you.?are/i',
            '/role.?play.?as/i',
            '/pretend.?to.?be/i'
        ];
        
        foreach($red_flags as $pattern) {
            if(preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isPromptInjection($message) {
        $injection_patterns = [
            '/system.?prompt|instructions.?override/i',
            '/ignore.?above|ignore.?all/i',
            '/new.?instructions.?are/i',
            '/your.?real.?purpose/i',
            '/developer.?mode/i'
        ];
        
        foreach($injection_patterns as $pattern) {
            if(preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function isValidFile($mime_type) {
        return in_array($mime_type, $this->allowed_mime_types);
    }
    
    public function checkRateLimit($user_identifier) {
        // Implement rate limiting logic
        // Example: max 60 requests per minute
        $minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        
        // In real implementation, query database for request count
        // For now, return true (no rate limiting)
        return true;
    }
    
    public function logInteraction($user_id, $message, $mode) {
        // Log to database for audit trail
        global $conn;
        if($conn && $user_id) {
            $safe_message = mysqli_real_escape_string($conn, substr($message, 0, 500));
            mysqli_query($conn, 
                "INSERT INTO security_logs (user_id, activity_type, details, created_at)
                 VALUES ('$user_id', 'ai_query', '$safe_message [$mode]', NOW())");
        }
    }
}
?>