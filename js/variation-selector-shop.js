jQuery(document).ready(function($) {
    'use strict';
    
    class ShopVariationSelector {
        constructor() {
            this.initEvents();
        }
        
        initEvents() {
            // Выбор цвета на странице магазина
            $('.variation-selector-shop .color-option:not(.disabled)').on('click', (e) => {
                this.handleColorSelect($(e.currentTarget));
            });
            
            // Выбор размера на странице магазина
            $(document).on('click', '.variation-selector-shop .size-option:not(.disabled)', (e) => {
                this.handleSizeSelect($(e.currentTarget));
            });
            
            // Обработка добавления в корзину со страницы магазина
            $(document).on('click', '.variation-add-to-cart', (e) => {
                e.preventDefault();
                this.handleAddToCart($(e.currentTarget));
            });
        }
        
        handleColorSelect($colorOption) {
            const $container = $colorOption.closest('.variation-selector-shop');
            const color = $colorOption.data('color');
            const availableSizes = $colorOption.data('available-sizes') || [];
            
            // Сбрасываем предыдущий выбор в этом контейнере
            $container.find('.color-option').removeClass('selected');
            $colorOption.addClass('selected');
            
            // Обновляем доступные размеры
            this.updateSizeOptions($container, availableSizes);
            
            // Сбрасываем выбранный размер
            $container.find('.size-option').removeClass('selected');
            $container.find('input[name="variation_size"]').val('');
            
            // Очищаем информацию
            this.clearVariationInfo($container);
        }
        
        handleSizeSelect($sizeOption) {
            const $container = $sizeOption.closest('.variation-selector-shop');
            const size = $sizeOption.data('size');
            
            $container.find('.size-option').removeClass('selected');
            $sizeOption.addClass('selected');
            
            $container.find('input[name="variation_size"]').val(size);
            
            // Загружаем данные комбинации
            this.loadCombinationData($container);
        }
        
        updateSizeOptions($container, availableSizes) {
            $container.find('.size-option').each((index, element) => {
                const $sizeOption = $(element);
                const size = $sizeOption.data('size');
                const isAvailable = availableSizes.includes(size);
                
                $sizeOption.toggleClass('disabled', !isAvailable);
                
                if (!isAvailable) {
                    $sizeOption.removeClass('selected');
                }
            });
        }
        
        loadCombinationData($container) {
            const productId = $container.data('product-id');
            const color = $container.find('.color-option.selected').data('color');
            const size = $container.find('.size-option.selected').data('size');
            
            if (!color || !size) {
                return;
            }
            
            $.ajax({
                url: variation_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_variation_data',
                    product_id: productId,
                    color: color,
                    size: size,
                    nonce: variation_ajax.nonce
                },
                beforeSend: () => {
                    this.showLoading($container);
                },
                success: (response) => {
                    if (response.success) {
                        this.updateVariationInfo($container, response.data);
                    } else {
                        this.showError($container, variation_ajax.loading_data_error);
                    }
                },
                error: () => {
                    this.showError($container, variation_ajax.connection_error);
                }
            });
        }
        
        updateVariationInfo($container, data) {
            // Обновляем цену
            $container.find('.price-container').show().find('.price').html(data.price);
            
            // Обновляем остатки
            const stockText = data.stock > 0 ? 
                data.stock + ' ' + variation_ajax.i18n_in_stock : variation_ajax.i18n_out_of_stock;
            const stockClass = data.stock > 0 ? 'in-stock' : 'out-of-stock';

            // скрыть/показать кнопку
            const cartButton = $container.find('.variation-add-to-cart-button');
            if (data.stock > 0) {
                cartButton.show();
            } else {
                cartButton.hide();
            }
            
            $container.find('.stock-container .stock-status')
                .text(stockText)
                .removeClass('in-stock out-of-stock')
                .addClass(stockClass);
            
            // Обновляем скрытые поля
            $container.find('input[name="variation_color"]').val($container.find('.color-option.selected').data('color'));
            $container.find('input[name="variation_price"]').val(data.raw_price);
            
            // Показываем/скрываем сообщения
            if (!data.is_valid) {
                this.showError($container, variation_ajax.i18n_combination_not_avilalble);
            } else {
                this.hideMessages($container);
            }
        }
        
        clearVariationInfo($container) {
            $container.find('.stock-container .stock-status').text('').removeClass('in-stock out-of-stock');
            $container.find('.price-container').hide();
            $container.find('input[name="variation_color"]').val('');
            $container.find('input[name="variation_price"]').val('');
            this.hideMessages($container);
            $container.find('.variation-add-to-cart-button').hide();
        }
        
        handleAddToCart($button) {
            const $container = $button.closest('.product').find('.variation-selector-shop');
            const productId = $container.data('product-id');
            const color = $container.find('input[name="variation_color"]').val();
            const size = $container.find('input[name="variation_size"]').val();
            
            if (!color || !size) {
                alert(variation_ajax.i18n_choose_color_and_size);
                return;
            }
            
            $.ajax({
                url: variation_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'handle_quick_add_to_cart',
                    product_id: productId,
                    color: color,
                    size: size,
                    quantity: 1,
                    nonce: variation_ajax.nonce
                },
                beforeSend: () => {
                    $button.addClass('loading').text(variation_ajax.i18n_adding_to_cart);
                },
                success: (response) => {
                    if (response.success) {
                        this.showAddToCartSuccess($button, response.data);
                        this.updateCartInfo(response.data);
                    } else {
                        alert(variation_ajax.error + ': ' + response.data);
                        $button.removeClass('loading').text(variation_ajax.i18n_add_to_cart);
                    }
                },
                error: () => {
                    alert(variation_ajax.connection_error);
                    $button.removeClass('loading').text(variation_ajax.i18n_add_to_cart);
                }
            });
        }
        
        showAddToCartSuccess($button, data) {
            $button.removeClass('loading').text(variation_ajax.i18n_added_to_cart);
            
            // Восстанавливаем текст кнопки через 2 секунды
            setTimeout(() => {
                $button.text(variation_ajax.i18n_add_to_cart);
            }, 2000);
        }
        
        updateCartInfo(data) {
            // Обновляем счетчик корзины в шапке
            $('.cart-contents-count').text(data.cart_count);
            $('.cart-contents .amount').html(data.cart_total);
            
            // Показываем уведомление WooCommerce если есть
            if (typeof wc_add_to_cart_params !== 'undefined') {
                $(document.body).trigger('wc_fragment_refresh');
            }
        }
        
        showLoading($container) {
            $container.addClass('loading');
        }
        
        hideLoading($container) {
            $container.removeClass('loading');
        }
        
        showError($container, message) {
            $container.find('.variation-messages-shop')
                .html(message)
                .addClass('error')
                .show();
        }
        
        hideMessages($container) {
            $container.find('.variation-messages-shop').hide().removeClass('error');
        }
    }
    
    // Инициализация селектора для страницы магазина
    new ShopVariationSelector();
});