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
 * Daily counters of the recommendation blocks (impressions and clicks per placement)
 * and the aggregates displayed on the dashboard.
 */
class InjellikStats
{
    const TABLE = 'injellik_stat';

    public static function impression($idShop, $placement)
    {
        self::increment($idShop, $placement, 'impressions');
    }

    public static function click($idShop, $placement)
    {
        self::increment($idShop, $placement, 'clicks');
    }

    private static function increment($idShop, $placement, $column)
    {
        $placement = preg_replace('/[^a-z0-9_]/', '', Tools::strtolower((string) $placement)) ?: 'custom';
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '` (`id_shop`, `date`, `placement`, `' . $column . '`)
             VALUES (' . (int) $idShop . ', \'' . date('Y-m-d') . '\', \'' . pSQL(Tools::substr($placement, 0, 32)) . '\', 1)
             ON DUPLICATE KEY UPDATE `' . $column . '` = `' . $column . '` + 1'
        );
    }

    /**
     * @return array<string, array{impressions: int, clicks: int, ctr: float}>
     */
    public static function byPlacement($idShop, $days = 30)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `placement`, SUM(`impressions`) AS impressions, SUM(`clicks`) AS clicks
             FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `date` >= \'' . date('Y-m-d', strtotime('-' . ((int) $days - 1) . ' days')) . '\'
             GROUP BY `placement` ORDER BY impressions DESC'
        ) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $impressions = (int) $row['impressions'];
            $clicks = (int) $row['clicks'];
            $result[$row['placement']] = [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions ? round(100 * $clicks / $impressions, 2) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Events per day and per action type over the last $days days (zero-filled).
     *
     * @return array<string, array<string, int>> date => [action_type => count]
     */
    public static function eventsPerDay($idShop, $days = 30)
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $series[date('Y-m-d', strtotime('-' . $i . ' days'))] = [];
        }
        $rows = Db::getInstance()->executeS(
            'SELECT DATE(`date_add`) AS day, `action_type`, COUNT(*) AS total
             FROM `' . _DB_PREFIX_ . InjellikEventQueue::TABLE . '`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `date_add` >= \'' . date('Y-m-d', strtotime('-' . ((int) $days - 1) . ' days')) . ' 00:00:00\'
             GROUP BY day, `action_type`'
        ) ?: [];
        foreach ($rows as $row) {
            if (isset($series[$row['day']])) {
                $series[$row['day']][$row['action_type']] = (int) $row['total'];
            }
        }

        return $series;
    }

    /**
     * @return array{pending: int, sent: int, error: int, last24h: int, last_sent: string|null}
     */
    public static function queueSummary($idShop)
    {
        $table = '`' . _DB_PREFIX_ . InjellikEventQueue::TABLE . '`';
        $summary = ['pending' => 0, 'sent' => 0, 'error' => 0];
        foreach (Db::getInstance()->executeS(
            'SELECT `status`, COUNT(*) AS total FROM ' . $table . ' WHERE `id_shop` = ' . (int) $idShop . ' GROUP BY `status`'
        ) ?: [] as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }
        $summary['last24h'] = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE `id_shop` = ' . (int) $idShop . ' AND `date_add` >= \'' . date('Y-m-d H:i:s', strtotime('-1 day')) . '\''
        );
        $summary['last_sent'] = Db::getInstance()->getValue(
            'SELECT MAX(`date_sent`) FROM ' . $table . ' WHERE `id_shop` = ' . (int) $idShop
        ) ?: null;

        return $summary;
    }

    public static function recentEvents($idShop, $limit = 25, $onlyProblems = false)
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . InjellikEventQueue::TABLE . '`
             WHERE `id_shop` = ' . (int) $idShop . ($onlyProblems ? ' AND `last_error` IS NOT NULL AND `status` <> \'sent\'' : '') . '
             ORDER BY `date_add` DESC, `id_injellik_event` DESC LIMIT ' . (int) $limit
        ) ?: [];
    }
}
