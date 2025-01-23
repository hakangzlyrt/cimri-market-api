<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'Parser.php';
require_once 'ProductService.php';

// Hata raporlamayı açıyoruz
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Parametreleri al
    $query = $_GET['q'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $sort = $_GET['sort'] ?? null;

    // Zorunlu parametreleri kontrol et
    if (!$query) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Query parameter is required'
        ]);
        exit;
    }

    // Sıralama seçeneklerini kontrol et
    $allowedSortOptions = ['price-asc', 'specUnit-asc'];
    if ($sort && !in_array($sort, $allowedSortOptions)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid sort parameter. Allowed values: ' . implode(', ', $allowedSortOptions)
        ]);
        exit;
    }

    // Sayfa numarasını kontrol et
    if ($page < 1) {
        $page = 1;
    }

    $service = new ProductService();
    $result = $service->getProducts($query, $page, $sort);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 