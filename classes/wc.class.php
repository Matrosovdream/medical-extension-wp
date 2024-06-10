<?php
class KC_woocommerce {

    public function createChildOrder($parent_order_id = false)
    {
  
      if (!$parent_order_id) {
        return false;
      }
  
      if (!$this->needCreateChild($parent_order_id)) {
        return false;
      }
  
      $original_order = wc_get_order($parent_order_id);
  
      $user_id = $original_order->get_user_id();
  
      if (!$original_order->get_meta('_has_deposit')) {
        return false;
      }
  
      $deposit = $original_order->get_total();
      $total = $original_order->get_meta('_full_price');
      $fee_to_pay = unserialize($original_order->get_meta('fee_to_pay'));
  
      $sum_pay = $total - $deposit;
      if ($sum_pay == 0) {
        return false;
      }
  
      global $woocommerce;
  
      // Now we create the order
      $order = wc_create_order();
      $ORDER_ID = $order->get_id();
  
      // Billing, shipping
      $order->set_address($original_order->get_address('billing'), 'billing');
      $order->set_address($original_order->get_address('shipping'), 'shipping');
  
      // Products
      $items = $original_order->get_items();
      foreach ($items as $item) {
        $product = wc_get_product($item->get_product_id());
        $product->set_price($sum_pay);
  
        $order->add_product($product, $item->get_quantity());
      }
  
      // Status update, by default: Pending payment
      //$order->update_status("Completed", 'Imported order', true);  
  
      $parent_order_link = "<a href='/wp-admin/post.php?post=" . $parent_order_id . "&action=edit'>#" . $parent_order_id . "</a>";
      $order->add_order_note(__('This order is for the remaining balance of the original order ' . $parent_order_link . '.', 'kivicare-booking-extension'));
      $order->add_meta_data('_balance_order_for', $parent_order_id);
      $order->add_meta_data('_parent_order', $parent_order_id);
  
      // Fees if needed
      if (is_array($fee_to_pay) && count($fee_to_pay) > 0) {
  
        foreach ($fee_to_pay as $fee) {
  
          $item_fee = new WC_Order_Item_Fee();
  
          $item_fee->set_name($fee['label']); // Generic fee name
          $item_fee->set_amount($fee['amount']); // Fee amount
          $item_fee->set_tax_class(''); // default for ''
          $item_fee->set_tax_status('none'); // or 'none'
          $item_fee->set_total($fee['amount']); // Fee amount
  
          // Calculating Fee taxes
          $item_fee->calculate_taxes($calculate_tax_for);
  
          // Add Fee item to the order
          $order->add_item($item_fee);
  
        }
  
      }
  
      $order->set_customer_id($user_id);
      $order->calculate_totals();
      $order->save();
  
      // Add post meta
      $parent_order_meta = get_post_meta($parent_order_id);
  
      add_post_meta($ORDER_ID, 'kivicare_appointment_id', $parent_order_meta['kivicare_appointment_id'][0]);
      add_post_meta($ORDER_ID, 'kivicare_doctor_id', $parent_order_meta['kivicare_doctor_id'][0]);
      add_post_meta($ORDER_ID, 'kivicare_widget_type', $parent_order_meta['kivicare_widget_type'][0]);
  
      // Update original order
      $original_order->update_meta_data('_balance_order_created', true);
      $original_order->save();
  
    }

    public function createOrderByAppointmentID($appointment_id = false)
    {
  
      if (!$appointment_id) {
        return false;
      }
  
      $kc = new KivicareExtension();
      $appointment = $kc->getAppointmentByID($appointment_id);
  
      // If need to create
      if (count($appointment['orders']) > 0) {
        return false;
      }
  
      // Variables
      $user_id = $appointment['patient_id'];
      $service_id = $appointment['service']['service_id'];
      $doctor_id = $appointment['doctor_id'];
      $clinic_id = $appointment['clinic_id'];
  
      // Products
      $kt = new KivicareExtension;
      $product_id = $kt->getProductByServiceID($service_id);
      $appointment['product_id'] = $product_id;
      $products = array($product_id);
      $product_price = $appointment['service']['info']['price'];
  
      // Fees, extras
      $booking_extras = $this->get_booking_settings_product($product_id);
  
      // Deposit
      $deposit = current($booking_extras['deposit']);
  
      global $woocommerce;
  
      // Now we create the order
      $order = wc_create_order();
      $ORDER_ID = $order->get_id();
  
      $user = get_userdata($user_id);
      $user_data = kcGetUserData($user_id);
  
      $billing_address = array(
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'company' => '',
        'address_1' => isset($user_data->basicData) && isset($user_data->basicData->address) ? $user_data->basicData->address : '',
        'address_2' => '',
        'city' => isset($user_data->basicData) && isset($user_data->basicData->city) ? $user_data->basicData->city : '',
        'state' => '',
        'postcode' => isset($user_data->basicData) && isset($user_data->basicData->postal_code) ? $user_data->basicData->postal_code : '',
        'country' => 'CA',
        'email' => $user->user_email,
        'phone' => isset($user_data->basicData) && isset($user_data->basicData->mobile_number) ? $user_data->basicData->mobile_number : '',
      );
  
  
      // Billing, shipping
      //$order->set_address( $appointment['patient']['billing'], 'billing' );
      $order->set_address($billing_address, 'billing');
      $order->set_address(array('shipping'), 'shipping');
  
      // Products, we set a deposit price here
      foreach ($products as $item_id) {
  
        $product = wc_get_product($product_id);
        $full_price = $product->get_price();
        $product_price = $product->get_price();
  
        if ($deposit) {
  
          $rate = $deposit['amount'];
  
          if ($deposit['type'] == 'fixed') {
            $amount = $rate;
          } else {
            $amount = ($product_price * $rate) / 100;
          }
  
          $product_title = get_post($item_id)->post_title;
          $label = $deposit['label'];
          $product_title .= ' | ' . $label;
  
        } else {
  
          $product_title = get_post($item_id)->post_title;
          $amount = $product_price;
  
        }
  
  
        $product = wc_get_product($item_id);
        $product->set_price($amount);
        $product->set_name($product_title);
  
        $order->add_product($product, 1);
  
      }
  
      $first_order = $this->is_user_first_order($user_id);
  
      // Fees, we apply all fees here including Opening
      if (!$first_order) {
        unset($booking_extras['opening']);
      }
  
      $fees = array();
      if (isset($booking_extras['opening']) && count($booking_extras['opening']) > 0) {
        $fees = array_merge($fees, $booking_extras['opening']);
      }
      if (isset($booking_extras['additional']) && count($booking_extras['additional']) > 0) {
        $fees = array_merge($fees, $booking_extras['additional']);
      }
  
      if ($deposit) {
        $order->add_meta_data('fee_to_pay', serialize($fees));
      } else {
  
        if (is_array($fees) && count($fees) > 0) {
  
          foreach ($fees as $fee) {
  
            $item_fee = new WC_Order_Item_Fee();
  
            $item_fee->set_name($fee['label']); // Generic fee name
            $item_fee->set_amount($fee['amount']); // Fee amount
            $item_fee->set_tax_class(''); // default for ''
            $item_fee->set_tax_status('none'); // or 'none'
            $item_fee->set_total($fee['amount']); // Fee amount
  
            // Calculating Fee taxes
            $item_fee->calculate_taxes($calculate_tax_for);
  
            // Add Fee item to the order
            $order->add_item($item_fee);
  
          }
  
        }
  
      }
  
  
      // Meta data
      $order->add_order_note(__('Order has been created from the dashboard', 'kivicare-booking-extension'));
      $order->add_meta_data('kivicare_appointment_id', $appointment_id);
      $order->add_meta_data('kivicare_doctor_id', $doctor_id);
      $order->add_meta_data('kivicare_widget_type', $parent_order_id);
  
      $order->add_meta_data('_has_deposit', 'yes', true);
      $order->add_meta_data('_full_price', $product_price, true);
  
      $order->set_customer_id($user_id);
  
      $order->calculate_totals();
  
      // Status update, by default: Pending payment
      //$order->update_status("completed", 'Imported order', true); 
  
      $order->save();
  
      if ($deposit) {
        $this->createChildOrder($ORDER_ID);
      }
  
    }

    public function needCreateChild($order_id = false)
    {
  
      if (!$order_id) {
        return false;
      }
  
      $original_order = wc_get_order($order_id);
  
      if (!$original_order->get_meta('_has_deposit')) {
        return false;
      }
  
      if ($original_order->get_meta('_balance_order_created')) {
        return false;
      }
  
      return true;
  
    }

    public function add_custom_meta_to_order($order_id, $data)
    {
  
      $order = wc_get_order($order_id);
  
      foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['data'])) {
          $product = $cart_item['data'];
  
          if ($product->get_meta('_has_deposit') == 'yes') {
  
            $full_price = $product->get_meta('_full_price');
            $fee_to_pay = $product->get_meta('fee_to_pay');
  
            $order->update_meta_data('_has_deposit', 'yes');
            $order->update_meta_data('_full_price', $full_price);
            $order->add_meta_data('fee_to_pay', $fee_to_pay);
            $order->save();
            break;
          }
        }
      }
  
    }

}