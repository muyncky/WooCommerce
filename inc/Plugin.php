<?php
/**
 * Description of Plugin
 *
 * @author Sergio
 */
namespace Dropday\WooCommerce\Order;

if (!class_exists('\\Dropday\\WooCommerce\\Order\\Plugin')):
    
    class Plugin
    {
        protected $id;
    	protected $mainMenuId;
    	protected $adapterName;
    	protected $title;
    	protected $description;
    	protected $optionKey;
    	protected $settings;
    	protected $adapter;
    	protected $pluginPath;
    	protected $version;
        protected $image_format = 'full';
        protected $api_uri = 'https://dropday.io/api/v1/';
        
        public function __construct($pluginPath, $adapterName, $description = '', $version = null) {
            $this->id = str_replace('-pro', '', basename($pluginPath, '.php'));
            $this->pluginPath = $pluginPath;
            $this->adapterName = $adapterName;
            $this->description = '';
            $this->version = $version;
            $this->optionKey = '';
            $this->settings = array(
                'live' => '1',
                'accountId' => '',
                'apiKey' => '',
                'notifyForStatus' => array(),
                'completeOrderForStatuses' => array()
            );

            $this->mainMenuId = 'options-general.php';
            $this->title = sprintf(__('%s Order Sync', $this->id), 'Dropday');
        }
        
        private function test()
        {
            $this->handleOrder(26);
        }

        public function getApiUrl($type = '') {
            if ($type) {
                return trim($this->api_uri, '/') . '/' . $type;
            } else {
                return trim($this->api_uri, '/');
            }
        }
        
        public function register()
	    {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

            // do not register when WooCommerce is not enabled
            if (!is_plugin_active('woocommerce/woocommerce.php')) {
                return;
            }

            $proPluginName = preg_replace('/(\.php|\/)/i', '-pro\\1', plugin_basename($this->pluginPath));
            if (is_plugin_active($proPluginName)) {
                return;
            }

            if (is_admin()) {
                add_action('admin_menu', array($this, 'onAdminMenu'));
                add_action( 'admin_init', array($this, 'dropdaySettingsInit') );
            }

            add_filter('plugin_action_links_' . plugin_basename($this->pluginPath), array($this, 'onPluginActionLinks'), 1, 1);
            add_action('init', array($this, 'onInit'), 5);
            add_action('woocommerce_order_status_changed', array($this, 'onOrderStatusChanged'), 10, 3);
	   }
	
    	public function onAdminMenu()
    	{
            add_submenu_page($this->mainMenuId, $this->title, $this->title, 'manage_options', 'admin-' . $this->id, array($this, 'displaySettingForm'));
	   }

    	public function onPluginActionLinks($links)
    	{
            $link = sprintf('<a href="%s">%s</a>', admin_url('options-general.php?page=admin-' . $this->id), __('Settings', $this->id));
            array_unshift($links, $link);
            return $links;
    	}

        public function onInit()
    	{
            $this->loadSettings();
    	}
        
        protected function loadSettings()
    	{		
            $this->settings = get_option( $this->id );
    	}
        
        public function dropdaySettingsInit() {
            register_setting(
                $this->id,
                $this->id,
                array( $this, 'sanitize' )
            );

            add_settings_section(
                $this->id.'_section_developers',
                __( 'Api Settings', $this->id ), array($this, 'dropdaySectionDevelopers'),
                $this->id
            );

            add_settings_field(
                $this->id.'_live',
                __( 'Live mode', $this->id ),
                array($this, 'dropdayFieldLiveModeCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_live',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
            add_settings_field(
                $this->id.'_apiKey',
                __( 'API Key', $this->id ),
                array($this, 'dropdayFieldApiKeyCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_apiKey',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
            
            add_settings_field(
                $this->id.'_accountId',
                __( 'Account ID', $this->id ),
                array($this, 'dropdayFieldAccountIdCb'),
                $this->id,
                $this->id.'_section_developers',
                array(
                    'label_for'         => $this->id.'_accountId',
                    'class'             => 'row',
                    'wporg_custom_data' => 'custom',
                )
            );
        }
        
        public function sanitize( $input )
        {
            $new_input = array();
            if (isset($input['apiKey'])) {
                $new_input['apiKey'] = sanitize_text_field($input['apiKey']);
            }

            if (isset($input['accountId'])) {
                $new_input['accountId'] = sanitize_text_field($input['accountId']);
            }

            if (isset($input['live'])) {
                $new_input['live'] = absint($input['live']);
            }

            return $new_input;
        }
        
        public function dropdaySectionDevelopers( $args ) {
            ?>
                <p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Please Enter your api settings below:.', $this->id ); ?></p>
            <?php
        }
        
        public function dropdayFieldLiveModeCb()
        {
            printf(
                '<input type="checkbox" id="'.$this->id.'_live" name="'.$this->id.'[live]" value="1" %s />',
                ( isset( $this->settings['live'] ) && $this->settings['live'] ) ? 'checked' : ''
            );
        }
        
        public function dropdayFieldApiKeyCb()
        {
            printf(
                '<input type="text" class="large-text" id="'.$this->id.'_apiKey" name="'.$this->id.'[apiKey]" value="%s" />',
                isset( $this->settings['apiKey'] ) ? esc_attr( $this->settings['apiKey']) : ''
            );
        }
        
        public function dropdayFieldAccountIdCb()
        {
            printf(
                '<input type="text" class="small-text" id="'.$this->id.'_accountId" name="'.$this->id.'[accountId]" value="%s" />',
                isset( $this->settings['accountId'] ) ? esc_attr( $this->settings['accountId']) : ''
            );
        }
        
        public function displaySettingForm() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( isset( $_GET['settings-updated'] ) ) {
                // add settings saved message with the class of "updated"
                add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'wporg' ), 'updated' );
            }

            settings_errors( 'wporg_messages' );
            $this->settings = get_option( $this->id );
            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    settings_fields( $this->id );
                    do_settings_sections( $this->id );
                    // output save settings button
                    submit_button( 'Save Settings' );
                    ?>
                </form>
            </div>
            <?php
        }
        
        public function onOrderStatusChanged($order_id, $old_status, $new_status)
        {
            $this->handleOrder($order_id);
        }
        
        public function handleOrder($order_id)
        {
            if (!$order_id ) {
                return false;
            }

            $order = wc_get_order( $order_id );
            if ( $order && $order->is_paid()) {
                $order_data = array(
                    'external_id' => ''.$order_id,
                    'source' => get_bloginfo('name'),
                    'total' => $order->get_total(),
                    'shipping_cost' => $order->get_shipping_total(),
                    'email' => $order->get_billing_email(),
                    'shipping_address' => array(
                        'first_name' => $order->get_shipping_first_name(),
                        'last_name' => $order->get_shipping_last_name(),
                        'company_name' => $order->get_shipping_company(),
                        'address1' => $order->get_shipping_address_1(),
                        'address2' => ($order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_shipping_address_2()),
                        'postcode' => $order->get_shipping_postcode(),
                        'city' => $order->get_shipping_city(),
                        'country' => WC()->countries->countries[$order->get_shipping_country()],
                        'phone' => $order->get_billing_phone(),
                    ),
                    'products' => array()
                );

                if (!$this->settings['live']) {
                    $order_data['test'] = true;
                }

                $products = $order->get_items();
                foreach ($products as $item_id => $item) {
                    $product = $item->get_product();
                    $terms = get_the_terms( $product->get_id(), 'product_cat' );
                    $cat = 'Home';
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        $cat = $terms[0]->name;
                    }
                    $terms = get_the_terms( get_the_ID(), 'product_brand' );
                    $brand_name = '';
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        $brand_name = $terms[0]->name;
                    }
                    
                    $image_url = wp_get_attachment_image_url( $product->get_image_id(), $this->image_format );
                    $p = array(
                        'external_id' => ''.$item->get_id(),
                        'name' => ''.$item->get_name(),
                        'reference' => ''.$product->get_sku(),
                        'quantity' => (int) $item->get_quantity(),
                        'price' => (float) $product->get_price(),
                        'image_url' => $image_url ? $image_url : '',
                        'brand' => ''.$brand_name,
                        'category' => ''.$cat,
                        'supplier' => '',
                    );

                    $order_data['products'][] = $p;
                }

                $response = $this->postOrder($order_data);

                $context = array( 'source' => $this->id );
                $logger = wc_get_logger();

                if (isset($response->errors) && count($response->errors)) {
                    $logger->info( '[dropday] error order#'.$order_id.': ' . json_encode($response->errors), $context );
                    $order->add_order_note( json_encode($response->errors) );
                } else {
                    $result = json_decode($response['body']);

                    if ($response['response']['code'] == 200) {
                        $logger->info( '[dropday] Order created :#'.$order_id.': ', $context );
                    } elseif ($response['response']['code'] == 422) {
                        $logger->warning( '[dropday] error order#'.$order_id.': ' . json_encode($result->errors), $context );
                        if (isset($result->errors) && count($result->errors)) {
                            foreach ($result->errors as $key => $error) {
                                foreach ($error as $message) {
                                    $order->add_order_note( $message );
                                }
                            }
                        }
                    } else {
                        $logger->warning( '[dropday] error order#'.$order_id.': response code ' . $response['response']['code'], $context );
                        $order->add_order_note( 'Unknown error in Dropray API, response code ' . $response['response']['code'] );
                    }
                }
            }
        }

        public function postOrder($order_data)
        {
            $order_data = json_encode($order_data);
            $headers = array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Expect' => '100-Continue',
                'Api-Key' => ''.$this->settings['apiKey'],
                'Account-Id' => ''.$this->settings['accountId'],
            );

            $args = array(
                'body'        => $order_data,
                'headers'     => $headers,
            );

            return wp_remote_post( $this->getApiUrl('orders'), $args );
        }
    }

endif;
