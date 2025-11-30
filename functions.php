<?php

include_once "dynamic-variations-selector.php";

/**
 * AJAX обработчик для получения данных комбинации
 */
function get_variation_data_ajax() {
    // Проверка nonce для безопасности
    if (!wp_verify_nonce($_POST['nonce'], 'variation_selector_nonce')) {
        wp_die('Security check failed');
    }
    
    // Санитизация входных данных
    $product_id = intval($_POST['product_id']);
    $color = sanitize_text_field($_POST['color']);
    $size = sanitize_text_field($_POST['size']);
    
    $selector = new DynamicVariationSelector($product_id);
    $product_data = $selector->get_product_data();
    
    if (!$product_data) {
        wp_send_json_error(__('Информация о продукте не найдена', 'woocommerce'));
    }
    
    $combination_info = $selector->get_combination_info($color, $size, $product_data);
    $is_valid = $selector->validate_combination($color, $size, $product_data);
    
    if (!$combination_info) {
        wp_send_json_error(__('Комбинация не доступна!', 'woocommerce'));
    }
    
    wp_send_json_success([
        'price' => wc_price($combination_info['price']),
        'raw_price' => $combination_info['price'],
        'stock' => $combination_info['stock'],
        'image' => $combination_info['image'],
        'available' => $combination_info['available'],
        'is_valid' => $is_valid,
        'combination_key' => $color . '_' . $size
    ]);
}
add_action('wp_ajax_get_variation_data', 'get_variation_data_ajax');
add_action('wp_ajax_nopriv_get_variation_data', 'get_variation_data_ajax');

/**
 * Вывод селектора вариаций на странице магазина
 */
function display_variation_selector_on_shop() {
    global $product;
    
    if (!$product) return;
    
    $product_id = $product->get_id();
    $selector = new DynamicVariationSelector($product_id);
    $product_data = $selector->get_product_data();
    
    if (!$product_data) {
        return; // Не показываем, если нет данных
    }
    
    $colors = ['red', 'yellow', 'green'];
    $sizes = ['S', 'M', 'L'];
    
    ?>
    <div class="variation-selector variation-selector-shop" data-product-id="<?php echo esc_attr($product_id); ?>">
        
        <!-- Выбор цвета -->
        <div class="color-selector">
            <div class="color-options">
                <?php foreach ($colors as $color): ?>
                    <?php 
                    $available_sizes = isset($product_data['compatibility'][$color]) ? 
                        $product_data['compatibility'][$color] : [];
                    $has_available = false;
                    
                    foreach ($available_sizes as $size) {
                        if ($selector->validate_combination($color, $size, $product_data)) {
                            $has_available = true;
                            break;
                        }
                    }
                    ?>
                    <div class="color-option <?php echo !$has_available ? 'disabled' : ''; ?>" 
                         data-color="<?php echo esc_attr($color); ?>"
                         data-available-sizes="<?php echo esc_attr(json_encode($available_sizes)); ?>">
                        <span class="color-circle color-<?php echo esc_attr($color); ?>" 
                              title="<?php echo esc_attr(ucfirst($color)); ?>"></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Выбор размера -->
        <div class="size-selector">
            <div class="size-options">
                <?php foreach ($sizes as $size): ?>
                    <div class="size-option disabled" data-size="<?php echo esc_attr($size); ?>">
                        <?php echo esc_html($size); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Информация о товаре -->
        <div class="variation-info-shop">
            <div class="price-container" style="display: none;">
                <span class="price"><?php echo $product->get_price_html(); ?></span>
            </div>
            <div class="stock-container">
                <span class="stock-status"></span>
            </div>
        </div>
        
        <!-- Сообщения об ошибках -->
        <div class="variation-messages-shop" style="display: none;"></div>

		<div class="variation-add-to-cart-button" style="display: none;">
			<?php custom_loop_add_to_cart(); ?>
		</div>
        
        <!-- Скрытые поля для добавления в корзину -->
        <input type="hidden" name="variation_color" value="">
        <input type="hidden" name="variation_size" value="">
        <input type="hidden" name="variation_price" value="">
        
    </div>
    
    <style>
    .variation-selector-shop {
        margin: 10px 0;
        padding: 10px;
        border: 1px solid #eee;
        border-radius: 5px;
        background: #f9f9f9;
    }
    
    .color-options, .size-options {
        display: flex;
        gap: 5px;
        margin: 5px 0;
        flex-wrap: wrap;
    }
    
    .color-option, .size-option {
        cursor: pointer;
        padding: 5px;
        border: 2px solid #ddd;
        border-radius: 4px;
        transition: all 0.3s ease;
        font-size: 12px;
    }
    
    .color-option {
        padding: 3px;
    }
    
    .color-option.selected, .size-option.selected {
        border-color: #007cba;
        background-color: #f0f8ff;
    }
    
    .color-option.disabled, .size-option.disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
    
    .color-option:not(.disabled):hover, .size-option:not(.disabled):hover {
        border-color: #999;
    }
    
    .color-circle {
        display: block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 1px solid #ccc;
    }
    
    .color-red { background-color: #ff4444; }
    .color-yellow { background-color: #ffdd44; }
    .color-green { background-color: #44ff44; }
    
    .variation-info-shop {
        margin-top: 8px;
        font-size: 12px;
    }
    
    .variation-info-shop .price {
        font-weight: bold;
        color: #007cba;
    }
    
    .stock-status.in-stock { 
        color: green; 
        font-size: 11px;
    }
    
    .stock-status.out-of-stock { 
        color: red; 
        font-size: 11px;
    }
    
    .variation-messages-shop {
        padding: 5px;
        margin: 5px 0;
        border-radius: 3px;
        font-size: 11px;
    }
    
    .variation-messages-shop.error {
        background-color: #ffeaa7;
        border: 1px solid #fdcb6e;
        color: #e17055;
    }

	.variation-selector-shop .added_to_cart.wc-forward {
		display: none;
	}
    
    /* Адаптивность для сетки товаров */
    @media (max-width: 768px) {
        .variation-selector-shop {
            padding: 5px;
        }
        
        .color-options, .size-options {
            gap: 3px;
        }
        
        .color-option, .size-option {
            padding: 2px;
            font-size: 10px;
        }
        
        .color-circle {
            width: 14px;
            height: 14px;
        }
    }
    </style>
    <?php
}

/**
 * Добавляем селектор в карточку товара на странице магазина
 */
function add_variation_selector_to_shop_loop() {
    display_variation_selector_on_shop();
}
add_action('woocommerce_after_shop_loop_item_title', 'add_variation_selector_to_shop_loop', 5);
add_action('woocommerce_single_product_summary', 'add_variation_selector_to_shop_loop', 5);

function custom_loop_add_to_cart() {
    global $product;
    
    if (!$product) return;
    
    $product_id = $product->get_id();
    
    echo sprintf(
        '<a href="%s" data-quantity="%s" class="%s product_type_%s add_to_cart_button ajax_add_to_cart variation-add-to-cart" data-product_id="%s" data-product_sku="%s" aria-label="%s" rel="nofollow">%s</a>',
        esc_url($product->add_to_cart_url()),
        esc_attr(1),
        esc_attr('button' . ($product->is_purchasable() ? '' : ' disabled')),
        esc_attr($product->get_type()),
        esc_attr($product->get_id()),
        esc_attr($product->get_sku()),
        esc_attr__('Добавить в корзину', 'woocommerce'),
        esc_html__('Добавить в корзину', 'woocommerce')
    );
}

/**
 * Добавляем скрипты для страниц магазина
 */
function variation_selector_scripts() {
    if (is_shop() || is_product_category() || is_product_tag() || is_product()) {
        wp_enqueue_script('jquery');
        
        // Регистрируем скрипт для селектора
        wp_register_script('variation-selector-shop', get_template_directory_uri() . '/wc-variations/js/variation-selector-shop.js', ['jquery'], '1.0', true);
        
        // Локализация скрипта
        wp_localize_script('variation-selector-shop', 'variation_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('variation_selector_nonce'),
			'i18n_add_to_cart' => __('Добавить в корзину', 'woocommerce'),
			'i18n_adding_to_cart' => __('Добавление...', 'woocommerce'),
			'i18n_added_to_cart' => __('Товар добавлен в корзину!', 'woocommerce'),
			'i18n_choose_color_and_size' => __('Сначала выберите цвер и размер!', 'woocommerce'),
			'i18n_combination_not_avilalble' => __('Комбинация не доступна!', 'woocommerce'),
			'i18n_in_stock' => __('в наличии', 'woocommerce'),
			'i18n_out_of_stock' => __('Нет в наличии', 'woocommerce'),
			'loading_data_error' => __('Ошибка загрузки данных!', 'woocommerce'),
			'connection_error' => __('Ошибка соединения!', 'woocommerce'),
			'error' => __('Ошибка', 'woocommerce'),
        ]);
        
        wp_enqueue_script('variation-selector-shop');
    }
}
add_action('wp_enqueue_scripts', 'variation_selector_scripts');

/**
 * Обработка быстрого добавления в корзину со страницы магазина
 */
function handle_quick_add_to_cart() {
    if (!wp_verify_nonce($_POST['nonce'], 'variation_selector_nonce')) {
        wp_die('Security check failed');
    }
    
    $product_id = intval($_POST['product_id']);
    $color = sanitize_text_field($_POST['color']);
    $size = sanitize_text_field($_POST['size']);
    $quantity = intval($_POST['quantity']) ?: 1;
    
    // Проверяем валидность комбинации
    $selector = new DynamicVariationSelector($product_id);
    $product_data = $selector->get_product_data();
    
    if (!$product_data || !$selector->validate_combination($color, $size, $product_data)) {
        wp_send_json_error(__('Комбинация не доступна!', 'woocommerce'));
    }
    
    $combination_info = $selector->get_combination_info($color, $size, $product_data);
    
    // Добавляем товар в корзину
    $cart_item_data = [
        'variation_color' => $color,
        'variation_size' => $size,
        'variation_price' => $combination_info['price']
    ];
    
    $cart_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);
    
    if ($cart_key) {
        wp_send_json_success([
            'message' => __('Товар добавлен в корзину', 'woocommerce'),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total()
        ]);
    } else {
        wp_send_json_error(__('Ошибка добавления в корзину', 'woocommerce'));
    }
}
add_action('wp_ajax_handle_quick_add_to_cart', 'handle_quick_add_to_cart');
add_action('wp_ajax_nopriv_handle_quick_add_to_cart', 'handle_quick_add_to_cart');

/**
 * Валидация при обычном добавлении в корзину (для страницы товара)
 */
function validate_custom_variation_before_add_to_cart($passed, $product_id, $quantity) {
    if (is_product()) { // Только на странице товара
        if (empty($_POST['variation_color']) || empty($_POST['variation_size'])) {
            wc_add_notice(__('Пожалуйста, выберите цвет и размер', 'woocommerce'), 'error');
            return false;
        }
        
        $color = sanitize_text_field($_POST['variation_color']);
        $size = sanitize_text_field($_POST['variation_size']);
        
        $selector = new DynamicVariationSelector($product_id);
        $product_data = $selector->get_product_data();
        
        if (!$product_data) {
            wc_add_notice(__('Ошибка данных товара', 'woocommerce'), 'error');
            return false;
        }
        
        if (!$selector->validate_combination($color, $size, $product_data)) {
            wc_add_notice(__('Выбранная комбинация недоступна', 'woocommerce'), 'error');
            return false;
        }
    }
    
    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'validate_custom_variation_before_add_to_cart', 10, 3);

// Остальные функции для работы с корзиной остаются без изменений
function add_custom_variation_data_to_cart_item($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['variation_color'])) {
        $cart_item_data['variation_color'] = sanitize_text_field($_POST['variation_color']);
    }
    
    if (isset($_POST['variation_size'])) {
        $cart_item_data['variation_size'] = sanitize_text_field($_POST['variation_size']);
    }
    
    if (isset($_POST['variation_price'])) {
        $cart_item_data['variation_price'] = floatval($_POST['variation_price']);
    }
    
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_custom_variation_data_to_cart_item', 10, 3);

function set_custom_variation_price($cart_object) {
    foreach ($cart_object->get_cart() as $cart_item) {
        if (isset($cart_item['variation_price'])) {
            $cart_item['data']->set_price($cart_item['variation_price']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'set_custom_variation_price');

function display_custom_variation_data_in_cart($item_data, $cart_item) {
    if (isset($cart_item['variation_color'])) {
        $item_data[] = [
            'key' => __('Цвет', 'woocommerce'),
            'value' => wc_clean($cart_item['variation_color'])
        ];
    }
    
    if (isset($cart_item['variation_size'])) {
        $item_data[] = [
            'key' => __('Размер', 'woocommerce'),
            'value' => wc_clean($cart_item['variation_size'])
        ];
    }
    
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_custom_variation_data_in_cart', 10, 2);
