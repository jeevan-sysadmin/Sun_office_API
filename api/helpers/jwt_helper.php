<?php
// C:\xampp\htdocs\sun_office\api\helpers\jwt_helper.php

class JWT {
    private static $secret_key = 'sun-office-secret-key-2026-jeevan-larosh';
    private static $algorithm = 'HS256';
    
    public static function encode($payload) {
        // Create header
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];
        
        // Encode header and payload
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            self::$secret_key, 
            true
        );
        
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Return JWT
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    public static function decode($token) {
        try {
            // Split the token
            $parts = explode('.', $token);
            
            if (count($parts) != 3) {
                return false;
            }
            
            list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
            
            // Verify signature
            $signature = self::base64UrlDecode($base64UrlSignature);
            $expectedSignature = hash_hmac('sha256', 
                $base64UrlHeader . "." . $base64UrlPayload, 
                self::$secret_key, 
                true
            );
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            // Decode payload
            $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log("JWT decode error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    private static function base64UrlDecode($data) {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        
        // Add padding if needed
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        
        return base64_decode($data);
    }
    
    public static function validateToken($token) {
        return self::decode($token) !== false;
    }
}
?>