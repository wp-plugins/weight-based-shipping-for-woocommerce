<?php
/**
 * Plugin Name: Weight based shipping for Woocommerce
 * Description: Simple weight based shipping method for Woocommerce.
 * Version: 2.0.1
 * Author: dangoodman
 */

add_action( 'plugins_loaded', 'init_woowbs', 0 );

function init_woowbs()
{
	if (!class_exists('WC_Shipping_Method'))
    {
        return;
    }

    if (!function_exists('WC'))
    {
        function WC()
        {
            return $GLOBALS['woocommerce'];
        }
    }

	class WC_Weight_Based_Shipping extends WC_Shipping_Method
    {
        public $name;
        public $profile_id;
        public $max_weight;
        public $rate;


		public function __construct($profile_id = null)
        {
            $process_admin_options = !isset($profile_id);

            $this->id = WBS_Profile_Manager::instance()->find_suitable_id($profile_id);
            $this->profile_id = $profile_id;

			$this->method_title = __('Weight Based', 'woocommerce');

			$this->admin_page_heading     = __('Weight based shipping', 'woocommerce');
			$this->admin_page_description = __('Define shipping by weight', 'woocommerce');

            if ($process_admin_options)
            {
			    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

			$this->init();
		}

		function init()
        {
			$this->init_form_fields();
			$this->init_settings();

			$this->enabled          = $this->get_option('enabled');
            $this->name             = $this->get_option('name');
			$this->title            = $this->get_option('title');
            $this->availability     = $this->get_option('availability');
            $this->countries 	    = $this->get_option('countries');
			$this->type             = 'order';
			$this->tax_status       = $this->get_option('tax_status');
			$this->fee              = $this->get_option('fee');
            $this->rate             = $this->get_option('rate');
            $this->max_weight       = $this->get_option('max_weight');

            if (empty($this->countries)) {
                $this->availability = $this->settings['availability'] = 'all';
            }
		}

		function init_form_fields()
        {
            $woocommerce = WC();
            $shipping_countries = method_exists($woocommerce->countries, 'get_shipping_countries')
                                    ? $woocommerce->countries->get_shipping_countries()
                                    : $woocommerce->countries->countries;

            $weight_unit = get_option( 'woocommerce_weight_unit' );

			$this->form_fields = array
            (
				'enabled'    => array
                (
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this shipping method', 'woocommerce' ),
					'default' => 'no',
				),
                'name'    => array
                (
                    'title'  => __('Profile Name', 'woocommerce'),
                    'type'   => 'text',
                ),
				'title'      => array(
					'title'       => __( 'Method Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Weight Based Shipping', 'woocommerce' ),
				),
                'availability' => array(
                    'title' 		=> __( 'Availability', 'woocommerce' ),
                    'type' 			=> 'select',
                    'default' 		=> 'all',
                    'class'			=> 'availability',
                    'options'		=> array(
                        'all' 		=> __( 'All allowed countries', 'woocommerce' ),
                        'specific' 	=> __( 'Specific Countries', 'woocommerce' ),
                    ),
                ),
                'countries' => array(
                    'title' 		=> __( 'Specific Countries', 'woocommerce' ),
                    'type' 			=> 'multiselect',
                    'class'			=> 'chosen_select',
                    'css'			=> 'width: 450px;',
                    'default' 		=> '',
                    'options'		=> $shipping_countries,
                    'custom_attributes' => array(
                        'data-placeholder' => __( 'Select some countries', 'woocommerce' )
                    )
                ),
				'tax_status' => array(
					'title'       => __( 'Tax Status', 'woocommerce' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'taxable',
					'options'     => array(
						'taxable' => __( 'Taxable', 'woocommerce' ),
						'none'    => __( 'None', 'woocommerce' ),
					),
				),
				'fee'        => array(
					'title'       => __( 'Handling Fee', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Fee excluding tax, e.g. 3.50. Leave blank to disable.', 'woocommerce' ),
					'default'     => '',
				),
				'rate'       => array(
					'title'       => __( 'Shipping Rate', 'woocommerce' ),
					'type'        => 'price',
					'description' => __( "Set your shipping price for 1 {$weight_unit}. Example: <code>1.95</code>.", 'woocommerce' ),
					'default'     => '',
				),
                'max_weight' => array
                (
                    'title'       => __( 'Max Weight', 'woocommerce' ),
                    'type'        => 'decimal',
                    'description' => __(
                        "The shipping option will not be shown during the checkout process
                        if order weight exceeds this limit. Example: <code>2.5</code>({$weight_unit}).
                        Leave blank to disable."
                    ),
                ),
			);
		}

		public function calculate_shipping($package = array())
        {
			$weight = WC()->cart->cart_contents_weight;

            $max_weight = (float)@$this->settings['max_weight'];
            if ($max_weight > 0 && $weight > $max_weight) {
                return;
            }

			$rate   = (float)@$this->settings['rate'];
			$price  = $weight * $rate;

			if ($price <= 0) {
				return;
			}

			if ($this->fee > 0 && $package['destination']['country']) {
				$price = $price + $this->fee;
			}

			$this->add_rate(array
			(
				'id'       => $this->id,
				'label'    => $this->title,
				'cost'     => $price,
				'taxes'    => '',
				'calc_tax' => 'per_order'
			));
		}

		public function admin_options()
        {
            $manager = WBS_Profile_Manager::instance();

            $profiles = $manager->profiles();

            $profile = $manager->profile();
            if (!isset($profile))
            {
                $profile = new self();
                $profiles[] = $profile;
            }

            $multiple_profiles_available = count($profiles) > 1;

            $create_profile_link = '<a class="add-new-h2" href="'.esc_html($profile->admin_options_page_url($manager->new_profile_id())).'">Create additional configuration</a>';

            $GLOBALS['hide_save_button'] = true;

			?>
				<h3><?php _e('Weight based shipping', 'woocommerce'); ?></h3>
				<p><?php _e('Lets you calculate shipping based on total weight of the cart. You can have multiple configurations active.', 'woocommerce'); ?></p>

            <?php if (!$multiple_profiles_available): ?>
                <?=$create_profile_link?><br><br><br>
            <?php endif; ?>

				<table class="form-table">
            <?php if ($multiple_profiles_available): ?>
                    <tr class="wbs-title">
                        <th colspan="2">
                            <h4>Available configurations <?=$create_profile_link?></h4>
                        </th>
                    </tr>

                    <tr class="wbs-profiles">
                        <td colspan="2">
                            <?php $profile->list_profiles($profiles); ?>
                        </td>
                    </tr>

                    <tr class="wbs-title">
                        <th colspan="2">
                            <h4>
                                Settings for <?=esc_html($profile->name)?> configuration
                            </h4>
                        </th>
                    </tr>
            <?php endif; ?>
					<?php $profile->generate_settings_html(); ?>
				</table>

                <input name="save" class="button-primary" type="submit"
                       value="<?php _e( 'Save changes', 'woocommerce' ); ?>" />

                &nbsp;&nbsp;&nbsp;&nbsp;
                <input class="button" type="submit" name="delete" value="<?=esc_html(__('Delete'))?>"
                       onclick="return confirm('<?=__('Are you sure?')?>');" />
			<?php
		}

        public function process_admin_options()
        {
            $result = parent::process_admin_options();

            if (!empty($_POST['delete']))
            {
                delete_option($this->plugin_id.$this->id.'_settings');
                echo '<script>location.href = '.json_encode(self::admin_options_page_url(null)).';</script>';
            }
            else
            {
                $this->init();

                $clone = WBS_Profile_Manager::instance()->profile($this->profile_id);
                if (isset($clone))
                {
                    $clone->init();
                }
            }

            return $result;
        }

        public function display_errors()
        {
            foreach ($this->errors as $error)
            {
                WC_Admin_Settings::add_error($error);
            }
        }

        private static function admin_options_page_url($profile_id = null)
        {
            $query = build_query(array_filter(array
            (
                "page"          => "wc-settings",
                "tab"           => "shipping",
                "section"       => __CLASS__,
                'wbs_profile'   => $profile_id,
            )));

            $url = admin_url("admin.php?{$query}");

            return $url;
        }

        /**
         * @param self[] $profiles
         */
        private function list_profiles($profiles)
        {
            ?>
                <table id="woowbs_shipping_methods" class="wc_shipping widefat">
                    <thead>
                        <tr>
                            <th class="name"><?php _e('Name', 'woocommerce'); ?></th>
                            <th><?php _e('Countries', 'woocommerce'); ?></th>
                            <th><?php _e('Max Weight', 'woocommerce'); ?></th>
                            <th><?php _e('Shipping Rate', 'woocommerce'); ?></th>
                            <th class="status"><?php _e('Status', 'woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
            <?php
            $current_profile_id = WBS_Profile_Manager::instance()->current_profile_id();
            foreach ($profiles as $profile):
                ?>
                        <tr
                            <?=($profile->profile_id === $current_profile_id ? 'class="wbs-current"' : null)?>
                            data-settings-url="<?=esc_html($profile->admin_options_page_url($profile->profile_id))?>"
                        >
                            <td class="name"><?=esc_html($profile->name)?></td>

                            <td>
                <?php if ($profile->availability === 'all'): ?>
                                <?=__('All allowed countries', 'woocommerce')?>
                <?php else: ?>
                                <?=esc_html(join(', ', $profile->countries))?>
                <?php endif; ?>
                            </td>

                            <td>
                                <?= esc_html(!empty($profile->max_weight) ? $profile->max_weight : '-') ?>
                            </td>

                            <td>
                                <?= esc_html($profile->rate); ?>
                            </td>

                            <td class="status">
                <?php if ($profile->enabled == 'yes'): ?>
                                <span class="status-enabled tips" data-tip="<?=__('Enabled', 'woocommerce')?>"><?=__('Enabled', 'woocommerce')?></span>
                <?php else: ?>
                                -
                <?php endif; ?>

                            </td>
                        </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                    jQuery((function($)
                    {
                        var $table = $("#woowbs_shipping_methods");
                        $table.find('tbody').on("sortcreate", function() { $(this).sortable('destroy'); });
                        $table.find('td').click(function(e) { if (e.target == this) location.href = $(this).parent().data('settingsUrl'); });
                    })(jQuery));
                </script>
                <style>
                    #woowbs_shipping_methods td { cursor: pointer; }
                    #woowbs_shipping_methods tr.wbs-current { background-color: #eee; }
                    #woowbs_shipping_methods tr:hover { background-color: #ddd; }
                    tr.wbs-title th { padding: 2em 0 0 0; }
                    tr.wbs-title h4 { font-size: 1.2em; }
                    tr.wbs-profiles > td { padding: 0; }
                </style>
            <?php
        }
	}

    class WBS_Profile_Manager
    {
        private static $instance;
        private $ordered_profiles;

        /** @var WC_Weight_Based_Shipping[] */
        private $profile_instances;


        public static function instance()
        {
            if (!isset(self::$instance))
            {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function profiles()
        {
            if (!isset($this->ordered_profiles))
            {
                $this->ordered_profiles = array();

                foreach (WC()->shipping->load_shipping_methods() as $method)
                {
                    if ($method instanceof WC_Weight_Based_Shipping)
                    {
                        $this->ordered_profiles[] = $method;
                    }
                }
            }

            return $this->ordered_profiles;
        }

        public function profile($name = null)
        {
            $this->find_suitable_id($name);
            $profiles = $this->instantiate_profiles();
            return $profiles[$name];
        }

        public function profile_exists($name)
        {
            $profiles = $this->instantiate_profiles();
            return isset($profiles[$name]);
        }

        public function find_suitable_id(&$profile_id)
        {
            if (empty($profile_id))
            {
                $profile_id = $this->current_profile_id();
            }

            if (empty($profile_id))
            {
                throw new Exception("Weight based shipping profile detection is not enabled for user space for security reason.");
            }

            $id_base = 'WC_Weight_Based_Shipping';

            $id = "{$id_base}_{$profile_id}";

            // Upgrade previous version data
            $prev_option_name = "woocommerce_{$id_base}_settings";
            if (($data = get_option($prev_option_name)) !== false)
            {
                update_option("woocommerce_{$id_base}_main_settings", $data);
                delete_option($prev_option_name);
            }

            return $id;
        }

        public function current_profile_id()
        {
            $profile_id = null;

            if (is_admin())
            {
                if (empty($profile_id))
                {
                    $profile_id = @$_GET['wbs_profile'];
                }

                if (empty($profile_id) && ($profiles = $this->profiles()))
                {
                    $profile_id = $profiles[0]->profile_id;
                }

                if (empty($profile_id))
                {
                    $profile_id = 'main';
                }
            }

            return $profile_id;
        }

        public function new_profile_id()
        {
            $new_profile_id_int = 0;

            do {
                $new_profile_id_int++;
                $new_profile_id = str_pad($new_profile_id_int, 5, '0', STR_PAD_LEFT);
            } while ($this->profile_exists($new_profile_id));

            return $new_profile_id;
        }

        public function _register_profiles($methods)
        {
            return array_merge($methods, $this->instantiate_profiles());
        }

        private function __construct()
        {
            add_filter('woocommerce_shipping_methods', array($this, '_register_profiles'));
        }

        private function instantiate_profiles()
        {
            if (!isset($this->profile_instances))
            {
                $this->profile_instances = array();

                $registered_profile_ids = array();
                {
                    foreach (array_keys(wp_load_alloptions()) as $option)
                    {
                        $matches = array();
                        if (preg_match("/^woocommerce_WC_Weight_Based_Shipping_(?<profile>\\w+)_settings$/", $option, $matches))
                        {
                            $registered_profile_ids[] = $matches['profile'];
                        }
                    }

                    if (empty($registered_profile_ids))
                    {
                        $registered_profile_ids[] = $this->new_profile_id();
                    }
                }

                foreach ($registered_profile_ids as $profile_id)
                {
                    $this->profile_instances[$profile_id] = new WC_Weight_Based_Shipping($profile_id);
                }
            }

            return $this->profile_instances;
        }
    }

    // Setup weight based shipping profiles
    WBS_Profile_Manager::instance();
}

?>