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
 * Onboarding wizard (until it is completed) and configuration page.
 */
class AdminInjellikConfigurationController extends ModuleAdminController
{
    const STEPS = ['welcome', 'connection', 'catalog', 'history', 'placements', 'done'];
    const SIGNUP_URL = 'https://www.web-nest.fr/injellik';

    /** @var InjellikRecommendations */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'Injellik';
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }

    public function initPageHeaderToolbar()
    {
        if (!$this->isOnboarding()) {
            $this->page_header_toolbar_btn['dashboard'] = [
                'href' => $this->context->link->getAdminLink('AdminInjellikDashboard'),
                'desc' => $this->trans('Dashboard', [], InjellikConfig::DOMAIN_ADMIN),
                'icon' => 'process-icon-stats',
            ];
        }
        parent::initPageHeaderToolbar();
    }

    public function initContent()
    {
        $this->content = $this->isOnboarding() ? $this->renderOnboarding() : $this->renderConfiguration();
        parent::initContent();
    }

    private function isOnboarding()
    {
        return (bool) Tools::getValue('onboarding') || !InjellikConfig::isOnboarded();
    }

    private function step()
    {
        $step = (string) Tools::getValue('step');
        if (!in_array($step, self::STEPS, true)) {
            $step = 'welcome';
        }
        // The steps after the connection need working credentials.
        if (!InjellikConfig::isConfigured() && !in_array($step, ['welcome', 'connection'], true)) {
            $step = 'connection';
        }

        return $step;
    }

    private function url(array $params = [])
    {
        return $this->context->link->getAdminLink('AdminInjellikConfiguration') . ($params ? '&' . http_build_query($params) : '');
    }

    private function stepUrl($step)
    {
        return $this->url(['onboarding' => 1, 'step' => $step]);
    }

    /* ------------------------------------------------------------------
     * Form submissions
     * ------------------------------------------------------------------ */

    public function postProcess()
    {
        if (Tools::isSubmit('submitInjellikConnection')) {
            $this->processConnection();
        } elseif (Tools::isSubmit('submitInjellikPlacements')) {
            $this->processPlacements();
        } elseif (Tools::isSubmit('submitInjellikAdvanced')) {
            $this->processAdvanced();
        } elseif (Tools::isSubmit('submitInjellikRegenerateCronToken')) {
            InjellikConfig::set(InjellikConfig::CRON_TOKEN, Tools::passwdGen(32));
            Tools::redirectAdmin($this->url(['conf' => 4]) . '#injellik-data');
        } elseif (Tools::isSubmit('submitInjellikResetHistory')) {
            InjellikConfig::set(InjellikConfig::IMPORT_LAST_ORDER, 0);
            InjellikConfig::set(InjellikConfig::IMPORT_ORDERS_COUNT, 0);
            Tools::redirectAdmin($this->url(['conf' => 4]) . '#injellik-data');
        } elseif (Tools::isSubmit('submitInjellikFinishOnboarding')) {
            InjellikConfig::set(InjellikConfig::ONBOARDED, 1);
            Tools::redirectAdmin($this->stepUrl('done'));
        }

        return parent::postProcess();
    }

    private function processConnection()
    {
        $url = InjellikApiClient::normalizeUrl(Tools::getValue('api_url'));
        $login = trim((string) Tools::getValue('api_login'));
        $password = (string) Tools::getValue('api_password');
        if ($password === '') {
            $password = (string) InjellikConfig::get(InjellikConfig::API_PASSWORD);
        }

        if (!Validate::isAbsoluteUrl($url)) {
            $this->errors[] = $this->trans('The API URL is not valid.', [], InjellikConfig::DOMAIN_ADMIN);
        }
        if ($login === '' || $password === '') {
            $this->errors[] = $this->trans('The login and the password are required.', [], InjellikConfig::DOMAIN_ADMIN);
        }
        if ($this->errors) {
            return;
        }

        InjellikConfig::set(InjellikConfig::API_URL, $url);
        InjellikConfig::set(InjellikConfig::API_LOGIN, $login);
        InjellikConfig::set(InjellikConfig::API_PASSWORD, $password);
        InjellikConfig::set(InjellikConfig::JWT, '');
        InjellikConfig::set(InjellikConfig::JWT_EXPIRES, 0);
        InjellikApiClient::reset();
        InjellikCache::clear();

        $test = $this->testConnection($url, $login, $password);
        if (!$test['ok']) {
            $this->errors[] = $test['message'];

            return;
        }

        Tools::redirectAdmin(Tools::getValue('onboarding') ? $this->stepUrl('catalog') : $this->url(['conf' => 4]));
    }

    private function processPlacements()
    {
        $input = Tools::getValue('placements');
        $input = is_array($input) ? $input : [];
        $placements = [];
        foreach (InjellikConfig::defaultPlacements() as $key => $default) {
            $row = isset($input[$key]) && is_array($input[$key]) ? $input[$key] : [];
            $placements[$key] = [
                'enabled' => !empty($row['enabled']),
                'strategy' => isset($row['strategy']) ? (string) $row['strategy'] : $default['strategy'],
                'fallback' => isset($row['fallback']) ? (string) $row['fallback'] : $default['fallback'],
                'limit' => isset($row['limit']) ? (int) $row['limit'] : $default['limit'],
                'title' => isset($row['title']) && is_array($row['title']) ? $row['title'] : [],
            ];
        }
        InjellikConfig::savePlacements($placements);
        InjellikCache::clear();

        if (Tools::getValue('onboarding')) {
            InjellikConfig::set(InjellikConfig::ONBOARDED, 1);
            Tools::redirectAdmin($this->stepUrl('done'));
        }
        Tools::redirectAdmin($this->url(['conf' => 4]) . '#injellik-placements');
    }

    private function processAdvanced()
    {
        InjellikConfig::set(InjellikConfig::RENDER_MODE, Tools::getValue('render_mode') === InjellikConfig::RENDER_SERVER ? InjellikConfig::RENDER_SERVER : InjellikConfig::RENDER_AJAX);
        InjellikConfig::set(InjellikConfig::SEND_MODE, Tools::getValue('send_mode') === InjellikConfig::SEND_CRON ? InjellikConfig::SEND_CRON : InjellikConfig::SEND_REALTIME);
        InjellikConfig::set(InjellikConfig::TIMEOUT, max(1, min(10, (int) Tools::getValue('timeout'))));
        InjellikConfig::set(InjellikConfig::CACHE_TTL, max(0, min(86400, (int) Tools::getValue('cache_ttl'))));
        InjellikConfig::set(InjellikConfig::HIDE_OUT_OF_STOCK, (int) (bool) Tools::getValue('hide_out_of_stock'));
        InjellikConfig::set(InjellikConfig::EXCLUDE_BOTS, (int) (bool) Tools::getValue('exclude_bots'));
        InjellikConfig::set(InjellikConfig::AUTO_SYNC, (int) (bool) Tools::getValue('auto_sync'));
        InjellikCache::clear();

        Tools::redirectAdmin($this->url(['conf' => 4]) . '#injellik-advanced');
    }

    /**
     * @return array{ok: bool, message: string, status: array|null}
     */
    private function testConnection($url, $login, $password)
    {
        $client = new InjellikApiClient($url, $login, $password, 5, null, false);
        try {
            $status = $client->status();
        } catch (InjellikApiException $e) {
            return ['ok' => false, 'status' => null, 'message' => $this->trans('The API cannot be reached: %s', [$e->getMessage()], InjellikConfig::DOMAIN_ADMIN)];
        }
        try {
            $client->authenticate();
        } catch (InjellikApiException $e) {
            $message = $e->getStatus() === 401
                ? $this->trans('The API rejected the login or the password.', [], InjellikConfig::DOMAIN_ADMIN)
                : $this->trans('Authentication failed: %s', [$e->getMessage()], InjellikConfig::DOMAIN_ADMIN);

            return ['ok' => false, 'status' => $status, 'message' => $message];
        }

        return ['ok' => true, 'status' => $status, 'message' => $this->trans('Connection successful.', [], InjellikConfig::DOMAIN_ADMIN)];
    }

    /* ------------------------------------------------------------------
     * AJAX actions (views/js/admin.js)
     * ------------------------------------------------------------------ */

    /**
     * Back-office jobs are not on the visitor path: they can wait longer than the front timeout.
     */
    public function init()
    {
        parent::init();
        InjellikApiClient::forShop((int) $this->context->shop->id)->setTimeout(20);
    }

    public function ajaxProcessTestConnection()
    {
        $url = Tools::getValue('api_url') !== false ? InjellikApiClient::normalizeUrl(Tools::getValue('api_url')) : InjellikConfig::get(InjellikConfig::API_URL);
        $login = Tools::getValue('api_login') !== false ? trim((string) Tools::getValue('api_login')) : InjellikConfig::get(InjellikConfig::API_LOGIN);
        $password = (string) Tools::getValue('api_password');
        if ($password === '') {
            $password = (string) InjellikConfig::get(InjellikConfig::API_PASSWORD);
        }
        $this->json($this->testConnection($url, $login, $password));
    }

    public function ajaxProcessSyncCatalog()
    {
        $idShop = (int) $this->context->shop->id;
        $afterId = (int) Tools::getValue('after_id');
        $done = (int) Tools::getValue('done_count');
        $total = InjellikSync::countProducts($idShop);

        try {
            $batch = InjellikSync::syncCatalogBatch($idShop, (int) Configuration::get('PS_LANG_DEFAULT'), $afterId);
        } catch (InjellikApiException $e) {
            InjellikConfig::recordError($e->getMessage(), $idShop);
            $this->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        $done += $batch['processed'];
        if ($batch['done']) {
            InjellikConfig::set(InjellikConfig::CATALOG_SYNCED_AT, date('Y-m-d H:i:s'), $idShop);
            InjellikConfig::set(InjellikConfig::CATALOG_COUNT, $done, $idShop);
            InjellikCache::clear();
        }

        $this->json([
            'ok' => true,
            'done' => $batch['done'],
            'after_id' => $batch['last_id'],
            'done_count' => $done,
            'total' => $total,
            'message' => $batch['done']
                ? $this->trans('%d products synchronised.', [$done], InjellikConfig::DOMAIN_ADMIN)
                : $this->trans('%d / %d products', [$done, $total], InjellikConfig::DOMAIN_ADMIN),
        ]);
    }

    public function ajaxProcessImportOrders()
    {
        $idShop = (int) $this->context->shop->id;
        $since = (string) Tools::getValue('since');
        $since = Validate::isDate($since) ? $since : null;
        $afterId = (int) InjellikConfig::get(InjellikConfig::IMPORT_LAST_ORDER, $idShop);
        $doneCount = (int) Tools::getValue('done_count');
        $total = $doneCount + InjellikSync::countOrders($idShop, $afterId, $since);

        try {
            $batch = InjellikSync::importOrdersBatch($idShop, $afterId, $since);
        } catch (InjellikApiException $e) {
            InjellikConfig::recordError($e->getMessage(), $idShop);
            $this->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        $doneCount += $batch['processed'];
        InjellikConfig::set(InjellikConfig::IMPORT_LAST_ORDER, $batch['last_id'], $idShop);
        InjellikConfig::set(InjellikConfig::IMPORT_ORDERS_COUNT, (int) InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_COUNT, $idShop) + $batch['processed'], $idShop);
        if ($batch['done']) {
            InjellikConfig::set(InjellikConfig::IMPORT_ORDERS_AT, date('Y-m-d H:i:s'), $idShop);
        }

        $this->json([
            'ok' => true,
            'done' => $batch['done'],
            'done_count' => $doneCount,
            'total' => max($total, $doneCount),
            'message' => $batch['done']
                ? $this->trans('%d orders imported.', [$doneCount], InjellikConfig::DOMAIN_ADMIN)
                : $this->trans('%d / %d orders', [$doneCount, $total], InjellikConfig::DOMAIN_ADMIN),
        ]);
    }

    private function json(array $data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /* ------------------------------------------------------------------
     * Pages
     * ------------------------------------------------------------------ */

    private function renderOnboarding()
    {
        $step = $this->step();
        $idShop = (int) $this->context->shop->id;

        $this->context->smarty->assign([
            'injellik' => $this->commonVariables() + [
                'step' => $step,
                'steps' => $this->stepLabels(),
                'step_index' => array_search($step, self::STEPS, true),
                'step_urls' => array_combine(self::STEPS, array_map([$this, 'stepUrl'], self::STEPS)),
                'signup_url' => self::SIGNUP_URL,
                'product_count' => InjellikSync::countProducts($idShop),
                'order_count' => InjellikSync::countOrders($idShop),
                'history_since' => date('Y-m-d', strtotime('-1 year')),
                'catalog_synced_at' => InjellikConfig::get(InjellikConfig::CATALOG_SYNCED_AT, $idShop),
                'orders_imported_at' => InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_AT, $idShop),
            ],
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/onboarding.tpl');
    }

    private function renderConfiguration()
    {
        $idShop = (int) $this->context->shop->id;

        $this->context->smarty->assign([
            'injellik' => $this->commonVariables() + [
                'catalog_synced_at' => InjellikConfig::get(InjellikConfig::CATALOG_SYNCED_AT, $idShop),
                'catalog_count' => (int) InjellikConfig::get(InjellikConfig::CATALOG_COUNT, $idShop),
                'product_count' => InjellikSync::countProducts($idShop),
                'orders_imported_at' => InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_AT, $idShop),
                'orders_imported' => (int) InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_COUNT, $idShop),
                'orders_remaining' => InjellikSync::countOrders($idShop, (int) InjellikConfig::get(InjellikConfig::IMPORT_LAST_ORDER, $idShop)),
                'history_since' => date('Y-m-d', strtotime('-1 year')),
                'cron_url' => $this->context->link->getModuleLink($this->module->name, 'cron', ['token' => InjellikConfig::get(InjellikConfig::CRON_TOKEN)], true, null, $idShop),
                'cron_catalog_url' => $this->context->link->getModuleLink($this->module->name, 'cron', ['token' => InjellikConfig::get(InjellikConfig::CRON_TOKEN), 'task' => 'catalog'], true, null, $idShop),
                'last_cron' => InjellikConfig::get(InjellikConfig::LAST_CRON, $idShop),
                'advanced' => [
                    'render_mode' => InjellikConfig::get(InjellikConfig::RENDER_MODE),
                    'send_mode' => InjellikConfig::get(InjellikConfig::SEND_MODE),
                    'timeout' => (int) InjellikConfig::get(InjellikConfig::TIMEOUT),
                    'cache_ttl' => (int) InjellikConfig::get(InjellikConfig::CACHE_TTL),
                    'hide_out_of_stock' => (bool) InjellikConfig::get(InjellikConfig::HIDE_OUT_OF_STOCK),
                    'exclude_bots' => (bool) InjellikConfig::get(InjellikConfig::EXCLUDE_BOTS),
                    'auto_sync' => (bool) InjellikConfig::get(InjellikConfig::AUTO_SYNC),
                ],
                'onboarding_url' => $this->stepUrl('welcome'),
                'multishop_warning' => Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP,
            ],
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/configuration.tpl');
    }

    private function commonVariables()
    {
        return [
            'form_url' => $this->url(),
            'ajax_url' => $this->url(['ajax' => 1]),
            'dashboard_url' => $this->context->link->getAdminLink('AdminInjellikDashboard'),
            'configuration_url' => $this->url(),
            'logo' => $this->module->getPathUri() . 'logo.png',
            'version' => $this->module->version,
            'connection' => [
                'url' => InjellikConfig::get(InjellikConfig::API_URL),
                'login' => InjellikConfig::get(InjellikConfig::API_LOGIN),
                'has_password' => (bool) InjellikConfig::get(InjellikConfig::API_PASSWORD),
                'configured' => InjellikConfig::isConfigured(),
            ],
            'placements' => $this->placementsForForm(),
            'strategies' => $this->strategyLabels(),
            'languages' => Language::getLanguages(false),
            'default_lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
            'errors' => $this->errors,
            'posted' => [
                'api_url' => Tools::getValue('api_url'),
                'api_login' => Tools::getValue('api_login'),
            ],
        ];
    }

    private function stepLabels()
    {
        $d = InjellikConfig::DOMAIN_ADMIN;

        return [
            'welcome' => $this->trans('Welcome', [], $d),
            'connection' => $this->trans('Connection', [], $d),
            'catalog' => $this->trans('Catalog', [], $d),
            'history' => $this->trans('Order history', [], $d),
            'placements' => $this->trans('Blocks', [], $d),
            'done' => $this->trans('Done', [], $d),
        ];
    }

    private function strategyLabels()
    {
        return InjellikConfig::strategyLabels($this->context->getTranslator());
    }

    private function placementsForForm()
    {
        $d = InjellikConfig::DOMAIN_ADMIN;
        $labels = [
            'product' => [$this->trans('Product page', [], $d), $this->trans('Below the product description.', [], $d)],
            'cart' => [$this->trans('Cart', [], $d), $this->trans('Below the cart summary, computed from the products in the cart.', [], $d)],
            'order_confirmation' => [$this->trans('Order confirmation', [], $d), $this->trans('After the order, computed from the products ordered.', [], $d)],
            'home' => [$this->trans('Home page', [], $d), $this->trans('Personalized selection on the home page.', [], $d)],
            'category' => [$this->trans('Category page', [], $d), $this->trans('Below the product list of the categories.', [], $d)],
            'custom' => [$this->trans('Widget and other hooks', [], $d), $this->trans('Blocks inserted with {widget} or hooked on another position in Design / Positions.', [], $d)],
        ];

        $placements = [];
        foreach (InjellikConfig::placements() as $key => $placement) {
            $placement['key'] = $key;
            $placement['label'] = $labels[$key][0];
            $placement['help'] = $labels[$key][1];
            $placement['default_title'] = InjellikRecommender::defaultTitle($placement['strategy'], $this->context->getTranslator());
            $placements[$key] = $placement;
        }

        return $placements;
    }
}
