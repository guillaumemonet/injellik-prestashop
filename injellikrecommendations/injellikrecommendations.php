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

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

require_once __DIR__ . '/classes/InjellikConfig.php';
require_once __DIR__ . '/classes/InjellikApiClient.php';
require_once __DIR__ . '/classes/InjellikCache.php';
require_once __DIR__ . '/classes/InjellikIdentity.php';
require_once __DIR__ . '/classes/InjellikEventQueue.php';
require_once __DIR__ . '/classes/InjellikStats.php';
require_once __DIR__ . '/classes/InjellikSync.php';
require_once __DIR__ . '/classes/InjellikRecommender.php';
require_once __DIR__ . '/classes/InjellikInstaller.php';

class InjellikRecommendations extends Module implements WidgetInterface
{
    public function __construct()
    {
        $this->name = 'injellikrecommendations';
        $this->tab = 'advertising_marketing';
        $this->version = '2.1.2';
        $this->author = 'Web-Nest';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('Injellik Recommendations', [], InjellikConfig::DOMAIN_ADMIN);
        $this->description = $this->trans('Personalized product recommendations powered by the Injellik API: tracks views, carts and orders, and displays recommendation blocks on your pages.', [], InjellikConfig::DOMAIN_ADMIN);
        $this->confirmUninstall = $this->trans('All the tracking history stored in your shop will be deleted. The data already sent to Injellik is kept.', [], InjellikConfig::DOMAIN_ADMIN);
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() && (new InjellikInstaller($this))->install();
    }

    public function uninstall()
    {
        return (new InjellikInstaller($this))->uninstall() && parent::uninstall();
    }

    /**
     * "Configure" button of the module manager: configuration page, or the onboarding when it is not done.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminInjellikConfiguration'));
    }

    /**
     * Discreet notice on the other back-office pages when the API refuses the shop
     * (the pages of the module display a detailed one).
     */
    public function hookDisplayDashboardTop()
    {
        return $this->safely(function () {
            return $this->renderApiStateNotice();
        }, '');
    }

    private function renderApiStateNotice()
    {
        $controller = $this->context->controller;
        if (!isset($this->context->employee) || !$this->context->employee || $controller instanceof AdminInjellikConfigurationController
            || $controller instanceof AdminInjellikDashboardController) {
            return '';
        }
        $state = InjellikConfig::apiState((int) $this->context->shop->id);
        if (!$state) {
            return '';
        }

        $template = $this->context->smarty->createTemplate($this->getLocalPath() . 'views/templates/admin/_api_state.tpl');
        $template->assign([
            'api_state' => $state,
            'links' => InjellikConfig::links(),
            'compact' => true,
            'configuration_url' => $this->context->link->getAdminLink('AdminInjellikConfiguration'),
        ]);

        return $template->fetch();
    }

    /* ------------------------------------------------------------------
     * Tracking
     * ------------------------------------------------------------------ */

    /**
     * Front assets, visitor identity and the VIEW action of product pages.
     */
    public function hookActionFrontControllerSetMedia()
    {
        $this->safely(function () {
            $this->setFrontMedia();
        });
    }

    private function setFrontMedia()
    {
        if (!InjellikConfig::isConfigured()) {
            return;
        }

        // The blocks need the script for every visitor; only the tracking ignores bots.
        $this->context->controller->registerJavascript('modules-injellik', 'modules/' . $this->name . '/views/js/front.js', ['position' => 'bottom', 'priority' => 200, 'attributes' => 'defer']);
        $this->context->controller->registerStylesheet('modules-injellik', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
        Media::addJsDef(['injellik' => [
            'renderUrl' => $this->context->link->getModuleLink($this->name, 'recommendations', [], null, null, null, true),
            'trackUrl' => $this->context->link->getModuleLink($this->name, 'track', [], null, null, null, true),
            'token' => Tools::getToken(false),
            // Without fastcgi_finish_request(), the events are not sent during the request (the visitor would
            // wait for the API): the script asks for it afterwards, in a request nobody waits for.
            'flush' => !InjellikEventQueue::canReleaseResponse() && InjellikConfig::get(InjellikConfig::SEND_MODE) !== InjellikConfig::SEND_CRON,
        ]]);

        if (!$this->isTrackingEnabled()) {
            return;
        }
        InjellikIdentity::touch($this->context);

        $controller = $this->context->controller;
        if (isset($controller->php_self) && $controller->php_self === 'product' && !Tools::getValue('ajax')) {
            $idProduct = (int) Tools::getValue('id_product');
            if ($idProduct > 0) {
                $this->track('VIEW', $idProduct);
            }
        }
    }

    public function hookActionCartUpdateQuantityBefore($params)
    {
        $this->safely(function () use ($params) {
            $this->trackCartUpdate($params);
        });
    }

    private function trackCartUpdate($params)
    {
        if (!$this->isTrackingEnabled() || (isset($params['operator']) && $params['operator'] !== 'up')) {
            return;
        }
        $product = isset($params['product']) ? $params['product'] : null;
        if ($product instanceof Product && $product->id) {
            $this->track('ADDCART', (int) $product->id);
        }
    }

    public function hookActionValidateOrder($params)
    {
        $this->safely(function () use ($params) {
            $this->trackOrder($params);
        });
    }

    private function trackOrder($params)
    {
        if (!InjellikConfig::isConfigured() || empty($params['order']) || !$params['order'] instanceof Order) {
            return;
        }
        /** @var Order $order */
        $order = $params['order'];
        $user = InjellikIdentity::customerUuid((int) $order->id_customer);
        // The order joins the browsing session of the customer. When it is validated outside of it
        // (payment notification, back-office order), it gets a session of its own, as in the history
        // import, instead of a random one created for a request that is not a visit.
        $visit = InjellikIdentity::hasSession($this->context);
        $session = $visit ? InjellikIdentity::sessionUuid($this->context) : 'order-' . (int) $order->id;

        foreach ($order->getProducts() as $line) {
            InjellikEventQueue::push((int) $order->id_shop, 'BUY', (string) $line['product_id'], $user, $session, $line['product_name']);
        }
        if ($visit) {
            InjellikIdentity::resetSession($this->context);
        }
    }

    /**
     * Queues an action of the current visitor (never calls the API during the request).
     */
    public function track($actionType, $idProduct, $rating = null)
    {
        if (!$this->isTrackingEnabled()) {
            return null;
        }

        return InjellikEventQueue::push(
            (int) $this->context->shop->id,
            $actionType,
            (string) (int) $idProduct,
            InjellikIdentity::userUuid($this->context),
            InjellikIdentity::sessionUuid($this->context),
            null,
            $rating
        );
    }

    /**
     * Sessions are the basis of the subscription billing: nothing is tracked for the bots (when they are
     * excluded), nor without a cookie, where every action would create a new visitor and a new session.
     */
    private function isTrackingEnabled()
    {
        return InjellikConfig::isConfigured()
            && $this->context->cookie
            && !(InjellikConfig::get(InjellikConfig::EXCLUDE_BOTS) && InjellikIdentity::isBot());
    }

    /* ------------------------------------------------------------------
     * Catalog synchronisation
     * ------------------------------------------------------------------ */

    public function hookActionObjectProductAddAfter($params)
    {
        $this->markProductDirty($params);
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        $this->markProductDirty($params);
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        $this->markProductDirty($params);
    }

    private function markProductDirty($params)
    {
        $this->safely(function () use ($params) {
            if (!empty($params['object']) && $params['object'] instanceof Product && InjellikConfig::isConfigured()) {
                InjellikSync::markProductDirty((int) $params['object']->id);
            }
        });
    }

    /**
     * The module must never break the shop: an error in a hook is logged and the hook is skipped.
     *
     * @param mixed $default value returned on error
     */
    private function safely(callable $callback, $default = null)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            try {
                PrestaShopLogger::addLog('[Injellik] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 3, null, 'Module', 0, false);
            } catch (Throwable $ignored) {
            }

            return $default;
        }
    }

    /* ------------------------------------------------------------------
     * Display: hooks and widget
     * ------------------------------------------------------------------ */

    public function hookDisplayFooterProduct($params)
    {
        return $this->renderWidget('displayFooterProduct', $params);
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        return $this->renderWidget('displayShoppingCartFooter', $params);
    }

    public function hookDisplayOrderConfirmation2($params)
    {
        return $this->renderWidget('displayOrderConfirmation2', $params);
    }

    public function hookDisplayHome($params)
    {
        return $this->renderWidget('displayHome', $params);
    }

    public function hookDisplayFooterCategory($params)
    {
        return $this->renderWidget('displayFooterCategory', $params);
    }

    /**
     * Widget usable anywhere in a template:
     *   {widget name='injellikrecommendations'}
     *   {widget name='injellikrecommendations' placement='product'}
     *   {widget name='injellikrecommendations' strategy='bought' product_id=$product.id limit=4 title='Complete the look'}
     * and on any hook through Design > Positions.
     */
    public function renderWidget($hookName, array $configuration)
    {
        return $this->safely(function () use ($hookName, $configuration) {
            $variables = $this->buildWidgetVariables($hookName, $configuration);

            return $variables ? (new InjellikRecommender($this, $this->context))->renderBlock($variables['request']) : '';
        }, '');
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return $this->safely(function () use ($hookName, $configuration) {
            return $this->buildWidgetVariables($hookName, $configuration);
        }, []);
    }

    private function buildWidgetVariables($hookName, array $configuration)
    {
        // Suspended subscription or invalid licence key: no block, without calling the API.
        if (!InjellikConfig::isConfigured() || InjellikConfig::isApiBlocked((int) $this->context->shop->id)) {
            return [];
        }

        $explicit = !empty($configuration['placement']);
        $key = $explicit ? (string) $configuration['placement'] : InjellikConfig::placementForHook($hookName);
        if ($key === null || !InjellikConfig::placement($key)) {
            $key = 'custom';
        }
        $placement = InjellikConfig::placement($key);
        if (!$placement['enabled'] && !$explicit) {
            return [];
        }

        $overrides = [];
        foreach (['strategy', 'fallback', 'limit', 'title'] as $option) {
            if (isset($configuration[$option]) && is_scalar($configuration[$option])) {
                $overrides[$option] = (string) $configuration[$option];
            }
        }

        $recommender = new InjellikRecommender($this, $this->context);

        return ['request' => $recommender->buildRequest($key, $this->contextProducts($key, $configuration), $overrides)];
    }

    /**
     * Products the recommendations are computed from: explicit parameter, hook parameters, then page context.
     *
     * @return int[]
     */
    private function contextProducts($placementKey, array $configuration)
    {
        if (!empty($configuration['product_id'])) {
            return array_map('intval', is_array($configuration['product_id']) ? $configuration['product_id'] : explode(',', (string) $configuration['product_id']));
        }
        if (!empty($configuration['product']) && (is_array($configuration['product']) || $configuration['product'] instanceof ArrayAccess)) {
            if (!empty($configuration['product']['id_product'])) {
                return [(int) $configuration['product']['id_product']];
            }
        }
        if (!empty($configuration['order']) && $configuration['order'] instanceof Order) {
            return array_map('intval', array_column($configuration['order']->getProducts(), 'product_id'));
        }

        $controller = $this->context->controller;
        if (isset($controller->php_self) && $controller->php_self === 'product' && Tools::getValue('id_product')) {
            return [(int) Tools::getValue('id_product')];
        }
        if (($placementKey === 'cart' || (isset($controller->php_self) && $controller->php_self === 'cart')) && $this->context->cart && $this->context->cart->id) {
            return array_map('intval', array_column($this->context->cart->getProducts(), 'id_product'));
        }

        return [];
    }
}
