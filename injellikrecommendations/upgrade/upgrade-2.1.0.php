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
 * 2.0.x → 2.1.0: licence keys and notice of a suspended subscription.
 * The login and the password already configured keep working: nothing to do for the merchant.
 *
 * @param InjellikRecommendations $module
 */
function upgrade_module_2_1_0($module)
{
    // Without a URL, the module uses the production API (the URLs already configured are kept).
    if (trim((string) Configuration::get(InjellikConfig::API_URL)) === '') {
        Configuration::updateValue(InjellikConfig::API_URL, InjellikConfig::DEFAULT_API_URL);
    }
    if (Shop::isFeatureActive()) {
        foreach (Shop::getShops(false, null, true) as $idShop) {
            if (Configuration::hasKey(InjellikConfig::API_URL, null, null, (int) $idShop)
                && trim((string) InjellikConfig::get(InjellikConfig::API_URL, (int) $idShop)) === '') {
                InjellikConfig::set(InjellikConfig::API_URL, InjellikConfig::DEFAULT_API_URL, (int) $idShop);
            }
        }
    }

    return $module->registerHook('displayDashboardTop');
}
