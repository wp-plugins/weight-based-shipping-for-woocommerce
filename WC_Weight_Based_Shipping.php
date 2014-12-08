<?php
    class WC_Weight_Based_Shipping extends WC_Shipping_Method
    {
        public $name;
        public $profile_id;
        public $rate;
        public $extra_weight_only;
        public $weight_step;
        public $min_weight;
        public $max_weight;

        /** @var WBS_Shipping_Class_Override_Set */
        public $shipping_class_overrides;


        public function __construct($profile_id = null)
        {
            $process_admin_options = !isset($profile_id);

            $this->id = WBS_Profile_Manager::instance()->find_suitable_id($profile_id);
            $this->profile_id = $profile_id;

            $this->method_title = __('Weight Based', 'woowbs');

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

            $this->fee = $this->get_option('fee');
            $this->settings['fee'] = $this->format_float($this->fee, '');

            $this->rate = $this->get_option('rate');
            $this->settings['rate'] = $this->format_float($this->rate, '');

            $this->extra_weight_only = $this->get_option('extra_weight_only');

            $this->weight_step = $this->validate_positive_float($this->get_option('weight_step'));
            $this->settings['weight_step'] = $this->format_float($this->weight_step, '');

            $this->min_weight = $this->validate_positive_float($this->get_option('min_weight'));
            $this->settings['min_weight'] = $this->format_float($this->min_weight, '');

            $this->max_weight = $this->validate_max_weight($this->get_option('max_weight'), $this->min_weight);
            $this->settings['max_weight'] = $this->format_float($this->max_weight, '');

            if (empty($this->countries))
            {
                $this->availability = $this->settings['availability'] = 'all';
            }

            $this->shipping_class_overrides = $this->get_option('shipping_class_overrides');
            if ($this->shipping_class_overrides == null)
            {
                $this->shipping_class_overrides = new WBS_Shipping_Class_Override_Set();
            }
        }

        function init_form_fields()
        {
            $woocommerce = WC();
            $shipping_countries = method_exists($woocommerce->countries, 'get_shipping_countries')
                ? $woocommerce->countries->get_shipping_countries()
                : $woocommerce->countries->countries;

            $this->form_fields = array
            (
                'enabled'    => array
                (
                    'title'   => __('Enable/Disable', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable this shipping method', 'woocommerce'),
                    'default' => 'no',
                ),
                'name'    => array
                (
                    'title'  => __('Profile Name', 'woowbs'),
                    'type'   => 'text',
                ),
                'title'      => array
                (
                    'title'       => __('Method Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Weight Based Shipping', 'woowbs'),
                ),
                'availability' => array
                (
                    'title' 		=> __('Availability', 'woocommerce'),
                    'type' 			=> 'select',
                    'default' 		=> 'all',
                    'class'			=> 'availability',
                    'options'		=> array
                    (
                        'all' 		=> __('All allowed countries', 'woocommerce'),
                        'specific' 	=> __('Specific countries', 'woocommerce'),
                        'excluding' => __('All countries except specified', 'woowbs'),
                    ),
                ),
                'countries' => array
                (
                    'title' 		=> __('Specific Countries', 'woocommerce'),
                    'type' 			=> 'multiselect',
                    'class'			=> 'chosen_select',
                    'default' 		=> '',
                    'options'		=> $shipping_countries,
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select some countries', 'woocommerce'),
                    ),
                    'html' =>
                        '<br />'.
                        '<a class="select_all  button" href="#">'.__('Select all',  'woocommerce').'</a> '.
                        '<a class="select_none button" href="#">'.__('Select none', 'woocommerce').'</a>',
                ),
                'tax_status' => array
                (
                    'title'       => __('Tax Status', 'woocommerce'),
                    'type'        => 'select',
                    'default'     => 'taxable',
                    'options'     => array
                    (
                        'taxable' => __('Taxable', 'woocommerce'),
                        'none'    => __('None', 'woocommerce'),
                    ),
                ),
                'fee'        => array
                (
                    'title'       => __('Handling Fee', 'woocommerce'),
                    'type'        => 'positive_decimal',
                    'description' =>
                        __('Fee excluding tax, e.g. <code>3.50</code>({{currency}}).', 'woowbs').'<br />'.
                        __('Constant part of shipping price. Leave it empty or zero if your shipping price depends only on order weight.', 'woowbs'),
                ),
                'rate'       => array
                (
                    'title'       => __('Shipping Rate', 'woowbs'),
                    'type'        => 'positive_decimal',
                    'description' =>
                        __('Set your shipping price for 1 {{weight_unit}}. Example: <code>1.95</code>({{currency}}/{{weight_unit}}).', 'woowbs').'<br />'.
                        __('Dynamic part of shipping price. Leave it empty or zero if your shipping price does not depend on order weight.', 'woowbs'),
                 ),
                'extra_weight_only' => array
                (
                    'title'         => __('Extra Weight Only', 'woowbs'),
                    'label'         => __('Rate weight after Min Weight', 'woowbs'),
                    'type'          => 'checkbox',
                    'default'       => 'yes',
                    'description'   => __('Apply Shipping Rate to the weight part exceeding Min Weight if checked. Otherwise, apply Shipping Rate to the whole cart weight.', 'woowbs'),
                ),
                'weight_step' => array
                (
                    'title'         => __('Weight Step', 'woowbs'),
                    'type'          => 'positive_decimal',
                    'description'   =>
                        __('Use this option if you need to rate every next weight chunk (e.g. every <code>0.5</code>{{weight_unit}}) rather than calculate price using precise order weight.', 'woowbs').'<br />'.
                        __('For example if total order weight is 1.325{{weight_unit}} and Shipping Rate is {{currency}}2 per {{weight_unit}} then total shipping price would be {{currency}}2.65 (assuming Handling Fee is zero) if Weight Step is disabled. But if Weight Step is set to 0.5{{weight_unit}} then shipping price would be {{currency}}3; if 0.1{{weight_unit}} then {{currency}}2.8; if 1{{weight_unit}} then {{currency}}4; and so on.', 'woowbs'),
                ),
                'min_weight' => array
                (
                    'title'       => __('Min Weight', 'woowbs'),
                    'type'        => 'positive_decimal',
                    'description' =>
                        __('The shipping option will not be shown during the checkout process if order weight less than this value. Example: <code>0.5</code>({{weight_unit}}).', 'woowbs'),
                ),
                'max_weight' => array
                (
                    'title'       => __('Max Weight', 'woowbs'),
                    'type'        => 'positive_decimal',
                    'description' =>
                        __('The shipping option will not be shown during the checkout process if order weight exceeds this limit. Example: <code>2.5</code>({{weight_unit}}). Leave blank to disable.', 'woowbs'),
                ),
                'shipping_class_overrides' => array
                (
                    'title'       => __('Shipping Class Overrides', 'woowbs'),
                    'type'        => 'shipping_class_overrides',
                    'description' => __('You can override some options for specific shipping classes', 'woowbs'),
                )
            );

            $placeholders = array
            (
                'weight_unit' => __(get_option('woocommerce_weight_unit'), 'woocommerce'),
                'currency' => get_woocommerce_currency_symbol(),
            );

            foreach ($this->form_fields as &$field)
            {
                $field['description'] = wbst(@$field['description'], $placeholders);
            }
        }

        public function calculate_shipping()
        {
            $cart = WC()->cart;

            $weight = $cart->cart_contents_weight;
            if ($this->min_weight && $weight < $this->min_weight) {
                return;
            }
            if ($this->max_weight && $weight > $this->max_weight) {
                return;
            }

            $default_override = new WBS_Shipping_Rate_Override(
                'default',
                $this->fee,
                $this->rate,
                $this->weight_step
            );

            /** @var WBS_Cart_Item_Bucket[] $buckets */
            $buckets = array();
            {
                foreach ($cart->get_cart() as $item_id => $item) {
                    /** @var WC_Product_Simple $product */
                    $product = $item['data'];

                    $override = $this->shipping_class_overrides->findByClass($product->get_shipping_class());
                    if ($override == null) {
                        $override = $default_override;
                    }

                    $class = $override->getClass();
                    if (!isset($buckets[$class])) {
                        $buckets[$class] = new WBS_Cart_Item_Bucket($override, 0);
                    }

                    $buckets[$class]->addWeight((float)$product->get_weight() * $item['quantity']);
                }
            }

            $price = 0;
            foreach ($buckets as $bucket) {
                $override = $bucket->getOverride();

                $weight = $bucket->getWeight();
                {
                    if ($override == $default_override) {
                        if ($this->extra_weight_only !== 'no' && $this->min_weight) {
                            $weight = max(0, $weight - $this->min_weight);
                        }
                    }

                    $weight_step = $override->getWeightStep();
                    if ($weight_step) {
                        $weight = ceil($weight / $weight_step) * $weight_step;
                    }
                }

                $price += $override->getFee() + $weight * $override->getRate();
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
            if (!empty($_GET['hide_221_upgrade_notice']))
            {
                delete_option('woowbs_show_221_upgrade_notice');
                $this->refresh();
            }

            $manager = WBS_Profile_Manager::instance(true);
            $profiles = $manager->profiles();
            $profile = $manager->profile();

            if (!empty($_GET['delete']))
            {
                if (isset($profile))
                {
                    delete_option($profile->get_wp_option_name());
                }

                $this->refresh();
            }

            if (!isset($profile))
            {
                $profile = new self();

                if (($source_profile_id = $_GET['duplicate']) != null &&
                    ($source_profile = $manager->profile($source_profile_id)) != null)
                {
                    $tmp_profile = clone($source_profile);
                    $tmp_profile->id = $profile->id;
                    $tmp_profile->profile_id = $profile->profile_id;
                    $tmp_profile->name .= ' ('._x('copy', 'noun', 'woowbs').')';
                    $tmp_profile->settings['name'] = $tmp_profile->name;

                    $profile = $tmp_profile;
                }

                $profiles[] = $profile;
            }

            $multiple_profiles_available = count($profiles) > 1;

            $create_profile_link_html =
                '<a class="add-new-h2" href="'.esc_html($this->new_profile_url()).'">'.
                    esc_html__('Create additional configuration', 'woowbs').
                '</a>';

            ?>
                <h3><?php esc_html_e('Weight based shipping', 'woowbs'); ?></h3>
                <p><?php esc_html_e('Lets you calculate shipping based on total weight of the cart. You can have multiple configurations active.', 'woowbs'); ?></p>

                <?php include(dirname(__FILE__).'/cta-buttons.php') ?>

            <?php if (!$multiple_profiles_available): ?>
                <?php echo $create_profile_link_html ?><br><br><br>
            <?php endif; ?>

                <table class="form-table">
            <?php if ($multiple_profiles_available): ?>
                    <tr class="wbs-title">
                        <th colspan="2">
                            <h4><?php esc_html_e('Available configurations', 'woowbs'); echo $create_profile_link_html; ?></h4>
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
                                <?php echo esc_html(wbst(__('Settings for {{profile_name}} configuration', 'woowbs'), $profile->name)) ?>
                            </h4>
                        </th>
                    </tr>
            <?php endif; ?>
                    <?php $profile->generate_settings_html(); ?>
                </table>
            <?php
        }

        public function process_admin_options()
        {
            $result = parent::process_admin_options();

            $this->init();

            $clone = WBS_Profile_Manager::instance()->profile($this->profile_id);
            if (isset($clone)) {
                $clone->init();
            }

            if ($result) {
                $this->purgeWoocommerceShippingCache();
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

        public function validate_positive_decimal_field($key)
        {
            return $this->validate_positive_float($this->validate_decimal_field($key));
        }

        public function validate_max_weight_field($key)
        {
            return $this->validate_max_weight($this->validate_decimal_field($key), $this->validate_positive_decimal_field('min_weight'));
        }

        public function validate_shipping_class_overrides_field($key)
        {
            $overrides = new WBS_Shipping_Class_Override_Set();

            $prefix = $this->plugin_id . $this->id . '_' . $key;

            foreach ((array)@$_POST["{$prefix}_class"] as $i => $class)
            {
                $fee = $_POST["{$prefix}_fee"][$i];
                $rate = $_POST["{$prefix}_rate"][$i];
                $weight_step = $_POST["{$prefix}_weight_step"][$i];
                $override = new WBS_Shipping_Rate_Override($class, $fee, $rate, $weight_step);
                $overrides->add($override);
            }

            return $overrides;
        }

        public function generate_positive_decimal_html($key, $data)
        {
            return $this->generate_decimal_html($key, $data);
        }

        function generate_shipping_class_overrides_html($key, $data)
        {
            $prefix = $this->plugin_id . $this->id . '_' . $key;

            $data = wp_parse_args($data, array(
                'title'             => '',
                'desc_tip'          => '',
                'description'       => '',
            ));

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php echo $this->get_description_html($data); ?>
                </th>
                <td class="forminp" id="<?php echo $this->id; ?>_flat_rates">
                    <table class="shippingrows widefat" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox"></th>
                                <th class="shipping_class"><?php _e( 'Shipping Class', 'woocommerce' ); ?></th>
                                <th><?php _e('Handling Fee', 'woocommerce'); ?></th>
                                <th><?php _e('Shipping Rate', 'woowbs'); ?></th>
                                <th><?php _e('Weight Step', 'woowbs'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="flat_rates">
                            <?php
                                $i = -1;
                                foreach ($this->shipping_class_overrides->listAll() as $override)
                                {
                                    $i++;

                                    echo '<tr class="flat_rate">
                                    <th class="check-column"><input type="checkbox" name="select" /></th>
                                    <td class="flat_rate_class">
                                            <select name="' . esc_attr($prefix . '_class[' . $i . ']' ) . '" class="select">';

                                    if ( WC()->shipping->get_shipping_classes() )
                                    {
                                        foreach (WC()->shipping->get_shipping_classes() as $shipping_class)
                                        {
                                            echo '<option value="' . esc_attr( $shipping_class->slug ) . '" '.selected($shipping_class->slug, $override->getClass(), false).'>'.$shipping_class->name.'</option>';
                                        }
                                    }
                                    else
                                    {
                                        echo '<option value="">'.__( 'Select a class&hellip;', 'woocommerce' ).'</option>';
                                    }

                                    echo '</select>
                                    </td>
                                    <td><input type="text" value="' . esc_attr($override->getFee()) . '" name="' . esc_attr( $prefix .'_fee[' . $i . ']' ) . '" size="4" class="wc_input_price" /></td>
                                    <td><input type="text" value="' . esc_attr($override->getRate()) . '" name="' . esc_attr( $prefix .'_rate[' . $i . ']' ) . '" size="4" class="wc_input_price" /></td>
                                    <td><input type="text" value="' . esc_attr($override->getWeightStep()) . '" name="' . esc_attr( $prefix .'_weight_step[' . $i . ']' ) . '" size="4" class="wc_input_decimal" /></td>
                                </tr>';
                                }
                            ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="5"><a href="#" class="add button"><?php _e('Add', 'woocommerce' ); ?></a> <a href="#" class="remove button"><?php _e( 'Delete selected costs', 'woocommerce' ); ?></a></th>
                        </tr>
                        </tfoot>
                    </table>
                    <script type="text/javascript">
                        jQuery(function()
                        {
                            jQuery('#<?php echo $this->id; ?>_flat_rates').on( 'click', 'a.add', function(){

                                var size = jQuery('#<?php echo $this->id; ?>_flat_rates tbody .flat_rate').size();

                                jQuery('<tr class="flat_rate">\
								<th class="check-column"><input type="checkbox" name="select" /></th>\
								<td class="flat_rate_class">\
									<select name="<?php echo $prefix; ?>_class[' + size + ']" class="select">\
						   				<?php
						   				if (WC()->shipping->get_shipping_classes()) :
											foreach (WC()->shipping->get_shipping_classes() as $class) :
												echo '<option value="' . esc_attr( $class->slug ) . '">' . esc_js( $class->name ) . '</option>';
											endforeach;
										else :
											echo '<option value="">'.__( 'Select a class&hellip;', 'woocommerce' ).'</option>';
										endif;
						   				?>\
						   			</select>\
						   		</td>\
								<td><input type="text" name="<?php echo $prefix; ?>_fee[' + size + ']" size="4" class="wc_input_price" /></td>\
								<td><input type="text" name="<?php echo $prefix; ?>_rate[' + size + ']" size="4" class="wc_input_price" /></td>\
								<td><input type="text" name="<?php echo $prefix; ?>_weight_step[' + size + ']" size="4" class="wc_input_decimal" /></td>\
							</tr>').appendTo('#<?php echo $this->id; ?>_flat_rates table tbody');

                                return false;
                            });

                            // Remove row
                            jQuery('#<?php echo $this->id; ?>_flat_rates').on( 'click', 'a.remove', function(){
                                var answer = confirm("<?php _e( 'Delete the selected rates?', 'woocommerce' ); ?>");
                                if (answer) {
                                    jQuery('#<?php echo $this->id; ?>_flat_rates table tbody tr th.check-column input:checked').each(function(i, el){
                                        jQuery(el).closest('tr').remove();
                                    });
                                }
                                return false;
                            });
                        });
                    </script>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        public function get_description_html($data)
        {
            return parent::get_description_html($data) . @$data['html'];
        }

        public function get_wp_option_name()
        {
            return sprintf('%s%s_settings', $this->plugin_id, $this->id);
        }

        public static function edit_profile_url($profile_id = null, $parameters = array())
        {
            $query = build_query(array_filter($parameters + array
            (
                "page"          => (version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings"),
                "tab"           => "shipping",
                "section"       => __CLASS__,
                'wbs_profile'   => $profile_id,
            )));

            $url = admin_url("admin.php?{$query}");

            return $url;
        }

        private function new_profile_url($parameters = array())
        {
            return $this->edit_profile_url(WBS_Profile_Manager::instance()->new_profile_id(), $parameters);
        }

        private function duplicate_profile_url($duplicate_profile_id, $parameters = array())
        {
            $parameters['duplicate'] = $duplicate_profile_id;
            $url = $this->new_profile_url($parameters);
            return $url;
        }

        private function delete_profile_url($profile_id, $parameters = array())
        {
            $parameters['delete'] = 'yes';
            $url = $this->edit_profile_url($profile_id, $parameters);
            return $url;
        }

        private function purgeWoocommerceShippingCache()
        {
            global $wpdb;

            $transients = $wpdb->get_col("
                SELECT SUBSTR(option_name, LENGTH('_transient_') + 1)
                FROM `{$wpdb->options}`
                WHERE option_name LIKE '_transient_wc_ship_%'
            ");

            foreach ($transients as $transient) {
                delete_transient($transient);
            }
        }

        private function validate_positive_float($value)
        {
            return max(0, (float)$value);
        }

        private function validate_max_weight($max_weight, $min_weight)
        {
            $max_weight = $this->validate_positive_float($max_weight);

            if ($max_weight && $min_weight && $max_weight < $min_weight)
            {
                $max_weight = $min_weight;
            }

            return $max_weight;
        }

        private function format_float($value, $zero_replacement = 0)
        {
            if ($value == 0)
            {
                return $zero_replacement;
            }

            return wc_float_to_string($value);
        }

        /**
         * @param self[] $profiles
         */
        private function list_profiles($profiles)
        {
            $current_profile_id = WBS_Profile_Manager::instance()->current_profile_id();

            ?>
            <table id="woowbs_shipping_methods" class="wc_shipping widefat">
                <thead>
                <tr>
                    <th class="name">   <?php esc_html_e('Name', 'woocommerce'); ?>         </th>
                    <th>                <?php esc_html_e('Countries', 'woocommerce'); ?>    </th>
                    <th>                <?php esc_html_e('Weight', 'woocommerce'); ?>       </th>
                    <th>                <?php esc_html_e('Handling Fee', 'woocommerce'); ?> </th>
                    <th>                <?php esc_html_e('Shipping Rate', 'woowbs'); ?>     </th>
                    <th>                <?php esc_html_e('Weight Step', 'woowbs'); ?>       </th>
                    <th class="status"> <?php esc_html_e('Status', 'woocommerce'); ?>       </th>
                    <th>                <?php esc_html_e('Actions'); ?>                     </th>
                </tr>
                </thead>
                <tbody>
            <?php foreach ($profiles as $profile): ?>
                    <tr
                        <?php echo ($profile->profile_id === $current_profile_id ? 'class="wbs-current"' : null) ?>
                        data-settings-url="<?php echo esc_html($profile->edit_profile_url($profile->profile_id)) ?>"
                    >
                        <td class="name"><?php echo esc_html($profile->name)?></td>

                        <td class="countries">
                <?php if ($profile->availability === 'all'): ?>
                            <?php esc_html_e('All', 'woowbs') ?>
                <?php else: ?>
                    <?php if ($profile->availability === 'excluding'): ?>
                            <?php esc_html_e('All except', 'woowbs') ?>
                    <?php endif; ?>
                            <?php echo esc_html(join(', ', $profile->countries))?>
                <?php endif; ?>
                        </td>

                        <td>
                            <?php echo $this->format_float($profile->min_weight) . ' — ' . $this->format_float($profile->max_weight, '&infin;'); ?>
                        </td>

                        <td>
                            <?php echo esc_html($this->format_float($profile->fee, '-')); ?>
                        </td>

                        <td>
                            <?php echo esc_html($profile->rate); ?>
                        </td>

                        <td>
                            <?php echo esc_html($this->format_float($profile->weight_step, '-')); ?>
                        </td>

                        <td class="status">
                <?php if ($profile->enabled == 'yes'): ?>
                            <span class="status-enabled tips" data-tip="<?php esc_html_e('Enabled', 'woocommerce')?>"><?php esc_html_e('Enabled', 'woocommerce')?></span>
                <?php else: ?>
                            -
                <?php endif; ?>
                        </td>

                        <td class="actions">
                            <a class="button" href="<?php echo esc_html($profile->duplicate_profile_url($profile->profile_id)) ?>">
                                <?php esc_html_e('Duplicate', 'woowbs') ?>
                            </a>

                            <a class="button" href="<?php echo esc_html($profile->delete_profile_url($profile->profile_id)) ?>"
                               onclick="return confirm('<?php esc_html_e('Last warning, are you sure?', 'woocommerce') ?>');">
                                <?php esc_html_e('Delete') ?>
                            </a>
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
                #woowbs_shipping_methods td { cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                #woowbs_shipping_methods td.actions { white-space: nowrap; }
                #woowbs_shipping_methods td.countries { max-width: 15em; }
                #woowbs_shipping_methods tr.wbs-current { background-color: #eee; }
                #woowbs_shipping_methods tr:hover { background-color: #ddd; }
                tr.wbs-title th { padding: 2em 0 0 0; }
                tr.wbs-title h4 { font-size: 1.2em; }
                tr.wbs-profiles > td { padding: 0; }
            </style>
            <?php
        }

        private function refresh()
        {
            echo '<script>location.href = ' . json_encode(self::edit_profile_url(null)) . ';</script>';
            die();
        }
    }
?>