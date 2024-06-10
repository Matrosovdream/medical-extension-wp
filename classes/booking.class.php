<?php
class BookingExtension extends KivicareHelper
{

  private $booking_settings;
  private $deposit_amount;
  private $full_sum;
  private $fees = array();

  private $wc;

  public function __construct()
  {

    // Settings
    $this->booking_settings = $this->get_booking_settings();

    // Initialize hooks
    $this->add_hooks();

    // Initialize filters
    $this->add_filters();
    
    // Meta fields, metaboxes
    $this->includeMetaboxes();

    // WooCommerce Helper
    $this->wc = new KC_woocommerce;

  }

  public function add_hooks() {

    add_action('woocommerce_order_status_completed', array($this, 'create_balance_pending_order'));
    add_action('woocommerce_checkout_update_order_meta', array($this->wc, 'add_custom_meta_to_order'), 10, 2);
    add_action('woocommerce_before_calculate_totals', array($this, 'booking_extension_extras_setup'), 10, 5);

  }

  public function add_filters() {

    add_filter('woocommerce_get_item_data', array($this, 'iconic_display_engraving_text_cart'), 10, 2);

  }

  public function includeMetaboxes()
  {

    // Product metaboxes
    new BookingExtension_metaboxes();

  }

  public function booking_extension_extras_setup($cart)
  {

    foreach ($cart->get_cart() as $item_id => $item) {

      if (!$item['kivicare_appointment_id']) {
        continue;
      }

      $product_id = $item['product_id'];
      $booking_extras = $this->get_booking_settings_product($product_id);

      $first_order = $this->is_user_first_order(get_current_user_id());
      //$first_order = true;
      if (!$first_order) {
        unset($booking_extras['opening']);
      }

      // Deposit
      if (count($booking_extras['deposit']) > 0) {
        $this->add_product_deposit($item, $booking_extras['deposit']);
      }

      // Fees
      $fees = array();
      if (isset($booking_extras['opening']) && count($booking_extras['opening']) > 0) {
        $fees = array_merge($fees, $booking_extras['opening']);
      }
      if (isset($booking_extras['additional']) && count($booking_extras['additional']) > 0) {
        $fees = array_merge($fees, $booking_extras['additional']);
      }

      if (count($fees) > 0) {
        $this->add_product_fees($item, $fees);
      }

    }

  }


  function add_product_deposit($cart_item, $data)
  {

    $deposit = array_values($data)[0];

    if (!$deposit['active']) {
      return false;
    }

    $product_id = $cart_item['product_id'];
    $product = wc_get_product($product_id);
    $full_price = $product->get_price();
    $product_price = $product->get_price();

    $rate = $deposit['amount'];

    if ($deposit['type'] == 'fixed') {
      $amount = $rate;
    } else {
      $amount = ($product_price * $rate) / 100;
    }

    $this->deposit_amount = $amount;

    $product_title = get_post($product_id)->post_title;
    $label = $deposit['label'];
    $product_title .= ' | ' . $label;

    $this->full_sum = $full_price;

    $cart_item['data']->set_price($amount);
    $cart_item['data']->set_name($product_title);
    $cart_item['data']->add_meta_data('_has_deposit', 'yes', true);
    $cart_item['data']->add_meta_data('_full_price', $full_price, true);

  }


  function add_product_fees($cart_item, $data)
  {

    $deposit_amount = $this->deposit_amount;
    if ($deposit_amount) {
      $pay_later = true;
    }

    $product_id = $cart_item['product_id'];
    $product = wc_get_product($product_id);
    $full_price = $product->get_price();

    foreach ($data as $fee) {

      $rate = $fee['amount'];

      if ($fee['type'] == 'fixed') {
        $amount = $rate;
      } else {
        $amount = ($full_price * $rate) / 100;
      }

      if ($pay_later) {

        $fees_pay[] = array(
          'label' => $fee['label'],
          'slug' => $fee['slug'],
          'amount' => $amount
        );

      } else {

        $fee_label = $product->get_name() . ' - ' . $fee['label'];
        WC()->cart->add_fee($fee_label, $amount, true, '');

      }

    }

    $this->fees = $fees_pay;

    $cart_item['data']->add_meta_data('fee_to_pay', serialize($fees_pay), true);

  }


  public function calculate_product_fees($cart_item)
  {

    $fees = $this->get_fees();

    $product_id = $cart_item['product_id'];
    $product = wc_get_product($product_id);
    $full_price = $product->get_price();

    $product_deposits = unserialize(get_post_meta($product_id, 'selected_deposit_options', true));

    foreach ($fees as $key => $fee) {
      WC()->cart->add_fee($product->get_name() . ' - ' . $fee['label'], 0, true, '');
    }

  }


  public function create_balance_pending_order($order_id = false)
  {

    if (!$order_id) {
      return false;
    }

    $this->wc->createChildOrder($order_id);

  }

  public function iconic_display_engraving_text_cart($item_data, $cart_item)
  {

    $deposit = $this->deposit_amount;
    $fees = $this->fees;

    if ($deposit) {

      $product_id = $cart_item['product_id'];

      $product = wc_get_product($product_id);
      $product_price = $product->get_price();

      $balance = $product_price - $deposit;

      $item_data[] = array(
        'key' => __('Service price', 'iconic'),
        'value' => wc_price($product_price),
        'display' => '',
      );

      if (count($fees) > 0) {

        $fees_sum = 0;
        foreach ($fees as $fee) {

          $item_data[] = array(
            'key' => __($fee['label'], 'iconic'),
            'value' => wc_price($fee['amount']),
            'display' => '',
          );

          $fees_sum += $fee['amount'];

        }

      }

      $item_data[] = array(
        'key' => __('Balance remaining to be paid at the clinic (Inc. Tax)', 'iconic'),
        'value' => wc_price($product_price + $fees_sum - $deposit),
        'display' => '',
      );

    }

    return $item_data;

  }


  public function get_fees()
  {
    return $this->get_booking_settings()['fees'];
  }


  public function get_deposits()
  {
    return $this->get_booking_settings()['deposits'];
  }

  public function get_booking_settings_product($product_id = false)
  {

    if (!$product_id) {
      return false;
    }

    $settings = $this->get_booking_settings();
    $product_extras = $this->get_product_extras($product_id);

    if (count($settings['deposit']) > 0) {

      foreach ($settings['deposit'] as $key => $option) {
        if (!$product_extras[$key]) {
          unset($settings['deposit'][$key]);
        }
      }

      foreach ($settings['opening'] as $key => $option) {
        if (!$product_extras[$key]) {
          unset($settings['opening'][$key]);
        }
      }

      foreach ($settings['additional'] as $key => $option) {
        if (!$product_extras[$key]) {
          unset($settings['opening'][$key]);
        }
      }

    }

    return $settings;

  }

}

new BookingExtension();