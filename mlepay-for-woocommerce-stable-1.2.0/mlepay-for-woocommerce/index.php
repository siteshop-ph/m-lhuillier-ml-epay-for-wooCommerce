<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit;
}

/*
	Plugin Name: ML ePay Payment Gateway For WooCommerce
	Plugin URI: https://github.com/siteshop-ph?tab=repositories
	Description: ML ePay Payment Gateway Makes easy for customers to order online and pay cash at any of 1700+ M Lhuillier branch nationwide in the Philippines. ; This plugin require a ML ePay Merchant Account you can get for free: <a href="https://www.mlepay.com/join">HERE</a> ;  
	Version: 1.2.0
	Author: Serge Frankin SiteShop.ph (Netpublica.com Corp.)
*/ 






if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


  add_action( 'plugins_loaded', 'woocommerce_mlepay_init', 0 );






  function woocommerce_mlepay_init() {








error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);


     // to prevent PHP notice when wp debug mode is enabled









    /**
    * Gateway class
    **/
    class WC_Controller_Mlepay extends WC_Payment_Gateway { 

      var $notify_url;

      public function __construct() {

        $this->state = 'CREATED';
        $this->id = 'mlepay';

        
        $this->icon = plugins_url( 'assets/mlepay.png', __FILE__ );
        $this->has_fields = false;
        $this->method_title = __( 'ML ePay', 'woocommerce_mlepay' );

        $this->liveurl = 'http://www.mlepay.com/api/v2/transaction/create';


        // Load the form fields.
        $this->init_form_fields();


        // Load the settings.
        $this->init_settings();
   

         // Define user setting variables.
        $this->enabled = $this->settings['enabled'];
        $this->title = $this->settings['title'];
        $this->complete_order_notice = $this->settings['complete_order_notice'];
        $this->description = $this->settings['description'];
        $this->mlepay_secret_key = $this->settings['mlepay_secret_key'];
        $this->mlepay_merchant_email = $this->settings['mlepay_merchant_email'];
        $this->expiration_day_duration = $this->settings['expiration_day_duration'];
        $this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_controller_mlepay', home_url( '/' ) ) );

        //$this->debug = $this->settings['debug'];






            // Active logs.
		if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $this->woocommerce_instance()->logger();
			}
		}





        add_action('woocommerce_api_wc_controller_mlepay', array($this, 'mlepay_response'));
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_mlepay', array( $this, 'receipt_page' ) );
      
      }
      
      /**
       * Initialize Gateway Settings Form Fields
       */
      function init_form_fields() {

        $this -> form_fields = array(
           'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce_mlepay' ),
                'type' => 'checkbox',
                'label' => __( '  Enable/Disable Plugin' ),
                'default' => 'yes'
                ),
            'title' => array(
                'title' => __('Title:', 'woocommerce_mlepay'),
                'type'=> 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce_mlepay'),
                'default' => __('Pay with cash at any M Lhuillier branch.', 'woocommerce_mlepay')),
            'description' => array(
                'title' => __('Description:', 'woocommerce_mlepay'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce_mlepay'),
                'default' => __('M Lhuillier - MLePay.com | CASH Payments through 1700+ M Lhuillier branches nationwide.', 'woocommerce_mlepay')),
             'mlepay_merchant_email' => array(
                'title' => __( 'Merchant Email Address', 'woocommerce_mlepay' ),
                'type' => 'text',
                'description' => __( 'Enter your Merchant Email address registered at ML ePay, you can find it within your ML ePay Account:<br>Login at ML ePay -> Click the top-right side drop-down list -> Account -> "Account Information" section', 'woocommerce_mlepay' ),
                'default' => 'my-ML-ePAY-merchant-email-address'
                ), 
             'mlepay_secret_key' => array(
                 'title' => __( 'Merchant Secret Key', 'woocommerce_mlepay' ),
                 'type' => 'textarea',
                 'description' => __( 'Enter your Merchant Secret Key, you can find it within your ML ePay Account:<br>Login at ML ePay -> Click the top-right side drop-down list -> Account -> "API Information" section', 'woocommerce_mlepay' ),
                 'default' => 'my-ML-ePAY-merchant-secret-key'
                ), 


             'expiration_day_duration' => array(
                 'title' => __('Time Allowed to Pay (in days)', 'woocommerce_mlepay'),
                 'type' => 'text', 
                 'required' => true,
                 'description' => __('Customers will have this allowed time in days for makes cash payment, after that their transaction code expire.<br>We recommend to allow at least 3 days','woocommerce_mlepay'),
                 'default' => __('4', 'woocommerce_mlepay')
		 ),
             'complete_order_notice' => array(
                 'title' => __('Transaction Instructions', 'woocommerce_mlepay'),
                 'type' => 'textarea',
                 'description' => __('This controls the instruction which the user sees when transaction code is displayed after checkout', 'woocommerce_mlepay'),
                 'default' => __('Please bring the above transaction code when you pay at an M Lhuillier branch. It will be needed when you pay for the transaction.', 'woocommerce_mlepay')),
	    'debug' => array(
		 'title' => __( 'Debug Log', 'woocommerce_mlepay' ),
	         'type' => 'checkbox',
	         'label' => __( 'Enable Debug log', 'woocommerce_mlepay' ),
		 'default' => 'yes',
                 'description' => sprintf( __( 'Log ML ePay events, such as Transaction Code Creation, Notification, inside:<br>log file %s', 'woocommerce-mlepay' ), '<code>wp-content/uploads/wc-logs/mlepay-' . sanitize_file_name( wp_hash( 'mlepay' ) ) . '.log</code>&nbsp;&nbsp;&nbsp;<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-status&tab=logs">See log file content</a>&nbsp;&nbsp;&nbsp;<br><br>If the file do not exist, please check that 1/ "wc-logs" folder exist and is Writable and 2/ "Log Directory Writable" item is fine:&nbsp;&nbsp;&nbsp;<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-status"> Here' )
		),
	      'display_callback_url' => array(
		  'title' => 'Webhook URL',
                  'type' => 'title',
		  'description' => '<b>This bellow URL must be set in your ML ePay Account:</b><br><br><font color="red"><code>'.WC()->api_request_url('wc_controller_mlepay').'</code></font><br><br>Login at ML ePay -> Click the top-right side drop-down list -> Account -> Edit Profile -> "API Information" section:<br><br>&nbsp;&nbsp;&nbsp; 1/ Set "Webhook URL" with above URL by clicking on field for "Webhook URL" <br><br>&nbsp;&nbsp;&nbsp; 2/ Enable "Webhook Status"</font><br>',
		  'desc_tip' => false,
                  'default' => ''                     
		)

        );
      
      }




        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            ?>
            <h3><?php _e( 'ML ePay', 'woocommerce_mlepay' ); ?></h3>
            <p><?php _e( 'Reach the 97% of Filipinos who don’t have a credit card through <a href="https://www.mlepay.com">ML ePay</a>.', 'woocommerce_mlepay' ); ?><br>
            <?php _e( 'Receive cash payments through 1500+ M Lhuillier branches nationwide.', 'woocommerce_mlepay' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }





      /**
      * Process the request to ask transaction code
      */
      function process_payment( $order_id ) {

        global $woocommerce;
        
        $order = new WC_Order( $order_id );               


               return array(
                  'result' => 'success',
                  'redirect' => $order->get_checkout_payment_url( true )
               );
       
      
      }

















      /**
      * Display receipt order (display transaction code & instruction)
      */
      function receipt_page( $order_id ) {

        global $woocommerce;
        
        try{

          $order = new WC_Order( $order_id );


     

  


          // to prevent to create more than one transaction code for same order (as ML epay do not check txnid/payload)
          if($order->status != 'pending') {




               // nothing to do



     }else{


              // Case order/transaction to create






          $randStrLen = 16;
          $nonce = $this->randString($randStrLen);
          $timestamp = time();        //  N.B.:   unix timestamp isn't affected by a timezone setting as it's alway in UTC. 
          
                    
     
         
         // get expirity in unix time
        $expiration_day_duration = $this->expiration_day_duration;
        
        $expiry = $timestamp + ( $expiration_day_duration * 86400 );    //  N.B.:   unix timestamp isn't affected by a timezone setting as it's alway in UTC. 
    
       
          

        
       




           // Check if Active: WooCommerce Plugin "Custom Order Numbers" by unaid Bhura / http://gremlin.io/ 
           if( class_exists( 'woocommerce_custom_order_numbers' ) ) {

                   // take CUSTOM order id string from WooCommerce Plugin "Custom Order Numbers" (there can be prefix, etc)
	           $txnid = $order->custom_order_number; 

           }else{

                   // just use regular woocommerce order id as txnid for mlepay
                   $txnid = $order->id;
 
           }







       
         
          $payload_id = $txnid;



          $product = $order->get_items();

          $product_name = array();










          foreach ( $order->get_items() as $item ) {

           
            if ( $item['qty'] ) {

                $item++;

                $product = $order->get_product_from_item( $item );

                $item_name  = $item['name'];
                //$item_name = $item . ". " . $item_name . " ";
            }
            array_push($product_name, $item_name);
          }




          $request_body = array(
                  "receiver_email"=> $this->mlepay_merchant_email, 
                  "sender_email"=> $order->billing_email,
                  "sender_name"=> $order->billing_first_name.' '.$order->billing_last_name,
                  "sender_phone"=> $order->billing_phone,
                  "sender_address"=> $order->billing_address_1.' '.$order->billing_address_2,
                  "amount"=> (int)($order->get_total() * 100),
                  "currency"=> "PHP",
                  "nonce"=> $nonce,
                  "timestamp"=> $timestamp,
                  "expiry"=> $expiry,
                  "payload"=> $payload_id,
                  "description"=> join(" ",$product_name)
              );



          $data_string = json_encode($request_body);
          $base_string = "POST";
          $base_string .= "&" . 'https%3A//www.mlepay.com/api/v2/transaction/create';
          $base_string .= "&" . rawurlencode($data_string);


          $secret_key = html_entity_decode($this->mlepay_secret_key);


          $signature = base64_encode(hash_hmac("sha256", $base_string, $secret_key, true));



          $ch = curl_init('https://www.mlepay.com/api/v2/transaction/create');  
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);                                                                 
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
              'Content-Type: application/json',                                                                                
              'X-Signature: ' . $signature)                                                                     
          );                                                                                                                   
           


          $result = curl_exec($ch);
          $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          $result = json_decode($result, true);



          





                            // N.B.: no ML ePay notification/callback when transaction code is created
          // update order status only if there is transaction code in answer
          if ( isset( $result['transaction']['code'] ) ) {



                  // case a transaction code was generated



          // Display transaction code to the customer with instruction
          echo '<br><div><big><span><font color="#292661">Your M Lhuillier - MLePay.com Transaction Code is:</font></span> <div id="mlepay_transaction_code"><br><big><b><font color="#e02b2b"><center>'. $result['transaction']['code'] . '</center></font></b></big></div><div><br><font color="#292661">MLePay.com has also sent to you this Transaction Code by email<br><br>' . $this->complete_order_notice . '</font></big></div></div>' ;





                                         // update order status (an admin note will be also created)
                                         $order->update_status('on-hold'); 

                                         // Add Admin and Customer note             
                                         $order->add_order_note(' -> ML ePay Transaction Code CREATED<br/> -> ML ePay Transaction Code: '.$result['transaction']['code'].'<br/> -> Order Status Updated to ON-HOLD', 1);  




                                                   if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
                                                                   

                                                             $this->log->add( 'mlepay', 'ML ePay - Transaction Code CREATED - ' . $result['transaction']['code'] . ' - For Order ' . $order->get_order_number() );                                                        


                                                    }  




         
                                         // no reduce order stock needed


                                         //empty cart
                                         $woocommerce->cart->empty_cart();












      
      }else{

            // no transaction code generated (ERROR ?)






          // Display error description at customer frontend
          echo '<br><div><big><big><font color="#e02b2b"><center><u>Error:</u>  '. $result['description'] . '</center></font></big></big></div> ' ;



           
                              if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
                                                                 
                                                                                              // error will be in $result['description']
                                          $this->log->add( 'mlepay', 'ML ePay - ERROR: *** ' . $result['description'] . ' *** When trying to create Transaction Code for Order ' . $payload_id );

                                          $this->log->add( 'mlepay', 'ML ePay - *** IMPORTANT *** If you are testing the Gateway, you should double check 1/ Merchant Email address, 2/ Merchant Secret Key, YOU FILLED AT: WooCommerce -> Settings -> Checkout -> ML ePay' );

      
                              }  




            // empty cart
            // not needed as cart was make empty when transaction code is generated 




    }










         




 
 
 // for test :  full response from transaction request
  /*       
     echo '<br><br><big>Full Gateway Answer for Debug:</big><hr><pre>';
     print_r( $result );
     echo '</pre>';
     
     echo "Expiry (Epoch Time):  " . $expiry."<br><br>";      

     echo "Timestamp (Epoch Time):  " . $timestamp."<br><br><hr>";
  */
       
     
     



 
     

     



       
       
           
      
        } // end of "try"




















  } // end case     order/transaction to create






        catch(Exception $e) {

          echo '<div><span>An Error occurred. Please try again.</span></div>';
        
        } // end exeption



  




 } // end of this function "receipt_page"
























     // For manage notification from ML ePay
     // N.B.: Managing Transaction code generation/answer is not here
      function mlepay_response() {



// Example of url response from mlepay:
//     

// Example of url after woocommerce internal redirection (url ending point):
//      http://demo-mlepay-woocommerce.siteshop.ph/checkout/order-received?order=224&key=wc_order_552669a6f1e08




        $body_response = file_get_contents('php://input');
        $headers = $this->parse_request_headers();

        

         $woo_api_url = WC()->api_request_url('wc_controller_mlepay'); 
            // give this  http://demo-mlepay-woocommerce.siteshop.ph/wc-api/wc_controller_mlepay/



        // to prepare
        $woo_site_url_prepare_1 =  explode( '/wc-api', $woo_api_url );

       
        // only take the first string before "wc-api"
        $woo_site_url_prepare_2 = $woo_site_url_prepare_1[0];


        $woo_site_url_prepare_3 = explode( ':', $woo_site_url_prepare_2 );


        $scheme = rawurlencode($woo_site_url_prepare_3[0] . ':');   // will give http:    or   https:    in URL encoded format

        
        $woo_site =  $woo_site_url_prepare_3[1];          
      

        $gateway_extension = '/wc-api/wc_controller_mlepay/';   //N.B. this give same result:   $_SERVER[REQUEST_URI]
                                                                // this part is not URL encoded format
                                                                // need to be lowercase as ML ePay api/interface store the Webhook URL in lowercase


         
       // serve to calculate the signature
       $base_string = 'POST&'.$scheme.$woo_site.$gateway_extension.'&'.rawurlencode($body_response);


        $secret_key = html_entity_decode($this->mlepay_secret_key);  
   


        
        // True signature
        $signature_ipn = base64_encode(hash_hmac("sha256", $base_string, $secret_key, true));
   







         $result = json_decode( $body_response, true );

         $result['description'];









                                               // For test
                                                 /*
                                                   if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
                                                           $this->log->add( 'mlepay', 'true signature:  ' . $signature_ipn);
                                                           $this->log->add( 'mlepay', 'received signature:  ' . $headers['X-Signature']);
                                                           $this->log->add( 'mlepay', 'woo_api_url:  ' . $woo_api_url);                            
                                                           $this->log->add( 'mlepay', 'string:  ' . $scheme.$woo_site.$gateway_extension);
                                                           $this->log->add( 'mlepay', 'base_string:  ' . $base_string);                                                                                                                                                                     
                                                           $this->log->add( 'mlepay', 'payload:  ' . $result['payload']);
                                                           $this->log->add( 'mlepay', 'Transaction status:  ' . $result['transaction_status']);                                    
                                                      }
                                                   */
 








$mlepay_response_txnid = $result['payload'];







           // Check if Active: WooCommerce Plugin "Custom Order Numbers" by unaid Bhura / http://gremlin.io/ 
           if( class_exists( 'woocommerce_custom_order_numbers' ) ) {


                 global $wpdb;

                 $wpdb->postmeta = $wpdb->base_prefix . 'postmeta';

		$retrieved_order_id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_custom_order_number' AND meta_value = '$mlepay_response_txnid'" );		
		  
                 $txnid_to_use = $retrieved_order_id;

                  //for test
                  //echo 'case custom order number plugin used';
                  //echo $txnid_to_use;



           }else{

                  // just use regular woocommerce order (real woocommerce order = txnid used with mlepay)
                  $txnid_to_use = $mlepay_response_txnid;
 

                  // for test
                  // echo 'case no custom order number plugin used';
                  //echo $txnid_to_use;

           }
















$order = new WC_Order( $txnid_to_use );  // important this must be located here for being also able to get log for very bellow case when digest is wrong 












        // Compare signature in header notification  with the true signature
        if( $headers['X-Signature'] == $signature_ipn ) {











                                               // For test
                                                /* 
                                                   if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
                                                           $this->log->add( 'mlepay', 'signature is OK');
                                                          
                                                      }
                                                */  













   /////////  check if order status exist in woocommerce

   $post_status = "";

   global $wpdb;  
   $wpdb->posts = $wpdb->base_prefix . 'posts';      
   $post_status = $wpdb->get_var( "SELECT post_status FROM $wpdb->posts WHERE post_type = 'shop_order' AND ID = '$txnid_to_use'");

   // for test
   //echo "post_status:  ".$post_status;
   //echo "strlen:  " .strlen($post_status);



   // only continue with existing order in woocommerce (that have an existing order status)
   // for info: when custom_order_numbers plugin used and if no order is found, this custom_orders_numbers plugin set post_id (real woocomerce order id) to zero "0"
   // if status have at least 2 characters long it's exist 
   if(strlen($post_status) > 2 ) {

   ////////////////////











///////////////// Available WooCommerce order status   //////////////////////////////
////////////////////////////////////////////////////////////////////////////////////
////    Pending     – Order received (unpaid)
////    Failed      – Payment failed or was declined (unpaid)
////    Processing  – Payment received and stock has been reduced- the order is awaiting fulfilment
////    Completed   – Order fulfilled and complete – requires no further action
////    On-Hold     – Awaiting payment  
////    Cancelled   – Cancelled by an admin or the customer – no further action required
////    Refunded    – Refunded by an admin – no further action required
/////////////////////////////////////////////////////////////////////////////////////









          
         











                                                 // for test
                                                  /*
                                                    if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {

                                                          $this->log->add( 'mlepay', 'ML ePay - Notification Received - Transaction status ' . $result['transaction_status']);
	                                            }

                                                  */












     if ( ! empty( $result['transaction_status'] ) ) {



              

             




         switch ( $result['transaction_status'] ) {


              






                         #################### CASE:  transaction is "PAID"  ####################
                          case 'PAID':
						

				   if($order->status == 'processing' OR $order->status == 'completed'){
                                         
                                          // nothing to do


                                         exit;	



                                      
				    }else{



                                         // update order status (an admin note will be also created)
                                         $order->update_status('processing'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> ML ePay Payment SUCCESSFUL<br/> -> ML ePay Transaction Code: ' . $result['transaction_code'] . '<br/> -> Order Status Updated to PROCESSING<br/> -> We will be shipping your order to you soon', 1); 





                                          
                                                    if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {

                                                          $this->log->add( 'mlepay', 'ML ePay - Notification Received - SUCCESSFUL Transaction ' . $result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

                                                          $this->log->add( 'mlepay', 'Order Status Updated to PROCESSING - SUCCESSFUL Transaction ' . $result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

	                                            }






                                         // reduce stock
				         $order->reduce_order_stock(); // if physical product vs downloadable product

                                         //empty cart
                                         // not needed as cart was make empty when transaction code is generated 





                                         exit;	

				    }  


                                   break;


	             













                   #################### CASE transaction is "EXPIRED"  ####################
                   case 'EXPIRED':      // Expired transaction at ML ePay can not be proceded again
                                        // ML ePay allow to re-create transaction code for same order but we preffer cancel order as stock can change after expiration, so better customer re-order again as there is new stock check

                                  

				   if($order->status == 'cancelled'){
                                  				         
 
                                         // nothing to do
          


                                         exit;	


                                      
				    }else{

                                       
                                         // update order status (an admin note will be also created)
                                         $order->update_status('cancelled'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> ML ePay Transaction Code EXPIRED<br/> -> ML ePay Transaction Code: ' .$result['transaction_code'] . '<br/> -> Order Status Updated to CANCELLED', 1);  





                                                    if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {

                                                          $this->log->add( 'mlepay', 'ML ePay - Notification Received - EXPIRED Transaction Code ' . $result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

                                                          $this->log->add( 'mlepay', 'Order Status Updated to CANCELLED - EXPIRED Transaction Code ' . $result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

	                                            }




 

                                         // no reduce order stock needed


	                                 //empty cart
                                        // not needed as cart was make empty when transaction code is generated 





                                         exit;	

				    }  


                                    break;

























                   #################### Case transaction is "CANCELLED"  ####################
                   case 'CANCELLED':                   
                                  
				   if($order->status == 'cancelled'){
                                  			         
                                         //Nothing to do


                                         exit;	


                                      
				    }else{
                                       

                                         // update order status (an admin note will be also created)
                                         $order->update_status('cancelled'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> ML ePay Transaction CANCELLED <br/> -> ML ePay Transaction Code: '.$result['transaction_code'] . '<br/> -> Order Status Updated to CANCELLED', 1); 





                                                    if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {

                                                          $this->log->add( 'mlepay', 'ML ePay - Notification Received - CANCELLED Transaction ' . $result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

                                                          $this->log->add( 'mlepay', 'Order Status Updated to CANCELLED - CANCELLED Transaction ' .$result['transaction_code'] . ' - For Order ' . $order->get_order_number() );

	                                            }






                                         // no reduce order stock needed


	                                 //empty cart
                                          // not needed as cart was make empty when transaction code is generated 





                                         exit;	

				    }  


                                    break;


















                   #################### Case  transaction is  NO ERROR CODE OR STATUS GIVEN IN BACK ####################
                   default :                                                    
 
                                    exit;

                                    break;




















            
              }  // end:    switch


            exit;



          }   //end:     if ( ! empty( $result['transaction_status'] ) )


     
         } // end:     if order exist in woocommerce:    if(strlen($post_status) > 2 )











}else{              // end:    if( $headers['X-Signature'] == $signature_ipn )     





             // Case  Signature was false



                                  if ( 'yes' == get_option('woocommerce_mlepay_settings')['debug'] ) {
                                  $this->log->add( 'mlepay', 'Notification Received - WRONG SIGNATURE - Transaction ' .$result['transaction_code'] . ' - For order ' . $order->get_order_number() );

                                   $this->log->add( 'mlepay', 'ML ePay - *** IMPORTANT *** If you are testing the Gateway, you should double check 1/ Merchant Email address, 2/ Merchant Secret Key, YOU FILLED AT: WooCommerce -> Settings -> Checkout -> ML ePay' );


	                           }


                        exit;



}




























    }  // end:    function mlepay_response
























      /**
      * Generate random string for nonce
      */
      function randString($length) {

          $charset='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
          $str = '';
          $count = strlen($charset);

          while ($length--) {
              $str .= $charset[mt_rand(0, $count-1)];
          }

          return $str;

      }














      /**
      * Request header
      */
      function parse_request_headers() {
        
        $headers = array();
        foreach($_SERVER as $key => $value) {
        
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
        
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        
        }
        
        return $headers;
      
      }





    }
    
    








	/**
	* Add Settings link to the plugin entry in the plugins menu
	**/	
		

		function mlepay_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_controller_mlepay">Settings</a>';

		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	

               add_filter('plugin_action_links', 'mlepay_plugin_action_links', 10, 2);


















	/**
 	* Display the wrong currency notice
 	**/
	function wrong_currency_notice_mlepay(){
		
                        
                         if( get_woocommerce_currency() != 'PHP' ){

   
                              // end of php to start html
	                      ?>

		               <div class="update-nag">
		                  <b>ML ePay Payment Gateway require that WooCommerce Currency be set to Philippine Peso</b><br>                                       
     	                      </div>


	                      <?php 
                              // re-start of php

		         }
	
         }










add_action( 'admin_notices', 'wrong_currency_notice_mlepay' );




















    
    
    
	/**
 	* Add mlepay Gateway to WC
 	**/
    function woocommerce_mlepay_add_gateway( $methods ) {
        $methods[] = 'WC_Controller_Mlepay';
        return $methods;
    }


    add_filter( 'woocommerce_payment_gateways', 'woocommerce_mlepay_add_gateway' );








    

  }
}











?>
