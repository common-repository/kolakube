<?php
/**
 * Plugin Name: Kolakube
 * Plugin URI: http://kolakube.com/
 * Description: A functionality plugin that enhances Kolakube products like Marketers Delight and powers the Page Leads system.
 * Version: 0.8
 * Author: Alex Mangini
 * Author URI: http://kolakube.com/about/
 * Author email: alex@kolakube.com
 * License: GPL2
 * Requires at least: 3.8
 * Tested up to: 4.0
 * Text Domain: kol
 * Domain Path: /languages/
 *
 * This plugin is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see http://www.gnu.org/licenses/.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Constants

define( 'KOL_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'KOL_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );


/**
 * Bare class to initialize the Kolakube API. Mostly
 * need this class to check it in extensions.
 *
 * @since 1.0
 */

final class kolakube {

	public function __construct() {
		load_plugin_textdomain( 'kol', false, KOL_DIR . 'languages/' );

		require_once( KOL_DIR . 'api/api.php' );
	}

}

new kolakube;