<?php
Class BookingExtension_metaboxes {

    public function __construct() {

        add_action('add_meta_boxes', array($this, 'booking_extension_product_box'));
        add_action( 'save_post', array($this, 'booking_extension_product_save_postdata') );

    }


    function booking_extension_product_box(){
        $screens = array( 'product' );
        add_meta_box( 'booking_extension_product_deposits', __('Facturation'), array($this, 'booking_extension_product_box_callback'), $screens, 'side',  'low' );
    }

    
    function booking_extension_product_box_callback( $post, $meta ) {

        $screens = $meta['args'];
    
        wp_nonce_field( plugin_basename(__FILE__), 'booking_extension_nonce' );
    
        $bookingExt = new BookingExtension();
        $options = $bookingExt->get_booking_settings();
    
        echo '<label for="deposit_plugin"><b>' . __("Dépôt/Frais supplémentaire requis", 'deposit_plugin' ) . '</b></label> ';
    
        $choices = array();
        
        if (isset($options['deposit'])) {
            $choices = array_merge($choices, $options['deposit']);
        }
        
        if (isset($options['opening'])) {
            $choices = array_merge($choices, $options['opening']);
        }
        
        if (isset($options['additional'])) {
            $choices = array_merge($choices, $options['additional']);
        }
    
        $selected = unserialize( get_post_meta( $post->ID, 'selected_deposit_options', true) );

        foreach( $choices as $key=>$item ) {
            
            if( $selected[ $item["slug"] ] ) { $sel = "checked"; } else { $sel = ""; }
            if( $item["type"] == 'fixed' ) { $type = "$";  } else { $type = "%"; }
            echo '
            <p>
                <input type="checkbox" id="product_deposit_'.$key.'" name="product_deposits['.$item["slug"].']" value="Y" size="25" '.$sel.' />
                <label for="product_deposit_'.$key.'">'.$item['label'].' ('.$item['amount'].''.$type.')</label>
            </p>
            ';
        }

        echo '<input type="hidden" name="save_product_deposits" value="Y" />';
        
    }


    function booking_extension_product_save_postdata( $post_id ) {
           
        if ( ! isset( $_POST['save_product_deposits'] ) )
            return;
             
        if ( ! wp_verify_nonce( $_POST['booking_extension_nonce'], plugin_basename(__FILE__) ) )
            return;
            
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
            return;
            
        if( ! current_user_can( 'edit_post', $post_id ) )
            return;
    
        update_post_meta( $post_id, 'selected_deposit_options', serialize($_POST['product_deposits']) );
    
    }
    

}