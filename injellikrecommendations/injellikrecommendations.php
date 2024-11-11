<?php

use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/InjellikApi.php';
require_once dirname(__FILE__) . '/classes/InjellikRecommendationsManager.php';
require_once dirname(__FILE__) . '/classes/InjellikBulkImport.php';
require_once dirname(__FILE__) . '/classes/InjellikSessionManager.php';

class InjellikRecommendations extends Module implements WidgetInterface {

    public function __construct() {
        $this->name = 'injellikrecommendations';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.3';
        $this->author = 'Web-Nest';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Injellik Recommendations');
        $this->description = $this->l('Displays product recommendations and logs user interactions with an external API.');
    }

    public function install() {
        return parent::install() &&
                $this->registerHook('displayFooterProduct') &&
                $this->registerHook('displayShoppingCart') &&
                $this->registerHook('actionCartUpdateQuantityBefore') &&
                $this->registerHook('actionValidateOrder') &&
                Configuration::updateValue('INJELLIK_API_URL', '') &&
                Configuration::updateValue('INJELLIK_API_LOGIN', '') &&
                Configuration::updateValue('INJELLIK_API_PASSWORD', '');
    }

    public function uninstall() {
        return parent::uninstall() &&
                Configuration::deleteByName('INJELLIK_API_URL') &&
                Configuration::deleteByName('INJELLIK_API_LOGIN') &&
                Configuration::deleteByName('INJELLIK_API_PASSWORD');
    }

    public function hookDisplayFooterProduct($params) {
        $productId = $params['product']['id_product'];
        InjellikApi::sendAction('VIEW', $productId);

        $mostViewedItems = InjellikApi::getMostViewed()['most_viewed_items'];

        $products = $this->getProductsTemplates($mostViewedItems);
        // Assigner le tableau des produits récupérés à Smarty
        $this->smarty->assign('products', $products);
        return $this->display(__FILE__, 'views/templates/front/recommendations.tpl');
    }

    public function hookDisplayShoppingCart($params) {
        // Exemple : récupérer le contenu du panier
        $cart = $this->context->cart;

        // Assigner des données à Smarty
        $this->context->smarty->assign([
            'cartTotal' => $cart->getOrderTotal(),
            'cartProducts' => $cart->getProducts(),
        ]);

        // Retourner le template à afficher dans la page panier
        return $this->display(__FILE__, 'views/templates/front/recommendations.tpl');
    }

    public function hookActionCartUpdateQuantityBefore($params) {
        // Récupération de l'ID du produit et de l'ID du client
        $productId = $params['product']->id;
        $quantity = $params['quantity'];

        // Déterminer l'action en fonction de la quantité
        if ($quantity > 0) {
            // Action d'ajout ou de mise à jour de quantité
            InjellikApi::sendAction('ADDCART', $productId);
        } else {
            // Action de suppression si la quantité est 0
            InjellikApi::sendAction('REMOVECART', $productId);
        }
    }

    public function hookActionValidateOrder($params) {
        // Boucle sur chaque produit de la commande
        foreach ($params['order']->getProducts() as $product) {
            $productId = $product['product_id'];
            // Appel de l'API pour chaque produit acheté
            InjellikApi::sendAction('BUY', $productId);
        }
    }

    public function getWidgetVariables($hookName, array $configuration) {
        $productIds = [];
        if (!empty($productIds)) {
            $products = null;

            if (!empty($products)) {
                return [
                    'products' => $products,
                ];
            }
        }

        return false;
    }

    public function renderWidget($hookName, array $configuration) {

        return $this->fetch('views/templates/front/recommendations.tpl');
    }

    public function getContent() {
        $output = '';
        if (Tools::isSubmit('submitInjellikConfig')) {
            Configuration::updateValue('INJELLIK_API_URL', Tools::getValue('INJELLIK_API_URL'));
            Configuration::updateValue('INJELLIK_API_LOGIN', Tools::getValue('INJELLIK_API_LOGIN'));
            Configuration::updateValue('INJELLIK_API_PASSWORD', Tools::getValue('INJELLIK_API_PASSWORD'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        if (Tools::isSubmit('submitImportOrders')) {
            InjellikBulkImport::importOrders();
            $output .= $this->displayConfirmation($this->l('Orders imported successfully.'));
        }

        if (Tools::isSubmit('submitImportProducts')) {
            InjellikBulkImport::importProducts();
            $output .= $this->displayConfirmation($this->l('Products imported successfully.'));
        }

        return $output . $this->renderConfigurationForm();
    }

    protected function renderConfigurationForm() {
        $fields_form = [
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Injellik API Settings')
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('API URL'),
                            'name' => 'INJELLIK_API_URL',
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('API Login'),
                            'name' => 'INJELLIK_API_LOGIN',
                            'required' => true
                        ],
                        [
                            'type' => 'password',
                            'label' => $this->l('API Password'),
                            'name' => 'INJELLIK_API_PASSWORD',
                            'required' => true
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save')
                    ]
                ]
            ],
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Data Import Options')
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('Last Imported Order ID'),
                            'name' => 'INJELLIK_LAST_IMPORTED_ORDER_ID',
                            'readonly' => true
                        ]
                    ],
                    'buttons' => [
                        [
                            'type' => 'button',
                            'name' => 'submitImportOrders',
                            'id' => 'submitImportOrders',
                            'class' => 'btn btn-primary',
                            'title' => $this->l('Import Orders in Bulk'),
                            'text' => $this->l('Import Orders in Bulk'), // Texte du bouton
                            'onclick' => 'importOrdersInBulk();'
                        ],
                        [
                            'type' => 'button',
                            'name' => 'submitImportProducts',
                            'id' => 'submitImportProducts',
                            'class' => 'btn btn-primary',
                            'title' => $this->l('Import Products in Bulk'),
                            'text' => $this->l('Import Products in Bulk'), // Texte du bouton
                            'onclick' => 'importProductsInBulk();'
                        ]
                    ]
                ]
            ],
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Injellik Information')
                    ],
                    'description' => $this->l('Injellik is a recommendation API that allows you to enhance your store with personalized product recommendations. To get started with Injellik, please register on the following page: <a href="https://www.web-nest.fr/injellik" target="_blank">https://www.web-nest.fr/injellik</a>')
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->submit_action = 'submitInjellikConfig';
        $helper->fields_value = [
            'INJELLIK_API_URL' => Configuration::get('INJELLIK_API_URL'),
            'INJELLIK_API_LOGIN' => Configuration::get('INJELLIK_API_LOGIN'),
            'INJELLIK_API_PASSWORD' => Configuration::get('INJELLIK_API_PASSWORD'),
            'INJELLIK_LAST_IMPORTED_ORDER_ID' => Configuration::get('INJELLIK_LAST_IMPORTED_ORDER_ID'), // ID de la dernière commande importée
        ];
        $helper->module = $this;

        return $helper->generateForm($fields_form);
    }

    private function getProductsTemplates($idProducts) {
        if (!empty($idProducts)) {
            $showPrice = true; //(bool) Configuration::get('CROSSSELLING_DISPLAY_PRICE');

            $assembler = new ProductAssembler($this->context);

            $presenterFactory = new ProductPresenterFactory($this->context);
            $presentationSettings = $presenterFactory->getPresentationSettings();
            if (version_compare(_PS_VERSION_, '1.7.5', '>=')) {
                $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
                        new ImageRetriever(
                                $this->context->link
                        ),
                        $this->context->link,
                        new PriceFormatter(),
                        new ProductColorsRetriever(),
                        $this->context->getTranslator()
                );
            } else {
                $presenter = new \PrestaShop\PrestaShop\Core\Product\ProductListingPresenter(
                        new ImageRetriever(
                                $this->context->link
                        ),
                        $this->context->link,
                        new PriceFormatter(),
                        new ProductColorsRetriever(),
                        $this->context->getTranslator()
                );
            }

            $productsForTemplate = [];

            $presentationSettings->showPrices = $showPrice;

            if (is_array($idProducts)) {
                foreach ($idProducts as $productId) {
                    $assembled = $assembler->assembleProduct(['id_product' => (int) $productId['sku']]);
                    if ($assembled !== false) {
                        $productsForTemplate[] = $presenter->present(
                                $presentationSettings,
                                $assembled,
                                $this->context->language
                        );
                    }
                }
            }

            return $productsForTemplate;
        }
    }
}
