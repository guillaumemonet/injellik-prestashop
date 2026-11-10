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

/**
 * Visitor identity sent with every action.
 *
 * - user_uuid: derived from the customer id when logged in (stable across devices and
 *   consistent with the order history import), otherwise a random id kept in the cookie.
 * - user_session_uuid: a browsing session, renewed after 30 minutes of inactivity and after
 *   each order. The API builds its "baskets" from sessions.
 */
class InjellikIdentity
{
    const COOKIE_VISITOR = 'injellik_vid';
    const COOKIE_SESSION = 'injellik_sid';
    const COOKIE_SEEN = 'injellik_seen';
    const SESSION_IDLE = 1800;

    /** @var bool the visitor id has been created by the current request */
    private static $newVisitor = false;

    public static function userUuid(?Context $context = null)
    {
        $context = $context ?: Context::getContext();
        if ($context->customer && $context->customer->id && $context->customer->isLogged()) {
            return self::customerUuid((int) $context->customer->id);
        }

        $cookie = $context->cookie;
        $visitor = $cookie ? (string) $cookie->{self::COOKIE_VISITOR} : '';
        if ($visitor === '') {
            $visitor = 'v-' . self::uuid();
            self::$newVisitor = true;
            self::write($context, [self::COOKIE_VISITOR => $visitor]);
        }

        return $visitor;
    }

    /**
     * Stable pseudonymous id of a customer: no personal data leaves the shop.
     */
    public static function customerUuid($idCustomer)
    {
        return 'c-' . Tools::substr(hash('sha256', _COOKIE_KEY_ . '|customer|' . (int) $idCustomer), 0, 32);
    }

    public static function sessionUuid(?Context $context = null)
    {
        $context = $context ?: Context::getContext();
        $cookie = $context->cookie;
        $session = $cookie ? (string) $cookie->{self::COOKIE_SESSION} : '';
        $seen = $cookie ? (int) $cookie->{self::COOKIE_SEEN} : 0;

        if ($session === '' || $seen < time() - self::SESSION_IDLE) {
            $session = 's-' . self::uuid();
            self::write($context, [self::COOKIE_SESSION => $session, self::COOKIE_SEEN => time()]);
        }

        return $session;
    }

    /**
     * True when the current request comes from a visitor whose browsing session is known
     * (false for a payment notification sent by a server, a back-office order...).
     */
    public static function hasSession(?Context $context = null)
    {
        $context = $context ?: Context::getContext();

        return $context->cookie && (string) $context->cookie->{self::COOKIE_SESSION} !== '';
    }

    /**
     * True when the visitor was already known before this request (logged-in customer, or visitor cookie sent
     * by the browser). A request without cookie, such as a script replaying a request, is not a visit.
     */
    public static function isKnownVisitor(?Context $context = null)
    {
        $context = $context ?: Context::getContext();
        if ($context->customer && $context->customer->id && $context->customer->isLogged()) {
            return true;
        }

        return !self::$newVisitor && $context->cookie && (string) $context->cookie->{self::COOKIE_VISITOR} !== '';
    }

    /**
     * Creates the identifiers and extends the session. Call it before any output so that the cookie can be sent.
     */
    public static function touch(?Context $context = null)
    {
        $context = $context ?: Context::getContext();
        self::userUuid($context);
        self::sessionUuid($context);
        if ($context->cookie && (int) $context->cookie->{self::COOKIE_SEEN} < time() - 60) {
            self::write($context, [self::COOKIE_SEEN => time()]);
        }
    }

    /**
     * Starts a new session (after an order: the next purchases belong to another basket).
     */
    public static function resetSession(?Context $context = null)
    {
        $context = $context ?: Context::getContext();
        self::write($context, [self::COOKIE_SESSION => 's-' . self::uuid(), self::COOKIE_SEEN => time()]);
    }

    public static function isBot()
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match('/bot|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|lighthouse|pagespeed|headless|phantomjs|curl|wget|python-requests|httpclient/i', $userAgent);
    }

    private static function write(Context $context, array $values)
    {
        if (!$context->cookie) {
            return;
        }
        foreach ($values as $name => $value) {
            $context->cookie->{$name} = $value;
        }
        if (!headers_sent()) {
            $context->cookie->write();
        }
    }

    private static function uuid()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
