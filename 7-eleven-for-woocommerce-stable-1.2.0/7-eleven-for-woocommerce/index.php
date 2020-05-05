<?php
/*
	Plugin Name: 7-ELEVEN For WooCommerce
	Plugin URI: https://github.com/siteshop-ph?tab=repositories
	Description: 7-ELEVEN (7-CONNECT Payment Gateway) for accepting Over-The-Counter Cash Payments in the Philippines. Because credit card and banking penetration is too low ; 7-ELEVEN makes accessible payment options to the masses! <strong>This plugin require a 7-CONNECT Merchant Account to order <a href="http://7-connect.philseven.com/merchants/" target="_blank">Here</a></strong> ; 
	Version: 1.2.0
	Author: Serge Frankin SiteShop.ph (Netpublica.com Corp.)
*/ 








//Load the function
add_action( 'plugins_loaded', 'woocommerce_7eleven_init', 0 );

/**
 * Load 7eleven gateway plugin function
 * 
 * @return mixed
 */
function woocommerce_7eleven_init() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
         return;
    }











error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);


     // to prevent PHP notice when wp debug mode is enabled





  
    




    /**
     * Define the 7eleven gateway
     * 
     */
    class WC_Controller_7eleven extends WC_Payment_Gateway {

        /**
         * Construct the 7eleven gateway class
         * 
         * @global mixed $woocommerce
         */
        public function __construct() {

            global $woocommerce;

            $this->id = '7eleven';
            $this->icon = plugins_url( 'assets/7-eleven.png', __FILE__ );  
            $this->has_fields = false;
          
            $this->method_title = __( '7-ELEVEN', 'woocommerce_7eleven' );


            // Load the form fields.
            $this->init_form_fields();


            // Load the settings.
            $this->init_settings();





            // Define user setting variables.
            $this->enabled = $this->settings['enabled'];
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];


                       
   


            // Actions.
            add_action( 'woocommerce_receipt_7eleven', array( &$this, 'receipt_page' ) );



            // Active logs.
		if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $this->woocommerce_instance()->logger();
			}
		}

            


          //save setting configuration
          add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
               
         // Payment API hook
         add_action( 'woocommerce_api_wc_controller_7eleven', array( $this, 'Seven_eleven_response' ) );

        

        }



        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            ?>
            <h3><?php _e( '7-ELEVEN', 'woocommerce_7eleven' ); ?></h3>
            <p><?php _e( '7-ELEVEN (7-CONNECT Payment Gateway) is one of most popular payment gateway in the Philippines, its makes possible to pay cash at any 7-ELEVEN Store.', 'woocommerce_7eleven' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }





        /**
         * Gateway Settings Form Fields.
         * 
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce_7eleven' ),
                    'type' => 'checkbox',
                    'label' => __( '  Enable/Disable Plugin' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce_7eleven' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce_7eleven' ),
                    'default' => __( '', 'woocommerce_7eleven' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce_7eleven' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce_7eleven' ),
                    'default' => __( 'Pay in cash at any 7-Eleven outlet nationwide', 'woocommerce_7eleven' )
                ),
        		'merchant_id' => array(
                    'title' => __( '7-CONNECT Merchant\'s ID', 'woocommerce_7eleven' ),
                    'type' => 'text',
                    'description' => __( 'Enter your Merchant\'s ID as provided by 7-CONNECT.', 'woocommerce_7eleven' ),
                    'default' => 'my-7-connect-merchant-id'
                ),
                'api_password' => array(
                    'title' => __( '7-CONNECT Transaction Key', 'woocommerce_7eleven' ),
                    'type' => 'textarea',
                    'description' => __( 'Enter your Transaction Key as provided by 7-CONNECT.', 'woocommerce_7eleven' ),
                    'default' => 'my-7-connect-transaction-key'
                ),
                'test_mode' => array(
                    'title' => __( 'Gateway Test Mode', 'woocommerce_7eleven' ),
                    'type' => 'checkbox',
                    'description' => __( 'Enable this if you want to use your 7-CONNECT Test Account with no real money transaction, when disabled you will be using your 7-CONNECT Production Account', 'woocommerce_7eleven' ),
                    'default' => 'yes'
	        ), 
		'debug' => array(
		     'title' => __( 'Debug Log', 'woocommerce-7eleven' ),
	             'type' => 'checkbox',
	             'label' => __( 'Enable Debug log', 'woocommerce-7eleven' ),
		     'default' => 'yes',
                     'description' => sprintf( __( 'Log 7-CONNECT events, such as Web Redirection, Notification, inside: log file %s', 'woocommerce-7eleven' ), '<code>wp-content/uploads/wc-logs/7eleven-' . sanitize_file_name( wp_hash( '7eleven' ) ) . '.log</code>&nbsp;&nbsp;&nbsp;<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-status&tab=logs">See log file content</a>' )
		), 
		'last_ran_cron_synchronization' => array(
		     'title' => __( 'Daily Order Status Synchronization', 'woocommerce_7eleven' ),
	             'type' => 'text',
	             'label' => __( 'Last Sync timestamp', 'woocommerce_7eleven' ),
		     'default' => '',
                     'disabled' => true,
	             'description' => __( 'Last Successful Sync - Timestamp<br><br><u>Explanation:</u><br>WP-Cron, it s an auto scheduled task run every 24 hours to synchronize your order status with your 7-CONNECT account. It\'s usefull in case on the fly order status update failled with 7-CONNECT notification. Also because 7-CONNECT do not send notify when transaction EXPIRED, so this daily syncronization is helpful to Cancel wooCommerce order associated with a 7-CONNECT EXPIRED transaction.', 'woocommerce_7eleven' )
		),
		'display_postback_url' => array(
		     'title' => 'Postback URL',
                     'type' => 'title',
		     'description' => 'This URL should be communicated to 7-CONNECT Support:<br><font color="red"><code>'.WC()->api_request_url('WC_Controller_7eleven').'</code></font>',
		     'desc_tip' => false,
                     'default' => ''                     
		)          
            );
        }



















        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {


            global $woocommerce;


	    $order = new WC_Order( $order_id ); 
            
   
 
          





           // Check if Active: WooCommerce Plugin "Custom Order Numbers" by unaid Bhura / http://gremlin.io/ 
           if( class_exists( 'woocommerce_custom_order_numbers' ) ) {

                   // take CUSTOM order id string from WooCommerce Plugin "Custom Order Numbers" (there can be prefix, etc)
	           $merchantRef = $order->custom_order_number; 

           }else{

                   // just use regular woocommerce order id as txnid for 7eleven
                   $merchantRef = $order->id;
 
           }






















$merchantID = get_option('woocommerce_7eleven_settings')['merchant_id'];

$transactionKey = html_entity_decode(get_option('woocommerce_7eleven_settings')['api_password']);





//for test     you have also to disable this line below:  header("Location: $url_request_params");
    //echo "merchantID:  ". $merchantID;
    //echo "transactionKey:  ". $transactionKey;     // IMPORTANT MAKE IT FALSE BEFORE TO ECHO

















  // get String for :
        $firstName = $order->billing_first_name;
        $lastName = $order->billing_last_name;
        $address1 = $order->billing_address_1;
        $address2 = $order->billing_address_2;
        $city = $order->billing_city;
        $state = $order->billing_state;
        $country = $order->billing_country;
        $zipCode = $order->billing_postcode;
        $telNo = $order->billing_phone;
        $email = $order->billing_email;



  // For check purpose:     you have also to disable this line below:  header("Location: $url_request_params");

 /*
          echo "merchantID:  " . $merchantID . "<br><br>";
          echo "merchantRef:  " . $merchantRef . "<br><br>";
          echo "firstName:  " . $firstName . "<br><br>";
          echo "lastName:  " . $lastName . "<br><br>";
          echo "address1:  " . $address1 . "<br><br>";                   
          echo "address2:  " . $address2 . "<br><br>";
          echo "city:  " . $city . "<br><br>";
          echo "state:  " . $state . "<br><br>";
          echo "country:  " . $country . "<br><br>";
          echo "zipCode:  " . $zipCode . "<br><br>";
          echo "telNo:  " . $telNo . "<br><br>";
          echo "email:  " . $email . "<br><br>";

*/








 $successURL = WC()->api_request_url('WC_Controller_7eleven');


 $failURL = WC()->api_request_url('WC_Controller_7eleven');


 $returnPaymentDetails = "Y";








 
            ## Hostname of woocommerce install
            $hostname = $_SERVER['HTTP_HOST']; 




	    //$amount = $order->order_total;
            $amount = number_format ($order->order_total, 2, '.' , $thousands_sep = '');
	    $ccy = get_woocommerce_currency();
	    $transactionDescription = 'Your order on '.$hostname;
	      // $email = $order->billing_email;   // ever set before
	     // $transactionKey = html_entity_decode(get_option('woocommerce_7eleven_settings')['api_password']);     // ever set before
	    
	  



             ## purge old values if there are
             $token_str = "";
             $token = "";



     
            ## create the token for 7eleven
            $token_str = $merchantID . $merchantRef . '{' . $transactionKey . '}' ;  


            ## to create 40 Char sha1
            $token = sha1($token_str, $raw_output = false);






                        
            $args = array(
                'merchantID' => $merchantID,
                'merchantRef' => $merchantRef,
                'amount' => $amount,
                'successURL' => $successURL,
                'failURL' => $failURL,
                'email' => $email,
                'transactionDescription' => $transactionDescription,
                'returnPaymentDetails' => $returnPaymentDetails,
                'token' => $token,
                
            );



            $args_array = array();






        if(get_option('woocommerce_7eleven_settings')['test_mode'] == 'yes'){


		            $url = 'https://testpay.cliqq.net/transact?';   // test

	   }else{

		           $url = 'https://pay.7-eleven.com.ph/transact?';  // live       
	}



















          if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                   if(get_option('woocommerce_7eleven_settings')['test_mode'] == 'yes'){

			   $this->log->add( '7eleven', '7-CONNECT - TEST ACCOUNT USED' );
	           
                   }else{

                           $this->log->add( '7eleven', '7-CONNECT - PRODUCTION ACCOUNT USED' );

                   }

          }















			foreach ($args as $key => $value) {
			     	$args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}






















/////////// START:  update woo created order status /////////////////////////////////////



   // since 7-connect do not sent notify for created transaction, 
   // so for every submit order at checkout (never mind having 7-connect error or not), we pass woo order to 'on-hold'

   // so here, we do not have yet the payID (7-connect transaction ID), because 7-connect only diplay it for shopper at 7-connect website



				   if($order->status == 'on-hold'){
                                  				         
                                         //No update needed (to prevent double notification from GET and POST)


                                         //No add note needed   (to prevent double notification from GET and POST)

                                       


                                                   if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {
                                                           $this->log->add( '7eleven', '7-CONNECT - Transaction created - For Order ' . $order->get_order_number() );
                                                    }




                                         exit;	// to prevent new transaction creation since 7-connect do not check if woo order ID is unique

                                      



				    }else{


	         

                                         // update order status (an admin note will be also created)
                                         $order->update_status('on-hold'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> 7-ELEVEN Transaction created<br/> -> Order Status Updated to ON-HOLD', 1);   
	         
                                         // no reduce order stock needed


                                         //empty cart
                                         $woocommerce->cart->empty_cart();

 


                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                                                           $this->log->add( '7eleven', '7-CONNECT - Transaction created - For Order ' . $order->get_order_number() );

                                                           $this->log->add( '7eleven', 'Order Status Updated to ON-HOLD - For Order ' . $order->get_order_number() );
                                                    
	                                            }




                                         //exit;
	

				    }  






/////////// END:  update woo created order status /////////////////////////////////////















// for info, web redirect straigh from 7-connect if token is wrong:   http://demo-7-connect-woocommerce.siteshop.ph/index.php/wc-api/WC_Controller_7eleven/?message=Invalid+Token.









// for test
  //echo $merchantID;
  // echo get_option('woocommerce_7eleven_settings')['test_mode'];
























// START:    THIS BLOCK CAN BE DISABLED TO    STOP REDIRECTION TO 7eleven


			wc_enqueue_js( '
				jQuery.blockUI({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to 7-ELEVEN Site.', 'woocommerce-7eleven' ) ) . '",
					baseZ: 99999,
					overlayCSS: {
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:		"24px",
					}
				});
				jQuery("#submit-payment-form").click();
			' );


// END:    THIS BLOCK CAN BE DISABLED TO    STOP REDIRECTION TO 7-ELEVEN










		return '<form action="' . esc_url( $url ) . '" method="get" id="payment-form" target="_top">
				' . implode( '', $args_array ) . '
				<input type="submit" class="button alt" id="submit-payment-form" value="' . __( 'Pay via 7-ELEVEN', 'woocommerce-7eleven' ) . '" /> 
			</form>';


        }









        /**
         * Order error button.
         *
         * @param  object $order Order data.
         * @return string Error message and cancel button.
         */


            // function name can not start by number

        protected function Seven_eleven_order_error( $order ) {
            $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woocommerce_7eleven' ) . '</p>';
            $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'woocommerce_7eleven' ) . '</a>';
            return $html;
        }










        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */

    
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

			return array(
				'result'   => 'success',
			   'redirect' => $order->get_checkout_payment_url( true )
			);

      }

   











        /**
         * Output for the order received page.
         * 
         */
        public function receipt_page( $order ) {
            echo $this->generate_form( $order );
            
        }


















           // function name can not start by number
function Seven_eleven_response($merchantRef_to_use){


// Example of response from 7-connect:
//     


/*

*/	
   


        global $woocommerce;

   
   




// IMPORTANT: for info 7-connect send very different data depending:


     // if redirection back to shop (GET):  
                // no transaction status given back by 7-connect


     // if IPN (POST): 
              //authentication by token (like a digest), all parameters in the body


     








///////// START:  For test : get full  7-connect data /////////////////////////////

/*

        $get_input_data =  $_SERVER["QUERY_STRING"];             //get data


        $body_response = file_get_contents('php://input');        //post data



             // log.txt 
                  
               $fp = fopen('/home/demo-7-connect-woocommerce/public_html/test/log.txt', 'a');                                                                        
                       fwrite($fp, date("Y-m-d H:i:s",time()) . "  GET: " . $get_input_data ."\n");                  
                       fwrite($fp, date("Y-m-d H:i:s",time()) . "  POST:  " . $body_response ."\n");                                                  
               fclose($fp);  


*/


///////// END:  For test : get full  7-connect data /////////////////////////////
















                        



                               if ( isset( $_REQUEST['merchantRef'] ) AND !isset( $_REQUEST['type'] ) ) {



                                          $response_case = "REDIRECTION";           



                                           // CASE :  Redirect URL case from 7-connect to shop after well created transaction:  




                                            // e.g.:    http://ok.com/?merchantID=designplustest&merchantRef=0000001&amount=1000.00&payID=9916-2840-0202&token=b1779cd6ac82482bd692d96666bd0f726663cccd&proceed=Continue+browsing

                                             
                                             $redirect_params = $_SERVER["QUERY_STRING"];   //all GET data;   

                                        
                                             $response_merchantRef = $_REQUEST['merchantRef'];  // woo order id

                                             $response_payID = $_REQUEST['payID'];              // 7-connect id


                                             
                                }















                               if ( isset( $_REQUEST['message'] ) AND !isset( $_REQUEST['type'] ) ) {


                                        // CASE : ERROR



                                        // e.g.,  7-connect redirect straight back to shop if token is wrong:   http://demo-7-connect-woocommerce.siteshop.ph/index.php/wc-api/WC_Controller_7eleven/?message=Invalid+Token. 



                                             $redirect_params = $_SERVER["QUERY_STRING"];   //all GET data;     
                                             

                                             $response_case = "ERROR"; 


                                             $GET_ERROR_message = $_REQUEST['message']; 

                                             
                                             
                                }
















                              if ( isset( $_REQUEST['type'] ) ) {



                                        // CASE:  NOTIFY 



                                           // data structure send from 7-connect:  (REQUEST): 
                                             //  http://demo-7-connect-woocommerce.siteshop.ph/wc-api/WC_Controller_7eleven/?sevenConnectId=9916-2870-0080&type=VALIDATE&merchantRef=118&amount=20.0&token=200f36a5443840949512cb88484defc7fd85c14b&paymentDetails=%7B%22payID%22%3A%229916-2870-0080%22%2C+%22store%22%3A%220001%22%2C+%22pos%22%3A%222%22%7D



                                             $response_params = file_get_contents('php://input');  // all received POST data


                                             $response_case = "NOTIFICATION";  
                                             $response_transactiontype = $_REQUEST['type'];
                                             $response_merchantRef = $_REQUEST['merchantRef'];
                                             $response_amount = $_REQUEST['amount'];
                                             $response_token = $_REQUEST['token'];
                                             $response_payID = $_REQUEST['sevenConnectId']; 




                                              // for being able to check later if token is valid
                                                  $true_transactionKey = html_entity_decode(get_option('woocommerce_7eleven_settings')['api_password']); // true secret
                                                  $true_merchantID = get_option('woocommerce_7eleven_settings')['merchant_id']; // since not given in the 7-connect response
 
                                                       ## create the true token for 7eleven
                                                           $true_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . '{' . $true_transactionKey . '}';

                                                           ## to create 40 Char sha1
                                                           $true_token = sha1($true_token_str, $raw_output = false);



                               
                              }   



















///////// START:  For test complementary info :  /////////////////////////////

/*

             // log.txt 
                  
               $fp = fopen('/home/demo-7-connect-woocommerce/public_html/test/log.txt', 'a');                                                                        
                       fwrite($fp, date("Y-m-d H:i:s",time()) . "  RESPONSE CASE: " . $response_case ."\n");                  
                                                 
               fclose($fp);  


*/

///////// END:  For test complementary info  /////////////////////////////























//////////////// START:  "error" page  ////////////////////////////////


// This is for not having log writting for non-related to 7-connect data received
if ( $response_case == "ERROR" ) {   



        // case GET data :  redirection from 7-connect back to shop

        // from 7-connect API: in this case, no transaction status given back,








         // Display error description at customer frontend
          echo '<br><br><br><div><big><big><font color="#e02b2b"><center><u>Error:</u></big>  ' ;


                                       
          echo "<br> For error detail please check your 7-ELEVEN logs at your shop panel:
                 <br><br> WooCommerce >> System Status >> Logs >> 7eleven - xxxxxxxxxxxxxxxxxx.log  >> View" ;
                                      






      echo  '</font><br><br><br><br><font color="blue">IF YOU ARE A SHOPPER PLEASE COPY ABOVE MESSAGE AND SEND IT BY EMAIL TO THE SHOP ADMIN</font></b>       </center></big></div> ';

     






           
                              if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {
                                                                 
                                                                                                                                      
                                               $this->log->add( '7eleven', '7-CONNECT - API - *** ERROR *** ' . $GET_ERROR_message . ' - Redirect params - ' . $redirect_params );
                                       

      
                              }  




            // empty cart
                 // not needed 



                 exit;   // Important to exist since some bellow variable like $response_merchantRef for case "REDIRECTION" or "NOTIFICATION" do not exist




}


//////////////// END:  "error" page  ////////////////////////////////














           // Check if Active: WooCommerce Plugin "Custom Order Numbers" by unaid Bhura / http://gremlin.io/ 
           if( class_exists( 'woocommerce_custom_order_numbers' ) ) {


                 global $wpdb;

                 $wpdb->postmeta = $wpdb->base_prefix . 'postmeta';

	        	$retrieved_order_id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_custom_order_number' AND meta_value = '$response_merchantRef'" );		
		  
                 $txnid_to_use = $retrieved_order_id;

                  //for test
                  //echo 'case custom order number plugin used';
                  //echo $txnid_to_use;



           }else{

                  // just use regular woocommerce order (real woocommerce order = txnid used with 7-connect)
                  $txnid_to_use = $response_merchantRef;
 

                  // for test
                  // echo 'case no custom order number plugin used';
                  //echo $txnid_to_use;

           }



















//////////////// START:  "order confirmation" page  ////////////////////////////////


// This is for not having log writting for non-related to 7-connect data received
if ( $response_case == "REDIRECTION" AND isset( $_REQUEST['merchantID'] ) ) {   



        // case GET data :  redirection from 7-connect back to shop

        // from7-connect API: in this case, no transaction status given back,








$order = new WC_Order( $txnid_to_use );







                                         ///////////// Do the redirection

                                         // hard coded redirection way for the ending point name "order-received":
                                         //$redirect = add_query_arg('key', $order->order_key, add_query_arg('order-received', $txnid_to_use, $this->get_return_url($order)));
                                         
                                      
                                        // dynamic redirection way for ending point name
                                        // (in case it was renamed from woocommerce general checkout settings):
                                         global $wpdb;

                                         $wpdb->options = $wpdb->base_prefix . 'options';

		                                 $order_received_endpoint = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'woocommerce_checkout_order_received_endpoint'" );



                                     




                                                          $redirect = add_query_arg('key', $order->order_key, add_query_arg($order_received_endpoint, $txnid_to_use, $this->get_return_url($order)));   // so "order confimation" page WILL INCLUDE order detail recap 



                                                          // Example of retrieved url  ($redirect value)
                                                          // N.B.: wc_order_553a37860ff34 can also be found from postmeta table, but we do not used that way
                                                          // http://demo-woocommerce.siteshop.ph/checkout/order-received/?order-received=131&key=wc_order_553a37860ff34





                                                          wp_redirect($redirect); //do the redirect






                                                                   if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                                                                         $this->log->add( '7eleven', 'REDIRECTION - BACK TO SHOP FROM 7-CONNECT - payID -  '.$response_payID.' - For Order ' . $order->get_order_number() . ' - Redirect params - ' . $redirect_params );

	                                                            }




                                         exit;	


}


//////////////// END:  "order confirmation" page  ////////////////////////////////

















   




// This is for not having log writting for non-related to 7-connect data received
if ( $response_case == "NOTIFICATION" AND isset( $_REQUEST['token'] ) ) {        // these value come from above


        
           // case POST data notification from 7-connect









$order = new WC_Order( $txnid_to_use );  // important this must be located here for being also able to get log for very bellow case when digest is wrong 












                               if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                                         $this->log->add( '7eleven', $response_transactiontype . ' - REQUEST RECEIVED FROM 7-CONNECT - PayID - ' . $response_payID . ' - For Order ' . $order->get_order_number() . ' - Request Params - ' . $response_params );                                                          

	                       }














        // check if 7-connect POST NOTIFICATION IS AUTHENTIC
         // Disable this line when testing all gateway type of response 
    if( $response_token == $true_token ) {     















   /// check if order status exist in woocommerce


   //////////////////////////////////////////////


   // IMPORTANT  NONE OF THIS OTHER WY WAS WORKING:

//if(!is_null($txnid_to_use)) {                 // ok with custom_order_numbers plugin used       NO without plugin
//if(isset($txnid_to_use)) {                    // ok with custom_order_numbers plugin used     NO without plugin
//if(!is_null($txnid_to_use) AND isset($txnid_to_use) ) {
//$order = if(new WC_Order( $txnid_to_use )){   // ;
// only continue if 
// if (!is_null(new WC_Order( $txnid_to_use))) {
// only continue with order existing in woocommerce
//$status = $order->status;
//if(isset($tatus)) {
//if(is_bool($status)) {

   ///////////////////////////////////////////










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


      



  
                                 
     	


	      switch ( $response_transactiontype ) {
			







                          #################### CASE:  VALIDATE  ####################
                          case 'VALIDATE':


                                   // 7-connect ask here for confirmation that order can be pay by shopper, 
                                   //so we could also check here if item stock is ok, etc....

						

				   if($order->status == 'processing' OR $order->status == 'completed'){


                                            $confirmation_authCode = "";                 // confirmation code 
                                            $confirmation_responseCode = "DECLINED";     // for 7-connect confirmation
                                            $confirmation_responseDesc = "EVER PAID";    // for 7-connect confirmation

                                            $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                                             ## to create 40 Char sha1
                                             $confirmation_token = sha1($confirmation_token_str, $raw_output = false);


                                   				         
                                            //No update needed


                                            //No add note needed






                                             //exit;	




                                      
				    } else {


                                             $confirmation_authCode = "";                      // confirmation code
                                             
                                             $confirmation_responseCode = "SUCCESS";          // for 7-connect confirmation
                                             $confirmation_responseDesc = "SUCCESSFUL";       // for 7-connect confirmation


                                            $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                                             ## to create 40 Char sha1
                                             $confirmation_token = sha1($confirmation_token_str, $raw_output = false);



                                   				         
                                              //No update needed


                                              //No add note needed


      



                                               //exit;	



				    }  



                                   break;










                      
                          #################### CASE:  transaction is PAID  ####################
                          case 'CONFIRM':
						

				   if($order->status == 'processing' OR $order->status == 'completed'){

                                        // this case should even not happen, since there is the "VALIDATE" step before

                                        
                                           $confirmation_authCode = substr(md5(rand()),0,15);   // confirmation code , could be anything, but it's required for "CONFIRM" case

                                           $confirmation_responseCode = "SUCCESS";          // for 7-connect confirmation
                                           $confirmation_responseDesc = "SUCCESSFUL";       // for 7-connect confirmation



                                            $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_authCode . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                                             ## to create 40 Char sha1
                                             $confirmation_token = sha1($confirmation_token_str, $raw_output = false);





                                            //No update needed


                                            //No add note needed


                                             //exit;	



                                      
				    }else{



                                      $confirmation_authCode = substr(md5(rand()),0,15);   // confirmation code , could be anything, but it's required for "CONFIRM" case

                                      $confirmation_responseCode = "SUCCESS";          // for 7-connect confirmation
                                      $confirmation_responseDesc = "SUCCESSFUL";       // for 7-connect confirmation



                                       $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_authCode . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                                        ## to create 40 Char sha1
                                        $confirmation_token = sha1($confirmation_token_str, $raw_output = false);





                                         // update order status (an admin note will be also created)
                                         $order->update_status('processing'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> 7-ELEVEN Payment SUCCESSFUL<br/> -> PayID: '.$response_payID.'<br/> -> Order status updated to PROCESSING<br/> -> We will be shipping your order to you soon', 1); 

                                         // reduce stock
				         $order->reduce_order_stock(); // if physical product vs downloadable product

                                         //empty cart
                                         $woocommerce->cart->empty_cart();





                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                                                          $this->log->add( '7eleven', '7-CONNECT - PAID - PayID - ' .$response_payID . ' - For Order ' . $order->get_order_number() );

                                                          $this->log->add( '7eleven', 'Order Status updated to PROCESSING - payment SUCCESSFUL - PayID ' . $response_payID . ' - For Order ' . $order->get_order_number() );

	                                            }





                                               //exit;	



				    }  



                                   break;


	             











                   #################### Case  transaction no "type" given  ####################
                   default :                                                    
 
                                   
                                     $confirmation_authCode = "";                                   // confirmation code 
                                     $confirmation_responseCode = "DECLINED";                       // for 7-connect confirmation
                                     $confirmation_responseDesc = "UNKNOWN TRANSACTION TYPE";       // for 7-connect confirmation



                                     $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                                     ## to create 40 Char sha1
                                     $confirmation_token = sha1($confirmation_token_str, $raw_output = false);





                                     // no redirection needed
                                     
                                     //exit;

                                     // break;













}     //END:      Switch           

   









}      // END:        if order exist in woocommerce:    if(strlen($post_status) > 2 )









//            /*           // Enable this line when testing all gateway type of response









}else{               // end:     check if 7-connect POST NOTIFICATION IS AUTHENTIC    





             // Case  AUTHENTICATION of notify was FALSE


                        $confirmation_authCode = "";                     // for 7-connect confirmation
                        $confirmation_responseCode = "DECLINED";         // for 7-connect confirmation
                        $confirmation_responseDesc = "INVALID TOKEN";    // for 7-connect confirmation



                        $confirmation_token_str = $response_transactiontype . $true_merchantID . $response_merchantRef . $confirmation_responseCode . '{' . $true_transactionKey . '}';

                        ## to create 40 Char sha1
                        $confirmation_token = sha1($confirmation_token_str, $raw_output = false);




                            
                        // nothing to do
                               

                          

                                  if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {
                                             $this->log->add( '7eleven', '7-CONNECT - *** WRONG AUTHENTICATION *** - payID - ' . $response_payID . ' - For order - ' . $response_merchantRef );
	                           }





                        //exit;




}






//            */             // Here enable this line when testing all gateway type of response














/////////////////// START:      Print confirmation/response data for 7-connect   ///////////////////////////////////////////////////


                                    

 //set like GET variables way
   
      $confirmation_params = "?type=" . urlencode($response_transactiontype) .
                      "&merchantID=" . urlencode($true_merchantID) .
		      "&merchantRef=" .  urlencode($response_merchantRef) . 
		      "&amount=" . urlencode($response_amount) .
		      "&authCode=" . urlencode($confirmation_authCode) .
		      "&responseCode=" . urlencode($confirmation_responseCode) .
                      "&responseDesc=" . urlencode($confirmation_responseDesc) .  
		      "&token=" . urlencode($confirmation_token) ;
   
   
       // print response in the body for 7-connect confirmation
        echo $confirmation_params;       


       



                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {

                                                              $this->log->add( '7eleven', $response_transactiontype . ' - RESPONSE GIVEN TO 7-CONNECT - ResponseCode - ' . $confirmation_responseCode . ' - ResponseDesc - ' . $confirmation_responseDesc . ' - PayID - ' . $response_payID . ' - Order - ' . $order->get_order_number() . ' - Response Params - ' . $confirmation_params );

	                                            }








        die();    // if not die() , the $confirmation_params will not be echoed but only "-1"  for successfull call back & "1" for not sucessfull will be echoed.

/*
why die ():
The reason might be that this action is been called via some ajax. In wordpress the ajax default returns the die() function before the function ends. So if we don't specify die() in our custom function it will append 1 with the data in our callback. So you have to call die or exit after the output is being echo to the browser to prevent returning and other data in our custom functions which are been called via ajax.


*/


/////////////////// END:      Print confirmation/response data for 7-connect   ///////////////////////////////////////////////////






         





}else{  

              // end :       if ( $response_case == "NOTIFICATION" AND isset( $_REQUEST['token'] ) ) { 

 
               
                      // case there was no POST data & no token data in the body



                               // nothing to do
                       




}

}




}











	/**
	* Add Settings link to the plugin entry in the plugins menu
	**/	
		



                // function name can not start by number
        
		function Seven_eleven_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_controller_7eleven">Settings</a>';

		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	

               add_filter('plugin_action_links', 'Seven_eleven_plugin_action_links', 10, 2);

       












//////////////  START:    WP cron for the 7-connect syncronization   ////////////////////////////////




              // this sync is very needed for cancel woo order with EXPIRED transaction since 7-CONNECT do not send notify for EXPIRED transaction.
                  //    the sync serve also for other transaction status, as a second update method in complement to notify from 7-CONNECT

                    

             
             require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "sync" . DIRECTORY_SEPARATOR . "sync.php"; 

             

//////////////  END:    WP cron for the 7-connect syncronization   ////////////////////////////////














	/**
 	* Add 7eleven Gateway to WC
 	**/
    function woocommerce_7eleven_add_gateway( $methods ) {
        $methods[] = 'WC_Controller_7eleven';
        return $methods;
    }


    add_filter( 'woocommerce_payment_gateways', 'woocommerce_7eleven_add_gateway' );














}











?>
