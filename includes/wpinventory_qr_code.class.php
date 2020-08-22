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
use Endroid\QrCode\Response\QrCodeResponse;

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
			'init'                  => NULL,
			'admin_enqueue_scripts' => NULL,
			'wpim_admin_menu'       => NULL,
			'wpim_edit_settings'    => NULL,
			'wpim_save_settings'    => [ 10, 1 ]
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
	    
		echo '<h3>' . self::__( 'QR Code Manager' ) . '</h3>';

		/**
		 * More permissions stuff
		 */
//		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
//			echo '<p class="description">' . self::__( 'You do not have permission to bulk manage items.' ) . '</p>';
//		}

		echo '<h4>' . self::__( 'This is fetching the first item in your DB' ) . '</h4>';
		echo '<p>This is a POC (proof of concept) only.</p>';

		/**
		 * For POC, let's fetch the very first item
		 */

		$db    = new WPIMDB();
		$table = $db->inventory_table;
		$query = "SELECT inventory_id FROM " . $table . " LIMIT 1";
		$id    = $db->get_results( $query );
		$id    = (int) $id[0]->inventory_id;

		$item = new WPIMItem();
		$item = $item->get( $id );

		$data = '<p><strong>ID: </strong> ' . $item->inventory_id . '</p>';
		$data .= '<p><strong>Number: </strong>' . $item->inventory_number . '</p>';
		$data .= '<p><strong>Name: </strong>' . $item->inventory_name . '</p>';
		$data .= '<div><strong>Description</strong><br>' . $item->inventory_description . '</div>';
		$data .= '<p><strong>Size: </strong>' . $item->inventory_size . '</p>';
		$data .= '<p><strong>Manufacturer: </strong>' . $item->inventory_manufacturer . '</p>';
		$data .= '<p><strong>Make: </strong>' . $item->inventory_make . '</p>';
		$data .= '<p><strong>Model: </strong>' . $item->inventory_model . '</p>';
		$data .= '<p><strong>Year: </strong>' . $item->inventory_year . '</p>';
		$data .= '<p><strong>Serial: </strong>' . $item->inventory_serial . '</p>';
		$data .= '<p><strong>FOB: </strong>' . $item->inventory_fob . '</p>';
		$data .= '<p><strong>Quantity: </strong>' . $item->inventory_quantity . '</p>';
		$data .= '<p><strong>Quantity Reserved: </strong>' . $item->inventory_quantity_reserved . '</p>';
		$data .= '<p><strong>Pice: </strong>' . $item->inventory_price . '</p>';
		$data .= '<p><strong>Category ID: </strong>' . $item->category_id . '</p>';


		$qrCode = new QrCode( $data );
		$qrCode->setSize( 300 );
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
		$qrCode->writeFile( __DIR__ . '/qrcode.png' );

// Generate a data URI to include image data inline (i.e. inside an <img> tag)
		$dataUri = $qrCode->writeDataUri();

		echo '<img src="' . $dataUri . '">';
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
}

WPInventoryQRCodeInit::initialize();
