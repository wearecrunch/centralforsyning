<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if ( ! class_exists( 'Smartsend_Logistics_GLS' ) ) {
	class Smartsend_Logistics_GLS extends WC_Shipping_Method {

		public $PrimaryClass;
		
		/**
	 	* Constructor.
	 	*/
		public function __construct() {
			$this->id                 	= 'smartsend_gls'; 
			$this->method_title       	= __( 'GLS','smart-send-logistics');
			
			$this->method_description 	= __( 'GLS','smart-send-logistics'); 				
			$this->table_rate_option    = 'GLS_table_rate';
			$this->PrimaryClass 		= new Smartsend_Logistics_PrimaryClass();
			
			$this->init();
		}
		
		/**
		 * init function.
		 */
		public function init() {
		
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->enabled					= $this->get_option( 'enabled' );
			$this->title 					= $this->get_option( 'title' );
			$this->cheap_expensive 			= $this->get_option( 'cheap_expensive' );
			$this->tax_status   			= $this->get_option( 'tax_status' );
			$this->notemail    				= $this->get_option( 'notemail' );
			$this->notesms    				= $this->get_option( 'notesms' );
			$this->return  					= $this->get_option( 'return' );
	
			// Actions
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_table_rates' ) );

			// Load Table rates
			$this->load_table_rates();
		}
		
		/**
		* is_available function.
	 	* @param array $package
	 	* @return bool
	 	*/
		public function is_available( $package ){
			$option = $this->enabled;
			if($option == "yes") {
				$is_available = TRUE;
			} else {
				$is_available = FALSE;
			}
			return apply_filters( 'smartsend_logistics_' . $this->id . '_is_available', $is_available, $package );
		}

		/**
	 	* Initialise Gateway Settings Form Fields.
	 	*/
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __( 'Enable/Disable','smart-send-logistics'),
					'type' 			=> 'checkbox',
					'label' 		=> __( 'Enable this shipping method','smart-send-logistics'),
					'default' 		=> 'no'
				),
				'title' => array(
					'title' 		=> __( 'Carrier title','smart-send-logistics'),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout','smart-send-logistics'),
					'default'		=> __( 'GLS','smart-send-logistics'),
					'desc_tip'		=> true,
				),
				'domestic_shipping_table' => array(
					'type'      	=> 'shipping_table'
				),
				'cheap_expensive' => array(
					'title'    		=> __( 'Cheapest or most expensive','smart-send-logistics'),
					'description'   => __( 'This controls cheapest or most expensive on the frontend','smart-send-logistics'),
					'default'  		=> 'cheapest',
					'type'    	 	=> 'select',
					'class'         => 'wc-enhanced-select',
					'options'  		=> array(
						'cheapest'      => __( 'Cheapest','smart-send-logistics'),
						'expensive' 	=> __( 'Most expensive','smart-send-logistics'),
					)
				),
				'tax_status' 		=> array(
					'title' 			=> __( 'Tax Status', 'woocommerce' ),
					'type'      		=> 'select',
					'class'         	=> 'wc-enhanced-select',
					'default'   		=> 'taxable',
					'options'   		=> array(
						'taxable' 			=> __( 'Taxable','woocommerce'),
						'none' 				=> _x( 'None', 'Tax status', 'woocommerce' )
					),
				),
				'notemail' 	=> array(
					'title'    			=> __( 'Email notification','smart-send-logistics'),
					'description'     	=> __( 'Send an email with info about delivery','smart-send-logistics'),
					'type' 				=> 'checkbox',
					'label' 			=> __( 'Enable','smart-send-logistics'),
					'default' 			=> 'yes'
				),
				'notesms' 	=> array(
					'title'    			=> __( 'SMS notification','smart-send-logistics'),
					'description'     	=> __( 'Send an SMS with info about delivery','smart-send-logistics'),
					'type' 				=> 'checkbox',
					'label' 			=> __( 'Enable','smart-send-logistics'),
					'default' 			=> 'yes'
				),
				'return' 	=> array(
					'title'    			=> __( 'Return shipping method','smart-send-logistics'),
					'description'     	=> __( 'Method used for return labels','smart-send-logistics'),
					'default'  			=> 'postdanmark',
					'type'     			=> 'select',
					'class'         	=> 'wc-enhanced-select',
					'options'  			=> array(
						'smartsendpostdanmark_private'	=> __( 'Post Danmark','smart-send-logistics'),
						'smartsendposten_private'      	=> __( 'Posten','smart-send-logistics'),
						'smartsendgls_private'      	=> __( 'GLS','smart-send-logistics'),
						'smartsendbring_private'      	=> __( 'Bring','smart-send-logistics'),
					)
				)
			);
		
		} // End init_form_fields()

		/**
		 * calculate_shipping function.
		 *
		 * @access public
		 * @param mixed $package
		 * @return void
		 */
		function calculate_shipping( $package = array() ) {
			$this->PrimaryClass->calculate_shipping($package = array(),$this);
		}

		/**
		 * validate_additional_costs_field function.
		 *
		 * @access public
		 * @param mixed   $key
		 * @return void
		 */
		function validate_shipping_table_field( $key ) {
			return false;
		}			
		
		function generate_shipping_table_html() {
			return $this->PrimaryClass->generate_shipping_table_html($this);
		}

		/**
		 * process_table_rates function.
		 *
		 * @access public
		 * @return void
		 */
		function process_table_rates() {
			$this->PrimaryClass->process_table_rates($this);
		}

		/**
		 * save_default_costs function.
		 *
		 * @access public
		 * @param mixed   $values
		 * @return void
		 */
		function save_default_costs( $fields ) {
			return $this->PrimaryClass->save_default_costs($fields);
		}

		/**
		 * get_table_rates function.
		 *
		 * @access public
		 * @return void
		 */
		function load_table_rates() {
			$this->table_rates = $this->get_table_rates();
		}
		
		/**
		 * get_table_rates function.
		 *
		 * @access public
		 * @return void
		 */
		function get_table_rates() {
			return array_filter( (array) get_option( $this->table_rate_option ) );
		}
		
		/**
		 * get_default_table_rates function.
		 *
		 * @access public
		 * @return void
		 */
		function get_default_table_rates() {
		
			return array(
				array(
					'class'			=> 'all',
					'methods'		=> 'Pickup',
					'minO' 			=> '0',
					'maxO' 			=> '500',
					'minwO' 		=> '0',
					'maxwO' 		=> '100000',
					'shippingO' 	=> 40.00,
					'country' 		=> 'DK',
					'method_name' 	=> __('Pickuppoint','smart-send-logistics'),
					),
				array(
					'class'			=> 'all',
					'methods'		=> 'Pickup',
					'minO' 			=> '500',
					'maxO' 			=> '100000',
					'minwO' 		=> '0',
					'maxwO' 		=> '100000',
					'shippingO' 	=> 0,
					'country' 		=> 'DK',
					'method_name' 	=> __('Pickuppoint','smart-send-logistics'),
					),
				array(
					'class'			=> 'all',
					'methods'		=> 'privatehome',
					'minO' 			=> '0',
					'maxO' 			=> '500',
					'minwO' 		=> '0',
					'maxwO' 		=> '100000',
					'shippingO' 	=> 50.00,
					'country' 		=> 'DK',
					'method_name' 	=> __('Delivered to door','smart-send-logistics'),
					),
				array(
					'class'			=> 'all',
					'methods'		=> 'privatehome',
					'minO' 			=> '500',
					'maxO' 			=> '100000',
					'minwO' 		=> '0',
					'maxwO' 		=> '100000',
					'shippingO' 	=> 10.00,
					'country' 		=> 'DK',
					'method_name' 	=> __('Delivered to door','smart-send-logistics'),
					)
				);	
		}
		
		/**
		 * save_default_table_rates function.
		 *
		 * @access public
		 * @return void
		 */
		function save_default_table_rates() {
			$table_rates = $this->get_default_table_rates();
			update_option( $this->table_rate_option, $table_rates );
		}			
							
		/**
		 * get_methods function.
		 *
		 * @access public
		 * @return void
		 */
		function get_methods(){
			$shipping_methods = array(
				'private'		=> 'Private',
                'privatehome' 	=> 'Private to home',
				'commercial'	=> 'Commercial'
				);
			if(function_exists('is_plugin_active') && !is_plugin_active( 'vc_pdk_allinone/vc_pdk_allinone.php')) {
				$shipping_methods = array_merge(array('pickup' => 'Pickup'),$shipping_methods);
			}
			
			return $shipping_methods;
		}

	}
}