<?php
class KivicareExtension extends KivicareHelper
{

  public function __construct()
  {

    // Initialize hooks
    $this->add_hooks();

  }

  public function add_hooks() {

    // JS scripts
    add_action('admin_enqueue_scripts', array($this, 'kivicare_scripts'));

    // Ajax hooks
    add_action('wp_ajax_nopriv_get_appointment_order', array($this, 'kivicare_ajax_get_appointment_order'));
    add_action('wp_ajax_get_appointment_order', array($this, 'kivicare_ajax_get_appointment_order'));

    add_action('wp_ajax_nopriv_get_payment_method_appointment', array($this, 'get_payment_method_appointment_func'));
    add_action('wp_ajax_get_payment_method_appointment', array($this, 'get_payment_method_appointment_func'));

    add_action('kc_appointment_book', array($this, 'kivicare_appointment_book_func'));

  }

  public function kivicare_scripts()
  {

    wp_enqueue_script(
      'kivicare_extension',
      get_stylesheet_directory_uri() . '/assets/kivicare.js?t=' . time(),
      array('jquery'),
      '1.0',
      true
    );

  }

  function kivicare_ajax_get_appointment_order()
  {

    global $wpdb;

    $appointment_id = $_POST['appointment_id'];
    $type = $_POST['type'];

    if (!$appointment_id) {

      // Looks awful, I know
      $prs = explode('/', $_POST['url']);
      $prs2 = $prs[count($prs) - 1];
      $encounter_id = explode('?', $prs2)[0];

      $appointment_id = $this->getEncounterByID($encounter_id)->appointment_id;

    }

    $order = $this->getOrderByAppointment($appointment_id, $type);

    if ($order) {

      $data = array(
        "balance_order_id" => $order->get_id(),
        "balance_order_status" => $order->get_status(),
        "link" => esc_url($order->get_checkout_payment_url()),
        "edit" => esc_url(get_edit_post_link($order->get_id())),
      );

    } else {

      $data = array(
        "balance_order_id" => '',
        "balance_order_status" => '',
        "link" => '',
        "edit" => '',
      );

    }

    echo wp_send_json($data);
    wp_die();

  }


  public function kivicare_appointment_book_func($appointment_id)
  {

    if (!$appointment_id) {
      return false;
    }

    $request_body = json_decode(file_get_contents('php://input'), true);
    $widget_type = $request_body['widgetType'];
    $payment_type = $request_body['payment_mode'];

    // Order is being created from the Widget just with Payment offline
    if ($widget_type == 'phpWidget' && $payment_type == 'paymentWoocommerce') {
      return false;
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {

      $bc = new KC_woocommerce();
      $bc->createOrderByAppointmentID($appointment_id);

      if ($_GET['order']) {
        die();
      }

    }

  }


  public function get_payment_method_appointment_func()
  {

    $service_id = $_POST['service_id'];
    $deposit = false;

    $posts = get_posts(
      array(
        "post_type" => "product",
        "meta_key" => "kivicare_service_id",
        "meta_value" => $service_id
      )
    );
    $product_id = $posts[0]->ID;

    $bk = new BookingExtension;
    $booking_extras = $bk->get_booking_settings_product($product_id);

    if (count($booking_extras['deposit'])) {
      $deposit = true;
    }

    $data = array(
      "deposit" => $deposit,
      "service_id" => $service_id,
      "product_id" => $product_id
    );

    echo wp_send_json($data);
    wp_die();

  }

}

new KivicareExtension();