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
 * Catalog synchronisation and order history import, both processed in batches
 * so that the back office can show a progress bar and never hits max_execution_time.
 *
 * The SKU sent to the API is the PrestaShop product id.
 */
class InjellikSync
{
    const CATALOG_BATCH = 100;
    const ORDERS_BATCH = 20;

    /** @var int[] products saved during the current request (automatic synchronisation) */
    private static $dirtyProducts = [];
    private static $shutdownRegistered = false;

    public static function countProducts($idShop)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `id_shop` = ' . (int) $idShop
        );
    }

    /**
     * Sends the next batch of products after $afterId.
     *
     * @return array{done: bool, last_id: int, processed: int, created: int, updated: int}
     */
    public static function syncCatalogBatch($idShop, $idLang, $afterId, $batch = self::CATALOG_BATCH)
    {
        $ids = array_map('intval', array_column(Db::getInstance()->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_shop`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `id_product` > ' . (int) $afterId . '
             ORDER BY `id_product` LIMIT ' . (int) $batch
        ) ?: [], 'id_product'));

        $result = ['done' => count($ids) < $batch, 'last_id' => (int) $afterId, 'processed' => 0, 'created' => 0, 'updated' => 0];
        if (!$ids) {
            return $result;
        }

        $response = InjellikApiClient::forShop($idShop)->importItems(self::buildItems($ids, $idShop, $idLang));
        $result['last_id'] = max($ids);
        $result['processed'] = count($ids);
        $result['created'] = isset($response['created']) ? (int) $response['created'] : 0;
        $result['updated'] = isset($response['updated']) ? (int) $response['updated'] : 0;

        return $result;
    }

    /**
     * Item payloads for the API. The "profile" feeds the similarity model (/api/related).
     *
     * @param int[] $ids
     */
    public static function buildItems(array $ids, $idShop, $idLang)
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product`, p.`ean13`, p.`id_manufacturer`, ps.`active`, ps.`visibility`, ps.`id_category_default`,
                    pl.`name`, pl.`description_short`
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON (ps.`id_product` = p.`id_product` AND ps.`id_shop` = ' . (int) $idShop . ')
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . (int) $idLang . ' AND pl.`id_shop` = ' . (int) $idShop . ')
             WHERE p.`id_product` IN (' . $in . ')'
        ) ?: [];

        $categories = [];
        foreach (Db::getInstance()->executeS(
            'SELECT `id_product`, `id_category` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_product` IN (' . $in . ')'
        ) ?: [] as $row) {
            $categories[(int) $row['id_product']][] = (string) $row['id_category'];
        }

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $price = (float) Product::getPriceStatic($id, true, null, 2);
            $name = trim((string) $row['name']);
            $description = trim(html_entity_decode(strip_tags((string) $row['description_short']), ENT_QUOTES, 'UTF-8'));

            $item = [
                'sku' => (string) $id,
                'name' => Tools::substr($name !== '' ? $name : 'Product #' . $id, 0, 255),
                'description' => $description !== '' ? Tools::substr($description, 0, 2000) : null,
                'price' => max(0, round($price, 2)),
                'enabled' => (bool) $row['active'] && $row['visibility'] !== 'none',
                'profile' => [
                    'categories' => isset($categories[$id]) ? $categories[$id] : [],
                    'default_category' => (string) $row['id_category_default'],
                    'manufacturer' => (int) $row['id_manufacturer'] ? (string) $row['id_manufacturer'] : null,
                    'price' => max(0, round($price, 2)),
                ],
            ];
            if ($item['profile']['manufacturer'] === null) {
                unset($item['profile']['manufacturer']);
            }
            if ($item['description'] === null) {
                unset($item['description']);
            }
            $ean = trim((string) $row['ean13']);
            if ($ean !== '' && Tools::strlen($ean) <= 13) {
                $item['ean'] = $ean;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Queues a product for synchronisation at the end of the request (one API call for a whole CSV import batch).
     */
    public static function markProductDirty($idProduct)
    {
        $idProduct = (int) $idProduct;
        if ($idProduct <= 0) {
            return;
        }
        self::$dirtyProducts[$idProduct] = $idProduct;
        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([__CLASS__, 'syncDirtyProducts']);
        }
    }

    public static function syncDirtyProducts()
    {
        if (!self::$dirtyProducts) {
            return;
        }
        $ids = array_values(self::$dirtyProducts);
        self::$dirtyProducts = [];
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $shops = Shop::isFeatureActive() ? Shop::getContextListShopID() : [(int) Context::getContext()->shop->id];
        foreach ($shops as $idShop) {
            if (!InjellikConfig::isConfigured($idShop) || !InjellikConfig::get(InjellikConfig::AUTO_SYNC, $idShop)) {
                continue;
            }
            try {
                $items = self::buildItems($ids, $idShop, $idLang);
                // Products deleted from the shop: disable them so that they are no longer recommended.
                $found = array_column($items, 'sku');
                foreach ($ids as $id) {
                    if (!in_array((string) $id, $found, true)) {
                        $items[] = ['sku' => (string) $id, 'name' => 'Product #' . $id, 'enabled' => false];
                    }
                }
                foreach (array_chunk($items, self::CATALOG_BATCH) as $chunk) {
                    InjellikApiClient::forShop($idShop)->importItems($chunk);
                }
            } catch (Exception $e) {
                InjellikConfig::recordError($e->getMessage(), $idShop);
                PrestaShopLogger::addLog('[Injellik] Product synchronisation failed: ' . $e->getMessage(), 2, null, 'Module', 0, true);
            }
        }
    }

    public static function countOrders($idShop, $afterId = 0, $since = null)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE ' . self::ordersWhere($idShop, $afterId, $since)
        );
    }

    /**
     * Sends a BUY action for each product of the next orders after $afterId.
     * Each order becomes its own session (= one basket for the association rules).
     *
     * @return array{done: bool, last_id: int, processed: int, sent: int, errors: int}
     */
    public static function importOrdersBatch($idShop, $afterId, $since = null, $batch = self::ORDERS_BATCH)
    {
        $orders = Db::getInstance()->executeS(
            'SELECT `id_order`, `id_customer` FROM `' . _DB_PREFIX_ . 'orders`
             WHERE ' . self::ordersWhere($idShop, $afterId, $since) . '
             ORDER BY `id_order` LIMIT ' . (int) $batch
        ) ?: [];

        $result = ['done' => count($orders) < $batch, 'last_id' => (int) $afterId, 'processed' => 0, 'sent' => 0, 'errors' => 0];
        if (!$orders) {
            return $result;
        }

        $lines = [];
        foreach (Db::getInstance()->executeS(
            'SELECT `id_order`, `product_id`, `product_name` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` IN (' . implode(',', array_map('intval', array_column($orders, 'id_order'))) . ')'
        ) ?: [] as $line) {
            $lines[(int) $line['id_order']][(int) $line['product_id']] = $line['product_name'];
        }

        $client = InjellikApiClient::forShop($idShop);
        foreach ($orders as $order) {
            $idOrder = (int) $order['id_order'];
            $products = isset($lines[$idOrder]) ? $lines[$idOrder] : [];
            foreach ($products as $idProduct => $name) {
                try {
                    $client->sendAction([
                        'action_type' => 'BUY',
                        'item_sku' => (string) $idProduct,
                        'user_uuid' => InjellikIdentity::customerUuid((int) $order['id_customer']),
                        'user_session_uuid' => 'order-' . $idOrder,
                        'name' => Tools::substr((string) $name, 0, 255),
                    ]);
                    ++$result['sent'];
                } catch (InjellikApiException $e) {
                    if ($e->isRetryable()) {
                        // Stop here: the batch restarts from this order on the next call.
                        throw $e;
                    }
                    ++$result['errors'];
                }
            }
            $result['last_id'] = $idOrder;
            ++$result['processed'];
        }

        return $result;
    }

    private static function ordersWhere($idShop, $afterId, $since)
    {
        $where = '`id_shop` = ' . (int) $idShop . ' AND `valid` = 1 AND `id_order` > ' . (int) $afterId;
        if ($since && Validate::isDate($since)) {
            $where .= ' AND `date_add` >= \'' . pSQL($since) . ' 00:00:00\'';
        }

        return $where;
    }
}
