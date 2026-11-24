<?php
/**
 * banners-api.php
 * Kayrja3 ghi JSON dyal produits (mn cache 3la l'aghlab, khafif bezaf).
 * Kaytsandi mn JS f posts.php b fetch() mora ma tal3 page, bach
 * l banners matbtach l page mn البداية.
 */
require __DIR__ . '/janabio-products.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // browser cache 5 min

$products = get_janabio_products();
shuffle($products);
echo json_encode($products, JSON_UNESCAPED_UNICODE);
