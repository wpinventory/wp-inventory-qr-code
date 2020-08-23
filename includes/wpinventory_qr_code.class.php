<?php

/**
 * @help:  https://github.com/endroid/qr-code/issues/107
 */

/**
 * This is the class that takes care of all the WordPress Inventory qr code hooks and actions.
 * The real management takes place in the WPInventory Class
 * @author WP Inventory Manager
 */

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;

//use Endroid\QrCode\Response\QrCodeResponse; // This was from the example but I am not understanding how to implement it

class WPInventoryQRCodeInit extends WPIMItem {

	const VERSION = '1.0.0';

	private static $instance;

	private static $qrcode;

	protected static $url;

	protected static $self_url;

	public static $base_path;

	protected static $base_url;

	protected static $fields;

	const NONCE_ACTION = '*&^WPIM_QR_CODE_NONCE@#$';

	private static $item_key = 'wpim_qr_code';

	public function __construct() {
		parent::__construct();

		self::$qrcode = new WPIMItem();
	}

	/**
	 * Constructor magic method.  Sets up all the actions and hooks.
	 */
	public static function initialize() {

		self::add_filters();
		self::add_actions();

		self::$self_url = 'admin.php?page=manage_qr_code';
	}

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private static function add_filters() {
		$filters = [
			'wpim_get_items' => NULL
		];

		foreach ( $filters as $filter => $args ) {
			if ( method_exists( __CLASS__, $filter ) ) {
				if ( ! $args ) {
					add_filter( $filter, [ __CLASS__, $filter ] );
				} else {
					add_filter( $filter, [ __CLASS__, $filter ], $args[0], $args[1] );
				}
			}
		}
	}

	/**
	 * Set up all the wordpress hooks
	 */
	private static function add_actions() {
		$actions = [
			'init'                     => NULL,
			'wpim_admin_menu'          => NULL,
			'wpim_edit_settings'       => NULL,
			'wpim_save_settings'       => [ 10, 1 ],
			'wpim_admin_edit_form_end' => [ 10, 2 ]
		];

		foreach ( $actions as $action => $args ) {
			if ( method_exists( __CLASS__, $action ) ) {
				if ( ! $args ) {
					add_action( $action, [ __CLASS__, $action ] );
				} else {
					add_action( $action, [ __CLASS__, $action ], $args[0], $args[1] );
				}
			}
		}
	}

	public static function init() {
//		if ( ! load_plugin_textdomain( self::LANG, FALSE, '/wp-content/languages/' ) ) {
//			$plugin_dir = trim( plugin_dir_path( __FILE__ ) );
//			$plugin_dir = basename( str_ireplace( '/includes/', '', $plugin_dir ) );
//			load_plugin_textdomain( self::LANG, FALSE, $plugin_dir . "/languages/" );
//		}
	}

	/**
	 * WordPress admin_enqueue_scripts action callback function
	 */
	public static function admin_enqueue_scripts() {
	}

	/**
	 * WordPress admin_menu action callback function
	 */
	public static function wpim_admin_menu() {
		$lowest_role = self::$config->get( 'permissions_lowest_role' );
		$show        = TRUE;

		// Don't bother showing if neither option is available.

		/**
		 * Hacking this for now.  I don't understand why this doesn't fire when I am an Administrator - Cale time
		 */

//		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
//			$show = FALSE;
//		}

		if ( $show ) {
			self::add_submenu( 'QR Codes', $lowest_role );
		}
	}

	/**
	 * Utility function to simplify adding submenus
	 */
	private static function add_submenu( $title, $role = 'manage_options' ) {

		/**
		 * May or may not license this
		 */
//		if ( ! parent::validate( 'wpim_qr_code' ) ) {
//			return;
//		}

		$slug  = strtolower( str_replace( [ " ", "/" ], "_", $title ) );
		$title = self::__( 'QR Codes' );
		add_submenu_page( self::MENU, $title, $title, $role, 'manage_' . $slug, [ __CLASS__, 'admin_' . $slug ] );
		self::$pages[] = 'manage_' . $slug;
	}

	/**
	 * Add the settings to the config
	 *
	 * @param array $default
	 *
	 * @return array
	 */
	public static function wpim_default_config( $default ) {
		$default['permission_lowest_role_qr_manage'] = 'manage_options';

		return $default;
	}

	/**
	 * Allow control over who may import or export
	 */
	public static function wpim_edit_settings() {
		$settings = self::$config->get_all();
		if ( empty( $settings['permission_lowest_role_qr_manage'] ) ) {
			$settings['permission_lowest_role_qr_manage'] = '';
		}

		$dropdown_array = [
			'manage_options'    => self::__( 'Administrator' ),
			'edit_others_posts' => self::__( 'Editor' ),
			'publish_posts'     => self::__( 'Author' ),
			'edit_posts'        => self::__( 'Contributor' ),
			'read'              => self::__( 'Subscriber' )
		];

		$role_dropdown = self::dropdown_array( "permission_lowest_role_qr_manage", $settings['permission_lowest_role_qr_manage'], $dropdown_array );

		?>
        <h3 data-tab="bulk_management_settings"><?php self::_e( 'QR Code Settings' ); ?></h3>
        <table class='form-table'>
            <tr>
                <th><?php self::_e( 'Minimum Role to Use QR Codes' ); ?></th>
                <td><?php echo $role_dropdown; ?></td>
            </tr>
        </table>
	<?php }

	public static function wpim_save_settings( $data ) {
		// No need to do anything here.
	}

	public static function wpim_admin_edit_form_end( $item, $inventory_id ) {
		/**
		 * TODO:  Instead of generating the QR code each time the item is loaded, which is resource intensive; let's load the QR code image if one already exists
		 */
		$data = self::get_qr_code_data( $inventory_id );
		echo '<tr><th>' . self::__( 'QR Code' ) . '</th><td>' . self::get_qr_code( $data ) . '<p><a href="' . admin_url( 'admin.php?page=manage_qr_codes&inventory_id=' . $inventory_id ) . '">Print Code</a></p></td></tr>';
	}

	/**
	 * Controller for high-level bulk item management actions
	 */
	public static function admin_qr_code_management() { ?>
        <div class="wrap inventorywrap">

			<?php
			self::header( '', 'QR Codes Version', self::VERSION, 'wpim_qr_code' );

			$action = self::request( 'method' );

			if ( ! $action ) {
				self::admin_qr_codes();
			}
			?>
        </div>
		<?php
	}

	/**
	 * Admin QR Code interface
	 */
	public static function admin_qr_codes() {

		if ( 'manage_qr_codes' == self::request( 'page' ) ) {
			return self::print_qr_codes_page();
		}
		/**
		 * TODOS:
		 *
		 * [] - Print page with options: qty to print, width and height (size) to print - one item only from the item edit page with URL parameter $inventory_id attached
		 * [] - From QR Code management page options to print by:  category, date range, inventory_id range, type - or a combination of which
		 * [] - Print page for individual print item except it will break them up by item. Example: qty 20 is chosen for 6 items it will show 6 sections of 20 qrcodes by item name
		 * [] - Simple javascript button to trigger default print window
		 */

		echo '<h3>' . self::__( 'QR Code Manager' ) . '</h3>';

		/**
		 * More permissions stuff
		 */
//		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
//			echo '<p class="description">' . self::__( 'You do not have permission to bulk manage items.' ) . '</p>';
//		}

		echo '<h4>' . self::__( 'This is fetching the first item in your DB' ) . '</h4>';

		/**
		 * For POC, let's fetch the very first item
		 */

		$data = self::get_qr_code_data( $inventory_id = NULL );

		echo self::get_qr_code( $data );
	}

	public static function get_qr_code_data( $inventory_id ) {


		if ( ! self::request( 'inventory_id' ) || $inventory_id == NULL ) {
			// No ID present, grab first one from the items table
			$db           = new WPIMDB();
			$table        = $db->inventory_table;
			$query        = "SELECT * FROM " . $table . " LIMIT 1";
			$item         = $db->get_results( $query );
			$inventory_id = (int) $item[0]->inventory_id;
		}

		$admin = new WPIMAdmin();
		/**
		 * TODO:  If AIM is installed, this has to be per type
		 */
		$display = $admin::getDisplay( 'detail' );

		/** Example of a details list array
		 * array (size=9)
		 * 0 => string 'inventory_images' (length=16)
		 * 1 => string 'inventory_name' (length=14)
		 * 2 => string 'inventory_manufacturer' (length=22)
		 * 3 => string 'inventory_make' (length=14)
		 * 4 => string 'inventory_model' (length=15)
		 * 5 => string 'inventory_price' (length=15)
		 * 6 => string 'inventory_year' (length=14)
		 * 7 => string 'inventory_description' (length=21)
		 * 8 => string 'inventory_media' (length=15)
		 */

		// This won't be necessary if it can't support images (I dont think)
//		$item = new WPIMItem();

		$data = '';
		foreach ( $display AS $field ) {

			/**
			 * It may not be possible to do images:  https://stackoverflow.com/questions/9997587/qrcode-which-contains-an-image-and-text
			 */
//			if ( in_array( $field, [ 'inventory_images', 'inventory_image' ] ) ) {
//				$images = $item->get_images( $inventory_id );
//				if ( ! empty( $images ) ) {
//					if ( 'inventory_images' == $field ) {
//						if ( count( $images ) > 1 ) {
//							$data .= '<p>We have some images here we need to deal with.</p>';
//						}
//					}
//
//					$data .= '<p><img src="' . $images[0]->thumbnail . '" alt=""></p>';
//				}
//			}


			$item = self::get_inventory_item( $inventory_id );


			foreach ( $item as $key => $value ) {
				if ( $field == $key ) {
					$data .= '<p><strong>' . $admin::get_label( $field ) . '</strong> ' . $value . '</p>';
				}
			}
		}

		return $data;
	}

	/**
	 * Merge in any relevant pages for loading scripts, css.
	 *
	 * @param array $pages
	 *
	 * @return array:
	 */
	public static function wpim_admin_pages( $pages ) {
		$pages = array_merge( $pages, self::$pages );

		return $pages;
	}

	/**
	 * Filter for add-ons to indicate that bulk item is installed
	 *
	 * @param array $add_ons
	 *
	 * @return array
	 */
	public static function wpim_add_ons_list( $add_ons ) {
		if ( ! $add_ons ) {
			return [];
		}

		foreach ( $add_ons AS $index => $add_on ) {
			if ( stripos( $add_on->title, 'qr' ) !== FALSE ) {
				$add_ons[ $index ]->installed = TRUE;
				self::$item_key               = $add_on->key;
			}
		}

		return $add_ons;
	}

	public static function get_qr_code( $data, $width = 300 ) {

		if ( $data == NULL ) {
			return self::__( 'No data was supplied to render the QR code.' );
		}

		$qrCode = new QrCode( $data );
		$qrCode->setSize( $width );
		$qrCode->setMargin( 10 );

// Set advanced options
		$qrCode->setWriterByName( 'png' );
		$qrCode->setEncoding( 'UTF-8' );
		$qrCode->setErrorCorrectionLevel( ErrorCorrectionLevel::LOW() );
		$qrCode->setForegroundColor( [ 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0 ] );
		$qrCode->setBackgroundColor( [ 'r' => 255, 'g' => 255, 'b' => 255, 'a' => 0 ] );
		$qrCode->setLabel( 'Scan the code', 16, QRCODE_PLUGIN_PATH . 'vendor/qrcode/vendor/endroid/qr-code/assets/fonts/noto_sans.otf', LabelAlignment::CENTER() );
//		$qrCode->setLogoPath( QRCODE_PLUGIN_PATH . 'vendor/qrcode/vendor/endroid/qr-code/assets/images/symfony.png' );
		$qrCode->setLogoSize( 150, 200 );
		$qrCode->setValidateResult( FALSE );

// Round block sizes to improve readability and make the blocks sharper in pixel based outputs (like png).
// There are three approaches:
		$qrCode->setRoundBlockSize( TRUE, QrCode::ROUND_BLOCK_SIZE_MODE_MARGIN ); // The size of the qr code is shrinked, if necessary, but the size of the final image remains unchanged due to additional margin being added (default)
		$qrCode->setRoundBlockSize( TRUE, QrCode::ROUND_BLOCK_SIZE_MODE_ENLARGE ); // The size of the qr code and the final image is enlarged, if necessary
		$qrCode->setRoundBlockSize( TRUE, QrCode::ROUND_BLOCK_SIZE_MODE_SHRINK ); // The size of the qr code and the final image is shrinked, if necessary

// Set additional writer options (SvgWriter example)
		$qrCode->setWriterOptions( [ 'exclude_xml_declaration' => TRUE ] );

// Save it to a file
		/**
		 * TODO:  save the file based on $inventory_id and overwrite it if one already exists - keep it clean!
		 */
		$qrCode->writeFile( __DIR__ . '/qrcode.png' );

// Generate a data URI to include image data inline (i.e. inside an <img> tag)
		$dataUri = $qrCode->writeDataUri();

		return '<img src="' . $dataUri . '">';
	}

	public static function print_qr_codes_page() {


		if ( isset( $_POST['print_qr_codes_submit'] ) ) {
			$quantity = ( isset( $_POST['print_qr_code_quantity'] ) ) ? $_POST['print_qr_code_quantity'] : NULL;
			if ( NULL == $quantity ) {
				echo '<p>' . self::__( 'You must provide a valid quantity to print' ) . '</p>';
				return;
			}

			$inventory_id = (int) self::request( 'inventory_id' );

			$data    = self::get_qr_code_data( $inventory_id );
			$qr_code = self::get_qr_code( $data, $_POST['qr_code_width'] );

			$count = 0;
			while ( $count < $quantity ) {
				echo $qr_code;
				$count ++;
			}

			echo '<p><a class="button-primary qr_code_print_window" href="javascript:void(0)">' . self::__( 'Print' ) . '</a></p>';

			echo '<script>';
			echo 'jQuery(function($) {
			$(".qr_code_print_window").on("click", function() {
			window.print();
			});
			});';
			echo '</script>';

			echo '<style>';

			echo '
			@media print {
			    #adminmenumain,
			    #wpadminbar,
			    .notice {
			        display: none;
			    }
			    
			    #wpcontent, #wpfooter {
			        margin: 0 !important;
			    }
			}
			';
			echo '</style>';

			return;
		}

		echo '<form method="post" action="">';
		echo '<h2>' . self::__( 'How many would you like to print?' ) . '</h2>';
		echo '<p><input class="print_qr_code_quantity" name="print_qr_code_quantity" type="number" value=""></p>';
		echo '<h2>' . self::__( 'Width of the QR Code' ) . '</h2>';
		echo '<p><input class="qr_code_width" type="number" name="qr_code_width" value="300"><br><small>' . self::__( 'In pixels (px)' ) . '</small></p>';

		wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
		submit_button( 'Print', 'primary', 'print_qr_codes_submit' );

		echo '</form>';
	}
}

WPInventoryQRCodeInit::initialize();
