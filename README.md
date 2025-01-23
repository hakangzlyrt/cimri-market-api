# Cimri Market API

Bu API, Cimri.com'dan market ürünlerini çekmek için geliştirilmiş bir PHP uygulamasıdır.

## Kurulum

1. PHP 7.4 veya daha yüksek bir sürüm gereklidir
2. cURL PHP eklentisi aktif olmalıdır
3. Dosyaları web sunucunuza yükleyin
4. Klasöre yazma izni verin (cookies.txt dosyası için)

## Hızlı Başlangıç

Yerel sunucuyu başlatmak için:

```bash
php -S localhost:8000
```

## API Endpoint'leri

### Ürün Arama

```
GET /index.php?q={arama_terimi}
```

#### Parametreler

| Parametre | Tip     | Zorunlu | Açıklama                              |
| --------- | ------- | ------- | ------------------------------------- |
| q         | string  | Evet    | Arama terimi (örn: şeker, süt, ekmek) |
| page      | integer | Hayır   | Sayfa numarası (varsayılan: 1)        |
| sort      | string  | Hayır   | Sıralama seçeneği                     |

#### Sıralama Seçenekleri

- `price-asc`: En düşük fiyat
- `specUnit-asc`: En düşük birim fiyat

#### Örnek İstekler

1. Basit arama:

```
http://localhost:8000/index.php?q=şeker
```

2. Sayfalama ile:

```
http://localhost:8000/index.php?q=şeker&page=2
```

3. Sıralama ile:

```
http://localhost:8000/index.php?q=şeker&sort=price-asc
```

#### Örnek Yanıt

```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": "1431121",
        "name": "kristal 50 kg toz seker",
        "url": "https://www.cimri.com/market/toz-ve-kup-seker/en-ucuz-kristal-50-kg-toz-seker-fiyatlari,1431121",
        "price": "1250.00",
        "high_price": "1450.00",
        "currency": "TRY",
        "brand": "Kristal",
        "description": "50 kg Toz Şeker",
        "image": "https://...",
        "markets": [
          {
            "name": "Market A",
            "price": "1250.00",
            "url": "https://..."
          },
          {
            "name": "Market B",
            "price": "1300.00",
            "url": "https://..."
          }
        ]
      }
    ],
    "page": 1,
    "query": "şeker",
    "sort": null
  }
}
```

## Proje Yapısı

- `index.php`: Ana API endpoint'i
- `Parser.php`: HTML parser ve veri çekme işlemleri
- `ProductService.php`: Ürün servisi ve business logic
- `cookies.txt`: Cookie dosyası (otomatik oluşturulur)

## Hata Kodları

| HTTP Kodu | Açıklama                                     |
| --------- | -------------------------------------------- |
| 200       | Başarılı                                     |
| 400       | Geçersiz istek (eksik veya hatalı parametre) |
| 429       | Çok fazla istek                              |
| 500       | Sunucu hatası                                |

## Güvenlik

- Rate limiting uygulanmıştır
- CORS koruması vardır
- SSL doğrulama devre dışıdır (geliştirme ortamı için)

## Notlar

1. API, Cimri.com'un HTML yapısına bağımlıdır. Site yapısı değişirse güncelleme gerekebilir.
2. Her istek için 0.5 saniye bekleme süresi vardır (rate limiting).
3. Ürün detayları için her ürünün sayfası ayrıca ziyaret edilir.
4. Debug bilgileri `debug.html` ve `debug_headers.txt` dosyalarına kaydedilir.

## Örnek Kullanım (PHP)

```php
<?php
$url = 'http://localhost:8000/index.php';
$params = [
    'q' => 'şeker',
    'page' => 1,
    'sort' => 'price-asc'
];

$url .= '?' . http_build_query($params);
$response = file_get_contents($url);
$data = json_decode($response, true);

print_r($data);
```

## Örnek Kullanım (JavaScript)

```javascript
fetch("http://localhost:8000/index.php?q=şeker")
  .then((response) => response.json())
  .then((data) => console.log(data))
  .catch((error) => console.error("Error:", error));
```

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır.
