<?php

/**
 * Plugin Name:    WP Inventory QR Code
 * Plugin URI:    http://www.wpinventory.com
 * Description:    Add-On to the WP Inventory plugin, allows for creation of QR Codes to be read by any modern QR code reader on Android or Apple devices.
 * Version:        1.0.0
 * Author:        WP Inventory Manager
 * Author URI:    http://www.wpinventory.com/
 *
 * ------------------------------------------------------------------------
 * Copyright 2009-2020 WP Inventory Manager
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

// Instantiate the classes
function activate_wpim_qr_code() {
	$min_version = '2.0.5';
	if ( ! WPIMCore::check_version( $min_version, 'WP Inventory Manager QR Codes' ) ) {
		return;
	}

	add_action( 'wpim_core_loaded', 'launch_wpim_qr_code' );
}

function launch_wpim_qr_code() {
	require_once "includes/wpinventory_qr_code.class.php";
	require_once "vendor/qrcode/vendor/autoload.php";
	define( 'QRCODE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// Cannot load the plugin files until we are certain required WP Inventory classes are loaded
add_action( 'wpim_load_add_ons', 'activate_wpim_qr_code' );
