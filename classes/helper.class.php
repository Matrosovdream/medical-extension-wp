<?php
Class KivicareHelper {

    private $kivicare_tables = array(
      "appointments" => 'wp_kc_appointments',
      "appointment_service_mapping" => "wp_kc_appointment_service_mapping",
      "services" => "wp_kc_services",
      "service_doctor_mapping" => "wp_kc_service_doctor_mapping",
      "encounters" => "wp_kc_patient_encounters",
    );

    public function get_booking_settings() {

        // Fees, deposits
        $options = get_field('fees', 'option');
  
        $fees_data = array();
        $deposits_data = array();
        foreach( $options as $key=>$fee ) {
          if ($fee['fee_active'] == 1) {
            
            $fee_type = $fee['fee_type'];
            if( $fee_type == 0 ) { $type = 'deposit'; }
            if( $fee_type == 1 ) { $type = 'opening'; }
            if( $fee_type == 2 ) { $type = 'additional'; }
            
            $data[ $type ][ $fee['slug'] ] = array(
              "active" => $fee['fee_active'],
              "label" => $fee['fee_label'],
              "slug" => $fee['slug'],
              "type" => ($fee['fee_calcul'] == 0) ? 'fixed' : 'percent',
              "amount" => $fee['fee_amount']
            );
          }
        }
  
        return $data;
  
    }

    public function is_user_first_order( $user_id=false ) {

        if( !$user_id ) { return false; }

        $count = wc_get_customer_order_count( $user_id );

        if( $count == 0 ) { return true; }

    }

    public function get_product_extras( $product_id=false ) {

        if( !$product_id ) { return false; }
  
        $product_deposits = unserialize( get_post_meta( $product_id, 'selected_deposit_options', true ) );
  
        return $product_deposits;
  
    }

    public function getEncounterByID( $encounter_id ) {

      if( !$encounter_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['encounters'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `id`='.$encounter_id );

      return $rows[0];

    }

    public function getAppointmentByID( $appointment_id ) {

      if( !$appointment_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['appointments'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `id`='.$appointment_id, 'ARRAY_A' );
      $item = $rows[0];

      // Encounter
      $item['encounter'] = $this->getEncounterByAppointmentID( $appointment_id );
      $item['service'] = $this->getServiceByAppointmentID( $appointment_id );

      // Patient
      $item['patient'] = $this->getPatientByID( $item['patient_id'] );

      // Orders
      $item['orders'] = $this->getOrdersByAppointmentID( $appointment_id );

      return $item;

  }

  public function getEncounterByAppointmentID( $appointment_id ) {

      if( !$appointment_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['encounters'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `appointment_id`='.$appointment_id, 'ARRAY_A' );

      return $rows[0];

  }

  public function getOrderByAppointment( $appointment_id, $type ) {

      if( !$appointment_id ) { return false; }

      global $wpdb;

      $rows = $wpdb->get_results( 'SELECT * FROM `wp_postmeta` WHERE `meta_key`="kivicare_appointment_id" AND `meta_value`='.$appointment_id.' ORDER BY `post_id` DESC' );
      
      //var_dump($rows);
      
      if( $type == 'deposit' && count($rows) > 1 ) {
        $order_id = $rows[1]->post_id;
      } else {
        $order_id = $rows[0]->post_id;
      }
  
      if( $order_id ) {
          return wc_get_order($order_id);
      }
      
  }

  public function getOrdersByAppointmentID( $appointment_id ) {

      if( !$appointment_id ) { return false; }

      global $wpdb;

      $rows = $wpdb->get_results( 'SELECT post_id FROM `wp_postmeta` WHERE `meta_key`="kivicare_appointment_id" AND `meta_value`='.$appointment_id.' ORDER BY `post_id` DESC' );

      $orders = array();
      foreach( $rows as $order ) {
          $orders[] = $order->post_id;
      }

      return $orders;

  }

  public function getServiceByAppointmentID( $appointment_id ) {

      if( !$appointment_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['appointment_service_mapping'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `appointment_id`='.$appointment_id, 'ARRAY_A' );

      $data = $rows[0];

      $data['info'] = $this->getServiceByID( $data['service_id'] );
      $data['product_id'] = $this->getProductByServiceID( $data['service_id'] );

      $bk = new BookingExtension;
      $product_extras = $bk->get_booking_settings_product( $data['product_id'] );

      if( count( $product_extras['deposit'] ) > 0 ) {
          $data['deposit'] = true;
      }
      
      return $data;

  }

  public function getServiceByID( $service_id ) {

      if( !$service_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['services'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `id`='.$service_id, 'ARRAY_A' );

      return $rows[0];

  }

  public function getProductByServiceID( $service_id ) {

      if( !$service_id ) { return false; }

      global $wpdb;

      $table = $this->kivicare_tables['service_doctor_mapping'];
      $rows = $wpdb->get_results( 'SELECT * FROM `'.$table.'` WHERE `service_id`='.$service_id, 'ARRAY_A' );

      $data =  $rows[0];
      $extra = json_decode( $data['extra'], true );

      return $extra['product_id'];

  }

  public function getPatientByID( $patient_id ) {

      if( !$patient_id ) { return false; }

      $user = (array) get_user_by('id', $patient_id)->data;
      $user_meta = get_user_meta( $patient_id );

      // Get billing data
      foreach( $user_meta as $key=>$value ) {

          if( strpos( $key, 'billing' ) !== false ) { 
              $key = explode('billing_', $key)[1];
              $billing_data[ $key ] = $value[0];
          }

      }

      $user['billing'] = $billing_data;

      return $user;

  }

}