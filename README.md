# Art Lib Tools

Набор переиспользуемых PHP-хелперов и утилит для разработки под WordPress и WooCommerce.

Пакет: `art/lib-tools` **1.3.0** (MIT). Неймспейс: `Art\LibTools\`.

Требования: PHP 8.0+, WordPress 5.5+. Это библиотека, не плагин: хуки регистрируются только после явного вызова
`boot()`.

---

## Установка

Подключается через Composer с указанием VCS-репозитория:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/artikus11/art-lib-tools.git"
    }
  ],
  "require": {
    "art/lib-tools": "dev-master"
  }
}
```

---

## Структура библиотеки

```text
art-lib-tools/
├── src/
│   ├── Helpers/
│   │   ├── LogHelper.php       # Универсальное логирование (WooCommerce Logger + error_log)
│   │   ├── TextHelper.php      # Обрезка текста, очистка от шорткодов/медиа, склонение слов
│   │   ├── UrlAccessible.php   # Быстрая проверка доступности URL (HEAD/GET с fallback)
│   │   └── UrlNormalizer.php   # Нормализация URL с внутренним кэшированием
│   └── WordPress/
│       ├── LocalPlugins/
│       │   └── PluginUpdateDisabler.php  # Отключение проверок обновлений для локальных/самописных плагинов
│       └── WooCommerce/
│           └── HPOSCompatible.php        # Декларация совместимости с HPOS (High-Performance Order Storage)
├── tests/                      # PHPUnit + WP_Mock
├── phpunit.xml.dist
└── phpcs.xml
```

---

## Использование

### Helpers

#### `LogHelper`

Пишет сообщения в WooCommerce Logger (если WooCommerce активен) или использует системный `error_log`. Позволяет
привязать отладку к переключателю в опциях плагина.

```php
use Art\LibTools\Helpers\LogHelper;

// Обычная запись
LogHelper::log( 'Данные обработаны успешно', 'info', 'my-plugin-slug' );

// Логирование массивов/объектов
LogHelper::log( $data_array, 'debug', 'my-plugin-slug' );

// Запись с проверкой флага в get_option('my_plugin_settings')['debug_logging']
// Сообщения уровня 'error' пишутся всегда, независимо от настройки
LogHelper::log( $debug_info, 'debug', 'my-plugin-slug', 'my_plugin_settings' );
```

---

#### `TextHelper`

Содержит методы для безопасной очистки и форматирования текста.

```php
use Art\LibTools\Helpers\TextHelper;

// Обрезка текста (удаляет теги, медиа, Gutenberg-блоки и шорткоды)
$short_text = TextHelper::get_truncate( $post_content, 50, 'letters', '...' );
$short_words = TextHelper::get_truncate( $post_content, 20, 'words' );

// Склонение слов после чисел
echo 1 . ' ' . TextHelper::plural_form( 1, 'книга,книги,книг' );  // 1 книга
echo 3 . ' ' . TextHelper::plural_form( 3, [ 'товар', 'товара', 'товаров' ] ); // 3 товара
```

---

#### `UrlAccessible`

Проверяет доступность URL с помощью `HEAD`-запроса. В случае ответа `405 Method Not Allowed` выполняет повторный
диапазонный `GET`-запрос.

```php
use Art\LibTools\Helpers\UrlAccessible;

$is_online = UrlAccessible::is_url_accessible( 'https://example.com/file.jpg', [
    'timeout' => 3,
] );

if ( $is_online ) {
    // URL доступен
}
```

---

#### `UrlNormalizer`

Заменяет CDN/субдоменные варианты ссылок на канонические и кэширует результаты в памяти во избежание повторной обработки
на больших массивах данных.

```php
use Art\LibTools\Helpers\UrlNormalizer;

// Нормализация отдельного URL
$clean_url = UrlNormalizer::normalize_url( 'https://s1.example.com/image.jpg' );

// Рекурсивная нормализация массива
$clean_array = UrlNormalizer::process_array( $data_array );

// Очистка внутреннего кэша
UrlNormalizer::clear_cache();
```

---

### WordPress & WooCommerce

#### `PluginUpdateDisabler`

Удаляет выбранные плагины из структуры `site_transient_update_plugins`, предотвращая лишние запросы на сервер обновлений
и перехватывая окна информации о плагине.

```php
use Art\LibTools\WordPress\LocalPlugins\PluginUpdateDisabler;

$disabler = new PluginUpdateDisabler();

$disabler->register(
    'my-custom-plugin',                   // Slug плагина
    'my-custom-plugin/my-custom-plugin.php', // Относительный путь к главному файлу
    [
        'name'    => 'My Custom Plugin',
        'version' => '1.0.0',
    ]
);

$disabler->boot();
```

---

#### `HPOSCompatible`

Безопасно декларирует совместимость с High-Performance Order Storage (HPOS) в WooCommerce на событии
`before_woocommerce_init`.

```php
use Art\LibTools\WordPress\WooCommerce\HPOSCompatible;

// В основном файле плагина:
$hpos = new HPOSCompatible( __FILE__, true );
$hpos->boot();
```

---

## Разработка

```bash
composer install
composer test    # PHPUnit 9 + WP_Mock
composer phpcs   # WordPress Coding Standards
composer phpcbf  # автоисправление стиля
```

Юнит-тесты покрывают хелперы (`LogHelper`, `TextHelper`, `UrlAccessible`, `UrlNormalizer`) и WP-классы (
`PluginUpdateDisabler`, `HPOSCompatible`). WordPress-функции мокаются через WP_Mock, WooCommerce `FeaturesUtil` — через
stub в `tests/stubs/`.
