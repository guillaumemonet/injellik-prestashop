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
 * Requests sent by front.js with navigator.sendBeacon (nobody waits for them):
 * - click on a recommended product: CLICK action + block statistics;
 * - flush=1: sends the pending events of the visitor, on servers that cannot release a response early.
 */
class InjellikrecommendationsTrackModuleFrontController extends ModuleFrontController
{
    /** @var InjellikRecommendations */
    public $module;

    public function postProcess()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals((string) Tools::getToken(false), (string) Tools::getValue('token'))) {
                $this->handle();
            }
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[Injellik] Tracking failed: ' . $e->getMessage(), 2, null, 'Module', 0, false);
        }

        http_response_code(204);
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
        exit;
    }

    private function handle()
    {
        $idShop = (int) $this->context->shop->id;

        // A click belongs to a visit: requests without the visit cookie (scripts) are ignored, so that
        // nobody can inflate the sessions, the clicks or the queue.
        if (!InjellikIdentity::isKnownVisitor($this->context)) {
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $product = $idProduct > 0 ? new Product($idProduct, false, null, $idShop) : null;
        if ($product && Validate::isLoadedObject($product) && $product->active && in_array($product->visibility, ['both', 'catalog', 'search'], true)) {
            $this->module->track('CLICK', $idProduct);
            $placement = (string) Tools::getValue('placement');
            if (InjellikConfig::placement($placement)) {
                InjellikStats::click($idShop, $placement);
            }
        }

        if (Tools::getValue('flush') && InjellikConfig::isConfigured($idShop)
            && InjellikConfig::get(InjellikConfig::SEND_MODE, $idShop) !== InjellikConfig::SEND_CRON) {
            // Only the events of this visitor, a few at a time: the request cannot be used to empty the queue.
            InjellikEventQueue::flushVisitor($idShop, InjellikIdentity::userUuid($this->context));
        }
    }
}
