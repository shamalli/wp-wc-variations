<?php

// Класс для работы с вариативными товарами (остается без изменений)
class DynamicVariationSelector {
    private $product_id;
    private $transient_key;
    public function __construct($product_id) {
        $this->product_id = $product_id;
        $this->transient_key = 'product_variations';
    }
    
    /**
     * Получение данных из API с кэшированием
     */
    public function get_product_data() {
        // Пробуем получить данные из кэша
        $cached_data = get_transient($this->transient_key);
        
        if ($cached_data !== false && isset($cached_data['products'][$this->product_id])) {
            return $cached_data['products'][$this->product_id];
        }
        
        // Если нет в кэше, загружаем из API
        $api_url = home_url('/api-data.json');
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'sslverify' => false
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['products'][$this->product_id])) {
            return false;
        }
        
        // Кэшируем на 10 минут
        set_transient($this->transient_key, $data, 10 * MINUTE_IN_SECONDS);
        
        if (isset($data['products'][$this->product_id])) {
            return $data['products'][$this->product_id];
        }
    }
    
    /**
     * Валидация комбинации
     */
    public function validate_combination($color, $size, $product_data) {
        if (!isset($product_data['compatibility'][$color])) {
            return false;
        }
        
        if (!in_array($size, $product_data['compatibility'][$color])) {
            return false;
        }
        
        $combination_key = $color . '_' . $size;
        
        if (!isset($product_data['combinations'][$combination_key])) {
            return false;
        }
        
        $combination = $product_data['combinations'][$combination_key];
        
        return $combination['available'] && $combination['stock'] > 0;
    }
    
    /**
     * Получение информации о комбинации
     */
    public function get_combination_info($color, $size, $product_data) {
        $combination_key = $color . '_' . $size;
        
        if (!isset($product_data['combinations'][$combination_key])) {
            return false;
        }
        
        return $product_data['combinations'][$combination_key];
    }
}