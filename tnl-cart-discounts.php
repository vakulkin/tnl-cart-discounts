<?php
/**
 * Plugin Name: TNL Cart Discounts
 * Description: Zastosuj niestandardowe zasady rabatowe w zależności od ilości produktów w koszyku, z pominięciem produktów typu 'weight' i 'pallet_weight'.
 * Version: 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Wyjdź, jeśli uzyskano dostęp bezpośrednio
}

if ( ! class_exists( 'TNLCartDiscounts' ) ) {

    class TNLCartDiscounts {

        public function __construct() {
            add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_custom_discounts' ), 20, 1 );
        }

        /**
         * Zastosuj niestandardowe rabaty do koszyka.
         *
         * @param WC_Cart $cart
         */
        public function apply_custom_discounts( $cart ) {
            if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
                return;
            }

            if ( $cart->is_empty() ) {
                return;
            }

            // Pobierz kwalifikujące się pozycje koszyka (z pominięciem produktów typu 'weight' i 'pallet_weight')
            $eligible_items = $this->get_eligible_cart_items( $cart );

            $total_quantity = array_sum( wp_list_pluck( $eligible_items, 'quantity' ) );

            if ( $total_quantity >= 1 && $total_quantity <= 3 ) {
                $this->apply_percentage_discount( $cart, $eligible_items, $total_quantity );
            } elseif ( $total_quantity >= 4 ) {
                $this->apply_special_discount( $cart, $eligible_items );
            }
        }

        /**
         * Pobiera kwalifikujące się pozycje koszyka z pominięciem produktów typu 'weight' i 'pallet_weight'.
         *
         * @param WC_Cart $cart
         * @return array
         */
        private function get_eligible_cart_items( $cart ) {
            $eligible_items = array();

            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                $product_id    = $cart_item['product_id'];
                $shipping_cost = get_field( 'shipping_cost', $product_id );

                // Pomijaj produkty typu 'weight' i 'pallet_weight'
                if ( $shipping_cost !== 'weight' && $shipping_cost !== 'pallet_weight' ) {
                    $eligible_items[ $cart_item_key ] = $cart_item;
                }
            }

            return $eligible_items;
        }

        /**
         * Zastosuj procentowy rabat w zależności od ilości.
         *
         * @param WC_Cart $cart
         * @param array   $eligible_items
         * @param int     $quantity
         */
        private function apply_percentage_discount( $cart, $eligible_items, $quantity ) {
            $discount_rates = array(
                1 => 10,
                2 => 15,
                3 => 20,
            );

            $percentage = $discount_rates[ $quantity ];
            $discount   = 0;

            foreach ( $eligible_items as $cart_item ) {
                $product_price = $cart_item['data']->get_price();
                $discount     += ( $product_price * $cart_item['quantity'] ) * ( $percentage / 100 );
            }

            if ( $discount > 0 ) {
                $fee_title = sprintf( 'Rabat (%d%%)', $percentage );
                $cart->add_fee( $fee_title, -$discount );
            }
        }

        /**
         * Zastosuj specjalny rabat dla 4 lub więcej pozycji.
         *
         * @param WC_Cart $cart
         * @param array   $eligible_items
         */
        private function apply_special_discount( $cart, $eligible_items ) {
            $product_prices = array();

            // Zbierz ceny produktów bez uwzględnienia ilości
            foreach ( $eligible_items as $cart_item ) {
                $product_price = $cart_item['data']->get_price();
                $product_prices[] = $product_price;
            }

            if ( count( $product_prices ) > 0 ) {
                // Znajdź najniższą cenę spośród wszystkich produktów
                $cheapest_price = min( $product_prices );

                // Sprawdź, czy ilość kwalifikujących się produktów jest co najmniej 4
                $total_quantity = array_sum( wp_list_pluck( $eligible_items, 'quantity' ) );

                if ( $total_quantity >= 4 ) {
                    // Oblicz rabat, aby jedna sztuka najtańszego produktu kosztowała 1 zł
                    $discount = $cheapest_price - 1;

                    if ( $discount > 0 ) {
                        $fee_title = 'Najtańszy produkt za 1 zł';
                        $cart->add_fee( $fee_title, -$discount );
                    }
                }
            }
        }
    }

    new TNLCartDiscounts();
}
