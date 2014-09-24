<?php
    class WBS_Upgrader
    {
        public static function setup($pluginFile)
        {
            if (!self::$loaded)
            {
                self::$loaded = true;

                $upgrader = new self($pluginFile);
                $upgrader->onLoad();
            }
        }

        public function __construct($pluginFile)
        {
            $this->pluginFile = $pluginFile;
        }

        public function onLoad()
        {
            $this->setupHooks();
        }

        public function onAdminInit()
        {
            $this->checkForUpgrade();
        }

        public function onAdminNotices()
        {
            $this->show221UpgradeNotice();
        }

        private static $loaded;
        private $pluginFile;

        private function checkForUpgrade()
        {
            $previous_version = get_option('woowbs_version');
            if (empty($previous_version)) {
                $previous_version = '2.1.1';
            }

            $current_version = get_plugin_data($this->pluginFile, false, false);
            $current_version = $current_version['Version'];

            if ($previous_version !== $current_version) {
                if (version_compare($previous_version, '2.2.1') < 0) {
                    update_option('woowbs_show_221_upgrade_notice', true);
                }

                if (version_compare($previous_version, '2.4.0') < 0) {
                    $existing_profiles = WBS_Profile_Manager::instance()->profiles();
                    foreach ($existing_profiles as $profile) {
                        $option = $profile->get_wp_option_name();
                        $config = get_option($option);
                        $config['extra_weight_only'] = 'no';
                        update_option($option, $config);
                    }
                }

                update_option('woowbs_version', $current_version);
            }
        }

        private function setupHooks()
        {
            add_action('admin_init', array($this, 'onAdminInit'));
            add_action('admin_notices', array($this, 'onAdminNotices'));
        }

        private function show221UpgradeNotice()
        {
            if (!get_option('woowbs_show_221_upgrade_notice'))
            {
                return;
            }

            $setting_page_url = esc_html(WC_Weight_Based_Shipping::edit_profile_url());
            $hide_notice_url = esc_html(WC_Weight_Based_Shipping::edit_profile_url(null, array('hide_221_upgrade_notice' => 'yes')));

            echo '
            <div class="highlight" style="padding: 1em; border: 1px solid red;">
                <big>
                    Behavior of "Weight based shipping for WooCommerce" plugin changed in the new version.
                    <a id="woowbs-221-upgrade-notice-collapse" href="#">Less</a>
                </big>
                <div id="woowbs-221-upgrade-notice-collapse-content">
                    <p>Previously, weight based shipping option has not been shown to user if total weight of their cart is zero.
                    Since version 2.2.1 this is changed so shipping option is available to user with price set to Handling Fee.
                    If it does not suite your needs well you can return previous behavior by setting Min Weight to something
                    a bit greater zero, e.g. 0.001, so that zero-weight orders will not match constraints and the shipping
                    option will not be shown.</p>
                    <p>Please <a href="'.$setting_page_url.'">review settings</a>
                    and make appropriate changes if it\'s needed.</p>
                    <p><a class="button" href="'.$hide_notice_url.'">Don\'t show this message again</a></p>
                </div>
            </div>
            <script>
                (function($) {
                    var $collapser = $("#woowbs-221-upgrade-notice-collapse");
                    var $content = $("#woowbs-221-upgrade-notice-collapse-content");
                    var toggleSpeed = 0;

                    $collapser.click(function()
                    {
                        $content.toggle(toggleSpeed, function() {
                            $collapser.text($content.is(":visible") ? "Less" : "More");
                        });

                        return false;
                    });

                    $collapser.click();
                    toggleSpeed = "fast";
                })(jQuery);
            </script>';
        }
    }
?>