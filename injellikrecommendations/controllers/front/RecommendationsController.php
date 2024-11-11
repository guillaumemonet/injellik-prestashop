
<?php

class RecommendationsController extends ModuleFrontController {

    public function initContent() {
        parent::initContent();

        $productId = Tools::getValue('id_product');
        $recommendations = InjellikApi::getRecommendations($productId);

        $this->context->smarty->assign([
            'recommendations' => $recommendations,
            'link' => $this->context->link,
        ]);

        $this->setTemplate('module:injellikrecommendations/views/templates/front/recommendations.tpl');
    }

    public function postProcess() {
        $productId = Tools::getValue('product_id');

        if ($productId) {
            InjellikApi::sendAction('CLICK', $productId);
            $productLink = $this->context->link->getProductLink((int) $productId);
            Tools::redirect($productLink);
        }
    }
}
