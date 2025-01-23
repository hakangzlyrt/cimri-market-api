<?php

class ProductService {
    private $parser;
    private $baseUrl = 'https://www.cimri.com/market/arama';

    public function __construct() {
        $this->parser = new Parser();
    }

    public function getProducts($query, $page = 1, $sort = null) {
        $url = $this->buildUrl($query, $page, $sort);
        
        try {
            $products = $this->parser
                ->fetchContent($url)
                ->parseProducts();
                
            return [
                'success' => true,
                'data' => [
                    'products' => $products,
                    'page' => $page,
                    'query' => $query,
                    'sort' => $sort
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function buildUrl($query, $page, $sort) {
        $url = $this->baseUrl . '?q=' . urlencode($query);
        
        if ($page > 1) {
            $url .= '&page=' . $page;
        }
        
        if ($sort) {
            $url .= '&sort=' . $sort;
        }
        
        return $url;
    }
} 