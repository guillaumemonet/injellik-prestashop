<?php

class InjellikSessionManager {

    private static $sessionDuration = 86400; // 24 heures en secondes

    /**
     * Get or create a session UUID for the current customer.
     * 
     * @return string The session UUID
     */
    public static function getUserSessionUUID() {
        if (!self::getCookie('injellik_user_session_uuid') || self::isSessionExpired()) {
            self::createNewSession();
        }
        return self::getCookie('injellik_user_session_uuid');
    }

    /**
     * Get or create a user UUID for the current customer.
     * 
     * @return string The user UUID
     */
    public static function getUserUUID() {
        if (!self::getCookie('injellik_user_uuid')) {
            self::createNewUser();
        }
        return self::getCookie('injellik_user_uuid');
    }

    /**
     * Check if the current session is expired.
     * 
     * @return bool True if the session is expired, false otherwise
     */
    private static function isSessionExpired() {
        $expiryTime = self::getCookie('injellik_session_expiry');
        return $expiryTime && time() > $expiryTime;
    }

    /**
     * Create a new session and set the expiry time.
     */
    private static function createNewSession() {
        self::setCookie('injellik_user_session_uuid', uniqid('session_', true));
        self::setCookie('injellik_session_expiry', time() + self::$sessionDuration);
    }

    /**
     * Create a new user UUID.
     */
    private static function createNewUser() {
        self::setCookie('injellik_user_uuid', uniqid('user_', true));
    }

    /**
     * End the session when an order is placed.
     */
    public static function endSession() {
        self::unsetCookie('injellik_user_session_uuid');
        self::unsetCookie('injellik_session_expiry');
    }

    /**
     * Helper to get a cookie value.
     */
    private static function getCookie($name) {
        return Context::getContext()->cookie->$name;
    }

    /**
     * Helper to set a cookie value.
     */
    private static function setCookie($name, $value) {
        Context::getContext()->cookie->$name = $value;
        Context::getContext()->cookie->write();
    }

    /**
     * Helper to unset a cookie value.
     */
    private static function unsetCookie($name) {
        unset(Context::getContext()->cookie->$name);
        Context::getContext()->cookie->write();
    }
}
