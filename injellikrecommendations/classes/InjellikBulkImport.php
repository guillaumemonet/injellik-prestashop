<?php

class InjellikBulkImport {

    /**
     * Import all orders as actions in the API, starting from the last imported order.
     */
    public static function importOrders() {
        // Récupération de la dernière commande importée
        $lastImportedOrderId = Configuration::get('INJELLIK_LAST_IMPORTED_ORDER_ID', null);
        $orders = self::getOrdersSinceLastImported($lastImportedOrderId);

        foreach ($orders as $order) {
            // Initialiser une nouvelle session pour chaque commande
            $sessionId = uniqid('session_', true);
            foreach ($order->getProducts() as $product) {
                try {
                    // Envoie de l'action 'BUY' pour chaque produit de la commande
                    InjellikApi::sendAction('BUY', $product['product_id'], $sessionId);
                } catch (Exception $e) {
                    // Gestion d'erreur pour éviter un plantage en cas d'échec de l'appel API
                    PrestaShopLogger::addLog('Error sending bulk order action to Injellik API: ' . $e->getMessage(), 3);
                }
            }
            // Mise à jour de l'ID de la dernière commande importée
            Configuration::updateValue('INJELLIK_LAST_IMPORTED_ORDER_ID', $order->id);
        }
    }

    /**
     * Import all products to the API in bulk, starting from the last imported product.
     */
    public static function importProducts() {
        // Récupération du dernier produit importé
        $lastImportedProductId = Configuration::get('INJELLIK_LAST_IMPORTED_PRODUCT_ID', null);
        $products = self::getProductsSinceLastImported($lastImportedProductId);

        foreach ($products as $product) {
            try {
                // Envoie des informations produit à l'API
                InjellikApi::sendProduct($product['id_product'], $product['name'], $product['price']);
                // Met à jour l'ID du dernier produit importé
                Configuration::updateValue('INJELLIK_LAST_IMPORTED_PRODUCT_ID', $product['id_product']);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('Error sending bulk product data to Injellik API: ' . $e->getMessage(), 3);
            }
        }
    }

    /**
     * Get all orders since the last imported order ID, filtered to only include valid orders.
     *
     * @param int|null $lastImportedOrderId The ID of the last imported order.
     * @return array List of valid orders since the last imported order.
     */
    protected static function getOrdersSinceLastImported($lastImportedOrderId) {
        $sql = new DbQuery();
        $sql->select('*')
                ->from('orders')
                ->where('id_order > ' . (int) $lastImportedOrderId)
                // Ajouter la condition pour les commandes valides
                ->where('current_state IN (' . self::getValidOrderStates() . ')')
                ->orderBy('id_order ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get all products since the last imported product ID, filtered to exclude archived products.
     *
     * @param int|null $lastImportedProductId The ID of the last imported product.
     * @return array List of products since the last imported product.
     */
    protected static function getProductsSinceLastImported($lastImportedProductId) {
        $sql = new DbQuery();
        $sql->select('id_product, name, price')
                ->from('product')
                ->where('id_product > ' . (int) $lastImportedProductId)
                // Ajouter la condition pour les produits actifs
                ->where('active = 1 AND deleted = 0')
                ->orderBy('id_product ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get a comma-separated list of valid order state IDs.
     *
     * @return string Comma-separated list of valid order state IDs.
     */
    private static function getValidOrderStates() {
        // Vous pouvez ajuster ces ID selon les états que vous considérez comme valides
        $validStates = [2, 3, 4]; // Exemple : 2 = payé, 3 = en cours de livraison, 4 = livré
        return implode(',', $validStates);
    }
}
