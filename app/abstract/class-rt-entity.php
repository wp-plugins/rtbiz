<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of class-rt-entity
 *
 * @author udit
 */
if ( ! class_exists( 'Rt_Entity' ) ) {

	/**
	 * Class Rt_Entity
	 *
	 * An abstract class for Rt_Contact & Rt_Company - Core Modules of rtBiz.
	 * This will handle most of the functionalities of these two entities.
	 *
	 * If at all any individual entity wants to change the behavior for itself
	 * then it will override that particular method in the its child class
	 */
	abstract class Rt_Entity {

		/**
		 * This array will hold all the post types that are meant to be connected with ORganization / Person
		 * Other plugin addons will register their useful post type here in the array and accordingly will be connected
		 * with person / organization via Posts 2 Posts
		 *
		 * @var array
		 */
		public $enabled_post_types = array();

		/**
		 * @var - Entity Core Post Type (Organization / Person)
		 */
		public $post_type;

		/**
		 * @var - Post Type Labels (Organization / Person)
		 */
		public $labels;

		/**
		 * @var array - Meta Fields Keys for Entity (Organzation / Person)
		 */
		public $meta_fields = array();

		/**
		 * @var string - Meta Key Prefix
		 */
		public static $meta_key_prefix = 'rt_biz_';

		/**
		 * @param $post_type
		 */
		public function __construct( $post_type ) {
			$this->post_type = $post_type;
			$this->hooks();
		}

		/**
		 *  Register Rt_Entity Core Post Type
		 */
		function init_entity() {
			$this->register_post_type( $this->post_type, $this->labels );
			add_action( 'admin_menu', array( $this, 'remove_metabox' ) );
		}

		function remove_metabox(){
			$metabox_ids = apply_filters( 'rt_entity_remove_meta_box', array( 'commentstatusdiv' ) );
			foreach ( $metabox_ids as $metabox_id ) {
				remove_meta_box( $metabox_id, $this->post_type, 'normal' );
			}
		}

		/**
		 *  Actions/Filtes used by Rt_Entity
		 */
		function hooks() {

			if ( is_admin() ) {
				add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'post_table_columns' ), 10, 1 );
				add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'manage_post_table_columns' ), 10, 2 );

				add_action( 'add_meta_boxes', array( $this, 'entity_meta_boxes' ) );
				add_action( 'admin_init', array( $this, 'entity_meta_boxes' ) );
				add_action( 'save_post', array( $this, 'save_entity_details' ) );

				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
				add_action( 'pre_post_update', array( $this, 'save_old_data' ) );

				/*add_filter( 'gettext', array( $this, 'change_publish_button' ), 10, 2 );*/

				add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_entity_update_messages' ), 10, 2 );
				add_filter( 'post_updated_messages', array( $this, 'entity_updated_messages' ), 10, 2 );
			}
			add_filter( 'pre_get_comments' , array( $this, 'preprocess_comment_handler' ) );
			add_filter( 'comment_feed_where', array( $this, 'skip_feed_comments' ) );
			do_action( 'rt_biz_entity_hooks', $this );
		}

		/**
		 * Filter the bulk action updated messages for '. $singular .'.
		 * @param $bulk_messages
		 * @param $bulk_counts
		 *
		 * @return $bulk_messages
		 */
		function bulk_entity_update_messages( $bulk_messages, $bulk_counts ){
			$singular = strtolower( $this->labels['singular_name'] );
			$plural = strtolower( $this->labels['name'] );
			$bulk_messages[ $this->post_type ] = array(
				'updated'   => _n( '%s '. $singular .' updated.', '%s '. $plural .' updated.', $bulk_counts['updated'] ),
				'locked'    => _n( '%s '. $singular .' not updated, somebody is editing it.', '%s '. $plural .' not updated, somebody is editing them.', $bulk_counts['locked'] ),
				'deleted'   => _n( '%s '. $singular .' permanently deleted.', '%s '. $plural .' permanently deleted.', $bulk_counts['deleted'] ),
				'trashed'   => _n( '%s '. $singular .' moved to the Trash.', '%s '. $plural .' moved to the Trash.', $bulk_counts['trashed'] ),
				'untrashed' => _n( '%s '. $singular .' restored from the Trash.', '%s '. $plural .' restored from the Trash.', $bulk_counts['untrashed'] ),
			);
			return $bulk_messages;
		}

		/**
		 * Added message when entity update
		 * @param $messages
		 *
		 * @return mixed
		 */
		function entity_updated_messages( $messages ){
			$singular = $this->labels['singular_name'];
			$messages[ $this->post_type ] = array(
				0  => '', // Unused. Messages start at index 1.
				1  => __( $singular .' updated.', RT_BIZ_TEXT_DOMAIN ),
				2  => __( 'Custom field updated.', RT_BIZ_TEXT_DOMAIN ),
				3  => __( 'Custom field deleted.', RT_BIZ_TEXT_DOMAIN ),
				4  => __( $singular .' updated.', RT_BIZ_TEXT_DOMAIN ),
				/* translators: %s: date and time of the revision */
				5  => isset( $_GET['revision'] ) ? sprintf( __( $singular .' restored to revision from %s', RT_BIZ_TEXT_DOMAIN ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6  => __( $singular .' published.', RT_BIZ_TEXT_DOMAIN ),
				7  => __( $singular .' saved.', RT_BIZ_TEXT_DOMAIN ),
				8  => __( $singular .' submitted.', RT_BIZ_TEXT_DOMAIN ),
				10 => __( $singular .' draft updated.', RT_BIZ_TEXT_DOMAIN )
			);

			return $messages;
		}

		/**
		 * @param $where
		 * skip rtbot comments from feeds
		 * @return string
		 */
		function skip_feed_comments( $where ){
			global $wpdb;
			$where .= $wpdb->prepare( ' AND comment_type != %s', 'rt_bot' );
			return $where;
		}

		function preprocess_comment_handler( $commentdata ) {
			// Contact and company comments needed on from end in furture need to remove else condition.
			if ( is_admin() ) {
				$screen = get_current_screen();
				if ( ( isset( $screen->post_type ) && ( rt_biz_get_contact_post_type() != $screen->post_type && rt_biz_get_company_post_type() != $screen->post_type ) ) && $screen->id != Rt_Biz::$dashboard_screen ) {
					$types = isset( $commentdata->query_vars['type__not_in'] ) ? $commentdata->query_vars['type__not_in'] : array();
					if ( ! is_array( $types ) ) {
						$types = array( $types );
					}
					$types[] = 'rt_bot';
					$commentdata->query_vars['type__not_in'] = $types;
				}
			} else {
				$types = isset( $commentdata->query_vars['type__not_in'] ) ? $commentdata->query_vars['type__not_in'] : array();
				if ( ! is_array( $types ) ) {
					$types = array( $types );
				}
				$types[] = 'rt_bot';
				$commentdata->query_vars['type__not_in'] = $types;
			}
			return $commentdata;
		}

		function save_old_data( $post_id ){
			if ( ! isset( $_POST['post_type'] ) ) {
				return;
			}
			if ( $this->post_type != $_POST['post_type'] ) {
				return;
			}
			$body = '';
			$flag = false;
			$post = get_post( $post_id );
			if ( $_POST['post_title'] != $post->post_title ) {
				$body = '<strong>'.__( 'Contact Title' ).'</strong> : ';
				$body .= rt_biz_text_diff( $post->post_title , $_POST['post_title'] );
			}
			if ( $_POST['excerpt'] != $post->post_content ) {
				$body = '<strong>'.__( 'Contact Content' ).'</strong> : ';
				$body .= rt_biz_text_diff( $post->post_content, $_POST['excerpt'] );
			}

			if ( isset( $_POST['tax_input'] ) ) {
				foreach ( $_POST['tax_input'] as $key => $val ) {
					$tmp = rt_biz_get_tex_diff( $post_id, $key );
					if ( '' != $tmp ) {
						$body .= $tmp;
						$flag = true;
					}
				}
			}

			$meta_key = '';
			switch ( $_POST['post_type'] ){
				case rt_biz_get_contact_post_type():
					$meta_key = 'contact_meta';
					break;
				case rt_biz_get_company_post_type():
					$meta_key = 'account_meta';
					break;
			}

			foreach ( $this->meta_fields as $field ) {
				if ( ! isset( $_POST[ $meta_key ][ $field['key'] ] ) ) {
					continue;
				}

				if ( 'contact_primary_email' == $field['key'] ) {
					if ( ! rt_biz_is_primary_email_unique( $_POST['contact_meta'][ $field['key'] ] ) ) {
						continue;
					}
				}

				if ( Rt_Company::$primary_email == $field['key'] ) {
					if ( ! rt_biz_is_primary_email_unique_company( $_POST['account_meta'][ $field['key'] ] ) ) {
						continue;
					}
				}

				if ( 'true' == $field['is_multiple'] ) {
					$val = self::get_meta( $post_id, $field['key'] );
					$filerval  = array_filter( $val );
					$filerpost = array_filter( $_POST[ $meta_key ][ $field['key'] ] );
					$diff      = array_diff( $filerval, $filerpost );
					$diff2 = array_diff( $filerpost, $filerval );
					$difftxt = rt_biz_text_diff( implode( ' ', $diff ), implode( ' ', $diff2 ) );
					if ( ! empty( $difftxt ) || '' != $difftxt ) {
						$skip_enter = str_replace( 'Enter', '', $field['label'] );
						$body .= "<strong>{ $skip_enter }</strong> : ".$difftxt;
						$flag = true;
					}
				} else {
					$val    = self::get_meta( $post_id, $field['key'], true );
					$newval = $_POST[ $meta_key ][ $field['key'] ];
					if ( $val != $newval ) {
						$difftxt = rt_biz_text_diff( $val, $newval );
						$skip_enter = str_replace( 'Enter','',$field['label'] );
						$body .= "<strong>{ $skip_enter }</strong> : ".$difftxt;
						$flag = true;
					}
				}
			}
			if ( $flag ) {
				$user = wp_get_current_user();
				$body = 'Updated by <strong>'.$user->display_name. '</strong> <br/>' .$body;
				$settings  = rt_biz_get_redux_settings();
				$label             = $settings['menu_label'];
				$data = array(
					'comment_post_ID' => $post_id,
					'comment_content' => $body,
					'comment_type' => 'rt_bot',
					'comment_approved' => 1,
					'comment_author' => $label. ' Bot',
				);
				wp_insert_comment( $data );
			}
		}

		/**
		 * @param $translation
		 * @param $text
		 * @return string
		 */
		function change_publish_button( $translation, $text ) {
			if ( $this->post_type == get_post_type() && 'Publish' == $text ) {
				return 'Add';
			}
			return $translation;
		}

		/**
		 *
		 */
		function enqueue_scripts_styles() {
			global $post;
			if ( isset( $post->post_type ) && $post->post_type == $this->post_type && ! wp_script_is( 'jquery-ui-autocomplete' ) ) {
				wp_enqueue_script( 'jquery-ui-autocomplete', '', array( 'jquery-ui-widget', 'jquery-ui-position' ), '1.9.2', true );
			}

			wp_enqueue_style( 'biz-admin-css', RT_BIZ_URL.'/app/assets/css/biz_admin.css' );
		}

		/**
		 * Registers Meta Box for Rt_Entity Meta Fields - Additional Information for Rt_Entity
		 */
		function entity_meta_boxes() {
			add_meta_box( 'rt-biz-entity-details', __( 'Additional Details' ), array( $this, 'render_additional_details_meta_box' ), $this->post_type, 'normal', 'default' );
			add_meta_box( 'rt-biz-entity-assigned_to', __( 'Assigned To' ), array( $this, 'render_assign_to_meta_box' ), $this->post_type, 'side', 'default' );
			do_action( 'rt_biz_entity_meta_boxes', $this->post_type );
		}

		function render_assign_to_meta_box( $post ){

			$assigned = rt_biz_get_entity_meta( $post->ID,'assgin_to', true );
			$assignedHTML = '';
			if ( $assigned && ! empty( $assigned ) ) {
				$author = get_user_by( 'id', $assigned );
				$assignedHTML = "<li id='assign-auth-" . $author->ID . "' class='contact-list'>" .
				                get_avatar( $author->user_email, 24 ) .
				                "<a href='#removeAssign' class='delete_row'>×</a>" .
				                "<br/><a target='_blank' class='assign-title heading' title='" . $author->display_name . "' href='" . get_edit_user_link( $author->ID ) . "'>" . $author->display_name . '</a>' .
				                "<input type='hidden' name='assign_to' value='" . $author->ID . "' /></li>";
			}
			$emps = rt_biz_get_module_employee( RT_BIZ_TEXT_DOMAIN );

			$arrSubscriberUser = array();
			foreach ( $emps as $author ) {
				$arrSubscriberUser[] = array(
					'id'             => $author->ID,
					'label'          => $author->display_name,
					'imghtml'        => get_avatar( $author->user_email, 24 ),
					'user_edit_link' => get_edit_user_link( $author->ID ),
				);
			}
			?>
			<div class="">
			<span class="prefix"
			      title="<?php __( 'Assign to' ); ?>"><label><strong><?php __( 'Assign to' ); ?></strong></label></span>
				<script>
					var arr_assign_user =<?php echo json_encode( $arrSubscriberUser ); ?>;
				</script>
				<input type="text" placeholder="Type assignee name to select" id="assign_user_ac"/>
				<ul id="divAssignList" class="">
					<?php echo balanceTags( $assignedHTML ); ?>
				</ul>
			</div>
			<?php
			do_action( 'rt_biz_assign_to_metabox_after', $post, $this->post_type );
		}

		function save_meta_assign_to( $post ){
			if ( isset( $_POST['assign_to'] ) ) {
				rt_biz_update_entity_meta( $post, 'assgin_to', $_POST['assign_to'] );
			} else {
				rt_biz_update_entity_meta( $post, 'assgin_to', '' );
			}
		}

		/**
		 *
		 * Render Additional Info MetaBox
		 *
		 * @param $post
		 */
		function render_additional_details_meta_box( $post ) {
			do_action( 'rt_biz_before_render_meta_fields', $post, $this );
			?>
			<div id="rtbiz-additional-detail-meta-box">
				<?php

				$category = array_unique( wp_list_pluck( $this->meta_fields, 'category' ) );
				$cathtml = array();
				foreach ( $category as $key => $value ) {
					$cathtml[ $value ]['title'] = '<div><h3 class="rtbiz-category-title">'.__( $value ). __( ' information:' ).' </h3> </div>';
				}
				$cathtml['other']['title']   = '<div><h3 class="rtbiz-category-title">'.__( 'Other information:' ).'</h3></div>';
				$other_flag         = false;
				//			$terms              = wp_get_post_terms( $post->ID, Rt_Contact::$user_category_taxonomy );
				//			if ( ! empty( $terms ) && is_array( $terms ) ) {
				//				$slug               = wp_list_pluck( $terms, 'slug' );
				//				$is_our_team_mate   = in_array( Rt_Contact::$employees_category_slug, $slug );
				//			}

				// find out if it is out team mate then show HR information
				$is_our_team_mate = false;
				$postid = $post;
				if ( is_object( $post ) ) {
					$postid = $post->ID;
				}

				$wp_user = rt_biz_get_wp_user_for_contact( $postid ); //get wp user
				$cap = rt_biz_get_access_role_cap( RT_BIZ_TEXT_DOMAIN, 'author' );
				if ( ! empty( $wp_user[0] ) ) {
					$is_our_team_mate = user_can( $wp_user[0], $cap );
				}
				foreach ( $this->meta_fields as $field ) {
					ob_start();
					$field = apply_filters( 'rt_entity_fields_loop_single_field', $field );

					if ( ! $is_our_team_mate ) {
						if ( isset( $field['hide_for_client'] ) && $field['hide_for_client'] ) {
							continue;
						}
					}

					if ( isset( $field['is_datepicker'] ) && $field['is_datepicker'] ) {
						$values = self::get_meta( $post->ID, $field['key'], true );
						?>
						<script>
							jQuery( document ).ready( function( $ ) {
								$( document ).on( 'focus', ".datepicker", function() {
									$( this ).datepicker( {
										'dateFormat': 'dd/mm/yy',
										changeMonth: true,
										changeYear: true
									} );
								} );
							} );
						</script>
					<?php if ( isset( $field['label'] ) ) { ?>
						<p class="rtbiz-form-group">
							<label for="<?php echo  ( isset( $field['id'] ) ) ? '' . $field['id'] . '' : '' ?>"><?php echo $field['label']; ?></label><?php } ?>
							<input type="text" <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> <?php echo ( isset( $field['id'] ) ) ? 'id="' . $field['id'] . '"' : ''; ?> value='<?php echo $values; ?>' <?php echo ( isset( $field['class'] ) ) ? 'class="datepicker ' . $field['class'] . '"' : 'class="datepicker"'; ?>>
							<br /><span></span>
							<?php //echo ( isset( $field[ 'description' ] ) ) ? '<p class="description">' . $field[ 'description' ] . '</p>' : ''; ?>
						</p>
					<?php
					} else if ( isset( $field['is_multiple'] ) && $field['is_multiple'] ) {
						$values = self::get_meta( $post->ID, $field['key'] );
					?>

					<?php if ( isset( $field['label'] ) ) { ?>
						<p class="rtbiz-form-group">
						<label for="<?php echo  ( isset( $field['id'] ) ) ? '' . $field['id'] . '' : '' ?>"><?php echo $field['label']; ?></label><?php } ?>
						<input <?php echo ( isset( $field['type'] ) ) ? 'type="' . $field['type'] . '"' : ''; ?> <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> <?php echo ( isset( $field['class'] ) ) ? 'class="' . $field['class'] . '"' : ''; ?>><button data-type='<?php echo ( $field['type']) ; ?>' type='button' class='button button-primary add-multiple'>+</button>
						<br /><span></span>
						<?php foreach ( $values as $value ) { ?>
						<input <?php echo ( isset( $field['type'] ) ) ? 'type="' . $field['type'] . '"' : ''; ?> <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> value = '<?php echo $value; ?>' <?php echo ( isset( $field['class'] ) ) ? 'class="second-multiple-input ' . $field['class'] . '"' : 'class="second-multiple-input"'; ?>>
						<button type='button' class='button delete-multiple'> - </button>
					<?php } ?>
						<?php //echo ( isset( $field[ 'description' ] ) ) ? '<p class="description">' . $field[ 'description' ] . '</p>' : ''; ?>
					</p>
					<?php
					} else if ( isset( $field['type'] ) && 'textarea' == $field['type'] ) {
						$values = self::get_meta( $post->ID, $field['key'], true );
						?>
						<p class="rtbiz-form-group">
							<?php if ( isset( $field['label'] ) ) { ?>
								<label for="<?php echo  ( isset( $field['id'] ) ) ? '' . $field['id'] . '' : '' ?>"><?php echo $field['label']; ?></label> <?php } ?>
							<textarea <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> <?php echo ( isset( $field['id'] ) ) ? 'id="' . $field['id'] . '"' : ''; ?> <?php echo ( isset( $field['class'] ) ) ? 'class="' . $field['class'] . '"' : ''; ?>><?php echo $values; ?></textarea>
							<br /><span></span>
							<?php //echo ( isset( $field[ 'description' ] ) ) ? '<p class="description">' . $field[ 'description' ] . '</p>' : ''; ?>
						</p>
					<?php
					} else if ( isset( $field['type'] ) && 'user_group' == $field['type'] ) {
						$user_id = self::get_meta( $post->ID, $field['key'], true );
						if ( empty( $user_id ) ) {
							continue;
						}
						?>
						<p class="rtbiz-form-group">
							<?php call_user_func( $field['data_source'], new WP_User( $user_id ) ); ?>
							<!--						--><?php //echo ( isset( $field[ 'description' ] ) ) ? '<p class="description">' . $field[ 'description' ] . '</p>' : ''; ?>
						</p>
					<?php
					} else if ( isset( $field['type'] ) && 'checkbox' == $field['type'] ) {
						$values = self::get_meta( $post->ID, $field['key'], true );
						?>
						<p class="rtbiz-form-group rtbiz-form-checkbox">
							<label for="<?php echo  ( isset( $field['id'] ) ) ? '' . $field['id'] . '' : '' ?>">
								<input value='yes' <?php echo ( 'yes' == $values )?'checked':''; ?> type='checkbox' <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> <?php echo ( isset( $field['id'] ) ) ? 'id="' . $field['id'] . '"' : ''; ?> <?php echo ( isset( $field['class'] ) ) ? 'class="' . $field['class'] . '"' : ''; ?> />
								<?php echo $field['text']; ?></label>
							<br />
							<span></span>
						</p> <?php
					} else {
						$values = self::get_meta( $post->ID, $field['key'], true );
						?>
						<p class="rtbiz-form-group">
							<?php if ( isset( $field['label'] ) ) { ?>
								<label for="<?php echo  ( isset( $field['id'] ) ) ? '' . $field['id'] . '' : '' ?>"><?php echo $field['label']; ?></label><?php } ?>
							<input <?php echo ( isset( $field['type'] ) ) ? 'type="' . $field['type'] . '"' : ''; ?> <?php echo ( isset( $field['name'] ) ) ? 'name="' . $field['name'] . '"' : ''; ?> <?php echo ( isset( $field['id'] ) ) ? 'id="' . $field['id'] . '"' : ''; ?> value='<?php echo $values; ?>' <?php echo ( isset( $field['class'] ) ) ? 'class="' . $field['class'] . '"' : ''; ?>>
							<br /><span></span>
							<!--						--><?php //echo ( isset( $field[ 'description' ] ) ) ? '<p class="description">' . $field[ 'description' ] . '</p>' : ''; ?>
						</p>
					<?php
					}
					$tmphtml = ob_get_clean();
					if ( isset( $field['category'] ) ) {
						$cathtml[ $field['category'] ]['fields'] .= $tmphtml;
					} else {
						$cathtml['other']['fields'] .= $tmphtml;
						$other_flag = true;
					}
				}
				$printimpload = array();
				if ( isset( $cathtml['Contact'] ) ) {
					$printimpload[] = $cathtml['Contact'];
					unset( $cathtml['Contact'] );
				}
				if ( isset( $cathtml['Social'] ) ) {
					$printimpload[] = $cathtml['Social'];
					unset( $cathtml['Social'] );
				}
				if ( isset( $cathtml['HR'] ) ) {
					if ( $is_our_team_mate ) {
						$printimpload[] = $cathtml['HR'];
					}
					unset( $cathtml['HR'] );
				}

				foreach ( $cathtml as $key => $value ) {
					if ( 'other' == $key ) {
						if ( true == $other_flag ) {
							$printimpload[] = $value;
						}
					} else {
						$printimpload[] = $value;
					}
				}
				$filter = function($category){ return $category['title'].'<div class="rtbiz-category-group">' . $category['fields'] . '</div>'; };
				$printimpload = array_map( $filter, $printimpload );
				echo implode( '<div class="add-gap-div"><hr></div>', $printimpload );

				?> </div> <?php
			do_action( 'rt_biz_after_render_meta_fields', $post, $this );
			wp_nonce_field( 'rt_biz_additional_details_metabox', 'rt_biz_additional_details_metabox_nonce' );
			$this->print_metabox_js();
			do_action( 'rt_biz_print_metabox_js', $post, $this );
		}

		/**
		 *  MetaBox JS - Overridden in Child Classes - Rt_Company & Rt_Contact
		 */
		function print_metabox_js() {

		}

		/**
		 *
		 * Saves Additional Info from MetaBox
		 *
		 * @param $post_id
		 */
		function save_entity_details( $post_id ) {
			/*
			 * We need to verify this came from the our screen and with proper authorization,
			 * because save_post can be triggered at other times.
			 */

			// Check if our nonce is set.
			if ( ! isset( $_POST['rt_biz_additional_details_metabox_nonce'] ) ) {
				return;
			}

			$nonce = $_POST['rt_biz_additional_details_metabox_nonce'];

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $nonce, 'rt_biz_additional_details_metabox' ) ) {
				return;
			}

			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			/* OK, its safe for us to save the data now. */
			$this->save_meta_assign_to( $post_id );

			$this->save_meta_values( $post_id );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $post_id
		 */
		function save_meta_values( $post_id ) {
			do_action( 'rt_biz_save_entity_meta', $post_id, $this );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $columns
		 * @return mixed|void
		 */
		function post_table_columns( $columns ) {
			return apply_filters( 'rt_entity_columns', $columns, $this );
		}

		/**
		 *
		 * Overridden in Child Classes
		 *
		 * @param $column
		 * @param $post_id
		 */
		function manage_post_table_columns( $column, $post_id ) {
			do_action( 'rt_entity_manage_columns', $column, $post_id, $this );
		}

		/**
		 *
		 * Registers post type for Connection with Rt_Entity (Organization / Person)
		 *
		 * @param $post_type
		 * @param $label
		 */
		function init_connection( $post_type, $label ) {
			add_action( 'p2p_init', array( $this, 'create_connection' ) );
			$this->enabled_post_types[ $post_type ] = $label;
		}

		/**
		 *  Create a connection between registered post types and Rt_Entity
		 */
		function create_connection() {
			foreach ( $this->enabled_post_types as $post_type => $label ) {
				p2p_register_connection_type( array(
					'name' => $post_type . '_to_' . $this->post_type,
					'from' => $post_type,
					'title' => ( $this->post_type == rt_biz_get_contact_post_type() ) ? 'Participants (Customers)' : '',
					'to' => $this->post_type,
					'admin_column' => 'from',
					'from_labels' => array(
						'column_title' => $this->labels['name'],
					),
				) );
			}
		}

		function clear_post_connections_to_entity( $post_type, $from ) {
			p2p_delete_connections( $post_type . '_to_' . $this->post_type, array( 'from' => $from ) );
		}

		/**
		 *
		 *
		 *
		 * @param $post_type
		 * @param string $from
		 * @param string $to
		 */
		function connect_post_to_entity( $post_type, $from = '', $to = '' ) {
			if ( ! p2p_connection_exists( $post_type . '_to_' . $this->post_type, array( 'from' => $from, 'to' => $to ) ) ) {
				p2p_create_connection( $post_type . '_to_' . $this->post_type, array( 'from' => $from, 'to' => $to ) );
			}
		}

		/**
		 *
		 * Converts Connections into String form. Kind of toString method.
		 *
		 * @param $post_id
		 * @param $connection
		 * @param string $term_seperator
		 * @return string
		 */
		static function connection_to_string( $post_id, $connection, $term_seperator = ' , ' ) {
			$post = get_post( $post_id );
			$termsArr = get_posts( array(
				'connected_type' => $post->post_type . '_to_' . $connection,
				'connected_items' => $post,
				'nopaging' => true,
				'suppress_filters' => false,
			) );
			$tmpStr = '';
			if ( $termsArr ) {
				$sep = '';
				foreach ( $termsArr as $tObj ) {
					$tmpStr .= $sep . $tObj->post_title;
					$sep = $term_seperator;
				}
			}
			return $tmpStr;
		}

		/**
		 * @param $name
		 * @param array $labels
		 */
		function register_post_type( $name, $labels = array() ) {
			$args = apply_filters( 'rt_entity_register_post_type_args', array(
				'labels' => $labels,
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true, // Show the UI in admin panel
				'show_in_nav_menus' => false,
				'show_in_menu' => Rt_Biz::$dashboard_slug,
				'show_in_admin_bar' => false,
				'supports' => array( 'title', 'comments', 'thumbnail' ),
				'capability_type' => $name,
				'map_meta_cap'       => true, //Required For ACL Without map_meta_cap Cap ACL isn't working.
				//Default WordPress check post capability on admin page so we need to map custom post type capability with post capability.
			), $name );
			register_post_type( $name, $args );
		}

		/**
		 * @param $post_id
		 * @param $post_type
		 * @param bool $fetch_entity
		 * @return array
		 */
		function get_posts_for_entity( $post_id, $post_type, $fetch_entity = false ) {
			$args = array(
				'post_type' => $post_type,
				'post_status' => 'any',
				'connected_type' => $post_type . '_to_' . $this->post_type,
				'connected_items' => $post_id,
				'nopaging' => true,
				'suppress_filters' => false,
			);

			if ( $fetch_entity ) {
				$args['post_type'] = $this->post_type;
			}

			return get_posts( $args );
		}

		/**
		 *
		 * Returns Rt_Entity Caps
		 *
		 * @return array
		 */
		function get_post_type_capabilities() {
			return array(
				"edit_{$this->post_type}" => true,
				"read_{$this->post_type}" => true,
				"delete_{$this->post_type}" => true,
				"edit_{$this->post_type}s" => true,
				"edit_others_{$this->post_type}s" => true,
				"publish_{$this->post_type}s" => true,
				"read_private_{$this->post_type}s" => true,
				"delete_{$this->post_type}s" => true,
				"delete_private_{$this->post_type}s" => true,
				"delete_published_{$this->post_type}s" => true,
				"delete_others_{$this->post_type}s" => true,
				"edit_private_{$this->post_type}s" => true,
				"edit_published_{$this->post_type}s" => true,
			);
		}

		/**
		 * @param $id
		 * @param $key
		 * @param $value
		 * @param bool $unique
		 */
		static function add_meta( $id, $key, $value, $unique = false ) {
			add_post_meta( $id, self::$meta_key_prefix . $key, $value, $unique );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param bool $single
		 * @return mixed
		 */
		static function get_meta( $id, $key, $single = false ) {
			return get_post_meta( $id, self::$meta_key_prefix . $key, $single );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param $value
		 * @param string $prev_value
		 */
		static function update_meta( $id, $key, $value, $prev_value = '' ) {
			update_post_meta( $id, self::$meta_key_prefix . $key, $value, $prev_value );
		}

		/**
		 * @param $id
		 * @param $key
		 * @param string $value
		 */
		static function delete_meta( $id, $key, $value = '' ) {
			delete_post_meta( $id, self::$meta_key_prefix . $key, $value );
		}

		/**
		 * @param $query
		 * @param $args
		 * @return array
		 */
		function search( $query, $args = array() ) {
			$query_args = array(
				'post_type' => $this->post_type,
				'post_status' => 'any',
				'posts_per_page' => 10,
				's' => $query,
			);
			$args = array_merge( $query_args, $args );
			$entity = new WP_Query( $args );

			return $entity->posts;
		}
		/**
		 * Remove single connection from registered post type to Rt_Entity
		 * @param string $post_type
		 * @param mixed $from
		 * @param mixed $to
		 */
		function clear_post_connection_to_entity( $post_type, $from = '', $to = '' ) {
			return p2p_delete_connections( $post_type . '_to_' . $this->post_type, array( 'from' => $from, 'to' => $to ) );
		}

	}

}
