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
 * Click on a recommended product (navigator.sendBeacon from front.js): CLICK action + block statistics.
 */
class InjellikrecommendationsTrackModuleFrontController extends ModuleFrontController
{
    /** @var InjellikRecommendations */
    public $module;

    public function postProcess()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $placement = (string) Tools::getValue('placement');

        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && Tools::getValue('token') === Tools::getToken(false)
            && $idProduct > 0
            && Validate::isLoadedObject(new Product($idProduct))) {
            $this->module->track('CLICK', $idProduct);
            if (InjellikConfig::placement($placement)) {
                InjellikStats::click((int) $this->context->shop->id, $placement);
            }
        }

        http_response_code(204);
        header('Cache-Control: private, no-store');
        exit;
    }
}
