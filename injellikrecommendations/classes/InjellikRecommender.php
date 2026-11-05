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

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

/**
 * Fetches recommendations from the API and turns them into product listings.
 *
 * A block is described by a "request": placement, strategy, fallback, limit, title and the
 * source products. Requests are signed when they are rendered asynchronously so that the
 * front controller only renders blocks the shop has generated.
 */
class InjellikRecommender
{
    const TEMPLATE = 'module:injellikrecommendations/views/templates/hook/recommendations.tpl';
    const PLACEHOLDER = 'module:injellikrecommendations/views/templates/hook/placeholder.tpl';

    /** @var Context */
    private $context;
    /** @var Module */
    private $module;

    public function __construct(Module $module, ?Context $context = null)
    {
        $this->module = $module;
        $this->context = $context ?: Context::getContext();
    }

    /**
     * Builds the block request of a placement, with optional overrides (widget parameters).
     *
     * @param int[] $productIds products the recommendations are computed from
     */
    public function buildRequest($placementKey, array $productIds = [], array $overrides = [])
    {
        $placement = InjellikConfig::placement($placementKey) ?: InjellikConfig::placement('custom');
        $strategies = InjellikConfig::strategies();

        $request = [
            'placement' => InjellikConfig::placement($placementKey) ? $placementKey : 'custom',
            'strategy' => $placement['strategy'],
            'fallback' => $placement['fallback'],
            'limit' => (int) $placement['limit'],
            'title' => '',
            'products' => array_values(array_unique(array_filter(array_map('intval', $productIds)))),
        ];

        if (!empty($overrides['strategy']) && isset($strategies[$overrides['strategy']])) {
            $request['strategy'] = $overrides['strategy'];
        }
        if (isset($overrides['fallback']) && ($overrides['fallback'] === '' || isset($strategies[$overrides['fallback']]))) {
            $request['fallback'] = $overrides['fallback'];
        }
        if (!empty($overrides['limit'])) {
            $request['limit'] = max(1, min(24, (int) $overrides['limit']));
        }
        if (isset($overrides['title']) && trim((string) $overrides['title']) !== '') {
            $request['title'] = Tools::substr(trim(strip_tags((string) $overrides['title'])), 0, 128);
        }
        if ($request['title'] === '') {
            $idLang = (int) $this->context->language->id;
            $request['title'] = !empty($placement['title'][$idLang])
                ? $placement['title'][$idLang]
                : self::defaultTitle($request['strategy'], $this->context->getTranslator());
        }
        $request['products'] = array_slice($request['products'], 0, 20);

        return $request;
    }

    /**
     * Renders the block: the products directly ("server" mode), or a placeholder loaded by front.js.
     */
    public function renderBlock(array $request)
    {
        if (InjellikConfig::get(InjellikConfig::RENDER_MODE) === InjellikConfig::RENDER_SERVER) {
            return $this->renderProducts($request);
        }

        $payload = base64_encode(json_encode($request));
        $this->context->smarty->assign('injellik_placeholder', [
            'placement' => $request['placement'],
            'payload' => $payload,
            'signature' => self::sign($payload),
        ]);

        return $this->module->fetch(self::PLACEHOLDER);
    }

    /**
     * Renders the product list, or an empty string when there is nothing to show.
     */
    public function renderProducts(array $request)
    {
        $ids = $this->recommend($request);
        $products = $ids ? $this->present($ids) : [];
        if (!$products) {
            return '';
        }

        InjellikStats::impression((int) $this->context->shop->id, $request['placement']);
        $this->context->smarty->assign('injellik', [
            'placement' => $request['placement'],
            'title' => $request['title'],
            'products' => $products,
        ]);

        return $this->module->fetch(self::TEMPLATE);
    }

    /**
     * Product ids recommended for a request: main strategy, then fallback, without the source products.
     *
     * @return int[]
     */
    public function recommend(array $request)
    {
        $exclude = array_flip($request['products']);
        $ids = [];
        foreach (array_unique(array_filter([$request['strategy'], $request['fallback']])) as $strategy) {
            foreach ($this->fetch($strategy, $request['products'], $request['limit'] + count($exclude) + 4) as $id) {
                if (!isset($exclude[$id]) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
            $ids = $this->filterAvailable($ids);
            if (count($ids) >= $request['limit']) {
                break;
            }
        }

        return array_slice($ids, 0, $request['limit']);
    }

    /**
     * Calls the API for one strategy. Failures are logged and give an empty list: a block never breaks a page.
     *
     * @param int[] $productIds
     *
     * @return int[]
     */
    public function fetch($strategy, array $productIds, $limit)
    {
        $strategies = InjellikConfig::strategies();
        $idShop = (int) $this->context->shop->id;
        if (!isset($strategies[$strategy]) || !InjellikConfig::isConfigured($idShop) || InjellikConfig::isApiBlocked($idShop)) {
            return [];
        }
        $context = $strategies[$strategy]['context'];
        if ($context === 'items' && !$productIds) {
            return [];
        }

        $limit = max(1, min(100, (int) $limit));
        $user = $context === 'user' ? InjellikIdentity::userUuid($this->context) : null;
        $cacheKey = implode('|', [$this->context->shop->id, $strategy, implode(',', $productIds), $user, $limit]);
        $ttl = $context === 'user' ? 60 : (int) InjellikConfig::get(InjellikConfig::CACHE_TTL);

        // After a refusal of the API, the cache must not display blocks before the API accepts the shop again.
        $cached = InjellikConfig::apiState($idShop) ? null : InjellikCache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $skus = self::extractSkus($this->callApi($strategy, $productIds, $user, $limit));
        } catch (InjellikApiException $e) {
            if ($e->isAccessDenied()) {
                // Suspended subscription or invalid key: the block is hidden, the back office shows why.
                return [];
            }
            if ($e->getStatus() !== 404) {
                InjellikConfig::recordError($e->getMessage());

                return [];
            }
            // Unknown product (not synchronised yet): nothing to recommend, cache it like an empty result.
            $skus = [];
        }

        $ids = [];
        foreach ($skus as $sku) {
            if (ctype_digit((string) $sku) && (int) $sku > 0) {
                $ids[] = (int) $sku;
            }
        }
        InjellikCache::set($cacheKey, $ids, $ttl);

        return $ids;
    }

    private function callApi($strategy, array $productIds, $user, $limit)
    {
        $client = InjellikApiClient::forShop((int) $this->context->shop->id);
        $query = ['limit' => $limit];
        $first = $productIds ? rawurlencode((string) $productIds[0]) : '';
        if (count($productIds) > 1) {
            $query['item_sku'] = array_map('strval', array_slice($productIds, 1));
        }

        switch ($strategy) {
            case 'viewed':
                return $client->get('/api/recommendations/viewed/' . $first, $query);
            case 'bought':
                return $client->get('/api/recommendations/bought/' . $first, $query);
            case 'similar':
                return $client->get('/api/related/' . $first, ['limit' => $limit]);
            case 'personalized':
                return $client->get('/api/recommendations/user/' . rawurlencode($user), ['limit' => $limit]);
            case 'predictive':
                return $client->post('/api/recommendations/predictive', ['user_uuid' => $user, 'limit' => $limit]);
            case 'most_viewed':
                return $client->get('/api/community-rankings/most-viewed', ['limit' => $limit]);
            case 'most_bought':
                return $client->get('/api/community-rankings/most-bought', ['limit' => $limit]);
        }

        return [];
    }

    /**
     * Reads the SKUs of any recommendation or ranking response.
     */
    private static function extractSkus(array $response)
    {
        foreach (['recommendations', 'related_items', 'most_viewed_items', 'most_bought_items'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_filter(array_map(function ($row) {
                    return isset($row['sku']) ? (string) $row['sku'] : null;
                }, $response[$key]));
            }
        }

        return [];
    }

    /**
     * Keeps the products that are active, visible in the catalog and (optionally) in stock, in the API order.
     *
     * @param int[] $ids
     *
     * @return int[]
     */
    private function filterAvailable(array $ids)
    {
        if (!$ids) {
            return [];
        }
        $idShop = (int) $this->context->shop->id;
        $visible = array_map('intval', array_column(Db::getInstance()->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_shop`
             WHERE `id_shop` = ' . $idShop . ' AND `active` = 1 AND `visibility` IN (\'both\', \'catalog\')
               AND `id_product` IN (' . implode(',', array_map('intval', $ids)) . ')'
        ) ?: [], 'id_product'));

        $hideOutOfStock = (bool) InjellikConfig::get(InjellikConfig::HIDE_OUT_OF_STOCK)
            && Configuration::get('PS_STOCK_MANAGEMENT');

        $result = [];
        foreach ($ids as $id) {
            if (!in_array($id, $visible, true)) {
                continue;
            }
            if ($hideOutOfStock
                && StockAvailable::getQuantityAvailableByProduct($id, null, $idShop) <= 0
                && !Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock($id, $idShop))) {
                continue;
            }
            $result[] = $id;
        }

        return $result;
    }

    /**
     * Presents the products like the theme product listings (same miniature template).
     *
     * @param int[] $ids
     */
    public function present(array $ids)
    {
        $assembler = new ProductAssembler($this->context);
        $presenterFactory = new ProductPresenterFactory($this->context);
        $settings = $presenterFactory->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever($this->context->link),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $products = [];
        foreach ($ids as $id) {
            $assembled = $assembler->assembleProduct(['id_product' => (int) $id]);
            if (!empty($assembled) && !empty($assembled['id_product'])) {
                $products[] = $presenter->present($settings, $assembled, $this->context->language);
            }
        }

        return $products;
    }

    /**
     * Title of a block whose placement has no custom title, worded after its strategy.
     */
    public static function defaultTitle($strategy, $translator)
    {
        $domain = InjellikConfig::DOMAIN_SHOP;
        switch ($strategy) {
            case 'viewed':
                return $translator->trans('Customers who viewed this product also viewed', [], $domain);
            case 'bought':
                return $translator->trans('Frequently bought together', [], $domain);
            case 'similar':
                return $translator->trans('Similar products', [], $domain);
            case 'personalized':
            case 'predictive':
                return $translator->trans('Selected for you', [], $domain);
            case 'most_viewed':
                return $translator->trans('Most viewed products', [], $domain);
            case 'most_bought':
                return $translator->trans('Best sellers', [], $domain);
        }

        return $translator->trans('You may also like', [], $domain);
    }

    public static function sign($payload)
    {
        return hash_hmac('sha256', (string) $payload, _COOKIE_KEY_ . '|injellik');
    }

    /**
     * Decodes a signed request from front.js, or returns null when it was tampered with.
     */
    public static function decodeRequest($payload, $signature)
    {
        if (!is_string($payload) || !is_string($signature) || !hash_equals(self::sign($payload), $signature)) {
            return null;
        }
        $request = json_decode((string) base64_decode($payload, true), true);
        if (!is_array($request) || !isset($request['placement'], $request['strategy'], $request['limit'], $request['products'])) {
            return null;
        }
        $request['fallback'] = isset($request['fallback']) ? (string) $request['fallback'] : '';
        $request['title'] = isset($request['title']) ? (string) $request['title'] : '';
        $request['products'] = array_map('intval', (array) $request['products']);
        $request['limit'] = max(1, min(24, (int) $request['limit']));

        return $request;
    }
}
