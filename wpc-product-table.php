<?php
/**
 * Plugin Name: WPC Product Table for WooCommerce
 * Plugin URI: https://wpclever.net/
 * Description: WPC Product Table helps you show selected products as a table on the page.
 * Version: 3.0.4
 * Author: WPClever
 * Author URI: https://wpclever.net
 * Text Domain: wpc-product-table
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * Requires at least: 4.0
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 9.3
 */

defined( 'ABSPATH' ) || exit;

! defined( 'WPCPT_VERSION' ) && define( 'WPCPT_VERSION', '3.0.4' );
! defined( 'WPCPT_LITE' ) && define( 'WPCPT_LITE', __FILE__ );
! defined( 'WPCPT_FILE' ) && define( 'WPCPT_FILE', __FILE__ );
! defined( 'WPCPT_URI' ) && define( 'WPCPT_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCPT_DIR' ) && define( 'WPCPT_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCPT_SUPPORT' ) && define( 'WPCPT_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=wpcpt&utm_campaign=wporg' );
! defined( 'WPCPT_REVIEWS' ) && define( 'WPCPT_REVIEWS', 'https://wordpress.org/support/plugin/wpc-product-table/reviews/?filter=5' );
! defined( 'WPCPT_CHANGELOG' ) && define( 'WPCPT_CHANGELOG', 'https://wordpress.org/plugins/wpc-product-table/#developers' );
! defined( 'WPCPT_DISCUSSION' ) && define( 'WPCPT_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-product-table' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCPT_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcpt_init' ) ) {
	add_action( 'plugins_loaded', 'wpcpt_init', 11 );

	function wpcpt_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-product-table', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcpt_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcpt' ) ) {
			class WPCleverWpcpt {
				protected static $columns = [];
				protected static $settings = [];
				protected static $localization = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcpt_settings', [] );

					add_action( 'init', [ $this, 'init' ] );
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );
					add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
					add_action( 'save_post_wpc_product_table', [ $this, 'save_product_table' ] );
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

					// action links
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// ajax
					add_action( 'wp_ajax_wpcpt_add_column', [ $this, 'ajax_add_column' ] );
					add_action( 'wp_ajax_wpcpt_search_term', [ $this, 'ajax_search_term' ] );

					// update product
					add_action( 'save_post', [ $this, 'save_post' ], 10, 2 );

					// table columns
					add_filter( 'manage_edit-wpc_product_table_columns', [ $this, 'table_columns' ] );
					add_action( 'manage_wpc_product_table_posts_custom_column', [
						$this,
						'table_custom_column'
					], 10, 2 );

					self::$columns = [
						'order'             => esc_html__( 'Order', 'wpc-product-table' ),
						'thumbnail'         => esc_html__( 'Thumbnail', 'wpc-product-table' ),
						'name'              => esc_html__( 'Name', 'wpc-product-table' ),
						'name_price'        => esc_html__( 'Name & Price', 'wpc-product-table' ),
						'price'             => esc_html__( 'Price', 'wpc-product-table' ),
						'price_html'        => esc_html__( 'Price HTML', 'wpc-product-table' ),
						'sku'               => esc_html__( 'SKU', 'wpc-product-table' ),
						'review'            => esc_html__( 'Review', 'wpc-product-table' ),
						'short_description' => esc_html__( 'Short Description', 'wpc-product-table' ),
						'add_to_cart'       => esc_html__( 'Add To Cart Button', 'wpc-product-table' ),
						'add_to_cart_form'  => esc_html__( 'Add To Cart Form', 'wpc-product-table' ),
						'availability'      => esc_html__( 'Availability', 'wpc-product-table' ),
						'stock'             => esc_html__( 'Stock', 'wpc-product-table' ),
						'compare'           => esc_html__( 'Compare', 'wpc-product-table' ),
						'quickview'         => esc_html__( 'Quick View', 'wpc-product-table' ),
						'wishlist'          => esc_html__( 'Wishlist', 'wpc-product-table' ),
					];

					self::$localization = [
						'decimal'        => '',
						'emptyTable'     => esc_html__( 'No data available in table', 'wpc-product-table' ),
						'info'           => esc_html__( 'Showing _START_ to _END_ of _TOTAL_ entries', 'wpc-product-table' ),
						'infoEmpty'      => esc_html__( 'Showing 0 to 0 of 0 entries', 'wpc-product-table' ),
						'infoFiltered'   => esc_html__( '(filtered from _MAX_ total entries)', 'wpc-product-table' ),
						'infoPostFix'    => '',
						'thousands'      => esc_html__( ',', 'wpc-product-table' ),
						'lengthMenu'     => esc_html__( 'Show _MENU_ entries', 'wpc-product-table' ),
						'loadingRecords' => esc_html__( 'Loading...', 'wpc-product-table' ),
						'processing'     => esc_html__( 'Processing...', 'wpc-product-table' ),
						'search'         => esc_html__( 'Search:', 'wpc-product-table' ),
						'zeroRecords'    => esc_html__( 'No matching records found', 'wpc-product-table' ),
						'paginate'       => [
							'first'    => esc_html__( 'First', 'wpc-product-table' ),
							'last'     => esc_html__( 'Last', 'wpc-product-table' ),
							'next'     => esc_html__( 'Next', 'wpc-product-table' ),
							'previous' => esc_html__( 'Previous', 'wpc-product-table' )
						],
						'aria'           => [
							'sortAscending'  => esc_html__( ': activate to sort column ascending', 'wpc-product-table' ),
							'sortDescending' => esc_html__( ': activate to sort column descending', 'wpc-product-table' )
						]
					];
				}

				function init() {
					$labels = [
						'name'          => _x( 'Product Tables', 'Post Type General Name', 'wpc-product-table' ),
						'singular_name' => _x( 'Product Table', 'Post Type Singular Name', 'wpc-product-table' ),
						'add_new_item'  => esc_html__( 'Add New Product Table', 'wpc-product-table' ),
						'add_new'       => esc_html__( 'Add New', 'wpc-product-table' ),
						'edit_item'     => esc_html__( 'Edit Product Table', 'wpc-product-table' ),
						'update_item'   => esc_html__( 'Update Product Table', 'wpc-product-table' ),
						'search_items'  => esc_html__( 'Search Product Table', 'wpc-product-table' ),
					];

					$args = [
						'label'               => esc_html__( 'Product Table', 'wpc-product-table' ),
						'labels'              => $labels,
						'supports'            => [ 'title', 'excerpt' ],
						'hierarchical'        => false,
						'public'              => false,
						'show_ui'             => true,
						'show_in_menu'        => true,
						'show_in_nav_menus'   => true,
						'show_in_admin_bar'   => true,
						'menu_position'       => 28,
						'menu_icon'           => 'dashicons-list-view',
						'can_export'          => true,
						'has_archive'         => false,
						'exclude_from_search' => true,
						'publicly_queryable'  => false,
						'capability_type'     => 'post',
						'show_in_rest'        => false,
					];

					register_post_type( 'wpc_product_table', $args );

					// shortcode
					add_shortcode( 'wpcpt', [ $this, 'shortcode' ] );
					add_shortcode( 'wpc_product_table', [ $this, 'shortcode' ] );
				}

				public static function get_settings() {
					return apply_filters( 'wpcpt_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcpt_' . $name, $default );
					}

					return apply_filters( 'wpcpt_get_setting', $setting, $name, $default );
				}

				function register_settings() {
					// settings
					register_setting( 'wpcpt_settings', 'wpcpt_settings' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Product Table', 'wpc-product-table' ), esc_html__( 'Product Table', 'wpc-product-table' ), 'manage_options', 'wpclever-wpcpt', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					add_thickbox();
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Table', 'wpc-product-table' ) . ' ' . esc_html( WPCPT_VERSION ) . ' ' . ( defined( 'WPCPT_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-product-table' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-product-table' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCPT_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-product-table' ); ?></a> |
                                <a href="<?php echo esc_url( WPCPT_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-product-table' ); ?></a> |
                                <a href="<?php echo esc_url( WPCPT_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-product-table' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-product-table' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=how' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'how' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'How to use?', 'wpc-product-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-product-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpc_product_table' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Product Tables', 'wpc-product-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-product-table' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-product-table' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'how' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
										<?php esc_html_e( 'Please go to WP-admin >> Product Tables to create a table then insert the shortcode where you want to show this table.', 'wpc-product-table' ); ?>
                                    </p>
                                </div>
							<?php } elseif ( $active_tab === 'settings' ) {
								$link_image = self::get_setting( 'link_image', 'yes' );
								$link_name  = self::get_setting( 'link_name', 'yes' );
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr>
                                            <th><?php esc_html_e( 'Link on the product image', 'wpc-product-table' ); ?></th>
                                            <td>
                                                <label> <select name="wpcpt_settings[link_image]">
                                                        <option value="yes" <?php selected( $link_image, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'wpc-product-table' ); ?></option>
                                                        <option value="yes_blank" <?php selected( $link_image, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'wpc-product-table' ); ?></option>
                                                        <option value="yes_popup" <?php selected( $link_image, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-product-table' ); ?></option>
                                                        <option value="no" <?php selected( $link_image, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-table' ); ?></option>
                                                    </select> </label>
                                                <p class="description">If you choose "Open quick view popup", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link on the product name', 'wpc-product-table' ); ?></th>
                                            <td>
                                                <label> <select name="wpcpt_settings[link_name]">
                                                        <option value="yes" <?php selected( $link_name, 'yes' ); ?>><?php esc_html_e( 'Yes, open in the same tab', 'wpc-product-table' ); ?></option>
                                                        <option value="yes_blank" <?php selected( $link_name, 'yes_blank' ); ?>><?php esc_html_e( 'Yes, open in the new tab', 'wpc-product-table' ); ?></option>
                                                        <option value="yes_popup" <?php selected( $link_name, 'yes_popup' ); ?>><?php esc_html_e( 'Yes, open quick view popup', 'wpc-product-table' ); ?></option>
                                                        <option value="no" <?php selected( $link_name, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-table' ); ?></option>
                                                    </select> </label>
                                                <p class="description">If you choose "Open quick view popup", please install
                                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>" class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.
                                                </p>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcpt_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
                                        Get the Premium Version just $29!
                                        <a href="https://wpclever.net/downloads/product-table?utm_source=pro&utm_medium=wpcpt&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/product-table</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version:</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Add columns with custom text/shortcode, custom field, or custom attribute.</li>
                                        <li>- Get the lifetime update & premium support.</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$how                  = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=how' ) ) . '">' . esc_html__( 'How to use?', 'wpc-product-table' ) . '</a>';
						$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-product-table' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcpt&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-product-table' ) . '</a>';
						array_unshift( $links, $how, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCPT_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-product-table' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function get_table_data( $id ) {
					$data                  = [ 'id' => $id ];
					$data['type']          = get_post_meta( $id, 'type', true ) ?: 'products';
					$data['categories']    = get_post_meta( $id, 'categories', true ) ?: '';
					$data['tags']          = get_post_meta( $id, 'tags', true ) ?: '';
					$data['terms']         = get_post_meta( $id, 'terms', true ) ?: '';
					$data['include']       = get_post_meta( $id, 'include', true ) ?: '';
					$data['exclude']       = get_post_meta( $id, 'exclude', true ) ?: '';
					$data['limit']         = get_post_meta( $id, 'limit', true ) ?: 50;
					$data['orderby']       = get_post_meta( $id, 'orderby', true ) ?: 'default';
					$data['order']         = get_post_meta( $id, 'order', true ) ?: 'default';
					$data['style']         = get_post_meta( $id, 'style', true ) ?: 'default';
					$data['page_length']   = get_post_meta( $id, 'page_length', true ) ?: 10;
					$data['extra_classes'] = get_post_meta( $id, 'extra_classes', true ) ?: '';
					$data['columns']       = get_post_meta( $id, 'columns', true ) ?: '';
					$data['localization']  = get_post_meta( $id, 'localization', true ) ?: [];

					return apply_filters( 'wpcpt_get_table_data', $data, $id );
				}

				function get_table_products( $data ) {
					$args = [
						'is_wpcpt' => true,
						'limit'    => (int) $data['limit']
					];

					if ( ! empty( $data['orderby'] ) ) {
						$args['orderby'] = esc_attr( $data['orderby'] );
					}

					if ( ! empty( $data['order'] ) ) {
						$args['order'] = esc_attr( $data['order'] );
					}

					$cats    = explode( ',', $data['categories'] );
					$tags    = array_map( 'trim', explode( ',', $data['tags'] ) );
					$terms   = array_map( 'trim', explode( ',', $data['terms'] ) );
					$include = explode( ',', $data['include'] );
					$exclude = explode( ',', $data['exclude'] );

					switch ( $data['type'] ) {
						case 'best_selling':
							$args['meta_key'] = 'total_sales';
							$args['orderby']  = 'meta_value_num';
							$args['order']    = 'desc';

							break;
						case 'on_sale':
							$args['include'] = array_unique( array_merge( [ 0 ], wc_get_product_ids_on_sale() ) );

							break;
						case 'products':
							$args['type']    = array_merge( array_keys( wc_get_product_types() ), [ 'variation' ] );
							$args['include'] = array_unique( array_merge( [ 0 ], $include ) );
							$args['limit']   = - 1;

							if ( $args['orderby'] === 'default' ) {
								$args['orderby'] = 'include';
							}

							break;
						case 'categories_tags':
							if ( ! empty( $data['categories'] ) && ( $data['categories'] !== 'null' ) && ! empty( $cats ) ) {
								$args['category'] = array_unique( $cats );
							}

							if ( ! empty( $data['tags'] ) && ! empty( $tags ) ) {
								$args['tag'] = array_unique( $tags );
							}

							if ( ! empty( $data['exclude'] ) && ! empty( $exclude ) ) {
								$args['exclude'] = array_unique( $exclude );
							}

							break;
						default:
							// terms
							$args['tax_query'] = [
								[
									'taxonomy' => $data['type'],
									'field'    => 'slug',
									'terms'    => $terms,
									'operator' => 'IN',
								]
							];
					}

					return apply_filters( 'wpcpt_get_table_products', wc_get_products( $args ), $data );
				}

				function shortcode( $attrs ) {
					$output = '';
					$attrs  = shortcode_atts( [ 'id' => 0, 'name' => '' ], $attrs, 'wpc_product_table' );

					if ( $attrs['id'] ) {
						$data       = self::get_table_data( $attrs['id'] );
						$_products  = self::get_table_products( $data );
						$image_size = apply_filters( 'wpcpt_image_size', 'woocommerce_thumbnail', $attrs );

						if ( $_products && ( count( $_products ) > 0 ) && is_array( $data['columns'] ) && ( count( $data['columns'] ) > 0 ) ) {
							foreach ( self::$localization as $key => $val ) {
								if ( is_array( $val ) ) {
									foreach ( $val as $k => $v ) {
										if ( empty( $data['localization'][ $key ][ $k ] ) ) {
											$data['localization'][ $key ][ $k ] = $v;
										}
									}
								} else {
									if ( empty( $data['localization'][ $key ] ) ) {
										$data['localization'][ $key ] = $val;
									}
								}
							}

							$table_classes = 'display wpcpt_table wpc_product_table wpc_product_table_style_' . ( ! empty( $data['style'] ) ? $data['style'] : 'default' ) . ( ! empty( $data['extra_classes'] ) ? ' ' . $data['extra_classes'] : '' );

							ob_start();
							?>
                            <div class="wpcpt_wrapper wpc_product_table_wrapper wpc_product_table_wrapper_<?php echo esc_attr( $attrs['id'] ); ?> wpc_product_table_wrapper_style_<?php echo esc_attr( ! empty( $data['style'] ) ? $data['style'] : 'default' ); ?>">
                                <table class="<?php echo esc_attr( $table_classes ); ?>" data-language="<?php echo esc_attr( htmlspecialchars( json_encode( $data['localization'] ), ENT_QUOTES, 'UTF-8' ) ); ?>" data-page-length="<?php echo esc_attr( $data['page_length'] ); ?>">
                                    <thead>
                                    <tr class="wpcpt_tr wpc_product_table_tr">
										<?php
										foreach ( $data['columns'] as $column ) {
											$th_class = 'wpcpt_th wpc_product_table_th wpc_product_table_th_' . esc_attr( $column['type'] );
											echo '<th class="' . $th_class . '" data-orderable="' . esc_attr( $column['orderable'] ?? 'false' ) . '" data-searchable="' . esc_attr( $column['searchable'] ?? 'true' ) . '">' . esc_html( $column['title'] ) . '</th>';
										}
										?>
                                    </tr>
                                    </thead>
                                    <tbody>
									<?php
									$order = 1;

									global $post;

									foreach ( $_products as $_product ) {
										if ( ! $_product || ! $_product->is_visible() ) {
											continue;
										}

										$_product_id = $_product->get_id();

										$post = get_post( $_product_id );
										setup_postdata( $post );

										$link_image = self::get_setting( 'link_image', 'yes' );
										$link_name  = self::get_setting( 'link_name', 'yes' );
										?>
                                        <tr class="wpcpt_tr wpc_product_table_tr wpc_product_table_tr_<?php echo esc_attr( $_product_id ); ?>">
											<?php
											foreach ( $data['columns'] as $column ) {
												$td_class = 'wpcpt_td wpc_product_table_td wpc_product_table_td_' . esc_attr( $column['type'] ) . ' wpc_product_table_td_align_' . esc_attr( $column['align'] ?? 'left' ) . ' wpc_product_table_td_valign_' . esc_attr( $column['valign'] ?? 'top' );

												switch ( $column['type'] ) {
													case 'order':
														$td_content = '<td class="' . $td_class . '">' . $order . '</td>';

														break;
													case 'thumbnail':
														if ( $link_image !== 'no' ) {
															$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '"><a href="' . $_product->get_permalink() . '" ' . ( $link_image === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . $_product_id . '"' : '' ) . ' ' . ( $link_image === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $_product->get_image( $image_size ) . '</a></td>';
														} else {
															$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">' . $_product->get_image( $image_size ) . '</td>';
														}

														break;
													case 'name':
														if ( $link_name !== 'no' ) {
															$td_content = '<td class="' . $td_class . '"><a href="' . $_product->get_permalink() . '" ' . ( $link_image === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . $_product_id . '"' : '' ) . ' ' . ( $link_image === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $_product->get_name() . '</a></td>';
														} else {
															$td_content = '<td class="' . $td_class . '">' . $_product->get_name() . '</td>';
														}

														break;
													case 'name_price':
														if ( $link_name !== 'no' ) {
															$td_content = '<td class="' . $td_class . '"><div class="wpcpt_product_name""><a href="' . $_product->get_permalink() . '" ' . ( $link_image === 'yes_popup' ? 'class="woosq-link no-ajaxy" data-id="' . $_product_id . '"' : '' ) . ' ' . ( $link_image === 'yes_blank' ? 'target="_blank"' : '' ) . '>' . $_product->get_name() . '</a></div><div class="wpcpt_product_price">' . $_product->get_price_html() . '</div></td>';
														} else {
															$td_content = '<td class="' . $td_class . '"><div class="wpcpt_product_name"">' . $_product->get_name() . '</div><div class="wpcpt_product_price">' . $_product->get_price_html() . '</div></td>';
														}

														break;
													case 'price':
														$td_content = '<td class="' . $td_class . '">' . $_product->get_price() . '</td>';

														break;
													case 'price_html':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product->get_price() ) . '">' . $_product->get_price_html() . '</td>';

														break;
													case 'sku':
														$td_content = '<td class="' . $td_class . '">' . $_product->get_sku() . '</td>';

														break;
													case 'add_to_cart':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">' . do_shortcode( '[add_to_cart style="" show_price="false" id="' . $_product_id . '"]' ) . '</td>';

														break;
													case 'add_to_cart_form':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">';

														ob_start();
														woocommerce_template_single_add_to_cart();
														$td_content .= ob_get_clean();

														$td_content .= '</td>';

														break;
													case 'short_description':
														$td_content = '<td class="' . $td_class . '">' . $_product->get_short_description() . '</td>';

														break;
													case 'stock':
														$td_content = '<td class="' . $td_class . '">' . wc_get_stock_html( $_product ) . '</td>';

														break;
													case 'availability':
														$availability = $_product->get_availability();
														$td_content   = '<td class="' . $td_class . '">' . $availability['availability'] . '</td>';

														break;
													case 'review':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product->get_average_rating() ) . '">' . wc_get_rating_html( $_product->get_average_rating() ) . '</td>';

														break;
													case 'compare':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">' . do_shortcode( '[woosc id="' . $_product_id . '"]' ) . '</td>';

														break;
													case 'quickview':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">' . do_shortcode( '[woosq id="' . $_product_id . '"]' ) . '</td>';

														break;
													case 'wishlist':
														$td_content = '<td class="' . $td_class . '" data-sort="' . esc_attr( $_product_id ) . '">' . do_shortcode( '[woosw id="' . $_product_id . '"]' ) . '</td>';

														break;
													default:
														if ( str_starts_with( $column['type'], 'pa_' ) ) {
															$td_content        = '<td class="' . $td_class . '">';
															$product_attribute = $_product->get_attribute( $column['type'] );

															if ( empty( $product_attribute ) && $_product->is_type( 'variation' ) ) {
																$parent_product    = wc_get_product( $_product->get_parent_id() );
																$product_attribute = $parent_product->get_attribute( $column['type'] );
															}

															if ( ! empty( $product_attribute ) ) {
																$td_content .= esc_html( $product_attribute );
															}

															$td_content .= '</td>';
														} else {
															$td_content = '<td class="' . $td_class . '">&nbsp;</td>';
														}
												}

												echo apply_filters( 'wpcpt_' . $column['type'] . '_content', $td_content, $column, $_product, $order );
											}
											?>
                                        </tr>
										<?php
										$order ++;
									}

									wp_reset_postdata();
									?>
                                    </tbody>
                                    <tfoot>
                                    <tr class="wpcpt_tr wpc_product_table_tr">
										<?php
										foreach ( $data['columns'] as $column ) {
											$th_class = 'wpcpt_th wpc_product_table_th wpc_product_table_th_' . esc_attr( $column['type'] );
											echo '<th class="' . $th_class . '">' . esc_html( $column['title'] ) . '</th>';
										}
										?>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
							<?php
							$output = ob_get_clean();
						}
					}

					return apply_filters( 'wpc_product_table', $output, $attrs['id'] );
				}

				function add_meta_boxes() {
					add_meta_box( 'wpcpt_shortcode', esc_html__( 'Shortcode', 'wpc-product-table' ), [
						$this,
						'shortcode_callback'
					], 'wpc_product_table', 'advanced', 'high' );
					add_meta_box( 'wpcpt_configuration', esc_html__( 'Configuration', 'wpc-product-table' ), [
						$this,
						'configuration_callback'
					], 'wpc_product_table', 'advanced', 'low' );
				}

				function shortcode_callback( $post ) {
					echo '<div class="wpcpt_shortcode_txt"><input type="text" class="wpcpt_shortcode_input" data-id="' . $post->ID . '" readonly value="[wpc_product_table id=&quot;' . $post->ID . '&quot; name=&quot;' . esc_attr( $post->post_title ) . '&quot;]"/></div>';
					echo '<div class="wpcpt_shortcode_des">' . esc_html__( 'Place above shortcode where you want to show this product table.', 'wpc-product-table' ) . '</div>';
				}

				function configuration_callback( $post ) {
					$post_id = $post->ID;
					$data    = self::get_table_data( $post_id );

					wp_enqueue_editor();
					?>
                    <h2 class="nav-tab-wrapper">
                        <a href="#" class="nav-tab nav-tab-active wpcpt_configuration_nav" data-tab="source">
							<?php esc_html_e( 'Source', 'wpc-product-table' ); ?>
                        </a> <a href="#" class="nav-tab wpcpt_configuration_nav" data-tab="design">
							<?php esc_html_e( 'Design', 'wpc-product-table' ); ?>
                        </a> <a href="#" class="nav-tab wpcpt_configuration_nav" data-tab="localization">
							<?php esc_html_e( 'Localization', 'wpc-product-table' ); ?>
                        </a>
                    </h2>
                    <div class="wpcpt_configuration wpcpt_configuration_source">
                        <table class="wpcpt_configuration_table">
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Type', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label> <select name="wpcpt_configuration_type" class="wpcpt_configuration_type">
                                            <option value="products" <?php selected( $data['type'], 'products' ); ?>><?php esc_html_e( 'Products', 'wpc-product-table' ); ?></option>
                                            <option value="categories_tags" <?php selected( $data['type'], 'categories_tags' ); ?>><?php esc_html_e( 'Categories & Tags', 'wpc-product-table' ); ?></option>
                                            <option value="on_sale" <?php selected( $data['type'], 'on_sale' ); ?>><?php esc_html_e( 'On Sale', 'wpc-product-table' ); ?></option>
                                            <option value="best_selling" <?php selected( $data['type'], 'best_selling' ); ?>><?php esc_html_e( 'Best Selling', 'wpc-product-table' ); ?></option>
											<?php
											$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

											foreach ( $taxonomies as $taxonomy ) {
												echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . ( $data['type'] === $taxonomy->name ? 'selected' : '' ) . '>' . esc_html( $taxonomy->label ) . '</option>';
											}
											?>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_products">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Products', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <div class="wpcpt-product-search-wrapper">
                                        <input id="" class="wpcpt-product-search-input" name="wpcpt_configuration_include_products" type="hidden" value="<?php echo esc_attr( $data['include'] ); ?>"/>
                                        <label>
                                            <select class="wc-product-search wpcpt-product-search" data-sortable="true" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-product-table' ); ?>" data-action="woocommerce_json_search_products_and_variations">
												<?php
												$_product_ids = explode( ',', $data['include'] );

												foreach ( $_product_ids as $_product_id ) {
													$_product = wc_get_product( $_product_id );

													if ( $_product ) {
														echo '<option value="' . esc_attr( $_product_id ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
													}
												}
												?>
                                            </select> </label>
                                    </div>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_categories_tags">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Categories', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <div class="wpcpt-category-search-wrapper">
                                        <input id="" class="wpcpt-category-search-input" name="wpcpt_configuration_categories" type="hidden" value="<?php echo esc_attr( $data['categories'] ); ?>"/>
                                        <label>
                                            <select class="wc-category-search wpcpt-category-search" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for a category&hellip;', 'wpc-product-table' ); ?>">
												<?php
												$category_slugs = explode( ',', $data['categories'] );

												foreach ( $category_slugs as $category_slug ) {
													$category = get_term_by( 'slug', $category_slug, 'product_cat' );

													if ( $category ) {
														echo '<option value="' . esc_attr( $category_slug ) . '" selected="selected">' . wp_kses_post( $category->name ) . '</option>';
													}
												}
												?>
                                            </select> </label>
                                    </div>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_categories_tags">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Tags', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input name="wpcpt_configuration_tags" type="text" style="width: 100%" placeholder="<?php esc_attr_e( 'Add some tags, split by a comma...', 'wpc-product-table' ); ?>" value="<?php echo esc_attr( $data['tags'] ); ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_terms">
                                <td class="wpcpt_configuration_th wpcpt_configuration_type_terms_label">
									<?php esc_html_e( 'Terms', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <input name="wpcpt_configuration_terms" class="wpcpt_configuration_terms" type="hidden" style="width: 100%" value="<?php echo esc_attr( $data['terms'] ); ?>"/>
									<?php
									$terms = $data['terms'];

									if ( ! is_array( $terms ) ) {
										$terms = array_map( 'trim', explode( ',', $terms ) );
									}
									?>
                                    <label>
                                        <select class="wpcpt_configuration_terms_select" multiple="multiple" data-<?php echo esc_attr( $data['type'] ); ?>="<?php echo esc_attr( implode( ',', $terms ) ); ?>">
											<?php
											if ( ! empty( $terms ) ) {
												foreach ( $terms as $t ) {
													if ( $term = get_term_by( 'slug', $t, $data['type'] ) ) {
														echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
													}
												}
											}
											?>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_categories_tags">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Exclude products', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <div class="wpcpt-product-search-wrapper">
                                        <input id="" class="wpcpt-product-search-input" name="wpcpt_configuration_exclude_products" type="hidden" value="<?php echo esc_attr( $data['exclude'] ); ?>"/>
                                        <label>
                                            <select class="wc-product-search wpcpt-product-search" data-sortable="true" multiple="multiple" data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wpc-product-table' ); ?>" data-action="woocommerce_json_search_products_and_variations">
												<?php
												$_product_ids = explode( ',', $data['exclude'] );

												foreach ( $_product_ids as $_product_id ) {
													$_product = wc_get_product( $_product_id );

													if ( $_product ) {
														echo '<option value="' . esc_attr( $_product_id ) . '" selected="selected">' . wp_kses_post( $_product->get_formatted_name() ) . '</option>';
													}
												}
												?>
                                            </select> </label>
                                    </div>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_categories_tags wpcpt_configuration_type_on_sale wpcpt_configuration_type_best_selling wpcpt_configuration_type_terms">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Limit', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input name="wpcpt_configuration_limit" type="number" class="text small-text" placeholder="50" value="<?php echo esc_attr( $data['limit'] ); ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_products wpcpt_configuration_type_categories_tags wpcpt_configuration_type_on_sale wpcpt_configuration_type_terms">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Order by', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label> <select name="wpcpt_configuration_orderby">
                                            <option value="default" <?php selected( $data['orderby'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-product-table' ); ?></option>
                                            <option value="none" <?php selected( $data['orderby'], 'none' ); ?>><?php esc_html_e( 'None', 'wpc-product-table' ); ?></option>
                                            <option value="id" <?php selected( $data['orderby'], 'id' ); ?>><?php esc_html_e( 'ID', 'wpc-product-table' ); ?></option>
                                            <option value="name" <?php selected( $data['orderby'], 'name' ); ?>><?php esc_html_e( 'Name', 'wpc-product-table' ); ?></option>
                                            <option value="type" <?php selected( $data['orderby'], 'type' ); ?>><?php esc_html_e( 'Type', 'wpc-product-table' ); ?></option>
                                            <option value="rand" <?php selected( $data['orderby'], 'rand' ); ?>><?php esc_html_e( 'Rand', 'wpc-product-table' ); ?></option>
                                            <option value="date" <?php selected( $data['orderby'], 'date' ); ?>><?php esc_html_e( 'Date', 'wpc-product-table' ); ?></option>
                                            <option value="modified" <?php selected( $data['orderby'], 'modified' ); ?>><?php esc_html_e( 'Modified', 'wpc-product-table' ); ?></option>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr wpcpt_configuration_type_row wpcpt_configuration_type_products wpcpt_configuration_type_categories_tags wpcpt_configuration_type_on_sale wpcpt_configuration_type_terms">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Order', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label> <select name="wpcpt_configuration_order">
                                            <option value="default" <?php selected( $data['order'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-product-table' ); ?></option>
                                            <option value="desc" <?php selected( $data['order'], 'desc' ); ?>><?php esc_html_e( 'DESC', 'wpc-product-table' ); ?></option>
                                            <option value="asc" <?php selected( $data['order'], 'asc' ); ?>><?php esc_html_e( 'ASC', 'wpc-product-table' ); ?></option>
                                        </select> </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="wpcpt_configuration wpcpt_configuration_design" style="display: none">
                        <table class="wpcpt_configuration_table">
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Style', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label> <select name="wpcpt_configuration_style">
                                            <option value="default" <?php selected( $data['style'], 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-product-table' ); ?></option>
                                            <option value="01" <?php selected( $data['style'], '01' ); ?>><?php esc_html_e( '01 - Blue', 'wpc-product-table' ); ?></option>
                                            <option value="02" <?php selected( $data['style'], '02' ); ?>><?php esc_html_e( '02 - Green', 'wpc-product-table' ); ?></option>
                                            <option value="03" <?php selected( $data['style'], '03' ); ?>><?php esc_html_e( '03 - Orange', 'wpc-product-table' ); ?></option>
                                        </select> </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Products per page', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="number" class="text small-text" min="1" step="1" name="wpcpt_configuration_page_length" value="<?php echo esc_attr( $data['page_length'] ); ?>" placeholder="10"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Extra CSS classes', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_configuration_extra_classes" value="<?php echo esc_attr( $data['extra_classes'] ); ?>"/>
                                    </label>
									<?php esc_html_e( 'Add extra CSS classes for the table, split by one space.', 'wpc-product-table' ); ?>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'Columns', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <div class="wpcpt-columns">
										<?php
										$saved_columns = get_post_meta( $post_id, 'columns', true );

										if ( is_array( $saved_columns ) && ( count( $saved_columns ) > 0 ) ) {
											foreach ( $saved_columns as $saved_key => $saved_column ) {
												if ( is_numeric( $saved_key ) ) {
													$saved_key = self::generate_key();
												}

												self::get_column( $saved_key, $saved_column );
											}
										}
										?>
                                    </div>
									<?php self::new_column(); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="wpcpt_configuration wpcpt_configuration_localization" style="display: none">
						<?php
						$localization = get_post_meta( $post_id, 'localization', true );
						?>
                        <table class="wpcpt_configuration_table">
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">

                                </td>
                                <td class="wpcpt_configuration_td">
									<?php esc_html_e( 'Read more about the below strings here https://datatables.net/reference/option/language', 'wpc-product-table' ); ?>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'decimal', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[decimal]" value="<?php echo esc_attr( $localization['decimal'] ?? '' ); ?>" placeholder="<?php echo self::$localization['decimal']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'emptyTable', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[emptyTable]" value="<?php echo esc_attr( $localization['emptyTable'] ?? '' ); ?>" placeholder="<?php echo self::$localization['emptyTable']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'info', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[info]" value="<?php echo esc_attr( $localization['info'] ?? '' ); ?>" placeholder="<?php echo self::$localization['info']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'infoEmpty', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[infoEmpty]" value="<?php echo esc_attr( $localization['infoEmpty'] ?? '' ); ?>" placeholder="<?php echo self::$localization['infoEmpty']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'infoFiltered', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[infoFiltered]" value="<?php echo esc_attr( $localization['infoFiltered'] ?? '' ); ?>" placeholder="<?php echo self::$localization['infoFiltered']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'infoPostFix', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[infoPostFix]" value="<?php echo esc_attr( $localization['infoPostFix'] ?? '' ); ?>" placeholder="<?php echo self::$localization['infoPostFix']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'thousands', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[thousands]" value="<?php echo esc_attr( $localization['thousands'] ?? '' ); ?>" placeholder="<?php echo self::$localization['thousands']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'lengthMenu', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[lengthMenu]" value="<?php echo esc_attr( $localization['lengthMenu'] ?? '' ); ?>" placeholder="<?php echo self::$localization['lengthMenu']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'loadingRecords', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[loadingRecords]" value="<?php echo esc_attr( $localization['loadingRecords'] ?? '' ); ?>" placeholder="<?php echo self::$localization['loadingRecords']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'processing', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[processing]" value="<?php echo esc_attr( $localization['processing'] ?? '' ); ?>" placeholder="<?php echo self::$localization['processing']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'search', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[search]" value="<?php echo esc_attr( $localization['search'] ?? '' ); ?>" placeholder="<?php echo self::$localization['search']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'zeroRecords', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[zeroRecords]" value="<?php echo esc_attr( $localization['zeroRecords'] ?? '' ); ?>" placeholder="<?php echo self::$localization['zeroRecords']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'paginate:first', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[paginate][first]" value="<?php echo esc_attr( $localization['paginate']['first'] ?? '' ); ?>" placeholder="<?php echo self::$localization['paginate']['first']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'paginate:last', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[paginate][last]" value="<?php echo esc_attr( $localization['paginate']['last'] ?? '' ); ?>" placeholder="<?php echo self::$localization['paginate']['last']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'paginate:next', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[paginate][next]" value="<?php echo esc_attr( $localization['paginate']['next'] ?? '' ); ?>" placeholder="<?php echo self::$localization['paginate']['next']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'paginate:previous', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[paginate][previous]" value="<?php echo esc_attr( $localization['paginate']['previous'] ?? '' ); ?>" placeholder="<?php echo self::$localization['paginate']['previous']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'aria:sortAscending', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[aria][sortAscending]" value="<?php echo esc_attr( $localization['aria']['sortAscending'] ?? '' ); ?>" placeholder="<?php echo self::$localization['aria']['sortAscending']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                            <tr class="wpcpt_configuration_tr">
                                <td class="wpcpt_configuration_th">
									<?php esc_html_e( 'aria:sortDescending', 'wpc-product-table' ); ?>
                                </td>
                                <td class="wpcpt_configuration_td">
                                    <label>
                                        <input type="text" name="wpcpt_localization[aria][sortDescending]" value="<?php echo esc_attr( $localization['aria']['sortDescending'] ?? '' ); ?>" placeholder="<?php echo self::$localization['aria']['sortDescending']; ?>"/>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
					<?php
				}

				function get_column( $key, $col, $new = false ) {
					$col = array_merge( [
						'editor'     => '',
						'type'       => 'order',
						'orderable'  => 'false',
						'searchable' => 'true',
						'align'      => 'left',
						'valign'     => 'top',
						'title'      => '',
						'content'    => ''
					], $col );
					?>
                    <div class="<?php echo esc_attr( 'wpcpt-column wpcpt-column-' . $col['type'] ); ?>">
                        <div class="wpcpt-column-heading">
                            <span class="wpcpt-column-move"></span>
                            <span class="wpcpt-column-label"><?php echo esc_html( '#' . $col['type'] ); ?></span>
                            <span class="wpcpt-column-remove">&times;</span>
                        </div>
                        <div class="wpcpt-column-content">
                            <div class="wpcpt-column-line">
                                <div class="wpcpt-column-line-label"><?php esc_html_e( 'Column name', 'wpc-product-table' ); ?></div>
                                <div class="wpcpt-column-line-content">
                                    <input type="hidden" name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][type]" value="<?php echo esc_attr( $col['type'] ); ?>"/>
                                    <label>
                                        <input type="text" name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][title]" style="width: 100%" placeholder="<?php echo esc_attr( $col['type'] ); ?>" value="<?php echo esc_attr( $col['title'] ); ?>" required/>
                                    </label>
                                </div>
                            </div>
							<?php
							if ( str_starts_with( $col['type'], 'pa_' ) ) {
								echo '<input type="hidden" name="wpcpt_columns[' . $key . '][content]" value="' . esc_attr( $col['type'] ) . '"/>';
							} else {
								echo '<input type="hidden" name="wpcpt_columns[' . $key . '][content]" value=""/>';
							}
							?>
                            <div class="wpcpt-column-line">
                                <div class="wpcpt-column-line-label"><?php esc_html_e( 'Configuration', 'wpc-product-table' ); ?></div>
                                <div class="wpcpt-column-line-content">
                                    <label> <select name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][orderable]">
                                            <option value="false" <?php selected( $col['orderable'], 'false' ); ?>><?php esc_attr_e( 'Orderable: No', 'wpc-product-table' ); ?></option>
                                            <option value="true" <?php selected( $col['orderable'], 'true' ); ?>><?php esc_attr_e( 'Orderable: Yes', 'wpc-product-table' ); ?></option>
                                        </select> </label> <label>
                                        <select name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][searchable]">
                                            <option value="false" <?php selected( $col['searchable'], 'false' ); ?>><?php esc_attr_e( 'Searchable: No', 'wpc-product-table' ); ?></option>
                                            <option value="true" <?php selected( $col['searchable'], 'true' ); ?>><?php esc_attr_e( 'Searchable: Yes', 'wpc-product-table' ); ?></option>
                                        </select> </label> <label>
                                        <select name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][align]">
                                            <option value="left" <?php selected( $col['align'], 'left' ); ?>><?php esc_attr_e( 'Align: Left', 'wpc-product-table' ); ?></option>
                                            <option value="center" <?php selected( $col['align'], 'center' ); ?>><?php esc_attr_e( 'Align: Center', 'wpc-product-table' ); ?></option>
                                            <option value="right" <?php selected( $col['align'], 'right' ); ?>><?php esc_attr_e( 'Align: Right', 'wpc-product-table' ); ?></option>
                                        </select> </label> <label>
                                        <select name="wpcpt_columns[<?php echo esc_attr( $key ); ?>][valign]">
                                            <option value="top" <?php selected( $col['valign'], 'top' ); ?>><?php esc_attr_e( 'Vertical align: Top', 'wpc-product-table' ); ?></option>
                                            <option value="middle" <?php selected( $col['valign'], 'middle' ); ?>><?php esc_attr_e( 'Vertical align: Middle', 'wpc-product-table' ); ?></option>
                                            <option value="bottom" <?php selected( $col['valign'], 'bottom' ); ?>><?php esc_attr_e( 'Vertical align: Bottom', 'wpc-product-table' ); ?></option>
                                        </select> </label>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function ajax_add_column() {
					$key    = self::generate_key();
					$type   = sanitize_key( $_POST['type'] ?? 'order' );
					$title  = self::$columns[ $type ] ?? '';
					$editor = sanitize_key( $_POST['editor'] ?? '' );

					self::get_column( $key, [
						'type'   => $type,
						'title'  => $title,
						'editor' => $editor,
					], true );

					wp_die();
				}

				function new_column() {
					?>
                    <div class="wpcpt-columns-new">
                        <label> <select class="wpcpt-column-type">
								<?php
								foreach ( self::$columns as $key => $val ) {
									echo '<option value="' . $key . '">' . $val . '</option>';
								}

								if ( $wc_attributes = wc_get_attribute_taxonomies() ) {
									echo '<optgroup label="' . esc_attr__( 'Attributes', 'wpc-product-table' ) . '">';

									foreach ( $wc_attributes as $wc_attribute ) {
										echo '<option value="' . esc_attr( urlencode( 'pa_' . $wc_attribute->attribute_name ) ) . '" data-type="attribute">' . esc_html( $wc_attribute->attribute_label ) . '</option>';
									}

									echo '</optgroup>';
								}
								?>
                                <optgroup label="<?php esc_attr_e( 'Custom (Premium)', 'wpc-product-table' ); ?>">
                                    <option value="custom_field" disabled><?php esc_html_e( 'Custom field', 'wpc-product-table' ); ?></option>
                                    <option value="custom_attribute" disabled><?php esc_html_e( 'Custom attribute', 'wpc-product-table' ); ?></option>
                                    <option value="custom" disabled><?php esc_html_e( 'Custom text/shortcode', 'wpc-product-table' ); ?></option>
                                </optgroup>
                            </select> </label>
                        <input type="button" class="button wpcpt-column-new" value="<?php esc_attr_e( '+ Add new column', 'wpc-product-table' ); ?>"/>
                    </div>
					<?php
				}

				function table_columns( $columns ) {
					return [
						'cb'              => $columns['cb'],
						'title'           => esc_html__( 'Title', 'wpc-product-table' ),
						'wpcpt_shortcode' => esc_html__( 'Shortcode', 'wpc-product-table' ),
						'wpcpt_desc'      => esc_html__( 'Description', 'wpc-product-table' ),
						'date'            => esc_html__( 'Date', 'wpc-product-table' ),
					];
				}

				function table_custom_column( $column, $postid ) {
					if ( $column === 'wpcpt_shortcode' ) {
						echo '<input type="text" class="wpcpt_shortcode_input" readonly value="[wpc_product_table id=&quot;' . $postid . '&quot; name=&quot;' . esc_attr( get_the_title( $postid ) ) . '&quot;]"/>';
					}

					if ( $column === 'wpcpt_desc' ) {
						echo get_the_excerpt( $postid );
					}
				}

				function save_product_table( $post_id ) {
					if ( isset( $_POST['wpcpt_configuration_type'] ) ) {
						update_post_meta( $post_id, 'type', sanitize_text_field( $_POST['wpcpt_configuration_type'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_categories'] ) ) {
						update_post_meta( $post_id, 'categories', sanitize_text_field( $_POST['wpcpt_configuration_categories'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_tags'] ) ) {
						update_post_meta( $post_id, 'tags', sanitize_text_field( $_POST['wpcpt_configuration_tags'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_terms'] ) ) {
						update_post_meta( $post_id, 'terms', sanitize_text_field( $_POST['wpcpt_configuration_terms'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_include_products'] ) ) {
						update_post_meta( $post_id, 'include', sanitize_text_field( $_POST['wpcpt_configuration_include_products'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_exclude_products'] ) ) {
						update_post_meta( $post_id, 'exclude', sanitize_text_field( $_POST['wpcpt_configuration_exclude_products'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_limit'] ) ) {
						update_post_meta( $post_id, 'limit', sanitize_text_field( $_POST['wpcpt_configuration_limit'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_orderby'] ) ) {
						update_post_meta( $post_id, 'orderby', sanitize_text_field( $_POST['wpcpt_configuration_orderby'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_order'] ) ) {
						update_post_meta( $post_id, 'order', sanitize_text_field( $_POST['wpcpt_configuration_order'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_style'] ) ) {
						update_post_meta( $post_id, 'style', sanitize_text_field( $_POST['wpcpt_configuration_style'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_page_length'] ) ) {
						update_post_meta( $post_id, 'page_length', sanitize_text_field( $_POST['wpcpt_configuration_page_length'] ) );
					}

					if ( isset( $_POST['wpcpt_configuration_extra_classes'] ) ) {
						update_post_meta( $post_id, 'extra_classes', self::sanitize_classes( $_POST['wpcpt_configuration_extra_classes'] ) );
					}

					if ( isset( $_POST['wpcpt_columns'] ) ) {
						update_post_meta( $post_id, 'columns', self::sanitize_array( $_POST['wpcpt_columns'] ) );
					}

					if ( isset( $_POST['wpcpt_localization'] ) ) {
						update_post_meta( $post_id, 'localization', self::sanitize_array( $_POST['wpcpt_localization'] ) );
					}
				}

				function sanitize_array( $array ) {
					foreach ( $array as $key => &$value ) {
						if ( is_array( $value ) ) {
							$value = self::sanitize_array( $value );
						} else {
							$value = sanitize_text_field( $value );
						}
					}

					return $array;
				}

				function sanitize_classes( $classes ) {
					$sanitized = preg_replace( '/\s+/', ' ', trim( $classes ) );
					$sanitized = preg_replace( '/[^\sA-Za-z0-9_-]/', '', $sanitized );

					return $sanitized;
				}

				function enqueue_scripts() {
					wp_enqueue_style( 'datatables', WPCPT_URI . 'assets/libs/datatables/datatables.min.css', [], '1.10.22' );
					wp_enqueue_script( 'datatables', WPCPT_URI . 'assets/libs/datatables/datatables.min.js', [ 'jquery' ], '1.10.22', true );
					wp_enqueue_style( 'wpcpt-frontend', WPCPT_URI . 'assets/css/frontend.css', [], WPCPT_VERSION );
					wp_enqueue_script( 'wpcpt-frontend', WPCPT_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCPT_VERSION, true );
					wp_localize_script( 'wpcpt-frontend', 'wpcpt_vars', [
							'datatable_params' => apply_filters( 'wpcpt_datatable_params', json_encode( apply_filters( 'wpcpt_datatable_params_arr', [
								'pageLength' => 10,
							] ) ) ),
						]
					);
				}

				function admin_enqueue_scripts() {
					wp_enqueue_style( 'wpcpt-backend', WPCPT_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCPT_VERSION );
					wp_enqueue_script( 'wpcpt-backend', WPCPT_URI . 'assets/js/backend.js', [
						'jquery',
						'wp-color-picker',
						'jquery-ui-sortable',
						'wc-enhanced-select',
						'selectWoo'
					] );
				}

				function ajax_search_term() {
					$return = [];

					$args = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ),
					];

					$terms = get_terms( $args );

					if ( is_array( $terms ) && count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
				}

				function save_post( $post_id, $post ) {
					if ( $post->post_type === 'product' ) {
						delete_transient( 'wpcpq_get_product_meta_keys' );
					}
				}

				function get_meta_keys() {
					global $wpdb;
					$transient_key = 'wpcpq_get_product_meta_keys';
					$get_meta_keys = get_transient( $transient_key );

					if ( true === (bool) $get_meta_keys ) {
						return $get_meta_keys;
					}

					global $wp_post_types;

					if ( ! isset( $wp_post_types['product'] ) ) {
						return false;
					}

					$get_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm 
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
        WHERE p.post_type = %s", 'product' ) );

					set_transient( $transient_key, $get_meta_keys, DAY_IN_SECONDS );

					return $get_meta_keys;
				}

				public static function generate_key( $length = 4, $lower = true ) {
					$key         = '';
					$key_str     = apply_filters( 'wpcpq_key_characters', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' );
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < apply_filters( 'wpcpq_key_length', $length ); $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					if ( is_numeric( $key ) ) {
						$key = self::generate_key();
					}

					if ( $lower ) {
						$key = strtolower( $key );
					}

					return apply_filters( 'wpcpq_generate_key', $key );
				}
			}

			return WPCleverWpcpt::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcpt_notice_wc' ) ) {
	function wpcpt_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Product Table</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
