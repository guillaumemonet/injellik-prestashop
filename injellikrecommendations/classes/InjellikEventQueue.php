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
 */
class InjellikEventQueue
{
    const TABLE = 'injellik_event';
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_ERROR = 'error';
    const MAX_ATTEMPTS = 5;

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

        if (InjellikConfig::get(InjellikConfig::SEND_MODE, $idShop) !== InjellikConfig::SEND_CRON) {
            self::$created[] = $id;
            if (!self::$shutdownRegistered) {
                self::$shutdownRegistered = true;
                register_shutdown_function([__CLASS__, 'sendAfterResponse']);
            }
        }

        return $id;
    }

    /**
     * Shutdown handler: hands the response to the visitor first when PHP-FPM allows it.
     */
    public static function sendAfterResponse()
    {
        if (!self::$created) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $ids = self::$created;
        self::$created = [];

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `id_injellik_event` IN (' . implode(',', array_map('intval', $ids)) . ')
                   AND `status` = \'' . self::STATUS_PENDING . '\'
                 ORDER BY `id_injellik_event`'
            );
            self::sendRows($rows ?: []);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[Injellik] ' . $e->getMessage(), 2, null, 'Module', 0, true);
        }
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
            if (isset($unreachable[$idShop]) || !InjellikConfig::isConfigured($idShop)) {
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
