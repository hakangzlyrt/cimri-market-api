<?php

class Parser {
    private $html;
    private $headers;

    public function __construct() {
        $this->headers = [
            'authority' => 'www.cimri.com',
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'cache-control' => 'no-cache',
            'pragma' => 'no-cache',
            'sec-ch-ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"macOS"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ];
    }

    public function fetchContent($url) {
        $maxRetries = 3;
        $retry = 0;
        $lastError = null;

        while ($retry < $maxRetries) {
            try {
                return $this->tryFetchContent($url);
            } catch (Exception $e) {
                $lastError = $e;
                $retry++;
                sleep(2);
            }
        }

        throw new Exception("Maksimum deneme sayısına ulaşıldı. Son hata: " . $lastError->getMessage());
    }

    private function tryFetchContent($url) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $this->formatHeaders(),
            CURLOPT_COOKIEJAR => 'cookies.txt',
            CURLOPT_COOKIEFILE => 'cookies.txt',
            CURLOPT_USERAGENT => $this->headers['user-agent'],
            CURLOPT_HEADER => true,
            CURLOPT_AUTOREFERER => true
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $this->html = substr($response, $headerSize);

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode\nHeaders: $headers");
        }

        file_put_contents('debug_headers.txt', $headers);
        file_put_contents('debug.html', $this->html);
        
        return $this;
    }

    private function formatHeaders() {
        $formatted = [];
        foreach ($this->headers as $key => $value) {
            $formatted[] = "$key: $value";
        }
        return $formatted;
    }

    public function parseProducts() {
        if (empty($this->html)) {
            throw new Exception('HTML content is empty');
        }

        // application/ld+json script etiketlerini bul
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->html, $matches);

        if (empty($matches[1])) {
            throw new Exception('JSON-LD data not found');
        }

        $products = [];
        $foundProducts = false;

        foreach ($matches[1] as $jsonLd) {
            $data = json_decode($jsonLd, true);
            
            // Debug: JSON verisini kaydet
            file_put_contents('debug_jsonld.json', $jsonLd);

            if (isset($data['@type']) && $data['@type'] === 'WebPage' && isset($data['significantLinks'])) {
                $products = $this->extractProductsFromLinks($data['significantLinks']);
                $foundProducts = true;
                break;
            }
        }

        if (!$foundProducts) {
            throw new Exception('Product data not found in scripts');
        }

        // Her ürün için detay bilgilerini çek
        $productsWithDetails = [];
        foreach ($products as $product) {
            try {
                $details = $this->fetchProductDetails($product['url']);
                if (!empty($details)) {
                    $productsWithDetails[] = array_merge($product, $details);
                } else {
                    $productsWithDetails[] = $product;
                }
                // Rate limiting
                usleep(500000); // 0.5 saniye bekle
            } catch (Exception $e) {
                // Hata olursa detaysız devam et
                $productsWithDetails[] = $product;
            }
        }

        return $productsWithDetails;
    }

    private function fetchProductDetails($url) {
        try {
            $this->fetchContent($url);
            
            // application/ld+json script etiketlerini bul
            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->html, $matches);
            
            foreach ($matches[1] as $jsonLd) {
                $data = json_decode($jsonLd, true);
                
                if (isset($data['@type']) && $data['@type'] === 'Product') {
                    return [
                        'price' => $data['offers']['lowPrice'] ?? null,
                        'high_price' => $data['offers']['highPrice'] ?? null,
                        'currency' => $data['offers']['priceCurrency'] ?? 'TRY',
                        'brand' => $data['brand']['name'] ?? null,
                        'description' => $data['description'] ?? null,
                        'image' => $data['image'] ?? null,
                        'markets' => $this->extractMarkets()
                    ];
                }
            }

            // HTML'den fiyat ve market bilgilerini çek
            return [
                'markets' => $this->extractMarkets()
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    private function extractMarkets() {
        $markets = [];
        
        // Market kartlarını bul
        preg_match_all('/<div[^>]*class="[^"]*MerchantCard[^"]*"[^>]*>(.*?)<\/div>/s', $this->html, $matches);
        
        foreach ($matches[1] as $card) {
            try {
                $market = [];

                // Market adı
                if (preg_match('/<span[^>]*class="[^"]*MerchantName[^"]*"[^>]*>(.*?)<\/span>/s', $card, $nameMatch)) {
                    $market['name'] = strip_tags($nameMatch[1]);
                }

                // Fiyat
                if (preg_match('/data-price="([^"]*)"/', $card, $priceMatch)) {
                    $market['price'] = $priceMatch[1];
                }

                // Market URL
                if (preg_match('/href="([^"]*)"/', $card, $urlMatch)) {
                    $market['url'] = 'https://www.cimri.com' . $urlMatch[1];
                }

                if (!empty($market)) {
                    $markets[] = $market;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $markets;
    }

    private function extractProductsFromLinks($links) {
        $products = [];
        foreach ($links as $link) {
            // URL'den ürün bilgilerini çıkar
            if (preg_match('/en-ucuz-(.*?)-fiyatlari,(\d+)$/', $link, $matches)) {
                $name = str_replace('-', ' ', $matches[1]);
                $id = $matches[2];
                
                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'url' => $link
                ];
            }
        }
        return $products;
    }
} 