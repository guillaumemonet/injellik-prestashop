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
 * Monitoring page: API health, data synchronisation, event queue, block performance and rankings.
 */
class AdminInjellikDashboardController extends ModuleAdminController
{
    const ACTION_COLORS = [
        'VIEW' => '#25b9d7',
        'CLICK' => '#a56eff',
        'ADDCART' => '#ff9a52',
        'BUY' => '#70b580',
        'RATED' => '#fbbb22',
    ];

    /** @var InjellikRecommendations */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = 'Injellik';
    }

    public function init()
    {
        parent::init();
        InjellikApiClient::forShop((int) $this->context->shop->id)->setTimeout(10);
        if (!InjellikConfig::isOnboarded() && !$this->ajax) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminInjellikConfiguration'));
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['configuration'] = [
            'href' => $this->context->link->getAdminLink('AdminInjellikConfiguration'),
            'desc' => $this->trans('Configuration', [], InjellikConfig::DOMAIN_ADMIN),
            'icon' => 'process-icon-cogs',
        ];
        parent::initPageHeaderToolbar();
    }

    public function postProcess()
    {
        $idShop = (int) $this->context->shop->id;
        $change = $this->ajax || Tools::isSubmit('submitInjellikRetryErrors') || Tools::isSubmit('submitInjellikClearError');
        if ($change && !$this->access('edit')) {
            $message = $this->trans('You do not have permission to edit this.', [], 'Admin.Notifications.Error');
            if ($this->ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'done' => true, 'message' => $message]);
                exit;
            }
            $this->errors[] = $message;

            return false;
        }

        if (Tools::isSubmit('submitInjellikRetryErrors')) {
            InjellikEventQueue::retryErrors($idShop);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminInjellikDashboard') . '&conf=4');
        } elseif (Tools::isSubmit('submitInjellikClearError')) {
            InjellikConfig::set(InjellikConfig::LAST_ERROR, '', $idShop);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminInjellikDashboard') . '&conf=4');
        }

        return parent::postProcess();
    }

    public function ajaxProcessFlushQueue()
    {
        $result = InjellikEventQueue::flush((int) $this->context->shop->id, 200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'done' => $result['pending'] === 0 || $result['sent'] === 0,
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'pending' => $result['pending'],
            'message' => $this->trans('%d sent, %d failed, %d pending', [$result['sent'], $result['failed'], $result['pending']], InjellikConfig::DOMAIN_ADMIN),
        ]);
        exit;
    }

    public function initContent()
    {
        $idShop = (int) $this->context->shop->id;
        $days = 30;

        $this->context->smarty->assign('injellik', [
            'configuration_url' => $this->context->link->getAdminLink('AdminInjellikConfiguration'),
            'form_url' => $this->context->link->getAdminLink('AdminInjellikDashboard'),
            'ajax_url' => $this->context->link->getAdminLink('AdminInjellikDashboard') . '&ajax=1',
            'logo' => $this->module->getPathUri() . 'logo.png',
            'version' => $this->module->version,
            'api_state' => InjellikConfig::apiState($idShop),
            'links' => InjellikConfig::links(),
            'days' => $days,
            'api' => $this->apiHealth($idShop),
            'last_error' => InjellikConfig::lastError($idShop),
            'queue' => InjellikStats::queueSummary($idShop),
            'send_mode' => InjellikConfig::get(InjellikConfig::SEND_MODE),
            'last_cron' => InjellikConfig::get(InjellikConfig::LAST_CRON, $idShop),
            'catalog' => [
                'synced_at' => InjellikConfig::get(InjellikConfig::CATALOG_SYNCED_AT, $idShop),
                'count' => (int) InjellikConfig::get(InjellikConfig::CATALOG_COUNT, $idShop),
                'products' => InjellikSync::countProducts($idShop),
            ],
            'orders' => [
                'imported_at' => InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_AT, $idShop),
                'count' => (int) InjellikConfig::get(InjellikConfig::IMPORT_ORDERS_COUNT, $idShop),
            ],
            'chart' => $this->chart(InjellikStats::eventsPerDay($idShop, $days)),
            'colors' => self::ACTION_COLORS,
            'blocks' => $this->blocks(InjellikStats::byPlacement($idShop, $days)),
            'rankings' => $this->rankings($idShop),
            'problems' => InjellikStats::recentEvents($idShop, 10, true),
            'recent' => InjellikStats::recentEvents($idShop, 15),
        ]);

        $this->content = $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/dashboard.tpl');
        parent::initContent();
    }

    private function apiHealth($idShop)
    {
        $client = InjellikApiClient::forShop($idShop);
        $start = microtime(true);
        try {
            $status = $client->status();
            $latency = (int) round((microtime(true) - $start) * 1000);
            $client->get('/api/actiontypes');

            return ['ok' => true, 'database' => isset($status['database']) ? $status['database'] : null, 'latency' => $latency, 'message' => ''];
        } catch (InjellikApiException $e) {
            return ['ok' => false, 'database' => null, 'latency' => null, 'message' => InjellikConfig::apiErrorMessage($e, $this->context->getTranslator())];
        }
    }

    /**
     * Stacked bars of the events per day, scaled on the busiest day.
     */
    private function chart(array $series)
    {
        $max = 1;
        $totals = [];
        foreach ($series as $day => $counts) {
            $totals[$day] = array_sum($counts);
            $max = max($max, $totals[$day]);
        }

        $bars = [];
        foreach ($series as $day => $counts) {
            $segments = [];
            foreach (self::ACTION_COLORS as $type => $color) {
                if (!empty($counts[$type])) {
                    $segments[] = ['type' => $type, 'count' => $counts[$type], 'color' => $color, 'height' => round(100 * $counts[$type] / $max, 2)];
                }
            }
            foreach ($counts as $type => $count) {
                if (!isset(self::ACTION_COLORS[$type])) {
                    $segments[] = ['type' => $type, 'count' => $count, 'color' => '#bbcdd2', 'height' => round(100 * $count / $max, 2)];
                }
            }
            $bars[] = [
                'day' => $day,
                'label' => Tools::displayDate($day),
                'short' => date('d/m', strtotime($day)),
                'total' => $totals[$day],
                'segments' => $segments,
            ];
        }

        $byType = [];
        foreach ($series as $counts) {
            foreach ($counts as $type => $count) {
                $byType[$type] = (isset($byType[$type]) ? $byType[$type] : 0) + $count;
            }
        }
        arsort($byType);

        return ['bars' => $bars, 'max' => $max, 'total' => array_sum($totals), 'by_type' => $byType];
    }

    private function blocks(array $stats)
    {
        $labels = [];
        foreach (InjellikConfig::placements() as $key => $placement) {
            $labels[$key] = $placement;
        }
        $strategies = InjellikConfig::strategyLabels($this->context->getTranslator());

        $rows = [];
        foreach ($labels as $key => $placement) {
            $row = isset($stats[$key]) ? $stats[$key] : ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0];
            $row['key'] = $key;
            $row['enabled'] = $placement['enabled'];
            $row['strategy'] = isset($strategies[$placement['strategy']]) ? $strategies[$placement['strategy']]['label'] : $placement['strategy'];
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Most viewed and most bought products of the last 30 days, according to the API.
     */
    private function rankings($idShop)
    {
        $client = InjellikApiClient::forShop($idShop);
        $idLang = (int) $this->context->language->id;
        $result = [];
        foreach (['most-viewed' => 'most_viewed_items', 'most-bought' => 'most_bought_items'] as $endpoint => $key) {
            try {
                $response = $client->get('/api/community-rankings/' . $endpoint, ['limit' => 10]);
                $rows = isset($response[$key]) ? $response[$key] : [];
            } catch (InjellikApiException $e) {
                $rows = [];
            }
            foreach ($rows as &$row) {
                $id = (int) $row['sku'];
                $row['id_product'] = $id;
                $row['url'] = $id ? $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => $id], ['id_product' => $id, 'updateproduct' => 1]) : null;
                $name = $id ? Product::getProductName($id, null, $idLang) : '';
                $row['label'] = $name ?: $row['name'];
            }
            unset($row);
            $result[$key] = $rows;
        }

        return $result;
    }
}
