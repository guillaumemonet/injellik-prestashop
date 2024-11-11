<?php

class InjellikRecommendationsManager {

    public static function handleViewAction($productId) {
        InjellikApi::sendAction('VIEW', $productId);
    }
}
