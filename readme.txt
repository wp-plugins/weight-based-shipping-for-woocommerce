=== Weight based shipping for WooCommerce ===
Contributors: dangoodman
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=gravyzzap%40gmail.com
Tags: woocommerce, shipping, weight, commerce, ecommerce, shop
Requires at least: 3.8
Tested up to: 4.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple weight based shipping method for WooCommerce

== Description ==

Plugin adds a simple weight based shipping method to WooCommerce. You can have multiple configurations active depending on countries and max weight you need to handle. See Screenshots tab for details.

You can find plugin settings under WooCommerce -> Settings -> Shipping section.

== Changelog ==

= 2.3.0 =

* Duplicate profile feature
* New 'Weight Step' option for rough gradual shipping price calculation
* Added more detailed description to the Handling Fee and Shipping Rate fields to make their purpose clear
* Added Russian translation
* Plugin prepared for localization
* Refactoring

= 2.2.3 =

* Fixed: first time saving settings with fresh install does not save anything while reporting successful saving.
* Replace short php tags with their full equivalents to make code more portable.

= 2.2.2 =

Fix "parse error: syntax error, unexpected T_FUNCTION in woocommerce-weight-based-shipping.php on line 610" http://wordpress.org/support/topic/fatal-error-1164.

= 2.2.1 =

Allow zero weight shipping. Thus only Handling Fee is added to the final price.

Previously, weight based shipping option has not been shown to user if total weight of their cart is zero. Since version 2.2.1 this is changed so shipping option is available to user with price set to Handling Fee. If it does not suite your needs well you can return previous behavior by setting Min Weight to something a bit greater zero, e.g. 0.001, so that zero-weight orders will not match constraints and the shipping option will not be shown.

== Upgrade Notice ==

= 2.2.1 =

Allow zero weight shipping. Thus only Handling Fee is added to the final price.

Previously, weight based shipping option has not been shown to user if total weight of their cart is zero. Since version 2.2.1 this is changed so shipping option is available to user with price set to Handling Fee. If it does not suite your needs well you can return previous behavior by setting Min Weight to something a bit greater zero, e.g. 0.001, so that zero-weight orders will not match constraints and the shipping option will not be shown.

== Installation ==

1. Upload `woocommerce-weight-based-shipping` folder  to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Settings page