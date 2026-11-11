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
 * Local queue of the actions sent to the API.
 *
 * Hooks only insert a row (a few milliseconds, never a network call), so an API outage can
 * neither slow down nor break the shop. Rows are sent after the response has been delivered
 * ("realtime" mode) or by the cron task, and retried until MAX_ATTEMPTS.
 *
 * While the API refuses the shop (suspended subscription, invalid licence key), the events stay pending
 * without using their attempts, and are sent once the API accepts the shop again. The purge keeps the
 * queue bounded meanwhile.
 */
class InjellikEventQueue
{
    const TABLE = 'injellik_event';
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_ERROR = 'error';
    const MAX_ATTEMPTS = 5;
    /** Older pending events sent after a request, at most (see sendBacklog()) */
    const BACKLOG_BATCH = 20;

    /** @var int[] rows created by the current request */
    private static $created = [];
    private static $shutdownRegistered = false;

    /**
     * @return int|null id of the queued event
     */
    public static function push($idShop, $actionType, $sku, $userUuid, $sessionUuid = null, $name = null, $rating = null)
    {
        $sku = Tools::substr(trim((string) $sku), 0, 50);
        if ($sku === '' || $userUuid === '') {
            return null;
        }

        $inserted = Db::getInstance()->insert(self::TABLE, [
            'id_shop' => (int) $idShop,
            'action_type' => pSQL(Tools::substr($actionType, 0, 50)),
            'item_sku' => pSQL($sku),
            'item_name' => $name === null ? null : pSQL(Tools::substr((string) $name, 0, 255)),
            'user_uuid' => pSQL(Tools::substr($userUuid, 0, 64)),
            'session_uuid' => $sessionUuid === null ? null : pSQL(Tools::substr($sessionUuid, 0, 64)),
            'rating_value' => $rating === null ? null : max(1, min(5, (int) $rating)),
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ], true);

        if (!$inserted) {
            return null;
        }
        $id = (int) Db::getInstance()->Insert_ID();

        if (InjellikConfig::get(InjellikConfig::SEND_MODE, $idShop) !== InjellikConfig::SEND_CRON
            && !InjellikConfig::isApiBlocked($idShop)) {
            self::$created[] = $id;
            if (!self::$shutdownRegistered) {
                self::$shutdownRegistered = true;
                register_shutdown_function([__CLASS__, 'sendAfterResponse']);
            }
        }

        return $id;
    }

    /**
     * Shutdown handler. It only registers the real sending as the very last shutdown function, so that the
     * shutdown functions of PrestaShop and of the other modules still run before the response is released.
     */
    public static function sendAfterResponse()
    {
        if (self::$created) {
            register_shutdown_function([__CLASS__, 'releaseAndSend']);
        }
    }

    /**
     * Hands the response to the visitor, then sends the events of the request.
     */
    public static function releaseAndSend()
    {
        if (!self::$created) {
            return;
        }
        $ids = self::$created;
        self::$created = [];

        try {
            self::releaseResponse();
        } catch (Throwable $e) {
            // Without releasing the response, the events are left to the cron task: the visitor must not wait.
            return;
        }
        if (!self::$released) {
            return;
        }

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `id_injellik_event` IN (' . implode(',', array_map('intval', $ids)) . ')
                   AND `status` = \'' . self::STATUS_PENDING . '\'
                 ORDER BY `id_injellik_event`'
            );
            $result = self::sendRows($rows ?: []);
            if (!$result['failed'] && $rows) {
                self::sendBacklog((int) $rows[0]['id_shop']);
            }
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Injellik] ' . $e->getMessage(), 2, null, 'Module', 0, false);
        }
    }

    /**
     * Sends a few events left pending (API unreachable for a while, response that could not be released),
     * once the response is delivered: without a cron task, they would otherwise wait for the dashboard.
     *
     * Only events older than one minute (the request that created them has already tried), and by one
     * request at a time (non-blocking MySQL lock), so that no event is sent twice.
     */
    public static function sendBacklog($idShop)
    {
        if (!InjellikConfig::isConfigured($idShop) || InjellikConfig::isApiBlocked($idShop)) {
            return;
        }
        $db = Db::getInstance();
        $lock = "'" . md5(_DB_NAME_ . _DB_PREFIX_ . 'injellik_backlog_' . (int) $idShop) . "'";
        if (!(int) $db->getValue('SELECT GET_LOCK(' . $lock . ', 0)', false)) {
            return;
        }
        try {
            $rows = $db->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `status` = \'' . self::STATUS_PENDING . '\' AND `id_shop` = ' . (int) $idShop . '
                   AND `date_add` < \'' . pSQL(date('Y-m-d H:i:s', time() - 60)) . '\'
                 ORDER BY `id_injellik_event` LIMIT ' . self::BACKLOG_BATCH
            );
            self::sendRows($rows ?: []);
        } finally {
            $db->getValue('SELECT RELEASE_LOCK(' . $lock . ')', false);
        }
    }

    /** @var bool */
    private static $released = false;

    /**
     * Ends the response before the network calls.
     *
     * PrestaShop writes its cookie (cart, customer, language...) when the Cookie object is destroyed, that is
     * after the shutdown functions: once the response is released, it could no longer be sent and the visitor
     * would lose the cart he has just filled. The cookie and the PHP session are therefore written first.
     *
     * Without fastcgi_finish_request() / litespeed_finish_request() (mod_php...), the response cannot be released
     * early: the events stay pending and are sent by the cron task or a later request, instead of making the
     * visitor wait for the API.
     */
    public static function canReleaseResponse()
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    /**
     * Sends the pending events of one visitor (requested by front.js with sendBeacon when the server cannot
     * release the response early: nobody waits for that request).
     */
    public static function flushVisitor($idShop, $userUuid, $limit = 10)
    {
        if ((string) $userUuid === '' || InjellikConfig::isApiBlocked($idShop)) {
            return;
        }
        $rows = Db::getInstance()->executeS(sprintf(
            "SELECT * FROM `%s` WHERE `status` = '%s' AND `id_shop` = %d AND `user_uuid` = '%s' ORDER BY `id_injellik_event` LIMIT %d",
            _DB_PREFIX_ . self::TABLE,
            self::STATUS_PENDING,
            (int) $idShop,
            pSQL($userUuid),
            max(1, (int) $limit)
        ));
        $result = self::sendRows($rows ?: []);
        if (!$result['failed']) {
            self::sendBacklog((int) $idShop);
        }
    }

    private static function releaseResponse()
    {
        if (function_exists('fastcgi_finish_request')) {
            $finish = 'fastcgi_finish_request';
        } elseif (function_exists('litespeed_finish_request')) {
            $finish = 'litespeed_finish_request';
        } else {
            return;
        }
        // A fatal error ends the request in its own way.
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }

        $context = Context::getContext();
        if ($context && $context->cookie instanceof Cookie && !headers_sent()) {
            $context->cookie->write();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $finish();
        self::$released = true;
    }

    /**
     * Sends the pending events (cron, dashboard).
     *
     * @return array{sent: int, failed: int, pending: int}
     */
    public static function flush($idShop = null, $limit = 500)
    {
        $where = '`status` = \'' . self::STATUS_PENDING . '\'';
        if ($idShop !== null) {
            $where .= ' AND `id_shop` = ' . (int) $idShop;
        }
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE ' . $where . '
             ORDER BY `id_injellik_event` LIMIT ' . max(1, (int) $limit)
        );
        $result = self::sendRows($rows ?: []);
        $result['pending'] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE ' . $where);

        return $result;
    }

    /**
     * Puts the events in error back in the queue.
     */
    public static function retryErrors($idShop = null)
    {
        return Db::getInstance()->update(
            self::TABLE,
            ['status' => self::STATUS_PENDING, 'attempts' => 0],
            '`status` = \'' . self::STATUS_ERROR . '\'' . ($idShop !== null ? ' AND `id_shop` = ' . (int) $idShop : '')
        );
    }

    /**
     * Deletes the sent events older than $days and the events in error older than 3 × $days.
     */
    public static function purge($days = 30)
    {
        $days = max(1, (int) $days);
        // Dates are written by PHP: compare with PHP dates, the MySQL timezone may differ.
        Db::getInstance()->delete(
            self::TABLE,
            sprintf(
                "(`status` = '%s' AND `date_add` < '%s') OR `date_add` < '%s'",
                self::STATUS_SENT,
                date('Y-m-d H:i:s', strtotime('-' . $days . ' days')),
                date('Y-m-d H:i:s', strtotime('-' . ($days * 3) . ' days'))
            )
        );

        return (int) Db::getInstance()->Affected_Rows();
    }

    /**
     * @return array{sent: int, failed: int}
     */
    private static function sendRows(array $rows)
    {
        $sent = 0;
        $failed = 0;
        $unreachable = [];

        foreach ($rows as $row) {
            $idShop = (int) $row['id_shop'];
            if (isset($unreachable[$idShop]) || !InjellikConfig::isConfigured($idShop) || InjellikConfig::isApiBlocked($idShop)) {
                continue;
            }

            $payload = [
                'action_type' => $row['action_type'],
                'item_sku' => $row['item_sku'],
                'user_uuid' => $row['user_uuid'],
            ];
            foreach (['session_uuid' => 'user_session_uuid', 'item_name' => 'name', 'rating_value' => 'rating_value'] as $column => $field) {
                if ($row[$column] !== null && $row[$column] !== '') {
                    $payload[$field] = $column === 'rating_value' ? (int) $row[$column] : $row[$column];
                }
            }

            try {
                InjellikApiClient::forShop($idShop)->sendAction($payload);
                self::update((int) $row['id_injellik_event'], [
                    'status' => self::STATUS_SENT,
                    'attempts' => (int) $row['attempts'] + 1,
                    'last_error' => null,
                    'date_sent' => date('Y-m-d H:i:s'),
                ]);
                ++$sent;
            } catch (InjellikApiException $e) {
                if ($e->isUnavailable()) {
                    // Not sent, without using an attempt: the events wait for the API to be reachable again.
                    $unreachable[$idShop] = true;

                    continue;
                }
                if ($e->isAccessDenied()) {
                    // The event is valid: it stays pending, without using an attempt. The state of the
                    // API is stored by the client and displayed in the back office.
                    $unreachable[$idShop] = true;
                    self::purge();

                    continue;
                }
                $attempts = (int) $row['attempts'] + 1;
                $retry = $e->isRetryable() && $attempts < self::MAX_ATTEMPTS;
                self::update((int) $row['id_injellik_event'], [
                    'status' => $retry ? self::STATUS_PENDING : self::STATUS_ERROR,
                    'attempts' => $attempts,
                    'last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255)),
                ]);
                ++$failed;
                InjellikConfig::recordError($e->getMessage(), $idShop);
                if ($e->isRetryable()) {
                    // Do not wait for one timeout per event: the rest of the batch is sent later.
                    $unreachable[$idShop] = true;
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private static function update($id, array $data)
    {
        Db::getInstance()->update(self::TABLE, $data, '`id_injellik_event` = ' . (int) $id, 0, true);
    }
}
