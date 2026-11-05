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
 * Configuration keys, defaults and placement definitions.
 */
class InjellikConfig
{
    const API_URL = 'INJELLIK_API_URL';
    const LICENCE_KEY = 'INJELLIK_LICENCE_KEY';
    const API_LOGIN = 'INJELLIK_API_LOGIN';
    const API_PASSWORD = 'INJELLIK_API_PASSWORD';
    const JWT = 'INJELLIK_JWT';
    const JWT_EXPIRES = 'INJELLIK_JWT_EXPIRES';
    const ONBOARDED = 'INJELLIK_ONBOARDED';
    const PLACEMENTS = 'INJELLIK_PLACEMENTS';
    const RENDER_MODE = 'INJELLIK_RENDER_MODE';
    const SEND_MODE = 'INJELLIK_SEND_MODE';
    const TIMEOUT = 'INJELLIK_TIMEOUT';
    const CACHE_TTL = 'INJELLIK_CACHE_TTL';
    const HIDE_OUT_OF_STOCK = 'INJELLIK_HIDE_OUT_OF_STOCK';
    const EXCLUDE_BOTS = 'INJELLIK_EXCLUDE_BOTS';
    const AUTO_SYNC = 'INJELLIK_AUTO_SYNC';
    const CRON_TOKEN = 'INJELLIK_CRON_TOKEN';
    const CATALOG_SYNCED_AT = 'INJELLIK_CATALOG_SYNCED_AT';
    const CATALOG_COUNT = 'INJELLIK_CATALOG_COUNT';
    const IMPORT_LAST_ORDER = 'INJELLIK_IMPORT_LAST_ORDER';
    const IMPORT_ORDERS_AT = 'INJELLIK_IMPORT_ORDERS_AT';
    const IMPORT_ORDERS_COUNT = 'INJELLIK_IMPORT_ORDERS_COUNT';
    const LAST_CRON = 'INJELLIK_LAST_CRON';
    const LAST_ERROR = 'INJELLIK_LAST_ERROR';
    const API_STATE = 'INJELLIK_API_STATE';

    /** Production API, used when no URL is configured */
    const DEFAULT_API_URL = 'https://injellik.web-nest.fr';
    /** Licence key of a shop, displayed in the web-nest.fr account: "ijk_lic_" + 40 lowercase hexadecimal characters */
    const LICENCE_KEY_PATTERN = '/^ijk_lic_[0-9a-f]{40}$/';

    /** The subscription of the account is suspended (HTTP 403 "tenant_suspended") */
    const STATE_SUSPENDED = 'suspended';
    /** The licence key is unknown or revoked (HTTP 401) */
    const STATE_INVALID_KEY = 'invalid_key';
    /** Seconds between two attempts while the API refuses the shop */
    const STATE_RETRY_DELAY = 900;

    const SUBSCRIPTION_URL = 'https://web-nest.fr/mon-compte/abonnement/';
    const ACCOUNT_SHOPS_URL = 'https://web-nest.fr/mon-compte/boutiques/';
    const PRICING_URL = 'https://web-nest.fr/#tarifs';

    const RENDER_AJAX = 'ajax';
    const RENDER_SERVER = 'server';
    const SEND_REALTIME = 'realtime';
    const SEND_CRON = 'cron';

    const DOMAIN_ADMIN = 'Modules.Injellikrecommendations.Admin';
    const DOMAIN_SHOP = 'Modules.Injellikrecommendations.Shop';

    /**
     * Every key the module owns, removed on uninstall.
     *
     * @return string[]
     */
    public static function keys()
    {
        return [
            self::API_URL, self::LICENCE_KEY, self::API_LOGIN, self::API_PASSWORD, self::JWT, self::JWT_EXPIRES,
            self::ONBOARDED, self::PLACEMENTS, self::RENDER_MODE, self::SEND_MODE, self::TIMEOUT,
            self::CACHE_TTL, self::HIDE_OUT_OF_STOCK, self::EXCLUDE_BOTS, self::AUTO_SYNC, self::CRON_TOKEN,
            self::CATALOG_SYNCED_AT, self::CATALOG_COUNT, self::IMPORT_LAST_ORDER, self::IMPORT_ORDERS_AT,
            self::IMPORT_ORDERS_COUNT, self::LAST_CRON, self::LAST_ERROR, self::API_STATE,
            // Keys of version 1.x
            'INJELLIK_LAST_IMPORTED_ORDER_ID', 'INJELLIK_LAST_IMPORTED_PRODUCT_ID',
        ];
    }

    /**
     * Values written at install (only when missing, so that a reinstall keeps the credentials).
     *
     * @return array<string, mixed>
     */
    public static function defaults()
    {
        return [
            self::API_URL => self::DEFAULT_API_URL,
            self::RENDER_MODE => self::RENDER_AJAX,
            self::SEND_MODE => self::SEND_REALTIME,
            self::TIMEOUT => 3,
            self::CACHE_TTL => 900,
            self::HIDE_OUT_OF_STOCK => 1,
            self::EXCLUDE_BOTS => 1,
            self::AUTO_SYNC => 1,
            self::CRON_TOKEN => Tools::passwdGen(32),
            self::PLACEMENTS => json_encode(self::defaultPlacements()),
        ];
    }

    public static function get($key, $idShop = null, $default = false)
    {
        return Configuration::get($key, null, null, $idShop, $default);
    }

    public static function set($key, $value, $idShop = null)
    {
        if ($idShop !== null && Shop::isFeatureActive()) {
            return Configuration::updateValue($key, $value, false, (int) Shop::getGroupFromShop((int) $idShop, true), (int) $idShop);
        }

        return Configuration::updateValue($key, $value);
    }

    /**
     * URL of the API of a shop, the production API when none is configured.
     */
    public static function apiUrl($idShop = null)
    {
        $url = trim((string) self::get(self::API_URL, $idShop));

        return $url !== '' ? $url : self::DEFAULT_API_URL;
    }

    /**
     * A licence key (default mode) or a login and a password (accounts created before the subscriptions).
     */
    public static function isConfigured($idShop = null)
    {
        return self::usesLicenceKey($idShop)
            || (self::get(self::API_LOGIN, $idShop) && self::get(self::API_PASSWORD, $idShop));
    }

    public static function usesLicenceKey($idShop = null)
    {
        return (string) self::get(self::LICENCE_KEY, $idShop) !== '';
    }

    /**
     * Trims and lowercases a licence key typed or pasted in the back office.
     */
    public static function normalizeLicenceKey($key)
    {
        return Tools::strtolower(trim((string) $key));
    }

    public static function isValidLicenceKey($key)
    {
        return (bool) preg_match(self::LICENCE_KEY_PATTERN, (string) $key);
    }

    /**
     * State of a shop the API refuses: subscription suspended or licence key invalid.
     *
     * @return array{reason: string, since: string, checked_at: int}|null
     */
    public static function apiState($idShop = null)
    {
        $raw = self::get(self::API_STATE, $idShop);
        $state = $raw ? json_decode($raw, true) : null;

        return is_array($state) && isset($state['reason']) ? $state + ['since' => '', 'checked_at' => 0] : null;
    }

    /**
     * True while the API must not be called: it refused the shop less than STATE_RETRY_DELAY seconds ago.
     */
    public static function isApiBlocked($idShop = null)
    {
        $state = self::apiState($idShop);

        return $state !== null && (int) $state['checked_at'] > time() - self::STATE_RETRY_DELAY;
    }

    /**
     * Records a refusal of the API (the first date is kept while the reason is the same).
     */
    public static function setApiState($reason, $idShop = null)
    {
        $state = self::apiState($idShop);

        return self::set(self::API_STATE, json_encode([
            'reason' => (string) $reason,
            'since' => $state && $state['reason'] === $reason && $state['since'] ? $state['since'] : date('Y-m-d H:i:s'),
            'checked_at' => time(),
        ]), $idShop);
    }

    /**
     * Marks the start of a new attempt, so that concurrent requests keep waiting.
     */
    public static function touchApiState($idShop = null)
    {
        $state = self::apiState($idShop);
        if ($state) {
            $state['checked_at'] = time();
            self::set(self::API_STATE, json_encode($state), $idShop);
        }
    }

    /**
     * Allows a new attempt at the next call (the state stays displayed until then).
     */
    public static function allowApiRetry($idShop = null)
    {
        $state = self::apiState($idShop);
        if ($state) {
            $state['checked_at'] = 0;
            self::set(self::API_STATE, json_encode($state), $idShop);
        }
    }

    public static function clearApiState($idShop = null)
    {
        if (self::get(self::API_STATE, $idShop)) {
            self::set(self::API_STATE, '', $idShop);
        }
    }

    public static function isOnboarded($idShop = null)
    {
        return (bool) self::get(self::ONBOARDED, $idShop);
    }

    public static function recordError($message, $idShop = null)
    {
        self::set(self::LAST_ERROR, json_encode(['date' => date('Y-m-d H:i:s'), 'message' => Tools::substr((string) $message, 0, 500)]), $idShop);
    }

    /**
     * @return array{date: string, message: string}|null
     */
    public static function lastError($idShop = null)
    {
        $raw = self::get(self::LAST_ERROR, $idShop);
        $data = $raw ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Recommendation strategies: the API endpoint they rely on and the context they need.
     * "items" strategies need at least one product, "user" strategies use the visitor identity.
     *
     * @return array<string, array{context: string}>
     */
    public static function strategies()
    {
        return [
            'viewed' => ['context' => 'items'],
            'bought' => ['context' => 'items'],
            'similar' => ['context' => 'items'],
            'personalized' => ['context' => 'user'],
            'predictive' => ['context' => 'user'],
            'most_viewed' => ['context' => 'none'],
            'most_bought' => ['context' => 'none'],
        ];
    }

    /**
     * Labels of the strategies for the back office.
     */
    public static function strategyLabels($translator)
    {
        $d = self::DOMAIN_ADMIN;

        return [
            'viewed' => ['label' => $translator->trans('Viewed together', [], $d), 'help' => $translator->trans('Products viewed in the same sessions as the current product(s).', [], $d), 'context' => 'items'],
            'bought' => ['label' => $translator->trans('Bought together', [], $d), 'help' => $translator->trans('Products ordered with the current product(s).', [], $d), 'context' => 'items'],
            'similar' => ['label' => $translator->trans('Similar products', [], $d), 'help' => $translator->trans('Products with close categories, brand and price.', [], $d), 'context' => 'items'],
            'personalized' => ['label' => $translator->trans('Personalized', [], $d), 'help' => $translator->trans('Based on the recent activity of the visitor.', [], $d), 'context' => 'user'],
            'predictive' => ['label' => $translator->trans('Customers like you', [], $d), 'help' => $translator->trans('What customers with similar purchases also bought.', [], $d), 'context' => 'user'],
            'most_viewed' => ['label' => $translator->trans('Most viewed', [], $d), 'help' => $translator->trans('Most viewed products over the last 30 days.', [], $d), 'context' => 'none'],
            'most_bought' => ['label' => $translator->trans('Best sellers', [], $d), 'help' => $translator->trans('Most ordered products over the last 30 days.', [], $d), 'context' => 'none'],
        ];
    }

    /**
     * Built-in placements and the hook each one is displayed on.
     * "custom" applies to {widget} calls and to modules hooked on any other hook.
     *
     * @return array<string, array{hook: string|null, enabled: bool, strategy: string, fallback: string, limit: int, title: array}>
     */
    public static function defaultPlacements()
    {
        return [
            'product' => ['hook' => 'displayFooterProduct', 'enabled' => true, 'strategy' => 'viewed', 'fallback' => 'similar', 'limit' => 8, 'title' => []],
            'cart' => ['hook' => 'displayShoppingCartFooter', 'enabled' => true, 'strategy' => 'bought', 'fallback' => 'most_bought', 'limit' => 4, 'title' => []],
            'order_confirmation' => ['hook' => 'displayOrderConfirmation2', 'enabled' => true, 'strategy' => 'bought', 'fallback' => 'personalized', 'limit' => 4, 'title' => []],
            'home' => ['hook' => 'displayHome', 'enabled' => false, 'strategy' => 'personalized', 'fallback' => 'most_viewed', 'limit' => 8, 'title' => []],
            'category' => ['hook' => 'displayFooterCategory', 'enabled' => false, 'strategy' => 'personalized', 'fallback' => 'most_viewed', 'limit' => 8, 'title' => []],
            'custom' => ['hook' => null, 'enabled' => true, 'strategy' => 'personalized', 'fallback' => 'most_viewed', 'limit' => 8, 'title' => []],
        ];
    }

    /**
     * Stored placements merged over the defaults (new placements appear after an upgrade).
     */
    public static function placements($idShop = null)
    {
        $stored = json_decode((string) self::get(self::PLACEMENTS, $idShop), true);
        $placements = self::defaultPlacements();
        foreach ($placements as $key => $default) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $placements[$key] = self::sanitizePlacement(array_merge($default, $stored[$key]), $default);
            }
        }

        return $placements;
    }

    public static function placement($key, $idShop = null)
    {
        $placements = self::placements($idShop);

        return isset($placements[$key]) ? $placements[$key] : null;
    }

    public static function placementForHook($hookName)
    {
        foreach (self::defaultPlacements() as $key => $placement) {
            if ($placement['hook'] !== null && strcasecmp($placement['hook'], (string) $hookName) === 0) {
                return $key;
            }
        }

        return null;
    }

    public static function savePlacements(array $placements)
    {
        $defaults = self::defaultPlacements();
        $clean = [];
        foreach ($defaults as $key => $default) {
            $clean[$key] = self::sanitizePlacement(isset($placements[$key]) ? array_merge($default, $placements[$key]) : $default, $default);
            unset($clean[$key]['hook']);
        }

        return Configuration::updateValue(self::PLACEMENTS, json_encode($clean));
    }

    private static function sanitizePlacement(array $placement, array $default)
    {
        $strategies = self::strategies();
        $placement['hook'] = $default['hook'];
        $placement['enabled'] = (bool) $placement['enabled'];
        if (!isset($strategies[$placement['strategy']])) {
            $placement['strategy'] = $default['strategy'];
        }
        if ($placement['fallback'] !== '' && !isset($strategies[$placement['fallback']])) {
            $placement['fallback'] = $default['fallback'];
        }
        $placement['limit'] = max(1, min(24, (int) $placement['limit']));
        $titles = [];
        foreach ((array) $placement['title'] as $idLang => $title) {
            $title = trim(strip_tags((string) $title));
            if ($title !== '') {
                $titles[(int) $idLang] = Tools::substr($title, 0, 128);
            }
        }
        $placement['title'] = $titles;

        return $placement;
    }
}
