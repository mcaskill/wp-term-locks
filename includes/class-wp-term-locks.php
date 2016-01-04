<?php

/**
 * Term Locks Class
 *
 * @since 0.1.0
 *
 * @package Plugins/Terms/Metadata/Lock
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Term_Locks' ) ) :
/**
 * Main WP Term Locks class
 *
 * @since 0.1.0
 */
final class WP_Term_Locks extends WP_Term_Meta_UI {

	/**
	 * @var string Plugin version
	 */
	public $version = '0.1.0';

	/**
	 * @var string Database version
	 */
	public $db_version = 201601030001;

	/**
	 * @var string Database version
	 */
	public $db_version_key = 'wpdb_term_lock_version';

	/**
	 * @var string Metadata key
	 */
	public $meta_key = 'locks';

	/**
	 * @var bool No column for lock metadata
	 */
	public $has_column = false;

	/**
	 * Hook into queries, admin screens, and more!
	 *
	 * @since 0.1.0
	 */
	public function __construct( $file = '' ) {

		// Setup the labels
		$this->labels = array(
			'singular'    => esc_html__( 'Lock',  'wp-term-locks' ),
			'plural'      => esc_html__( 'Locks', 'wp-term-locks' ),
			'description' => esc_html__( 'Lock this term from being edited or deleted.', 'wp-term-locks' )
		);

		// Call the parent and pass the file
		parent::__construct( $file );

		// Maybe manipulate row actions
		foreach ( $this->taxonomies as $tax ) {
			add_filter( "{$tax}_row_actions", array( $this, 'row_actions'  ), 10, 2 );
			add_filter( 'term_name',          array( $this, 'term_name'    ), 99, 2 );
		}

		// Maybe can't edit this tag
		add_action( 'admin_action_edit', array( $this, 'maybe_map_meta_cap' ) );
	}

	/** Assets ****************************************************************/

	/**
	 * Enqueue quick-edit JS
	 *
	 * @since 0.1.0
	 */
	public function enqueue_scripts() {

		// Enqueue fancy locking; includes quick-edit
		wp_enqueue_script( 'term-locks', $this->url . 'assets/js/term-locks.js', array( 'wp-lock-picker' ), $this->db_version, true );
	}

	/**
	 * Add help tabs for `lock` column
	 *
	 * @since 0.1.2
	 */
	public function help_tabs() {
		get_current_screen()->add_help_tab(array(
			'id'      => 'wp_term_lock_help_tab',
			'title'   => __( 'Locks', 'wp-term-locks' ),
			'content' => '<p>' . __( 'Some terms might be locked, preventing them from being edited or deleted.',         'wp-term-locks' ) . '</p>' .
			             '<p>' . __( 'If a term is locked, you\'ll need to contact a system administrator to modify it.', 'wp-term-locks' ) . '</p>',
		) );
	}

	/**
	 * Filter row actions
	 *
	 * @since 0.1.0
	 *
	 * @param  array   $actions
	 * @param  object  $term
	 */
	public function row_actions( $actions = array(), $term = null ) {

		// Bail if current user can manage
		if ( current_user_can( 'manage_term_locksing' ) ) {
			return $actions;
		}

		// Look for locks
		$locks = $this->get_meta( $term->term_id );

		// Cannot edit
		if ( ! empty( $locks['edit'] ) ) {
			unset( $actions['edit'], $actions['inline hide-if-no-js'] );
		}

		// Cannot delete
		if ( ! empty( $locks['delete'] ) ) {
			unset( $actions['delete'] );
		}

		// Return actions
		return $actions;
	}

	/**
	 * Filter term name
	 *
	 * @since 0.1.0
	 *
	 * @param  string  $name
	 * @param  object  $term
	 */
	public function term_name( $name = '', $term = null ) {

		// Skip term name if tag isn't an object
		if ( ! is_object( $term ) ) {
			return $name;
		}

		// Get the meta data
		$locks = $this->get_meta( $term->term_id );

		// Locked from editing?
		if ( ! empty( $locks['edit'] ) ) {
			$name = '</a><span class="dashicons dashicons-lock"></span> <span class="row-title">' . $name . '</span><a href="">';
		}

		// Return name
		return $name;
	}

	/**
	 * Maybe filter `map_meta_cap` if taxonomy fits
	 *
	 * @since 0.1.0
	 */
	public function maybe_map_meta_cap() {

		// Bail if not one of these taxonomies
		if ( empty( $_GET['tag_ID'] ) || ! in_array( get_current_screen()->taxonomy, $this->taxonomies, true ) ) {
			return;
		}

		// Map capabilities
		if ( current_user_can( 'manage_term_locksing' ) ) {
			return;
		}

		// Get the current taxonomy name
		$tax                    = get_current_screen()->taxonomy;
		$this->current_taxonomy = get_taxonomy( $tax );
		$this->current_term     = get_term( $_GET['tag_ID'], $tax );

		// Add the filter
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 99, 2 );
	}

	/**
	 * Filter `map_meta_cap` and conditionally disallow editing & deleting if
	 * term is locked.
	 *
	 * @since 0.1.0
	 *
	 * @param  array   $caps
	 * @param  string  $cap
	 * @param  int     $user_id
	 * @param  array   $args
	 */
	public function map_meta_cap( $caps = array(), $cap = '' ) {

		// Edit or Delete term
		switch ( $cap ) {

			// Edit
			case $this->current_taxonomy->cap->edit_terms :

				// Do not allow if locked
				$locks = $this->get_meta( $this->current_term->term_id );
				if ( ! empty( $locks['edit'] ) ) {
					$caps = array( 'do_not_allow' );
				}

				break;

			// Delete
			case $this->current_taxonomy->cap->delete_terms :

				// Do not allow if locked
				$locks = $this->get_meta( $this->current_term->term_id );
				if ( ! empty( $locks['delete'] ) ) {
					$caps = array( 'do_not_allow' );
				}

				break;
		}

		return $caps;
	}

	/**
	 * Empty "Add Term" form field
	 *
	 * @since 0.1.0
	 */
	public function add_form_field() { }

	/**
	 * Add lock fields for users with `manage_term_locks` capability
	 *
	 * @since 0.1.0
	 */
	public function edit_form_field( $term = false ) {

		// Locked?
		$locks = $this->get_meta( $term->term_id );
		$time  = current_time( 'mysql' );

		// Edit lock time
		$edit_time = ! empty( $locks['edit'] )
			? $locks['edit']
			: $time;

		// Delete lock time
		$delete_time = ! empty( $locks['delete'] )
			? $locks['delete']
			: $time; ?>

		<tr class="form-field term-lock-wrap">
			<th scope="row" valign="top">
				<label for="term-<?php echo esc_attr( $this->meta_key ); ?>">
					<?php echo esc_html( $this->labels['plural'] ); ?>
				</label>
			</th>
			<td>
				<ul>
					<li>
						<label>
							<input type="checkbox" name="term-locks[edit]" value="<?php echo esc_attr( $edit_time ); ?>" <?php checked( ! empty( $locks['edit'] ) ); ?> />
							<span class="term-lock-edit"><?php esc_html_e( 'Edit Lock', 'wp-term-locks' ); ?></span>
						</label>
					</li>
					<li>
						<label>
							<input type="checkbox" name="term-locks[delete]" value="<?php echo esc_attr( $delete_time ); ?>" <?php checked( ! empty( $locks['delete'] ) ); ?> />
							<span class="term-lock-delete"><?php esc_html_e( 'Delete Lock', 'wp-term-locks' ); ?></span>
						</label>
					</li>
				</ul>

				<?php if ( ! empty( $this->labels['description'] ) ) : ?>

					<p class="description">
						<?php echo esc_html( $this->labels['description'] ); ?>
					</p>

				<?php endif; ?>

			</td>
		</tr>

		<?php
	}
}
endif;
