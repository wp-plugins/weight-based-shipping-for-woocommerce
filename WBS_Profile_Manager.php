<?php
    class WBS_Profile_Manager
    {
        private static $instance;
        private $ordered_profiles;

        /** @var WC_Weight_Based_Shipping[] */
        private $profile_instances;


        public static function setup()
        {
            self::instance();
        }

        public static function instance($reset_cache = false)
        {
            if (!isset(self::$instance))
            {
                self::$instance = new self();
            }

            if ($reset_cache)
            {
                unset(self::$instance->ordered_profiles);
                unset(self::$instance->profile_instances);
            }

            return self::$instance;
        }

        /** @return WC_Weight_Based_Shipping[] */
        public function profiles()
        {
            if (!isset($this->ordered_profiles))
            {
                $this->ordered_profiles = array();

                /** @var WC_Shipping $shipping */
                $shipping = WC()->shipping;
                foreach ($shipping->load_shipping_methods() as $method)
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
            return @$profiles[$name];
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
            if (!$this->profile_exists('main')) {
                return 'main';
            }

            $timestamp = time();

            $i = null;
            do {
                $new_profile_id = trim($timestamp.'-'.$i++, '-');
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
                        if (preg_match("/^woocommerce_WC_Weight_Based_Shipping_(\\w+)_settings$/", $option, $matches))
                        {
                            $registered_profile_ids[] = $matches[1];
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
?>