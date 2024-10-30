<?php

// Constants

define( 'KOL_API_DIR', KOL_DIR . 'api/' );
define( 'KOL_API_URL', KOL_URL . 'api/' );
define( 'KOL_API_URL_JS', KOL_API_URL . 'js/' );
define( 'KOL_API_URL_CSS', KOL_API_URL . 'css/' );

/**
 * Extend this class to create powerful WordPress settings pages
 * and meta boxes.
 *
 * This little API powers the Page Leads system from Kolakube and
 * can be used to easily create other plugins that run in a
 * similar manner.
 *
 * As of now, things are pretty messy and there's a lot to clean
 * up before this can hit 1.0.
 *
 * @since 0.8
 */

class kol_api {

	public $_id;
	public $_clean_id;
	public $_add_page;
	public $_get_option;
	public $_tab;
	public $_active_tab;
	public $_allowed_html = array(
		'a' => array(
			'href'  => array(),
			'class' => array(),
			'id'    => array()
		),
		'span' => array(
			'class' => array(),
			'id'    => array()
		),
		'img' => array(
			'src'    => array(),
			'alt'    => array(),
			'height' => array(),
			'width'  => array(),
			'class'  => array(),
			'id'     => array()
		),
		'br' => array(),
		'b'  => array(),
		'i'  => array(),
		's'  => array(
			'class' => array()
		)
	);

	public $suite;
	public $admin_page;
	public $admin_tab = '';
	public $meta_box  = '';
	public $edd_updater;


	/**
	 * This constructor loads all potential features of
	 * an admin screen / meta box, as well as the subclasses
	 * pseudo constructor, if it has one.
	 *
	 * @since 0.8
	 */

	public function __construct() {

		// Load subclass' psuedo contructor, if it exists

		if ( method_exists( $this, 'construct' ) )
			$this->construct();

		// Set core properties

		$this->_id       = get_class( $this );
		$this->_clean_id = preg_replace( '/^' . preg_quote( "{$this->suite}_", '/' ) . '/', '', $this->_id );

		$this->_get_option  = get_option( $this->_id );
		$this->_tab         = isset( $_GET['tab'] ) ? $_GET['tab'] : '';
		$this->_active_tab  = $this->_tab ? $this->_tab : 'settings';

		add_action( 'admin_init', array( $this, '_register_setting' ) );

		// Admin page

		if ( $this->admin_page )
			add_action( 'admin_menu', array( $this, 'add_menu' ) );

		// Admin tab / content

		if ( $this->admin_tab ) {
			if ( ! isset( $this->admin_tab['priority'] ) )
				$this->admin_tab['priority'] = 10;

			add_action( "{$this->suite}_admin_tabs", array( $this, 'admin_tab' ), $this->admin_tab['priority'] );
		}

		if ( method_exists( $this, 'fields' ) && $this->_active_tab == $this->_clean_id )
			add_action( "{$this->suite}_admin_tab_content", array( $this, 'admin_tab_content' ) );

		// Meta box

		if ( $this->meta_box ) {
			add_action( 'load-post.php', array( $this, 'meta_boxes' ) );
			add_action( 'load-post-new.php', array( $this, 'meta_boxes' ) );
			if ( isset( $this->meta_box['callback'] ) && $this->meta_box['callback'] == array( $this, 'module_meta_fields' ) ) {
				add_action( 'admin_footer-post.php', array( $this, 'meta_module_inline_scripts' ) );
				add_action( 'admin_footer-post-new.php', array( $this, 'meta_module_inline_scripts' ) );
			}
			add_filter( 'is_protected_meta', array( $this, 'hide_meta_keys' ), 10, 2 );
		}

		// Scripts

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		if ( method_exists( $this, 'admin_print_footer_scripts' ) )
			add_action( 'admin_print_footer_scripts', array( $this, 'load_admin_print_footer_scripts' ) );

		// EDD updater

		if ( method_exists( $this, 'edd_updater' ) ) {
			if ( ! class_exists( 'kol_plugin_updater' ) )
				include( KOL_API_DIR . 'extensions/edd-updater.php' );
			$this->edd_updater = $this->edd_updater();
			add_action( "{$this->suite}_edd_licenses", array( $this, 'edd_add_license' ) );
			add_action( 'admin_init', array( $this, 'edd_updater' ) );
			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );
		}

	}


	/**
	 * Adds new admin page to admin panel.
	 *
	 * @since 0.8
	 */

	public function add_menu() {
		$parent_slug = isset( $this->admin_page['parent_slug'] ) ? $this->admin_page['parent_slug'] : 'options-general.php';
		$capability  = isset( $this->admin_page['capability'] ) ? $this->admin_page['capability'] : 'manage_options';
		$callback    = isset( $this->admin_page['callback'] ) ? $this->admin_page['callback'] : array( $this, 'admin_page' );

		$this->_add_page = add_submenu_page( $parent_slug, $this->admin_page['name'], $this->admin_page['name'], $capability, $this->suite, $callback );
	}


	/**
	 * Registers settings of an entire class into single
	 * array.
	 *
	 * @since 1.0
	 */

	public function _register_setting() {
		register_setting( $this->_id, $this->_id, array( $this, 'admin_save' ) );
	}


	/**
	 * Builds out individual tab for use in tabbed navigation.
	 *
	 * @since 0.8
	 */

	public function admin_tab() { ?>

		<a href="?page=<?php echo urlencode( $this->suite ); ?>&tab=<?php echo $this->_clean_id; ?>" class="kol-tab<?php echo ( $this->_clean_id == $this->_active_tab ? ' kol-tab-active' : '' ); ?>" title="<?php esc_attr_e( $this->admin_tab['name'] ); ?>">

			<?php if ( isset( $this->admin_tab['dashicon'] ) ) : ?>
				<i class="kol-tab-dashicon dashicons dashicons-admin-<?php esc_attr_e( $this->admin_tab['dashicon'] ); ?>"></i>
			<?php endif; ?>

			<?php _e( $this->admin_tab['name'] ); ?>

		</a>

	<?php }


	/**
	 * Builds admin tab content using the Settings API. Goes with
	 * admin_tab().
	 *
	 * @todo Load postboxes script only when needed
	 * @since 0.8
	 */

	public function admin_tab_content() {
		$save = isset( $this->admin_tab['save'] ) ? $this->admin_tab['save'] : true;		
	?>

		<?php settings_fields( $this->_id ); ?>

		<?php $this->fields(); ?>

		<?php if ( $save ) : ?>
			<?php submit_button(); ?>
		<?php endif; ?>

		<?php wp_enqueue_script( 'postbox' ); ?>

		<script>
			jQuery( document ).ready( function() {
				postboxes.add_postbox_toggles( pagenow );
			} );
		</script>

	<?php }


	/**
	 * Saves all types of fields from the Settings API.
	 *
	 * @todo trickle down data so saving repeatable fields isn't so tedious
	 * @since 1.0
	 */

	public function admin_save( $input ) {
		foreach ( $this->register_fields() as $option => $field ) {
			$type           = isset( $field['type'] ) ? $field['type'] : '';
			$options        = isset( $field['options'] ) ? $field['options'] : '';
			$input[$option] = isset( $input[$option] ) ? $input[$option] : '';

			if ( $type == 'text' || $type == 'textarea' )
				$valid[$option] = wp_kses( $input[$option], $this->_allowed_html );

			elseif ( $type == 'code' )
				$valid[$option] = $input[$option];

			elseif ( $type == 'url' )
				$valid[$option] = esc_url_raw( $input[$option] );

			elseif ( $type == 'checkbox' && $options )
				foreach ( $options as $check )
					$valid[$option][$check] = ! empty( $input[$option][$check] ) ? 1 : 0;

			elseif ( $type == 'select' )
				$valid[$option] = in_array( $input[$option], $options ) ? $input[$option] : '';

			elseif ( $type == 'image' )
				$valid[$option] = intval( $input[$option] );

			elseif ( $type == 'repeat' ) {
				if ( array_filter( $input[$option][0] ) )
					foreach ( $input[$option] as $repeat_count => $repeat_input )
						foreach ( $field['repeat_fields'] as $repeat_id => $repeat_field )
							if ( $repeat_field['type'] == 'text' )
								$valid[$option][$repeat_count][$repeat_id] = wp_kses( $input[$option][$repeat_count][$repeat_id], $this->_allowed_html );
				else
					$valid[$option] = '';
			}

			elseif ( $type == 'edd_license' )
				$valid[$option] = sanitize_text_field( $input[$option] );
		}

		return $valid;
	}


	/**
	 * Loads meta boxes data to WordPress.
	 *
	 * @since 0.8
	 */

	public function meta_boxes() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'meta_save' ), 10, 2 );
	}


	/**
	 * Adds meta boxes to interface.
	 *
	 * @since 0.8
	 */

	public function add_meta_boxes() {
		$callback = isset( $this->meta_box['callback'] ) ? $this->meta_box['callback'] : array( $this, 'meta_fields' );
		$context  = isset( $this->meta_box['context'] ) ? $this->meta_box['context'] : 'advanced';
		$priority = isset( $this->meta_box['priority'] ) ? $this->meta_box['priority'] : 'default';

		foreach ( get_post_types() as $post_type )
			add_meta_box( $this->_id, $this->meta_box['name'], $callback, $post_type, $context, $priority );
	}


	/**
	 * Default meta box callback method.
	 *
	 * @since 0.8
	 */

	public function meta_fields() { ?>

		<?php wp_nonce_field( basename( __FILE__ ), "{$this->_id}_nonce" ); ?>

		<div class="kol-meta-box kol">

			<?php $this->fields(); ?>

		</div>

	<?php }


	/**
	 * If creating modules, use this callback. This activates
	 * the module to pull from global settings and allows user's
	 * to override them by creating custom modules.
	 *
	 * See Page Leads for examples.
	 *
	 * @since 0.8
	 */

	public function module_meta_fields() {
		$activate = get_post_meta( get_the_ID(), "{$this->_id}_activate", true );
		$custom   = get_post_meta( get_the_ID(), "{$this->_id}_custom", true );
		$a_enable = isset( $activate['enable'] ) ? $activate['enable'] : '';
		$c_enable = isset( $custom['enable'] ) ? $custom['enable'] : '';
	?>

		<div class="kol-meta-box kol">

			<?php wp_nonce_field( basename( __FILE__ ), "{$this->_id}_nonce" ); ?>
	
			<!-- Activate Module -->
	
			<?php $this->field( 'checkbox', 'activate', array(
				'enable' => sprintf( __( 'Enable %s', 'kol' ), $this->meta_box['name'] )
			) ); ?>
	
			<div id="<?php echo "{$this->_id}_module"; ?>"<?php echo $a_enable == '' ? ' style="display: none;"' : ''; ?>>
	
				<!-- Create Custom Module -->
	
				<div id="<?php echo "{$this->_id}_custom"; ?>">
	
					<?php $this->field( 'checkbox', 'custom', array(
						'enable' => sprintf( __( 'Create Custom %s', 'kol' ), $this->meta_box['name'] )
					) ); ?>
	
				</div>
	
				<!-- Fields -->
	
				<div id="<?php echo $this->_id; ?>_fields"<?php echo $c_enable == '' ? ' style="display: none;"' : ''; ?>>
	
					<hr />
	
					<?php $this->fields(); ?>
	
				</div>
	
			</div>

		</div>

	<?php }


	/**
	 * Creates toggle functionality for each module activation
	 * setting.
	 *
	 * @since 0.8
	 */

	public function meta_module_inline_scripts() { ?>
		<script>
			( function() {
				document.getElementById( '<?php echo "{$this->_id}_activate_enable"; ?>' ).onchange = function( e ) {
					document.getElementById( '<?php echo "{$this->_id}_module"; ?>' ).style.display = this.checked ? 'block' : 'none';
				}

				document.getElementById( '<?php echo "{$this->_id}_custom_enable"; ?>' ).onchange = function( e ) {
					document.getElementById( '<?php echo "{$this->_id}_fields"; ?>' ).style.display = this.checked ? 'block' : 'none';
				}	
			})();
		</script>
	<?php }


	/**
	 * Saves all types of post meta fields.
	 *
	 * @todo trickle down data so saving repeatable fields isn't so tedious
	 * @since 1.0
	 */

	public function meta_save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( ! isset( $_POST["{$this->_id}_nonce"] ) || ! wp_verify_nonce( $_POST["{$this->_id}_nonce"], basename( __FILE__ ) ) )
			return $post_id;

		if ( ! current_user_can( get_post_type_object( $post->post_type )->cap->edit_post, $post_id ) )
			return $post_id;

		$fields = $this->register_fields();

		// add module activation settings before we save

		if ( isset( $this->meta_box['callback'] ) && $this->meta_box['callback'] == array( $this, 'module_meta_fields' ) )
			$fields['activate'] = $fields['custom'] = array(
				'type'    => 'checkbox',
				'options' => array( 'enable' )
			);

		// loop through settings and save them suckers

		foreach ( $fields as $option => $field ) {
			$type    = isset( $field['type'] ) ? $field['type'] : '';
			$options = isset( $field['options'] ) ? $field['options'] : '';

			$key = "{$this->_id}_$option";
			$new = isset( $_POST[$key] ) ? $_POST[$key] : '';

			if ( $type == 'text' || $type == 'textarea' )
				$new = wp_kses( $new, $this->_allowed_html );

			if ( $type == 'code' )
				$new = $new;

			elseif ( $type == 'url' )
				$new = esc_url_raw( $new );

			elseif ( $type == 'checkbox' )
				foreach ( $options as $check )
					$new[$check] = isset( $new[$check] ) ? 1 : 0;

			elseif ( $type == 'select' )
				$new = in_array( $new, $options ) ? $new : '';				

			elseif ( $type == 'image' )
				$new = intval( $new );

			elseif ( $type == 'repeat' )
				if ( array_filter( $new[0] ) )
					foreach ( $new as $repeat_count => $repeat_input )
						foreach ( $field['repeat_fields'] as $repeat_id => $repeat_field )
							if ( $repeat_field['type'] == 'text' )
								$new[$repeat_count] = wp_kses( $new[$repeat_count], $this->_allowed_html );
				else
					$new = '';

			$value = get_post_meta( $post_id, $key, true );

			if ( $new && $new != $value )
				update_post_meta( $post_id, $key, $new );
			elseif ( $new == '' && $value )
				delete_post_meta( $post_id, $key, $value );
		}
	}


	/**
	 * So no meta values show up in the Custom Fields meta
	 * box, loop through all fields to hide them.
	 *
	 * @since 0.8
	 */

	public function hide_meta_keys( $protected, $meta_key ) {
		foreach ( $this->register_fields() as $key => $fields )
			if ( "{$this->_id}_$key" == $meta_key )
				return true;

		return $protected;
	}


	/**
	 * Loads all scripts and styles properly passed through various
	 * methods of enqueuing from subclasses.
	 *
	 * @since 0.8
	 */

	public function admin_scripts() {
		$screen = get_current_screen();
		$module = ( $this->admin_tab && $this->_tab == $this->_clean_id ) || ( $this->meta_box && $screen->base == 'post' || $screen->base == 'post-new' ) ? true : false; // loads on admin tab + meta box screens

		// register scripts

		wp_register_style( 'kol-admin', KOL_API_URL_CSS . 'admin.css' );
		wp_register_script( 'kol-repeat', KOL_API_URL_JS . 'repeat.js', array( 'jquery' ), true );

		// admin page

		if ( $this->admin_page && $screen->base == $this->_add_page ) {
			wp_enqueue_style( 'kol-admin' );

			if ( method_exists( $this, 'admin_page_scripts' ) )
				$this->admin_page_scripts();
		}

		// module (tab page + editor)

		if ( method_exists( $this, 'admin_module_scripts' ) && $module ) {
			wp_enqueue_style( 'kol-admin' );
			$this->admin_module_scripts();
		}
	}


	/**
	 * Loads printed admin scripts from base class and
	 * subclasses.
	 *
	 * @since 0.8
	 */

	public function load_admin_print_footer_scripts() {
		$screen = get_current_screen();
		$slug   = substr( $screen->base, -strlen( $this->suite ) ) === $this->suite; // check end of screen base for suite


		if ( $slug && ( $this->admin_tab && $this->_active_tab == $this->_clean_id ) || ( $this->meta_box && $screen->base == 'post' || $screen->base == 'post-new' ) )
			$this->admin_print_footer_scripts();
	}


	public function register_fields() {
		return array();
	}


	/**
	 * This outputs various settings fields like text, checkboxes,
	 * select, etc. For use in modules, this method detects where
	 * it is being loaded and loads either Settings API fields or
	 * meta fields.
	 *
	 * @since 0.8
	 */

	public function field( $type, $field, $values = null, $args = null ) {
		$screen = get_current_screen();
		$id     = esc_attr( "{$this->_id}_$field" );
 
		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ( $screen->base == 'post' || $screen->base == 'post-new' ) ) {
			$name   = esc_attr( "{$this->_id}_$field" );
			$option = get_post_meta( get_the_ID(), $name, true );

			if ( $args && isset( $args['parent'] ) && isset( $args['count'] ) && isset( $args['value'] ) ) {
				$name               = esc_attr( "{$this->_id}_" . $args['parent'] . '[' . $args['count'] . "][$field]" );
				$args['atts']['id'] = $id = esc_attr( "{$this->_id}_" . $args['parent'] . '_' . $args['count'] . "_$field" );
				$option = $args['value'][$field] = isset( $args['value'][$field] ) ? esc_attr( $args['value'][$field] ) : $option;
			}
		}
		else {
			$name   = esc_attr( "$this->_id[$field]" );
			$option = isset( $this->_get_option[$field] ) ? $this->_get_option[$field] : '';

			if ( $args && isset( $args['parent'] ) && isset( $args['count'] ) ) {
				$name   = esc_attr( "$this->_id[" . $args['parent'] . '][' . $args['count'] . "][$field]" );
				$id     = esc_attr( "{$this->_id}_" . $args['parent'] . '_' . $args['count'] . "_$field" );
				$option = isset( $this->_get_option[$args['parent']][$args['count']][$field] ) ? esc_attr( $this->_get_option[$args['parent']][$args['count']][$field] ) : '';
			}
		}

		if ( $type == 'text' )
			$this->text( $field, $name, $id, $option, $args );

		if ( $type == 'textarea' )
			$this->textarea( $field, $name, $id, $option );

		if ( $type == 'code' )
			$this->code( $field, $name, $id, $option );

		if ( $type == 'url' )
			$this->url( $field, $name, $id, $option, $args );

		if ( $type == 'checkbox' )
			$this->checkbox( $field, $name, $id, $option, $values, $args );

		if ( $type == 'select' )
			$this->select( $field, $name, $id, $option, $values, $args );

		if ( $type == 'image' )
			$this->image( $field, $name, $id, $option, $args );

		if ( $type == 'repeat' && method_exists( $this, $args['callback'] ) )
			$this->repeat( $field, $name, $option, $values, $args );

		if ( $type == 'edd_license' ) {
			$settings = get_option( "{$this->suite}_settings" );

			$name   = esc_attr( "{$this->suite}_settings[$field]" );
			$id     = esc_attr( "{$this->suite}_settings_$field" );
			$option = isset( $settings[$field] ) ? $settings[$field] : '';

			$this->edd_license( $field, $name, $id, $option, $args );
		}
	}


	/**
	 * Outputs a simple text input field with attributes.
	 *
	 * @since 0.8
	 */

	public function text( $field, $name, $id, $option, $args ) {
		$value       = isset( $args['value'][$field] ) ? $args['value'][$field] : $option;
		$class_size  = isset( $args['atts']['size'] ) ? 'size="' . $args['atts']['size'] . '"' : 'class="regular-text"';
		$placeholder = isset( $args['atts']['placeholder'] ) ? ' placeholder="' . esc_attr( $args['atts']['placeholder'] ) . '"' : '';
	?>

		<input type="text" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo $option; ?>"<?php echo $placeholder; ?> <?php echo $class_size; ?> />

	<?php }


	/**
	 * Outputs a simple textarea.
	 *
	 * @since 0.8
	 */

	public function textarea( $field, $name, $id, $option ) { ?>

		<textarea name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="large-text" rows="6"><?php echo esc_textarea( $option ); ?></textarea>

	<?php }


	/**
	 * Outputs a simple textarea to paste code into.
	 *
	 * @since 0.8
	 */

	public function code( $field, $name, $id, $option ) { ?>

		<textarea name="<?php echo $name; ?>" id="<?php echo $id; ?>" class="large-text" rows="15"><?php echo esc_textarea( $option ); ?></textarea>

	<?php }


	/**
	 * Outputs a simple text field for URL entry.
	 *
	 * @since 0.8
	 */

	public function url( $field, $name, $id, $option ) { ?>

		<input type="text" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo esc_url( $option ); ?>" class="regular-text" />

	<?php }


	/**
	 * Outputs a multi-checkbox field.
	 *
	 * @since 0.8
	 */

	public function checkbox( $field, $name, $id, $option, $values ) { ?>

		<?php foreach( $values as $val => $label ) :
			$name  = esc_attr( "{$name}[$val]" );
			$id    = esc_attr( "{$id}_$val" );
			$check = isset( $option[$val] ) ? $option[$val] : '';
		?>
			<p id="<?php esc_attr_e( $name ); ?>" class="kol-field">
				<input type="checkbox" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="1"<?php echo checked( $check, 1, false ); ?> />

				<label for="<?php echo $id; ?>"><?php esc_html_e( $label ); ?></label>
			</p>
		<?php endforeach; ?>

	<?php }


	/**
	 * Outputs a simple select field.
	 *
	 * @since 0.8
	 */

	public function select( $field, $name, $id, $option, $values ) { ?>

		<select name="<?php echo $name; ?>" id="<?php echo $id; ?>">
			<?php foreach ( $values as $val => $label ) : ?>
				<option value="<?php esc_attr_e( $val ); ?>"<?php echo selected( $option, $val, false ); ?>><?php esc_html_e( $label ); ?></option>
			<?php endforeach; ?>
		</select>

	<?php }


	/**
	 * Outputs repeatable fields.
	 *
	 * @since 0.8
	 */

	public function repeat( $field, $name, $option, $values, $args ) {
		$r      = 0;
		$repeat = array(
			'parent' => $field,
			'count'  => 0,
			'value'  => $option
		);
	?>

		<div class="kol-repeat">

			<a href="#" class="kol-repeat-add button kol-spacer"><?php _e( 'Add New', 'kol' ); ?></a>

			<div class="kol-repeat-fields">

				<?php if ( ! is_array( $option ) ) : ?>

					<div class="kol-repeat-field">

						<?php call_user_func( array( $this, $args['callback'] ), $repeat ); ?>

						<a href="#" class="kol-repeat-delete">&times;</a>

					</div>

				<?php else : ?>

					<?php foreach ( $option as $field ) :
						$repeat['value'] = $field;
						$repeat['count'] = $r;						
					?>

						<div class="kol-repeat-field">

							<?php call_user_func( array( $this, $args['callback'] ), $repeat ); ?>

							<a href="#" class="kol-repeat-delete">&times;</a>

						</div>

					<?php $r++; endforeach; ?>

				<?php endif; ?>

			</div>

		</div>

		<?php wp_enqueue_script( 'kol-repeat' ); ?>

	<?php }


	/**
	 * Outputs image upload field.
	 *
	 * @since 0.8
	 */

	public function image( $field, $name, $id, $option, $args ) {
		$value       = isset( $args['value'][$field] ) ? $args['value'][$field] : $option;
		$placeholder = isset( $args['atts']['placeholder'] ) ? ' placeholder="' . esc_attr( $args['atts']['placeholder'] ) . '"' : '';

		$image_id = $this->_tab == $this->_active_tab ? ( isset( $this->_get_option[$field] ) ? $this->_get_option[$field] : '' ) : get_post_meta( get_the_ID(), "{$this->_id}_$field", true );

		$image  = wp_get_attachment_image_src( intval( $image_id ), 'medium' );
		$hidden = empty( $image ) ? ' kol-media-hidden' : '';
	?>

		<div class="kol-media">

			<input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo $option; ?>"<?php echo $placeholder; ?> class="kol-media-url regular-text" />

			<p class="kol-spacer">
				<input type="button" class="kol-media-add button" value="<?php _e( 'Choose Image', 'kol' ); ?>" />			
				<input type="button" class="kol-media-remove button<?php esc_attr_e( $hidden ); ?>" value="<?php _e( 'Remove Image', 'kol' ); ?>" />
			</p>

			<img src="<?php echo $image[0]; ?>" width="<?php echo $image[1]; ?>" class="kol-media-preview-image<?php esc_attr_e( $hidden ); ?>" height="<?php echo $image[2]; ?>" alt="<?php _e( 'Preview image', 'kol' ); ?>" />

		</div>

	<?php }


	/**
	 * Easily create a label for your fields.
	 *
	 * @since 0.8
	 */

	public function label( $field, $name ) { ?>

		<label for="<?php esc_attr_e( "{$this->_id}_$field" ); ?>"><?php echo $name; ?></label>

	<?php }


	/**
	 * Easily create a description for your fields.
	 *
	 * @since 0.8
	 */

	public function desc( $desc ) { ?>

		<p class="description"><?php echo $desc; ?></p>

	<?php }


	/**
	 * The methods below all add the functionality of the
	 * EDD plugin updater to the Kol API. This will eventually
	 * be split into an API extension.
	 *
	 * @since 0.8
	 */

	public function edd_updater() {
		$settings = get_option( "{$this->suite}_settings" );

		new kol_plugin_updater( $this->edd_updater['url'], $this->edd_updater['path'], array( 
			'version' 	=> $this->edd_updater['version'],
			'license'   => isset( $settings[$this->edd_updater['field']] ) ? $settings[$this->edd_updater['field']] : '',
			'item_name' => $this->edd_updater['item_name'],
			'author' 	=> $this->edd_updater['author']
		) );
	}


	public function edd_license( $field, $name, $id, $option, $args ) {
		$value       = isset( $args['value'][$field] ) ? $args['value'][$field] : $option;
		$class_size  = isset( $args['atts']['size'] ) ? 'size="' . $args['atts']['size'] . '"' : 'class="regular-text"';
		$placeholder = isset( $args['atts']['placeholder'] ) ? ' placeholder="' . esc_attr( $args['atts']['placeholder'] ) . '"' : '';
		$status      = get_option( 'kol_edd_' . $this->edd_updater['field'] . '_status' );
	?>

		<div class="kol-edd-license-input">

			<input type="text" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo $option; ?>"<?php echo $placeholder; ?> <?php echo $class_size; ?> />

			<?php if ( ! empty( $option ) && $status == 'valid' ) : ?>
				<i class="dashicons dashicons-yes"></i>

			<?php elseif ( ! empty( $option ) && $status == 'invalid' ) : ?>
				<i class="dashicons dashicons-no"></i>
			<?php endif; ?>

		</div>

		<?php if ( $option !== false && $option != '' ) : ?>

			<?php if ( $status != 'valid' ) : ?>

				<?php wp_nonce_field( 'kol_license_nonce', 'kol_license_nonce' ); submit_button( __( 'activate', 'md'), 'secondary', 'kol_edd_' . $this->edd_updater['field'] . '_activate', false ); ?>

			<?php else : ?>

				<?php wp_nonce_field( 'kol_license_nonce', 'kol_license_nonce' ); submit_button( __( 'deactivate', 'md'), 'delete', 'kol_edd_' . $this->edd_updater['field'] . '_deactivate', false ); ?>

			<?php endif; ?>

		<?php endif; ?>

	<?php }


	public function edd_add_license() {
		$label = isset( $this->edd_updater['field_label'] ) ? $this->edd_updater['field_label'] : $this->edd_updater['item_name'];
	?>

		<table class="form-table">
			<tbody>

				<tr>
			
					<th scope="row">
						<?php $this->label( $this->edd_updater['field'], $label ); ?>
					</th>
		
					<td>
						<?php $this->field( 'edd_license', $this->edd_updater['field'] ); ?>
					</td>
			
				</tr>

			</tbody>
		</table>

	<?php }


	public function activate_license() {

		if ( isset( $_POST['kol_edd_' . $this->edd_updater['field'] . '_activate'] ) ) {

		 	if ( ! check_admin_referer( 'kol_license_nonce', 'kol_license_nonce' ) ) 	
		 		return;

		 	$settings = get_option( "{$this->suite}_settings" );

			$api_params = array(
				'edd_action'=> 'activate_license', 
				'license' 	=> trim( $settings[$this->edd_updater['field']] ), 
				'item_name' => urlencode( $this->edd_updater['item_name'] ),
				'url'       => home_url()
			);
			
			$response = wp_remote_get( add_query_arg( $api_params, $this->edd_updater['url'] ), array( 'timeout' => 15, 'sslverify' => false ) );
	
			if ( is_wp_error( $response ) )
				return false;
	
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
	
			update_option( 'kol_edd_' . $this->edd_updater['field'] . '_status', $license_data->license );

		}
	}
	

	public function deactivate_license() {
	
		if ( isset( $_POST['kol_edd_' . $this->edd_updater['field'] . '_deactivate'] ) ) {
	
		 	if ( ! check_admin_referer( 'kol_license_nonce', 'kol_license_nonce' ) ) 	
		 		return;

		 	$settings = get_option( "{$this->suite}_settings" );

			$api_params = array( 
				'edd_action'=> 'deactivate_license', 
				'license' 	=> trim( $settings[$this->edd_updater['field']] ), 
				'item_name' => urlencode( $this->edd_updater['item_name'] ),
				'url'       => home_url()
			);
			
			$response = wp_remote_get( add_query_arg( $api_params, $this->edd_updater['url'] ), array( 'timeout' => 15, 'sslverify' => false ) );
	
			if ( is_wp_error( $response ) )
				return false;
	
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
			if ( $license_data->license == 'deactivated' )
				delete_option( 'kol_edd_' . $this->edd_updater['field'] . '_status' );
	
		}
	}

}