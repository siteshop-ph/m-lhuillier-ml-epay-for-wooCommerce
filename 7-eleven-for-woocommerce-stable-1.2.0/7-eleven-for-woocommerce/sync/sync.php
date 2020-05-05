<?php







// this sync is very needed for cancel woo order with EXPIRED transaction since 7-CONNECT do not send notify for EXPIRED transaction.
//    the sync serve also for other transaction status, as a second update method in complement to notify







////////////// create wp cron to run syncronization  /////////////
if ( ! wp_next_scheduled( 'woocommerce_7eleven_synchronization' ) ) {
  wp_schedule_event( time(), 'daily', 'woocommerce_7eleven_synchronization' );  // alternative: twicedaily
}

     // cron tasks are stored in wp_options table option_name=cron

add_action( 'woocommerce_7eleven_synchronization', 'synchronization_7eleven' );
///////////////////////////////////////////////////////////////












   function synchronization_7eleven() {       
        // for test ECHO:  Disable  1/ here   and  2/ at closing of this function  3/enable debug mode in wp config file





$new_instance = new WC_Controller_7eleven();

// call this function within the class
$new_instance->__construct();  





      
          echo PHP_EOL . 'START CRON' . PHP_EOL . PHP_EOL;

        

                     if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug']) {

                             $new_instance->log->add( '7eleven', 'START CRON: Synchronization' );  
                  
                     }















    // select all woo order having status "on-hold'" since this is the very first status we have for order when vrush tracking id is created (pickup order created)


//global $wp;                       //seem not needed
//global $woocommerce, $post;       //seem not needed



global $wpdb;
$order_to_checks = $wpdb->get_results( "SELECT ID FROM {$wpdb->base_prefix}posts WHERE post_status = 'wc-on-hold'" );
  // can work as well
   //$order_to_checks = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}posts WHERE post_status = 'wc-on-hold'" ); 







//for test
//var_dump($order_to_checks) . "<br><br>";












          if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {


                   if(get_option('woocommerce_7eleven_settings')['test_mode'] == 'yes'){

			   $new_instance->log->add( '7eleven', 'CRON: 7-CONNECT - TEST ACCOUNT USED' );
	           
                     }else{

                           $new_instance->log->add( '7eleven', 'CRON: 7-CONNECT - PRODUCTION ACCOUNT USED' );

                   }


          }


















if ( $order_to_checks ) {



     

                 	foreach ( $order_to_checks as $post ) {
		

                                            // Proceed in loop with all woo order found in $order_to_checks database querry
                                                  
                                                           //setup_postdata( $post ); // seem not needed


                                                                        $woo_order_id_to_check = $post->ID ;
                                                                        

                                                                                             // get the "meta_value" from table "wp_postmeta" when "meta_key"  = "_payment_method", 
                                                                          $payment_method = get_post_meta( $woo_order_id_to_check, '_payment_method', true );



                                                                                                     if ( '7eleven' == $payment_method ) {




                                                                                                             //for test
                                                                                                             //echo "woo order ID: " . $post->ID ."<br><br>";





      
























           // Check if Active: WooCommerce Plugin "Custom Order Numbers" by unaid Bhura / http://gremlin.io/ 
           if( class_exists( 'woocommerce_custom_order_numbers' ) ) {

          
                 // for test
                 //echo "case custom_order_numbers plugin used";


                 // Retrieve real order from postmeta database table"
                 global $wpdb;

                 $wpdb->postmeta = $wpdb->base_prefix . 'postmeta';

		 $retrieved_order_id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_custom_order_number' AND meta_value = '$result->merchantRef'" );
		
		  
                 $merchantTxnId_to_use = $retrieved_order_id;

                 //for test
                // echo 'case custom order number plugin used';
                //echo $merchantTxnId_to_use;

                  

           }else{

                  // just use regular woocommerce order (real woocommerce order = txnid used with 7-CONNECT)
                  $merchantTxnId_to_use = $woo_order_id_to_check;


                  // for test
                  // echo 'case no custom order number plugin used';
                  //echo $merchantTxnId_to_use;
 
           }




















   










//////////////////////////////  START:  REQUEST  STATUS  TO  7-CONNECT  API  ////////////////////////////////////////////////////////////






$merchantID = get_option('woocommerce_7eleven_settings')['merchant_id'];

$transactionKey = html_entity_decode(get_option('woocommerce_7eleven_settings')['api_password']);


$merchantRef = $merchantTxnId_to_use;




             ## purge old values if there are
             $token_str = "";
             $token = "";


             
     
            ## create the token for 7eleven
            $token_str = $merchantRef . '{' . $transactionKey . '}' ;  


            ## to create 40 Char sha1
            $token = sha1($token_str, $raw_output = false);










        if(get_option('woocommerce_7eleven_settings')['test_mode'] == 'yes'){


		           $api_inquire_url = 'https://testpay.cliqq.net/inquire';   // test      N.B: not same URL as the one for create transaction

	   }else{

		           $api_inquire_url = 'https://pay.7-eleven.com.ph/inquire';  // live      N.B: not same URL as the one for create transaction
	}














$curl_data = array(                     
     'merchantID' => $merchantID,
     'merchantRef' => $merchantRef,
     'token' => $token,
);











    $options = array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_USERAGENT      => "spider",
      CURLOPT_AUTOREFERER    => true,
      CURLOPT_CONNECTTIMEOUT => 30,
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_MAXREDIRS      => 10,
      CURLOPT_POST           => 1,
      CURLOPT_POSTFIELDS     => $curl_data,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_VERBOSE        => 1 ,
    );





    $ch = curl_init($api_inquire_url);
    curl_setopt_array($ch, $options);
    $content = curl_exec($ch);
    curl_close($ch);



$result = json_decode($content);
    







// for test
//echo "<br><br>";
//var_dump($result);
//echo "<br><br>7-CONNECT status:  " . $result->status . "<br><br>";                









// answer example when all is good (json):

/*

object(stdClass)#1 (6) {
  ["merchantID"]=>
  string(14) "designplustest"
  ["merchantRef"]=>
  string(7) "0000002"
  ["payID"]=>
  string(14) "9916-2850-0027"
  ["status"]=>
  string(7) "EXPIRED"
  ["token"]=>
  string(40) "d02f046dcc0776f68d05dcf160f4acd95e42b7ae"
  ["message"]=>
  string(0) ""
}


*/





// answer example when wrong (json):

/* object(stdClass)#1 (6) {
  ["merchantID"]=>
  string(14) "designplustest"
  ["merchantRef"]=>
  string(7) "0000002"
  ["payID"]=>
  string(0) ""
  ["status"]=>
  string(5) "ERROR"
  ["token"]=>
  string(0) ""
  ["message"]=>
  string(14) "Invalid+Token."
*/


//////////////////////////////  END:  REQUEST  STATUS  TO  7-CONNECT  API  ////////////////////////////////////////////////////////////




















 // START:     process for each transaction




$order = new WC_Order( $woo_order_id_to_check);









             switch ( $result->status ) {







                   #################### Case transaction is "EXPIRED"  ####################
                   case 'EXPIRED':      
          

                              // this CRON Sync is very needed to cancel order with expired transaction since 7-CONNECT do not send notify for expired transaction
                                      
                                  
				   if($order->status == 'cancelled'){
                                  			         
                                       
                                        //No update needed  


                                      
				    }else{
                                       

                                         // update order status (an admin note will be also created)
                                         $order->update_status('cancelled'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> 7-ELEVEN: transaction EXPIRED<br/> -> PayID: '.$result->payID.'<br/> -> Order status updated to CANCELLED', 1); 

                                         // no reduce order stock needed


	                                 //empty cart
                                         // not needed

                                         // Do the redirection
                                         //not needed


                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug']) {

                                                           $new_instance->log->add( '7eleven', 'CRON: Find one new Transaction status - EXPIRED Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );

                                                           $new_instance->log->add( '7eleven', 'CRON: Order updated to CANCELLED - EXPIRED Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );

	                                            }


                                         // no exit needed as it's will stop other order process "for each")


				    }  


                                    break;








                                            


                      #################### CASE:  transaction is "PAID"  ####################
                          case 'PAID':
             
                        

                                   if($order->status == 'processing' OR $order->status == 'completed'){
                                   				         
                                         //No update needed

                                      
				    }else{


                                         // update order status (an admin note will be also created)
                                         $order->update_status('processing'); 


                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> 7-ELEVEN Payment SUCCESSFUL<br/> -> PayID: '.$response_payID.'<br/> -> Order status updated to PROCESSING<br/> -> We will be shipping your order to you soon', 1); 

                                         // reduce stock
				         $order->reduce_order_stock(); // if physical product vs downloadable product

                                         //empty cart
                                         //not needed

                                         // no redirection needed


                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug']) {

                                                        $new_instance->log->add( '7eleven', 'CRON: Find one new Transaction status - SUCCESSFUL Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );

                                                        $new_instance->log->add( '7eleven', 'CRON: Order updated to PROCESSING - SUCCESSFUL Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );


	                                            }

                                         
                                         // no exit needed as it's will stop other order process "for each")

                                 }



                           break;










                   #################### Case transaction is "POSTED"  ####################
                   case 'POSTED':              


                                    // match to case:  A successfully paid transaction. 
                                         //  This status indicates a successful posting of payment to the merchant site. 


                                   // so just in case, we do same as 'PAID':     
                                  

                        

                                   if($order->status == 'processing' OR $order->status == 'completed'){
                                   				         
                                         //No update needed

                                      
				    }else{


                                         // update order status (an admin note will be also created)
                                         $order->update_status('processing'); 

                                         // Add Admin and Customer note
                                         $order->add_order_note(' -> 7-ELEVEN Payment SUCCESSFUL<br/> -> PayID: '.$response_payID.'<br/> -> Order status updated to PROCESSING<br/> -> We will be shipping your order to you soon', 1); 

                                         // reduce stock
				         $order->reduce_order_stock(); // if physical product vs downloadable product

                                         //empty cart
                                         //not needed

                                         // no redirection needed


                                                    if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug']) {

                                                        $new_instance->log->add( '7eleven', 'CRON: Find one new Transaction status - SUCCESSFUL Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );

                                                        $new_instance->log->add( '7eleven', 'CRON: Order updated to PROCESSING - SUCCESSFUL Transaction: '.$result->payID.' - For Order: ' . $order->get_order_number() );


	                                            }

                                         
                                         // no exit needed as it's will stop other order process "for each")

                                 }



                           break;












                   #################### Case transaction is "UNPAID" waiting cash deposit at 7-ELEVEN ####################
                   case 'UNPAID':                    
                                  

				       // nothing to do  


                                           // no exit needed as it's will stop other order process "for each")

                                         
				


                                    break;







      


 
                   #################### CASE transaction is 'ERROR'  ####################
                   case 'ERROR':                    
                                  




                              if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug'] ) {
                                                                 
                                                                                                                                      
                                               $new_instance->log->add( '7eleven', 'CRON - API - *** ERROR *** ' . $result->message . ' - When inquire 7-CONNECT for Order: ' . $order->get_order_number() );
                                       

      
                              }                                           
                                        



                                         // no exit needed as it's will stop other order process "for each")
                                        

				     


                                    break;














                   #################### Case  transaction is  NO STATUS CODE GIVEN IN BACK ####################
                   default :                                                    
 
                                    // nothing to do redirection needed


                                    break;












             }




//////////////////////////////  END:  REQUEST  STATUS  TO  7-CONNECT  API  /////////////////////////////////////////////////////////////





  


                        }	    // END:      if ( '7eleven' == $payment_method ) {





	}	    // END:        foreach ( $order_to_checks as $post ) {






     } else {


	

                       // NOTHING TO DO


                                //for test


                                      //echo "nothing to do<br><br>";




     }    // END:        if ( $order_to_checks ) {










               echo "<br><br>CRON: Synchronization DONE<br><br>";


               echo "<br><br>END CRON <br><br>";












 // store last time ran for this cron
 $options = get_option('woocommerce_7eleven_settings');
 // update it
 date_default_timezone_set('Asia/Manila');
 $options['last_ran_cron_synchronization'] = date('Y-m-d\TH:i:s');
 // store updated data     
 update_option('woocommerce_7eleven_settings',$options);









                                     if ( 'yes' == get_option('woocommerce_7eleven_settings')['debug']) {
                            
                                                 $new_instance->log->add( '7eleven', 'CRON: Synchronization DONE');

                                                 $new_instance->log->add( '7eleven', 'END CRON: Synchronization');
 
                                      }
















      } // END   function synchronization_7eleven() {          // Disable here for test ECHO










?>
