<?php

class InjellikApi {

    private static $token = null;
    private static $tokenExpiry = null;

    /**
     * Get the base URL of the Injellik API from module configuration.
     *
     * @return string
     */
    private static function getApiUrl() {
        return Configuration::get('INJELLIK_API_URL');
    }

    /**
     * Authenticate with the Injellik API and retrieve a JWT token.
     *
     * @throws Exception If authentication fails
     */
    private static function authenticate() {
        $url = self::getApiUrl() . '/login';
        $loginData = [
            'email' => Configuration::get('INJELLIK_API_LOGIN'),
            'password' => Configuration::get('INJELLIK_API_PASSWORD'),
        ];

        $response = self::makeApiCall($url, $loginData, 'POST', false);
        if (!isset($response['token'])) {
            throw new Exception('Failed to retrieve JWT token from Injellik API.');
        }

        self::$token = $response['token'];
        // Assuming the token is valid for 1 hour; adjust based on your API's actual token expiry
        self::$tokenExpiry = time() + 3600;
    }

    /**
     * Ensure the JWT token is valid. If expired or not set, re-authenticate.
     */
    private static function ensureAuthenticated() {
        if (self::$token === null || time() >= self::$tokenExpiry) {
            self::authenticate();
        }
    }

    /**
     * Send an action to the Injellik API (e.g., VIEW, ADDCART, BUY, RECO).
     *
     * @param string $actionType The type of action to send (e.g., 'VIEW', 'ADDCART', 'BUY', 'RECO')
     * @param int $productId The ID of the product involved in the action
     * @param string|null $sessionId The session ID for the action (optional)
     */
    public static function sendAction($actionType, $productId) {
        self::ensureAuthenticated();
        $url = self::getApiUrl() . '/actions';
        $data = [
            'action_type' => $actionType,
            'item_sku' => $productId,
            'user_uuid' => InjellikSessionManager::getUserUUID(),
            'user_session_uuid' => InjellikSessionManager::getUserSessionUUID()
        ];

        self::makeApiCall($url, $data);
    }

    /**
     * Send product information to the Injellik API.
     *
     * @param int $productId The ID of the product
     * @param string $name The name of the product
     * @param float $price The price of the product
     */
    public static function sendProduct($productId, $name, $price) {
        self::ensureAuthenticated();
        $url = self::getApiUrl() . '/products';
        $data = [
            'item_sku' => $productId,
            'name' => $name,
            'price' => $price,
        ];

        self::makeApiCall($url, $data);
    }

    /**
     * Check the status of the Injellik API.
     *
     * @return bool True if API is up, false otherwise
     */
    public static function getMostViewed($cluster_id = null) {
        self::ensureAuthenticated();
        $url = self::getApiUrl() . '/community-rankings/most-viewed';
        if (!empty($cluster_id)) {
            $url .= "?cluster_id=" . $cluster_id;
        }
        try {
            $response = self::makeApiCall($url, [], 'GET', true);
            return $response;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error checking Injellik API status: ' . $e->getMessage(), 3);
            return false;
        }
    }

    /**
     * Check the status of the Injellik API.
     *
     * @return bool True if API is up, false otherwise
     */
    public static function checkApiStatus() {
        $url = self::getApiUrl() . '/status';
        try {
            $response = self::makeApiCall($url, [], 'GET', false);
            return isset($response['status']) && $response['status'] === 'up';
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error checking Injellik API status: ' . $e->getMessage(), 3);
            return false;
        }
    }

    /**
     * Make a generic API call to Injellik.
     *
     * @param string $url The API endpoint URL
     * @param array $data The data to send in the request
     * @param string $method The HTTP method to use (default 'POST')
     * @param bool $useAuth Whether to include the Authorization header (default true)
     * @return array The API response decoded from JSON
     * @throws Exception If the API call fails
     */
    private static function makeApiCall($url, $data = [], $method = 'POST', $useAuth = true) {
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => $method,
                'content' => json_encode($data),
                'timeout' => 5,
            ],
        ];

        if ($useAuth && self::$token !== null) {
            $options['http']['header'] .= "Authorization: Bearer " . self::$token . "\r\n";
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new Exception("API call to $url failed");
        }

        return json_decode($result, true);
    }
}
