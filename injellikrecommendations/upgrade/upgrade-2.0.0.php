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
 * 1.x → 2.0.0: event queue, statistics, back-office pages and new hooks.
 * The API credentials are kept; the onboarding is shown at the next visit of the configuration page.
 *
 * @param InjellikRecommendations $module
 */
function upgrade_module_2_0_0($module)
{
    $installer = new InjellikInstaller($module);

    foreach (['displayShoppingCart'] as $hook) {
        $module->unregisterHook($hook);
    }

    $url = Configuration::get(InjellikConfig::API_URL);
    if ($url) {
        Configuration::updateValue(InjellikConfig::API_URL, InjellikApiClient::normalizeUrl($url));
    }
    Configuration::deleteByName('INJELLIK_LAST_IMPORTED_ORDER_ID');
    Configuration::deleteByName('INJELLIK_LAST_IMPORTED_PRODUCT_ID');

    return $installer->installTables()
        && $module->registerHook(InjellikInstaller::hooks())
        && $installer->installConfiguration()
        && $installer->installTabs();
}
