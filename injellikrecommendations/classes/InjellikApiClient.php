<?php
/**
 * Injellik Recommendations for PrestaShop.
 *
 * @author    Web-Nest <contact@web-nest.fr>
 * @copyright Web-Nest
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class InjellikApiException extends Exception
{
    /** @var int HTTP status, 0 when the API could not be reached */
    private $status;

    public function __construct($message, $status = 0)
    {
        parent::__construct($message, (int) $status);
        $this->status = (int) $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Network error, timeout or server error: the same request may succeed later.
     */
    public function isRetryable()
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}

/**
 * HTTP client of the Injellik API (JWT authentication, JSON in and out).
 *
 * The JWT is cached in the configuration until shortly before it expires,
 * so a front request costs a single HTTP call.
 */
class InjellikApiClient
{
    const VERSION = '2.0.0';

    /** @var InjellikApiClient[] */
    private static $instances = [];

    private $baseUrl;
    private $login;
    private $password;
    private $timeout;
    private $idShop;
    private $token;
    private $persistToken;

    public function __construct($baseUrl, $login, $password, $timeout = 3, $idShop = null, $persistToken = true)
    {
        $this->baseUrl = self::normalizeUrl($baseUrl);
        $this->login = (string) $login;
        $this->password = (string) $password;
        $this->timeout = max(1, (int) $timeout);
        $this->idShop = $idShop === null ? null : (int) $idShop;
        $this->persistToken = (bool) $persistToken;
    }

    /**
     * Client built from the configuration of a shop (current shop by default).
     */
    public static function forShop($idShop = null)
    {
        $idShop = $idShop === null ? (int) Context::getContext()->shop->id : (int) $idShop;
        if (!isset(self::$instances[$idShop])) {
            self::$instances[$idShop] = new self(
                InjellikConfig::get(InjellikConfig::API_URL, $idShop),
                InjellikConfig::get(InjellikConfig::API_LOGIN, $idShop),
                InjellikConfig::get(InjellikConfig::API_PASSWORD, $idShop),
                (int) InjellikConfig::get(InjellikConfig::TIMEOUT, $idShop, 3),
                $idShop
            );
        }

        return self::$instances[$idShop];
    }

    public static function reset()
    {
        self::$instances = [];
    }

    /**
     * Accepts "https://host", "https://host/" or "https://host/api" (format of version 1.x).
     */
    public static function normalizeUrl($url)
    {
        $url = rtrim(trim((string) $url), '/');
        if (Tools::strtolower(Tools::substr($url, -4)) === '/api') {
            $url = Tools::substr($url, 0, -4);
        }

        return $url;
    }

    public function isConfigured()
    {
        return $this->baseUrl !== '' && $this->login !== '' && $this->password !== '';
    }

    public function setTimeout($timeout)
    {
        $this->timeout = max(1, (int) $timeout);

        return $this;
    }

    /**
     * Public health probe: {"status": "up", "database": "up"}.
     */
    public function status()
    {
        return $this->send('GET', $this->baseUrl . '/status', [], null);
    }

    /**
     * Authenticates and returns a fresh JWT.
     */
    public function authenticate()
    {
        $response = $this->send('POST', $this->baseUrl . '/api/login', [], ['email' => $this->login, 'password' => $this->password]);
        if (empty($response['token'])) {
            throw new InjellikApiException('The API did not return a token.', 500);
        }
        $this->token = (string) $response['token'];

        if ($this->persistToken) {
            InjellikConfig::set(InjellikConfig::JWT, $this->token, $this->idShop);
            InjellikConfig::set(InjellikConfig::JWT_EXPIRES, self::tokenExpiry($this->token), $this->idShop);
        }

        return $this->token;
    }

    public function get($path, array $query = [])
    {
        return $this->request('GET', $path, $query);
    }

    public function post($path, $body)
    {
        return $this->request('POST', $path, [], $body);
    }

    public function sendAction(array $action)
    {
        return $this->post('/api/actions', $action);
    }

    /**
     * Creates or updates items: {"created": n, "updated": n}.
     */
    public function importItems(array $items)
    {
        return $this->post('/api/items/import', array_values($items));
    }

    /**
     * Authenticated call to /api/*. A 401 triggers one re-authentication (expired or revoked token).
     */
    public function request($method, $path, array $query = [], $body = null)
    {
        if (!$this->isConfigured()) {
            throw new InjellikApiException('The Injellik API is not configured.', 0);
        }

        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&');
        }

        $token = $this->token();
        try {
            return $this->send($method, $url, ['Authorization: Bearer ' . $token], $body);
        } catch (InjellikApiException $e) {
            if ($e->getStatus() !== 401) {
                throw $e;
            }
            $this->authenticate();

            return $this->send($method, $url, ['Authorization: Bearer ' . $this->token], $body);
        }
    }

    private function token()
    {
        if ($this->token === null && $this->persistToken) {
            $expires = (int) InjellikConfig::get(InjellikConfig::JWT_EXPIRES, $this->idShop);
            if ($expires > time()) {
                $this->token = (string) InjellikConfig::get(InjellikConfig::JWT, $this->idShop);
            }
        }

        return $this->token ?: $this->authenticate();
    }

    /**
     * Expiry read from the JWT payload, one minute early; one hour when the payload is unreadable.
     */
    private static function tokenExpiry($jwt)
    {
        $parts = explode('.', $jwt);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (isset($payload['exp'])) {
                return (int) $payload['exp'] - 60;
            }
        }

        return time() + 3600;
    }

    private function send($method, $url, array $headers, $body)
    {
        if (!function_exists('curl_init')) {
            throw new InjellikApiException('The PHP cURL extension is required.', 0);
        }

        $headers[] = 'Accept: application/json';
        $ch = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(2, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'InjellikPrestaShop/' . self::VERSION . ' PrestaShop/' . _PS_VERSION_,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new InjellikApiException(sprintf('Injellik API unreachable (%s): %s', parse_url($url, PHP_URL_HOST), $error), 0);
        }

        $data = $raw === '' ? [] : json_decode($raw, true);
        if ($status >= 400) {
            $message = is_array($data) && isset($data['error']) ? (string) $data['error'] : sprintf('HTTP %d', $status);
            if (is_array($data) && !empty($data['violations'])) {
                $details = [];
                foreach ($data['violations'] as $violation) {
                    $details[] = $violation['field'] . ': ' . $violation['message'];
                }
                $message .= ' (' . implode('; ', $details) . ')';
            }
            throw new InjellikApiException($message, $status);
        }
        if (!is_array($data)) {
            throw new InjellikApiException(sprintf('Invalid JSON response (HTTP %d).', $status), $status ?: 500);
        }

        return $data;
    }
}
