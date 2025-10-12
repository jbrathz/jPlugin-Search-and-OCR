<?php
/**
 * Rate Limiter Class
 *
 * ป้องกัน spam และ bot attacks โดยจำกัดจำนวน requests ต่อ IP address
 * ใช้ WordPress Transients API สำหรับเก็บข้อมูล
 */

if (!defined('ABSPATH')) {
    exit;
}

class PDFS_Rate_Limiter {

    /**
     * จำกัดจำนวน requests สูงสุดต่อ time window
     * @var int
     */
    private static $max_requests = 20;

    /**
     * ช่วงเวลาในการตรวจสอบ (วินาที)
     * @var int
     */
    private static $time_window = 60;

    /**
     * ตรวจสอบว่า IP นี้ถูก rate limit หรือไม่
     *
     * @return bool true = อนุญาต, false = ถูก rate limit
     */
    public static function check_rate_limit() {
        $ip = self::get_client_ip();

        // ถ้าไม่สามารถดึง IP ได้ ให้ผ่าน (fail open for safety)
        if (empty($ip)) {
            return true;
        }

        $transient_key = 'jsearch_rl_' . md5($ip);

        // ดึงข้อมูลจำนวน requests จาก transient
        $requests = get_transient($transient_key);

        if ($requests === false) {
            // ครั้งแรก - สร้าง transient ใหม่
            set_transient($transient_key, 1, self::$time_window);
            return true;
        }

        if ($requests >= self::$max_requests) {
            // เกินจำกัดแล้ว - บล็อค
            PDFS_Logger::warning('Rate limit exceeded', array(
                'ip' => $ip,
                'requests' => $requests,
                'max_allowed' => self::$max_requests,
            ));
            return false;
        }

        // เพิ่มจำนวน request
        set_transient($transient_key, $requests + 1, self::$time_window);
        return true;
    }

    /**
     * ดึง IP address ของผู้ใช้
     * รองรับการดึง real IP จาก proxy/CDN เช่น Cloudflare
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip = '';

        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Behind proxy/load balancer
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For อาจมีหลาย IP (client, proxy1, proxy2)
            // เอา IP แรกสุด (client's real IP)
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_list[0]);
        }
        // Direct connection
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * ล้างข้อมูล rate limit ของ IP ที่ระบุ (สำหรับ admin/testing)
     *
     * @param string $ip
     * @return bool
     */
    public static function clear_rate_limit($ip = null) {
        if ($ip === null) {
            $ip = self::get_client_ip();
        }

        if (empty($ip)) {
            return false;
        }

        $transient_key = 'jsearch_rl_' . md5($ip);
        return delete_transient($transient_key);
    }

    /**
     * ดึงจำนวน requests ที่เหลือของ IP ปัจจุบัน (สำหรับ debugging)
     *
     * @return array
     */
    public static function get_status() {
        $ip = self::get_client_ip();
        $transient_key = 'jsearch_rl_' . md5($ip);
        $requests = get_transient($transient_key);

        return array(
            'ip' => $ip,
            'current_requests' => $requests !== false ? $requests : 0,
            'max_requests' => self::$max_requests,
            'remaining' => $requests !== false ? self::$max_requests - $requests : self::$max_requests,
            'time_window' => self::$time_window,
        );
    }
}
