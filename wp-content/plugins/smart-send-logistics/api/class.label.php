<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once( WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-order.php' );
require_once( WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-order.php' );

/**
 * Label class
 *
 * Create label API calls and send these to Smart Send
 *
 * @class 		Smartsend_Logistics_Order
 * @version		7.1.0
 * @author 		Smart Send
 */

class Smartsend_Logistics_Label {

	protected $request = array();
	protected $response;
	
	private $_test = false;
	
	protected $_notifications;
	
	
	public function __construct() {
    	$this->_notifications = array(
		//Notifications
		2101	=> __('Response','smart-send-logistics'),
		2102	=> __('Unknown status','smart-send-logistics'),
		2103	=> __('Combined PDF labels','smart-send-logistics'),
		2104	=> __('Combined label links','smart-send-logistics'),
		2105	=> __('PDF label','smart-send-logistics'),
		2106	=> __('Label link','smart-send-logistics'),
		//Errors
		2201	=> __('Trying to send empty order array','smart-send-logistics'),
		2202	=> __('Unknown API method','smart-send-logistics'),
		2203	=> __('An error occurred while sending order','smart-send-logistics'),
		2204	=> __('Failed to insert tracecode','smart-send-logistics')
		);
    }

 	/*
 	 * Function: is there a requerst?
 	 * both for single and mass generation
 	 */
 	public function isRequest() {
 		if(empty($this->request)) {
 			return false;
 		} else {
 			return true;
 		}
 	}
 
 
 	/*
 	 * Function: Get JSON request
 	 * both for single and mass generation
 	 */
 	protected function getJsonRequest() {
 		if(empty($this->request)) {
 			throw new Exception( $this->_notifications[2201] );
 		} else {
 			return json_encode($this->request);
 		}
 	}
 
 	/*
 	 * Function: Create an order request
 	 * both for single and mass generation
 	 */
 	public function createOrder($order,$return=false) {
		
		$smartsendorder = new Smartsend_Logistics_Order_Woocommerce();
		$smartsendorder->setOrderObject($order);
		$smartsendorder->setReturn($return);

		$smartsendorder->setInfo();
		$smartsendorder->setReceiver();
		$smartsendorder->setSender();
		$smartsendorder->setAgent();
		$smartsendorder->setService();
		$smartsendorder->setParcels();

		//All done. Add to request.
		$this->request[] = $smartsendorder->getFinalOrder();
 	}
 	
 	/*
 	 * Function: POST final cURL request
 	 * both for single and mass generation
 	 */
 	public function postRequest($single=false) {
 	
 		$ch = curl_init();               //intitiate curl

        /* Script URL */
        if($single == true) {
        	$url = 'https://smartsend-prod.apigee.net/v7/booking/order';
        } elseif($single == false) {
        	$url = 'https://smartsend-prod.apigee.net/v7/booking/orders';
        } else {
        	throw new Exception( $this->_notifications[2202] . ': ' . $single);
        }
        
        if(get_option( 'smartsend_logistics_username', '' ) == '' && is_plugin_active( 'vc_pdk_allinone/vc_pdk_allinone.php')) {
        	$settings = get_option('woocommerce_vc_pdk_allinone_settings');
			$username = $settings['license_email'];
			$licensekey = $settings['license_key'];
        } else {
        	$username = get_option( 'smartsend_logistics_username', '' );
        	$licensekey = get_option( 'smartsend_logistics_licencekey', '' );
        }
        
        $rel_dir = str_replace("/api","",__DIR__);
		$plugin_info = get_plugin_data($rel_dir . '/woocommerce-smartsend-logistics.php', $markup = true, $translate = true );

        curl_setopt($ch, CURLOPT_URL, $url);       //curl url
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->getJsonRequest());
        //curl_setopt($ch, CURLOPT_HTTPGET, true);   //curl request method
        //curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        	'apikey:N5egWgckXdb4NhV3bTzCAKB26ou73nJm',
        	'smartsendmail:'.$username,
        	'smartsendlicence:'.$licensekey,
        	'cmssystem:WooCommerce',
        	'cmsversion:'.$this->wpbo_get_woo_version_number(),
        	'appversion:'.$plugin_info["Version"],
        	'test:'.($this->_test ? 'true' : 'false'),
        	'Content-Type:application/json; charset=UTF-8'
        	));    //curl request header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $this->response = new StdClass();                       //creating new class
        $this->response->body = curl_exec($ch);             //executing the curl
        $this->response->code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->response->meta = curl_getinfo($ch);
        $curl_error = ($this->response->code > 0 ? null : curl_error($ch) . ' (' . curl_errno($ch) . ')');      //getting error from curl if any

        curl_close($ch);                          //closing the curl

        if ($curl_error) {
            throw new Exception( $this->_notifications[2203] . ': ' . $curl_error);
        }
        
        if(!($this->response->code >= '200') || !($this->response->code <= '210')) {
        	throw new Exception( $this->_notifications[2101] . ': ('.$this->response->code.') '.$this->response->body);
        }
 	
 	}
 	
 	public function wpbo_get_woo_version_number() {
			// If get_plugins() isn't available, require it
		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	
			// Create the plugins folder and file variables
		$plugin_folder = get_plugins( '/' . 'woocommerce' );
		$plugin_file = 'woocommerce.php';
	
		// If the plugin version number is set, return it 
		if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
			return $plugin_folder[$plugin_file]['Version'];

		} else {
		// Otherwise return null
			return NULL;
		}
	}
	
	/*
	 * Add Track and Trace number to parcels
	 * @string shipment_reference: unique if of shipment
	 * @string tracecode
	 */ 
 	protected function addTraceToShipment($shipment_reference,$tracecode) {
 	// NOT DONE! STILL MAGENTO!
 	
 	
	/*	$shipment_collection = Mage::getResourceModel('sales/order_shipment_collection');
		$shipment_collection->addAttributeToFilter('order_id', $order_id);
		
		foreach($shipment_collection as $sc) {
			$shipment = Mage::getModel('sales/order_shipment');
			$shipment->load($sc->getId());
			if($shipment->getId() != '') { 
				$track = Mage::getModel('sales/order_shipment_track')
						 ->setShipment($shipment)
						 ->setData('title', 'ShippingMethodName')
						 ->setData('number', $track_no)
						 ->setData('carrier_code', 'ShippingCarrierCode')
						 ->setData('order_id', $shipment->getData('order_id'))
						 ->save();
			}
		} */
		
		$shipment = Mage::getModel('sales/order_shipment');
		$shipment->load($shipment_reference);
		if($shipment->getId() != '') {
			$order = Mage::getModel('sales/order')->load($shipment->getData('order_id'));
			$smartsendorder = Mage::getModel('logistics/order');
		
			$track = Mage::getModel('sales/order_shipment_track')
				->setShipment($shipment)
				->setData('title', $smartsendorder->getMethod($order))
				->setData('number', $tracecode)
				->setData('carrier_code', $smartsendorder->getSmartSendCarrier($order))
				->setData('order_id', $shipment->getData('order_id'))
				->save();
		} else {
			throw new Exception( $this->_notifications[2204] );
		}
		
 	}
 	
 	/*
 	 * Function: go through parcels and add trace code
 	 */
 	protected function verifyParcels($json) {
 		if(isset($json->parcels) && is_array($json->parcels)) {
 			$trace_codes = array();
			foreach($json->parcels as $parcel) {
				if(isset($parcel->reference) && $parcel->reference != '' && isset($parcel->tracecode) && $parcel->tracecode != '') {
					$trace_codes[] = $parcel->tracecode;
				}	
			}
			
			if(!empty($trace_codes)) {
				$order = new WC_Order( $json->orderno );
				
				$smartsendorder = new Smartsend_Logistics_Order_Woocommerce();
				$smartsendorder->setOrderObject($order);
				
				$order_history_comment = __('Label generated with Smart Send Logistics');
				
				foreach($trace_codes as $trace_code) {
					//Add a note with a Track&Trace link
					if($smartsendorder->getShippingCarrier() == 'postdanmark') {
						$link = '<a href="http://www.postdanmark.dk/tracktrace/TrackTrace.do?i_stregkode='.$trace_code.'" target="_blank">'.$trace_code.'</a>';
					} elseif($smartsendorder->getShippingCarrier() == 'posten') {
						$link = '<a href="http://www.postnord.se/en/tools/track/Pages/track-and-trace.aspx?search='.$trace_code.'" target="_blank">'.$trace_code.'</a>';
					} elseif($smartsendorder->getShippingCarrier() == 'gls') {
						$link = '<a href="http://www.gls-group.eu/276-I-PORTAL-WEB/content/GLS/DK01/DA/5004.htm?txtAction=71000&txtRefNo='.$trace_code.'" target="_blank">'.$trace_code.'</a>';
					} elseif($smartsendorder->getShippingCarrier() == 'bring') {
						$link = '<a href="http://sporing.bring.no/sporing.html?q='.$trace_code.'" target="_blank">'.$trace_code.'</a>';
					} else {
						$link = null;
					}
					
					$order_history_comment .= '<br>' . __('Tracecode').': '.($link ? $link : $trace_code);
					
					//Add trace link to WooTheme extension 'Shipment Tracking'
					update_post_meta( $order->id, '_tracking_provider', $smartsendorder->getShippingCarrier($format=0) );
					//update_post_meta( $order->id, '_custom_tracking_provider', $smartsendorder->getShippingCarrier($format=0) );
					update_post_meta( $order->id, '_tracking_number', $trace_code );
					//update_post_meta( $order->id, '_custom_tracking_link', null );
					update_post_meta( $order->id, '_date_shipped', time() );
					
				}
				
				//Add pdf link to order comment if there is a pdf link
				if(isset($json->pdflink)) {
					$order_history_comment .= '<br><a href="' . $json->pdflink .'" target="_blank">' . __('Link to PDF label') .'</a>';
				}
				
				//Add order comment
				$order->add_order_note($order_history_comment);
			}
		}
	}	
	
 	/*
 	 * Function: Handle cURL response
 	 * both for single and mass generation
 	 */
 	public function handleRequest() {
 		if(strpos($this->response->meta['content_type'],'json') !== false) {
 			$_errors = array();
 			$_notification = array();
 			$_succeses = array();
 		
 			$json = json_decode($this->response->body);
 		/*	$this->_getSession()->addNotice($this->getJsonRequest());
 			$this->_getSession()->addNotice($this->response->body); */
 			
 			//Show a notice if info is given
 			if(isset($json->info)) {
 				if(is_array($json->info)) {
 					foreach($json->info as $info) {
 						$_notification[] = $info;
 					}
 				} else {
 					$_notification[] = $json->info;
 				}
 			}
 			
 			if(isset($json->combine_pdf) && get_option('smartsend_logistics_combinepdf','yes') == 'yes') {
 				$_succeses[] = '<a href="'. $json->combine_pdf .'" target="_blank">' . $this->_notifications[2103] .'</a>';
 			}
 			
 			if(isset($json->combine_link) && get_option('smartsend_logistics_combinepdf','yes') == 'yes') {
 				$_succeses[] = '<a href="'. $json->combine_link .'" target="_blank">' . $this->_notifications[2104] .'</a>';
 			}
 			
			if(isset($json->orders) && is_array($json->orders)) {
				// An array of orders was returned
				foreach($json->orders as $json_order) {
					if(isset($json_order->pdflink) && !(isset($json->combine_pdf) && get_option('smartsend_logistics_combinepdf','yes') == "yes")) {
						$_succeses[] = 'Order #'.$json_order->reference.': <a href="'. $json_order->pdflink .'" target="_blank">' . $this->_notifications[2105] .'</a>';
						// Go through parcels and add trace to shipments
						$this->verifyParcels($json_order);	
					} elseif(isset($json_order->link) && !(isset($json->combine_link) && get_option('smartsend_logistics_combinepdf','yes') == "yes")) {
						$_succeses[] = 'Order #'.$json_order->reference.': <a href="'. $json_order->link .'" target="_blank">' . $this->_notifications[2106] .'</a>';
						// Go through parcels and add trace to shipments
						$this->verifyParcels($json_order);	
					} elseif( (isset($json_order->pdflink) || isset($json_order->link) ) && get_option('smartsend_logistics_combinepdf','yes') == "yes") {
						$_succeses[] = 'Order #'.$json_order->reference.': '. $json_order->message;
						$this->verifyParcels($json_order);
					} else {
						if(isset($json_order->status) && $json_order->status != '') {
							$_errors[] = 'Order #'.$json_order->reference.': '. $json_order->message; 
						} else {
							$_errors[] = $this->_notifications[2102] . ': '. $json_order->message;
						}
					}
				}
			
			} else {
				// An array of orders was not returned. Check if just a single order was returned
			
				if(isset($json->pdflink) && !(isset($json->combine_pdf) && get_option('smartsend_logistics_combinepdf','yes') == 1)) {
					$_succeses[] = 'Order #'.$json->reference.': <a href="'. $json->pdflink .'" target="_blank">' . $this->_notifications[2105] .'</a>';
					// Go through parcels and add trace to shipments
					$this->verifyParcels($json);	
				} elseif(isset($json->link) && !(isset($json->combine_link) && get_option('smartsend_logistics_combinepdf','yes') == 1)) {
					$_succeses[] = 'Order #'.$json->reference.': <a href="'. $json->link .'" target="_blank">' . $this->_notifications[2106] .'</a>';
					// Go through parcels and add trace to shipments
					$this->verifyParcels($json);	
				} else {
					if(isset($json->status) && $json->status != '') {
						$_errors[] = 'Order #'.$json->reference.': '. $json->message;
					} else {
						$_errors[] = $this->_notifications[2102] . ': '. $json->message;
					}
				}
			}
 			
 			// Errors
			if(isset($_SESSION['smartsend_errors']) && is_array($_SESSION['smartsend_errors'])) {
 				$_SESSION['smartsend_errors'] 		= array_merge($_SESSION['smartsend_errors'],$_errors);
 			} else {
 				$_SESSION['smartsend_errors']		= $_errors;
 			}
 			
 			// Notifications
 			if(isset($_SESSION['smartsend_notification']) && is_array($_SESSION['smartsend_notification'])) {
 				$_SESSION['smartsend_notification'] 		= array_merge($_SESSION['smartsend_notification'],$_notification);
 			} else {
 				$_SESSION['smartsend_notification']		= $_notification;
 			}
 			
 			// Successes
 			if(isset($_SESSION['smartsend_succeses']) && is_array($_SESSION['smartsend_succeses'])) {
 				$_SESSION['smartsend_succeses'] 		= array_merge($_SESSION['smartsend_succeses'],$_succeses);
 			} else {
 				$_SESSION['smartsend_succeses']		= $_succeses;
 			}
 			
 			global $smartsend_errors;
 			$smartsend_errors = $GLOBALS['smartsend_errors'];
 		} else {
 			throw new Exception('Unknown content type: '.$this->response->meta['content_type']);
 		}
 		
 	}
 	
}