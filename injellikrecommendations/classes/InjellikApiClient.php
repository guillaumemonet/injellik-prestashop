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
    /** Error code of the API when the subscription of the account is suspended (HTTP 403) */
    const ERROR_SUSPENDED = 'tenant_suspended';
    /** Licence key unknown or revoked (HTTP 401 in licence key mode) */
    const ERROR_INVALID_KEY = 'invalid_licence_key';

    /** @var int HTTP status, 0 when the API could not be reached */
    private $status;
    /** @var string|null */
    private $errorCode;

    public function __construct($message, $status = 0, $errorCode = null)
    {
        parent::__construct($message, (int) $status);
        $this->status = (int) $status;
        $this->errorCode = $errorCode === null ? null : (string) $errorCode;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Network error, timeout or server error: the same request may succeed later.
     */
    public function isRetryable()
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }

    public function isSuspended()
    {
        return $this->errorCode === self::ERROR_SUSPENDED;
    }

    public function isInvalidKey()
    {
        return $this->errorCode === self::ERROR_INVALID_KEY;
    }

    /**
     * The API refuses the shop as a whole (suspended subscription, invalid licence key):
     * no request can succeed until the merchant acts.
     */
    public function isAccessDenied()
    {
        return $this->isSuspended() || $this->isInvalidKey();
    }
}

/**
 * HTTP client of the Injellik API (JSON in and out).
 *
 * Two authentication modes:
 * - licence key (default): "X-Api-Key" header on every request, no login;
 * - login and password (accounts created before the subscriptions): JWT from /api/login, cached in the
 *   configuration until shortly before it expires, so that a front request costs a single HTTP call.
 *
 * When the API refuses the shop (403 "tenant_suspended", or 401 with a licence key), the state is stored
 * and the client stops calling the API for InjellikConfig::STATE_RETRY_DELAY seconds.
 */
class InjellikApiClient
{
    const VERSION = '2.0.0';

    /** @var InjellikApiClient[] */
    private static $instances = [];

    private $baseUrl;
    private $login;
    private $password;
    private $licenceKey;
    private $timeout;
    private $idShop;
    private $token;
    private $persist;

    /**
     * @param string $licenceKey when not empty, used instead of the login and the password
     * @param bool $persist false for a client that must not touch the configuration (connection test):
     *                      no cached JWT, no stored API state
     */
    public function __construct($baseUrl, $login, $password, $timeout = 3, $idShop = null, $persist = true, $licenceKey = '')
    {
        $this->baseUrl = self::normalizeUrl($baseUrl);
        $this->login = (string) $login;
        $this->password = (string) $password;
        $this->licenceKey = trim((string) $licenceKey);
        $this->timeout = max(1, (int) $timeout);
        $this->idShop = $idShop === null ? null : (int) $idShop;
        $this->persist = (bool) $persist;
    }

    /**
     * Client built from the configuration of a shop (current shop by default).
     */
    public static function forShop($idShop = null)
    {
        $idShop = $idShop === null ? (int) Context::getContext()->shop->id : (int) $idShop;
        if (!isset(self::$instances[$idShop])) {
            self::$instances[$idShop] = new self(
                InjellikConfig::apiUrl($idShop),
                InjellikConfig::get(InjellikConfig::API_LOGIN, $idShop),
                InjellikConfig::get(InjellikConfig::API_PASSWORD, $idShop),
                (int) InjellikConfig::get(InjellikConfig::TIMEOUT, $idShop, 3),
                $idShop,
                true,
                InjellikConfig::get(InjellikConfig::LICENCE_KEY, $idShop)
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
        return $this->baseUrl !== '' && ($this->usesLicenceKey() || ($this->login !== '' && $this->password !== ''));
    }

    public function usesLicenceKey()
    {
        return $this->licenceKey !== '';
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
     * Authenticates and returns a fresh JWT (login and password mode only: a licence key needs no login).
     */
    public function authenticate()
    {
        if ($this->usesLicenceKey()) {
            throw new InjellikApiException('A licence key does not use /api/login.', 0);
        }
        $response = $this->send('POST', $this->baseUrl . '/api/login', [], ['email' => $this->login, 'password' => $this->password]);
        if (empty($response['token'])) {
            throw new InjellikApiException('The API did not return a token.', 500);
        }
        $this->token = (string) $response['token'];

        if ($this->persist) {
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
     * Authenticated call to /api/*.
     *
     * Licence key: "X-Api-Key" header, a 401 means that the key is invalid or revoked (no retry).
     * Login and password: a 401 triggers one re-authentication (expired or revoked JWT).
     * A shop refused by the API is not called again before InjellikConfig::STATE_RETRY_DELAY seconds;
     * the first successful call clears the state.
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

        $state = $this->persist ? InjellikConfig::apiState($this->idShop) : null;
        if ($state) {
            if (InjellikConfig::isApiBlocked($this->idShop)) {
                throw self::refusal($state['reason']);
            }
            // This request is the new attempt: the concurrent ones keep waiting.
            InjellikConfig::touchApiState($this->idShop);
        }

        try {
            $data = $this->usesLicenceKey() ? $this->sendWithKey($method, $url, $body) : $this->sendWithJwt($method, $url, $body);
        } catch (InjellikApiException $e) {
            if ($this->persist && $e->isAccessDenied()) {
                InjellikConfig::setApiState($e->isSuspended() ? InjellikConfig::STATE_SUSPENDED : InjellikConfig::STATE_INVALID_KEY, $this->idShop);
            }
            throw $e;
        }

        if ($state) {
            InjellikConfig::clearApiState($this->idShop);
        }

        return $data;
    }

    private function sendWithKey($method, $url, $body)
    {
        try {
            return $this->send($method, $url, ['X-Api-Key: ' . $this->licenceKey], $body);
        } catch (InjellikApiException $e) {
            if ($e->getStatus() === 401) {
                throw new InjellikApiException($e->getMessage(), 401, InjellikApiException::ERROR_INVALID_KEY);
            }
            throw $e;
        }
    }

    private function sendWithJwt($method, $url, $body)
    {
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

    /**
     * Exception thrown without calling the API while it refuses the shop.
     */
    private static function refusal($reason)
    {
        return $reason === InjellikConfig::STATE_SUSPENDED
            ? new InjellikApiException(InjellikApiException::ERROR_SUSPENDED, 403, InjellikApiException::ERROR_SUSPENDED)
            : new InjellikApiException('Invalid or revoked licence key.', 401, InjellikApiException::ERROR_INVALID_KEY);
    }

    private function token()
    {
        if ($this->token === null && $this->persist) {
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
            $error = is_array($data) && isset($data['error']) && is_scalar($data['error']) ? (string) $data['error'] : null;
            $message = $error !== null ? $error : sprintf('HTTP %d', $status);
            if (is_array($data) && !empty($data['violations'])) {
                $details = [];
                foreach ($data['violations'] as $violation) {
                    $details[] = $violation['field'] . ': ' . $violation['message'];
                }
                $message .= ' (' . implode('; ', $details) . ')';
            }
            $suspended = $status === 403 && $error === InjellikApiException::ERROR_SUSPENDED;
            throw new InjellikApiException($message, $status, $suspended ? $error : null);
        }
        if (!is_array($data)) {
            throw new InjellikApiException(sprintf('Invalid JSON response (HTTP %d).', $status), $status ?: 500);
        }

        return $data;
    }
}
