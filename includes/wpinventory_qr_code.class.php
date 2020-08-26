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
use Endroid\QrCode\LabelAlignment; // Not in use currently - may just remove it before production
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
			'admin_init'               => NULL,
			'wpim_admin_menu'          => NULL,
			'wpim_default_config'      => NULL,
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
		if ( ! load_plugin_textdomain( self::LANG, FALSE, '/wp-content/languages/' ) ) {
			$plugin_dir = trim( plugin_dir_path( __FILE__ ) );
			$plugin_dir = basename( str_ireplace( '/includes/', '', $plugin_dir ) );
			load_plugin_textdomain( self::LANG, FALSE, $plugin_dir . "/languages/" );
		}
	}

	public static function admin_init() {
		if ( ! wp_verify_nonce( self::request( "nonce" ), self::NONCE_ACTION ) ) {
			self::$error = self::__( 'Security failure.  Please try again.' );
		}

		if ( (int) self::request( 'qr_code_lookup_id' ) ) {
			wp_redirect( admin_url( 'admin.php?page=manage_qr_codes&inventory_id=' . self::request( 'qr_code_lookup_id' ) ) );
			die();
		}

		if ( ! empty( self::request( 'qr_code_lookup_ids' ) ) ) {
			$ids = explode( ',', self::request( 'qr_code_lookup_ids' ) );

			$url = '';

			foreach ( $ids as $key => $value ) {
				$url .= '&inventory_id[]=' . $value;
			}

			wp_redirect( admin_url( 'admin.php?page=manage_qr_codes' . $url ) );
			die();
		}

		if ( self::request( 'qr_code_start_range' ) < self::request( 'qr_code_end_range' ) ) {
			wp_redirect( admin_url( 'admin.php?page=manage_qr_codes&starting_id=' . self::request( 'qr_code_start_range' ) . '&ending_id=' . self::request( 'qr_code_end_range' ) ) );
			die();
		}

	}

	/**
	 * WordPress admin_menu action callback function
	 */
	public static function wpim_admin_menu() {
		/**
		 * Go over permissions with Cale.  I don't think they are working per setting: Ledger for example
		 */
		$show = TRUE;

		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
			$show = FALSE;
		}

		if ( $show ) {
			self::add_submenu( 'QR Codes', self::$config->get( 'permission_lowest_role_qr_manage' ) );
		}
	}

	/**
	 * Utility function to simplify adding submenus
	 *
	 * @param        $title
	 * @param string $role
	 */
	private static function add_submenu( $title, $role = 'manage_options' ) {

		/**
		 * I will end up licensing this for $24.99
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
		// By default, an admin should setup everything - right?
		$default['permission_lowest_role_qr_manage'] = 'manage_options';

		return $default;
	}


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
        <h3 data-tab="qr_code_management_settings"><?php self::_e( 'QR Code Settings' ); ?></h3>
        <table class='form-table'>
            <tr>
				<?php
				/**
				 * Cale, when to use self::_e() and self::__()
				 */
				?>
                <th><?php self::_e( 'Minimum Role to Use QR Codes' ); ?></th>
                <td><?php echo $role_dropdown; ?></td>
            </tr>
        </table>
	<?php }

	/**
	 * @param $data
	 *
	 * @return mixed
	 */
	public static function wpim_save_settings( $data ) {
		/**
		 * Talk to Cale, but it seems to me $data is always empty?  And, does it need to be returned as an action?
		 */
		self::$config->set( 'permission_lowest_role_qr_manage', self::request( 'permission_lowest_role_qr_manage' ) );
		return $data;
	}

	/**
	 * @param $item
	 * @param $inventory_id
	 */
	public static function wpim_admin_edit_form_end( $item, $inventory_id ) {
		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
			echo '<p class="description">' . self::__( 'You do not have permission to bulk manage items.' ) . '</p>';
			return;
		}
		$data = self::get_qr_code_data( $inventory_id );
		echo '<tr><th>' . self::__( 'QR Code' ) . '</th><td>' . self::get_qr_code( $data ) . '<p><a href="' . admin_url( 'admin.php?page=manage_qr_codes&inventory_id=' . $inventory_id ) . '">' . self::__( 'Print Code' ) . '</a></p></td></tr>';
	}

	/**
	 * Admin QR Code interface
	 */
	public static function admin_qr_codes() {
		if ( ! current_user_can( self::$config->get( 'permission_lowest_role_qr_manage' ) ) ) {
			return '<p class="description">' . self::__( 'You do not have permission to bulk manage items.' ) . '</p>';
		}

		if ( 'manage_qr_codes' == self::request( 'page' ) ) {
			return self::print_qr_codes_page();
		}
	}

	public static function get_qr_code_data( $inventory_id ) {
		$admin = new WPIMAdmin();
		/**
		 * TODO:  If AIM is installed, this has to be per type
		 */
		$display = $admin::getDisplay( 'detail' );

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
	 * Filter for add-ons to indicate that qr code is installed
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

	public static function get_qr_code( $data, $width = 300, $margin = 10 ) {

		if ( $data == NULL ) {
			return self::__( 'No data was supplied to render the QR code.' );
		}

		$qrCode = new QrCode( $data );
		$qrCode->setSize( $width );
		$qrCode->setMargin( $margin );

// Set advanced options
		$qrCode->setWriterByName( 'png' );
		$qrCode->setEncoding( 'UTF-8' );
		$qrCode->setErrorCorrectionLevel( ErrorCorrectionLevel::LOW() );
		$qrCode->setForegroundColor( [ 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0 ] );
		$qrCode->setBackgroundColor( [ 'r' => 255, 'g' => 255, 'b' => 255, 'a' => 0 ] );
		/**
		 * Unless this label can be something meaningful I really don't see the value.  They can just scan the code real fast if they are uncertain which item it came from.
		 */
//		$qrCode->setLabel( 'Scan the code', 16, QRCODE_PLUGIN_PATH . 'vendor/qrcode/vendor/endroid/qr-code/assets/fonts/noto_sans.otf', LabelAlignment::CENTER() );
		/**
		 * What in the world would anyone want to put their logo over the code so it could not be read?
		 */
//		$qrCode->setLogoPath( QRCODE_PLUGIN_PATH . 'vendor/qrcode/vendor/endroid/qr-code/assets/images/symfony.png' );
//		$qrCode->setLogoSize( 150, 200 );
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

	public static function single_print_job_form() {
		echo '<form action="" method="post">';
		echo '<p><input type="number" name="qr_code_lookup_id" value="1"></p>';
		wp_nonce_field( self::NONCE_ACTION, 'none' );
		submit_button( self::__( 'Submit' ), 'primary', 'qr_code_lookup_submit' );
		echo '</form>';
		return;
	}

	public static function multi_print_job_form() {
		echo '<form action="" method="post">';
		echo '<p><input type="text" name="qr_code_lookup_ids" value="" placeholder="1,2,3"><br><small class="description">' . self::__( 'Separate as many item IDs by comma (,): Example: 1,2,3,4' ) . '</small></p>';
		wp_nonce_field( self::NONCE_ACTION, 'none' );
		submit_button( self::__( 'Submit' ), 'primary', 'qr_code_lookup_ids_submit' );
		echo '</form>';
	}

	public static function range_print_job_form() {
		echo '<h2>' . self::__( 'Enter a range of item ID #s' ) . '</h2>';
		echo '<form action="" method="post" class="qr_code_id_range_form">';
		echo '<p><label>' . self::__( 'Start' ) . ' <input type="number" name="qr_code_start_range" value="1"></label><br>
        <label>' . self::__( 'End' ) . ' <input type="number" name="qr_code_end_range" value="5"></label><br>
        </p>';
		wp_nonce_field( self::NONCE_ACTION, 'nonce' );
		submit_button( self::__( 'Submit' ), 'primary', 'qr_code_lookup_range_submit' );
		echo '</form>';
		echo '<script>';

		echo '
		
		jQuery(function($) {
            $(".qr_code_id_range_form").change(function() {
                var start = $(\'input[name="qr_code_start_range"]\').val();
                var end = $(\'input[name="qr_code_end_range"]\').val();
                
                if (start <= 0) {
                    $(\'input[name="qr_code_start_range"]\').val(1);
                }
                
                if (start >= end) {
                    $(\'input[name="qr_code_end_range"]\').val(parseFloat(start) + 1);
                }
                
            });
		});
		';

		echo '</script>';
		return;
	}

	public static function print_qr_codes_page() {

		if ( isset( $_POST['print_qr_codes_type_submit'] ) ) {
			$option = self::request( 'qr_print_job_type' );

			if ( 'single' == $option ) {
				echo self::single_print_job_form();
				return;
			}

			if ( 'multi' == $option ) {
				echo self::multi_print_job_form();
				return;
			}

			if ( 'range' == $option ) {
				echo self::range_print_job_form();
				return;
			}

		}


		if ( isset( $_POST['print_qr_codes_submit'] ) ) {
			$quantity = ( (int) self::request( 'print_qr_code_quantity' ) ) ? self::request( 'print_qr_code_quantity' ) : 0;
			if ( ! (int) $quantity ) {
				echo '<p>' . self::__( 'You must provide a valid quantity to print' ) . '</p>';
				return;
			}

			echo '<h2>' . self::__( 'QR Codes' ) . '</h2>';

			// Long lists of like a few hundred or more it would be annoying to scroll all the way to the bottom so we put one up top as well
			echo '<p><a class="button-primary qr_code_print_window" href="javascript:void(0)">' . self::__( 'Print' ) . '</a> <a class="button-secondary page_reload" href="javascript:void(0)">' . self::__( 'Go Back' ) . '</a> </p>';

			$inventory_id = self::request( 'inventory_id' );

			if ( ! $inventory_id ) {
				if ( (int) self::request( 'starting_id' ) && self::request( 'starting_id' ) > 0 ) {
					$id = (int) self::request( 'starting_id' );
					/**
					 * If PHP is in safe mode this will NOT work.  Maybe use ini_set('max_execution_time', 0) ?
					 */
					set_time_limit( 0 );

					while ( $id <= (int) self::request( 'ending_id' ) ) {
						$data    = self::get_qr_code_data( $id );
						$qr_code = self::get_qr_code( $data, self::request( 'qr_code_width' ), self::request( 'qr_code_margin' ) );
						echo '<h2>' . self::__( 'Inventory ID#:' ) . ' ' . $id . '</h2>';
						$qr_count = 1;
						while ( $qr_count <= $quantity ) {
							echo $qr_code;
							sleep( .2 );
							$qr_count ++;
						}
						$id ++;
						sleep( .2 );
					}
				} else {
					echo '<p>' . self::__( 'There must be either an inventory id(s) or a range of ids.  An error has occurred.' ) . '</p>';
					return;
				}
			} else {
				if ( ! empty( $inventory_id ) ) {
					if ( ! is_array( $inventory_id ) ) {
						$inventory_id = (array) $inventory_id;
					}

					foreach ( $inventory_id as $id ) {
						$data    = self::get_qr_code_data( $id );
						$qr_code = self::get_qr_code( $data, self::request( 'qr_code_width' ), self::request( 'qr_code_margin' ) );
						echo '<h2>' . self::__( 'Inventory ID#:' ) . ' ' . $id . '</h2>';
						$count = 1;
						set_time_limit( 0 );
						while ( $count <= $quantity ) {
							echo $qr_code;
							$count ++;
							sleep( .2 );
						}
					}
				}
			}

			echo '<p><a class="button-primary qr_code_print_window" href="javascript:void(0)">' . self::__( 'Print' ) . '</a></p>';

			echo '<script>';
			echo 'jQuery(function($) {
			
			$(".qr_code_print_window").on("click", function() {
			window.print();
			});
			
			$(".page_reload").on("click", function() {
			location.reload();
			});
			
			});';

			echo '</script>';

			echo '<style>';

			echo '
			
			@media print {
			
			    #adminmenumain,
			    #wpadminbar,
			    .notice,
			    .button-primary.qr_code_print_window {
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

		if ( empty( self::request( 'inventory_id' ) ) && ( ! self::request( 'starting_id' ) || ! self::request( 'ending_id' ) ) ) {
			echo '<h2>' . self::__( 'How will you be running your print job?' ) . '</h2>';
			echo '<fieldset>';
			echo '<label><input type="radio" name="qr_print_job_type" value="single" checked> ' . self::__( 'Single Item' ) . '</label><br>';
			echo '<label><input type="radio" name="qr_print_job_type" value="multi"> ' . self::__( 'Multi Item' ) . '</label><br>';
			echo '<label><input type="radio" name="qr_print_job_type" value="range"> ' . self::__( 'Range of Items' ) . '</label>';
			echo '</fieldset>';
			wp_nonce_field( self::NONCE_ACTION, 'nonce' );
			submit_button( self::__( 'Select' ), 'primary', 'print_qr_codes_type_submit' );
			echo '</form>';
			return;
		}

		echo '<h2>' . self::__( 'How many would you like to print?' ) . '</h2>';
		echo '<p><input class="print_qr_code_quantity" name="print_qr_code_quantity" type="number" value="25"></p>';
		echo '<h2>' . self::__( 'Width of the QR Code' ) . '</h2>';
		echo '<p><input class="qr_code_width" type="number" name="qr_code_width" value="300"><br><small class="description">' . self::__( 'In pixels (px)' ) . '</small></p>';
		echo '<h2>Margin</h2>';
		echo '<p><input type="number" name="qr_code_margin" value="10"><br><small class="description">' . self::__( 'Note: Use 1/2 of what you actually need because the codes display inline which effectively doubles the desired amount.' ) . '</small></p>';

		/**
		 * TODO: Add two filter options: "category" (and if AIM is installed) "type"
		 */
		wp_nonce_field( self::NONCE_ACTION, 'nonce' );
		submit_button( self::__( 'Print' ), 'primary', 'print_qr_codes_submit' );

		echo '</form>';
	}
}

WPInventoryQRCodeInit::initialize();
