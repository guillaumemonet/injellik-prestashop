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
 * Maintenance task, to call every 5 to 15 minutes:
 *   curl -s "https://shop.example/module/injellikrecommendations/cron?token=XXX"
 *   curl -s "https://shop.example/module/injellikrecommendations/cron?token=XXX&task=catalog"  (full catalog, nightly)
 *
 * - events  (default): sends the pending events, purges the old ones and the expired cache
 * - catalog : synchronises the whole catalog of the shop
 */
class InjellikrecommendationsCronModuleFrontController extends ModuleFrontController
{
    /**
     * Token of the query string, or of the X-Injellik-Token header (kept out of the access logs).
     */
    private function token()
    {
        if (!empty($_SERVER['HTTP_X_INJELLIK_TOKEN'])) {
            return (string) $_SERVER['HTTP_X_INJELLIK_TOKEN'];
        }

        return (string) Tools::getValue('token');
    }

    public function postProcess()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');

        $expected = (string) InjellikConfig::get(InjellikConfig::CRON_TOKEN);
        if ($expected === '' || !hash_equals($expected, $this->token())) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }

        @set_time_limit(600);
        $idShop = (int) $this->context->shop->id;
        InjellikApiClient::forShop($idShop)->setTimeout(20);
        $report = [];

        try {
            if (Tools::getValue('task') === 'catalog') {
                // While the API refuses the shop, the synchronisation waits for the next run.
                $report['catalog'] = InjellikConfig::isApiBlocked($idShop) ? null : $this->syncCatalog($idShop);
            } else {
                $report['events'] = InjellikEventQueue::flush($idShop, 2000);
                $report['purged'] = (int) InjellikEventQueue::purge(30);
                InjellikCache::clear(86400);
            }
            InjellikConfig::set(InjellikConfig::LAST_CRON, date('Y-m-d H:i:s'), $idShop);
        } catch (Exception $e) {
            if (!$e instanceof InjellikApiException || !$e->isAccessDenied()) {
                InjellikConfig::recordError($e->getMessage(), $idShop);
                http_response_code(502);
                $report['error'] = $e->getMessage();
            }
        }
        // "suspended" or "invalid_key": the API refuses the shop, the events are kept until it accepts it again.
        $state = InjellikConfig::apiState($idShop);
        if ($state) {
            $report['api'] = $state['reason'];
        }

        echo json_encode($report);
        exit;
    }

    private function syncCatalog($idShop)
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $lastId = 0;
        $total = 0;
        do {
            $batch = InjellikSync::syncCatalogBatch($idShop, $idLang, $lastId);
            $lastId = $batch['last_id'];
            $total += $batch['processed'];
        } while (!$batch['done']);

        InjellikConfig::set(InjellikConfig::CATALOG_SYNCED_AT, date('Y-m-d H:i:s'), $idShop);
        InjellikConfig::set(InjellikConfig::CATALOG_COUNT, $total, $idShop);

        return ['products' => $total];
    }
}
