<?php
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Rt_Importer' ) ) {

	/**
	 * Class Rt_Importer
	 * Use for Access gravity form data
	 *
	 * @since rt-lib
	 */
	class Rt_Importer {

		/**
		 * @var $page_name Page name Which is appear in menubar
		 */
		static $page_name = 'Importer';

		/**
		 * @var $page_slug - Page slug for importer Page
		 */
		static $page_slug = 'rtbiz-importer';

		/**
		 * @var $parent_page_slug - Page slug under which the attributes page is to be shown. If null / empty then an individual Menu Page will be added
		 */
		var $parent_page_slug;

		/**
		 * @var $page_cap - Capability for Attributes Admin Page; if not passed, default cap will be 'manage_options'
		 */
		var $page_cap;

		/**
		 * @var $base_url - url for page
		 */
		var $base_url;

		/**
		 * @var $pageflag - flag for page :  true for page | false for subpage
		 */
		var $pageflag;

		/**
		 * @var $post_type - If any post type passed, only attributes for those post type will be listed on the page.
		 */
		var $post_type = array();

		/**
		 * @var array
		 */
		var $field_array = array();

		/**
		 * @param $args
		 */
		public function __construct( $parent_slug, $cap = '', $admin_menu = true ) {
			$this->pageflag = $admin_menu;
			$this->parent_page_slug = $parent_slug;
			if ( $this->pageflag ) {
				$this->page_cap = $cap;
				$this->base_url = get_admin_url( null, add_query_arg( array( 'page' => self::$page_slug ), 'admin.php' ) );
			} else {
				$this->base_url = get_admin_url( null, add_query_arg( array( 'page' => $this->parent_page_slug . '&subpage=' .  self::$page_slug ), 'admin.php' ) );
			}
			$this->auto_loader();
			$this->db_upgrade();
			$this->hook();
			$this->init();
			$this->init_importer_help();
			$this->rt_importer_ajax_hooks();
		}

		public function init(){
			global $rtlib_gravity_fields_mapping_model, $rtlib_importer_mapper;

			$rtlib_gravity_fields_mapping_model = new Rtlib_Gravity_Fields_Mapping_Model();
			$rtlib_importer_mapper = new Rt_Importer_Mapper( $this->parent_page_slug, $this->page_cap, $this->pageflag );
		}

		public function auto_loader() {
			$modles_autoload = new RT_WP_Autoload( trailingslashit( dirname( __FILE__ ) ) . 'models/' );
			$mapper_autoload = new RT_WP_Autoload( trailingslashit( dirname( __FILE__ ) ) );
		}

		public function db_upgrade() {
			$updateDB = new  RT_DB_Update( RT_LIB_FILE, trailingslashit( dirname( __FILE__ ) ) . 'schema/' );
			$updateDB->db_version_option_name .= '_RT_IMPORTER';
			$updateDB->install_db_version = $updateDB->get_install_db_version();
			$updateDB->do_upgrade();
		}

		public  function hook(){
			$this->field_array = apply_filters( 'rtlib_importer_fields', $this->field_array );
			$this->post_type   = apply_filters( 'rtlib_importer_posttype', $this->post_type );
			if ( $this->pageflag ){
				add_action( 'admin_menu', array( $this, 'register_attribute_menu' ) );
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		function init_importer_help(){
			global $rt_importer_help;
			$rt_importer_help = new Rt_Importer_Help();
		}

		/**
		 * Rgister the
		 */
		public function register_attribute_menu(  ) {
			if ( ! empty( $this->parent_page_slug ) ) {
				add_submenu_page( $this->parent_page_slug, __( ucfirst( self::$page_name ) ), __( ucfirst( self::$page_name ) ), $this->page_cap, self::$page_slug, array( $this, 'ui' ) );
			} else {
				add_menu_page( __( ucfirst( self::$page_name ) ), __( ucfirst( self::$page_name ) ), $this->page_cap, self::$page_slug, array( $this, 'ui' ) );
			}
		}

		/**
		 * Load handlebars template
		 *
		 * @since
		 */
		public function load_handlebars_templates() {
			?>
			<script id="map_table_content" type="text/x-handlebars-template">
				<table>
					{{#each data}}
					<tr>
						<td>{{this.value}}</td>
						<td>
							<select data-map-value='{{this.value}}'> {{#each ../mapData}}
								<option value="{{this.slug}}"
								        {{{mapfieldnew this.display ../value}}}>{{this.display}}</option>
								{{/each}} </select>
						</td>
						{{/each}}
				</table>
			</script>
			<script id="defined_filed-option" type="text/x-handlebars-template">
				{{#each this}}
				<option value="{{this.slug}}">{{this.display}}</option>				{{/each}}
			</script>
			<script id="key-type-option" type="text/x-handlebars-template">
				<option value="">--Select Key--</option>				{{#each this}}
				<option value="{{this.meta_key}}">{{this.meta_key}}</option>				{{/each}}
			</script>

		<?php
		}

		public function get_current_tab(){
			return isset( $_REQUEST['page'] ) ? ( isset( $_REQUEST['type'] )? $this->base_url . '&type='.$_REQUEST['type']: $this->base_url .'&type=gravity' ) : $this->base_url .'&type=gravity';
		}

		public function importer_tab(){
			// Declare local variables
			$tabs_html    = '';

			// Setup core admin tabs
			$tabs = array(
				/*array(
					'href' => $this->base_url . '&type=CSV',
					'name' => __( 'CSV' ),
					'slug' => self::$page_slug . '&type=CSV' ,
				),*/ array(
					'href' => $this->base_url . '&type=gravity',
					'name' => __( 'Gravity' ),
					'slug' => $this->base_url .'&type=gravity',
				),
			);
			$filterd_tab = apply_filters( 'rt_importer_add_tab', $tabs );

			if ( ! empty( $filterd_tab ) ){
				if ( $this->pageflag ) {
					$idle_class   = 'nav-tab';
					$active_class = 'nav-tab nav-tab-active';
					$tabs_html .= '<div class="nav-tab-wrapper" >';
					// Loop through tabs and build navigation
					foreach ( array_values( $filterd_tab ) as $tab_data ) {
						$is_current = (bool) ( $tab_data['slug'] == $this->get_current_tab() );
						$tab_class  = $is_current ? $active_class : $idle_class;

						if ( isset( $tab_data['class'] ) && is_array( $tab_data['class'] ) ) {
							$tab_class .= ' ' . implode( ' ', $tab_data['class'] );
						}

						$tabs_html .= '<a href="' . $tab_data['href'] . '" class="' . $tab_class . '">' . $tab_data['name'] . '</a>';
					}
					$tabs_html .= '</div>';
				} else {
					$idle_class   = '';
					$active_class = 'current';
					$tabs_html .= '<div class="sub-nav-tab-wrapper"><ul class="subsubsub">';
					foreach ( array_values( $filterd_tab ) as $i => $tab_data ) {
						$is_current = (bool) ( $tab_data['slug'] == $this->get_current_tab() );
						$tab_class  = $is_current ? $active_class : $idle_class;

						if ( isset( $tab_data['class'] ) && is_array( $tab_data['class'] ) ) {
							$tab_class .= ' ' . implode( ' ', $tab_data['class'] );
						}
						$separator = $i != ( count( $filterd_tab ) - 1 ) ? ' | ' : '';
						$tabs_html .= '<li class="' . $tab_data['name'] . '"><a href="' . $tab_data['href'] . '" class="' . $tab_class . '">' . $tab_data['name'] . '</a>'. $separator .'</li>';
					}
					$tabs_html .= '</ul></div>';
				}
			}

			// Output the tabs
			echo $tabs_html;
		}

		/**
		 * get forms
		 *
		 * @return bool
		 *
		 * @since
		 */
		public function get_forms() {
			if ( ! class_exists( 'RGForms' ) ) {
				return false;
			}
			$active = RGForms::get( 'active' ) == '' ? null : RGForms::get( 'active' );
			$forms  = RGFormsModel::get_forms( $active, 'title' );
			if ( isset( $forms ) && ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$return[ $form->id ] = $form->title;
				}

				return $return;
			} else {
				return false;
			}
		}

		/**
		 *
		 * get all gravity lead
		 *
		 * @param $form_id
		 *
		 * @since
		 */
		public function get_all_gravity_lead( $form_id ) {
			$gravityLeadTableName = RGFormsModel::get_lead_table_name();
			global $wpdb;
			$sql = $wpdb->prepare( "SELECT id FROM $gravityLeadTableName WHERE form_id=%d AND status='active'", $form_id );
			echo json_encode( $wpdb->get_results( $sql, ARRAY_A ) );
		}

		public function ui(){

			$this->load_handlebars_templates();
			$title_ele = $this->pageflag ? 'h2' : 'h3';?>
			<div class="wrap">
			<?php echo '<' . $title_ele . '>' .  __( 'Importer' ) . '</' . $title_ele . '>';
			$this->importer_tab();

			if ( ! isset( $_REQUEST['type'] ) ){
				$_REQUEST['type'] = 'gravity'; // remove when csv is active
			}

			if  ( 'gravity' == $_REQUEST['type'] ) {
				$forms    = $this->get_forms(); //get gravity for list

				if ( isset( $forms ) && ! empty( $forms ) ) {
					$noFormflag = false;
					if ( isset( $_POST['mapSource'] ) && trim( $_POST['mapSource'] ) == '' ) {
						$class = ' class="form-invalid" ';
					} else {
						$class = '';
					}
					$form_select = '<select name="mapSource" id="mapSource" ' . $class . '>';
					$form_select .= '<option value="">' . __( 'Please select a form' ) . '</option>';
					foreach ( $forms as $id => $form ) {
						if ( isset( $_POST['mapSource'] ) && intval( $_POST['mapSource'] ) == $id ) {
							$selected = "selected='selected'";
							$formname = $form;
						} else {
							$selected = '';
						}
						$form_select .= '<option value="' . $id . '"' . $selected . '>' . $form . '</option>';
					}
				} else {
					$form_select = '<strong>Please create some forms!</strong>';
					$noFormflag  = true;
				}

				if ( isset( $_POST['mapPostType'] ) && trim( $_POST['mapPostType'] ) == '' ) {
					$class = ' class="form-invalid" ';
				} else {
					$class = '';
				}
				$form_posttype = '<select name="mapPostType" id="mapPostType" ' . $class . '>';
				$form_posttype .= '<option value="">' . __( 'Please select a CPT' ) . '</option>';
				foreach ( $this->post_type as $cpt_slug => $cpt_label ) {
					if ( isset( $_POST['mapPostType'] ) && intval( $_POST['mapPostType'] ) == $cpt_slug ) {
						$selected = "selected='selected'";
						$formname = $form;
					} else {
						$selected = '';
					}
					$form_posttype .= '<option value="' . $cpt_slug . '"' . $selected . '>' . $cpt_label['lable'] . '</option>';
				}
				?>

				<form action="" method="post">
					<table class="form-table">
						<tr>
							<th scope="row"><label
									for="mapPostType"><?php _e( 'Select a CPT:' ); ?></label></th>
							<td>
								<?php echo balanceTags( $form_posttype ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label
									for="mapSource"><?php _e( 'Select a Form:' ); ?></label></th>
							<td>
								<?php echo balanceTags( $form_select ); ?>
							</td>
						</tr>
						<?php if ( ! $noFormflag ) : ?>
							<tr>
								<th scope="row"></th>
								<td><input type="button" id="map_submit" name="map_submit" value="Next"
								           class="button button-primary"/></td>
							</tr>
						<?php endif; ?>
					</table>
					<div id="mapping-form"></div>
				</form>
			<?php
			} else if ( isset( $_REQUEST['page'] ) && self::$page_slug == $_REQUEST['page'] ){
				?>
				<form action="" method="post" enctype="multipart/form-data">
					<table class="form-table">
						<tr>
							<th scope="row"><label
									for="map_upload"><?php _e( 'Upload a data file:' ); ?></label>
							</th>
							<td>
								<input type="file" name="map_upload" id="map_upload"/>
							</td>
						</tr>
						<tr>
							<td><input type="submit" name="map_submit" value="Upload" class="button"/></td>
						</tr>
					</table>
				</form>
			<?php
			} ?>
			</div><?php
		}

		public function rt_importer_ajax_hooks(){
			add_action( 'wp_ajax_rtlib_import', array( $this, 'rtlib_importer' ) ); // Display mapping field form
			add_action( 'wp_ajax_rtlib_map_import', array( $this, 'rtlib_map_import_callback' ) ); // import alll record & convert as CPT
			add_action( 'wp_ajax_rtlib_gravity_dummy_data', array( $this, 'get_random_gravity_data' ) ); // Dummy data deisplay
			add_action( 'wp_ajax_rtlib_map_import_feauture', array( $this, 'rtlib_map_import_feauture' ) ); // add entry in gravity mapper table
			add_action( 'wp_ajax_rtlib_defined_map_feild_value', array( $this, 'rtlib_defined_map_field_value' ) );

			add_action( 'gform_entry_info', array( $this, 'gravity_form_lead_meta' ), 1, 2 );
			add_action( 'gform_entry_created', array( $this, 'rtlib_auto_import' ), 1, 2 );
			add_filter( 'gform_pre_submission_filter', array( $this, 'rtlib_add_custome_field' ), 1, 1 );
		}

		public function rtlib_importer() {
			$flag      = true;

			if ( 'csv' == $_REQUEST['type'] ) {
				if ( isset( $_FILES['map_upload'] ) && 0 == $_FILES['map_upload']['error'] ) {
					if ( 'text/csv' != $_FILES['map_upload']['type'] ) {
						echo "<div class='error'>" . esc_html( __( 'Please upload a CSV file only!' ) ) . '</div>';

						return;
					}
					//Upload the file to 'Uploads' folder
					$file   = $_FILES['map_upload'];
					$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
					if ( isset( $upload['error'] ) ) { ?>
						<div id="map_message" class="error"><p><?php echo esc_html( $upload['error'] ); ?>  </p></div> <?php
						return false;;
					}
					if ( ! $flag ) {
						return;
					}
					$csv = new parseCSV();
					$csv->auto( $upload['file'] );
					$data = $csv->data[ rand( 1, count( $csv->data ) - 1 ) ]; ?>
					<div id="map_message" class="updated map_message"><p>
							<?php _e( 'File uploaded:' ); ?>
							<strong><?php echo esc_html( $_FILES['map_upload']['name'] ); ?></strong>
							<?php _e( 'Total Rows:' ); ?>
							<strong><?php echo esc_html( count( $csv->data ) ); ?></strong></p>
					</div>

					<form method="post" action="" id="rtlibMappingForm" name="rtlibMappingForm">
					<input type="hidden" name="mapSource" id="mapSource"
					       value="<?php echo esc_attr( $upload['file'] ); ?>"/>
					<input type="hidden" name="mapSourceType" id="mapSourceType"
					       value="<?php echo esc_attr( $_REQUEST['type'] ); ?>"/>
					<input type="hidden" name="mapEntryCount" id="mapEntryCount"
					       value="<?php echo esc_attr( count( $csv->data ) ); ?>"/>
					<table class="wp-list-table widefat fixed" id="map_mapping_table">
					<thead>
					<tr>
						<th scope="row"><?php _e( 'Column Name' ); ?></th>
						<th scope="row"><?php _e( 'Field Name' ); ?></th>
						<th scope="row"><?php _e( 'Default Value' ); ?></th>
						<th scope="row"><a href="#dummyDataPrev"> << </a><?php _e( 'Sample' ); ?> <a
								href="#dummyDataNext"> >> </a></th>
					</tr>
					</thead>
					<tbody style="background: white;">
					<?php foreach ( $csv->titles as $value ) { ?>
						<tr>
							<td><?php echo esc_html( ucfirst( $value ) ); ?></td>
							<td>
								<?php
								$fieldname   = str_replace( ' ', '-s-', $value );
								$form_fields = '<select data-og="' . $fieldname . '" name="field-' . $fieldname . '"  id="field-' . $fieldname . '" class="map_form_fields map_form_fixed_fields">';
								$form_fields .= '<option value="">Choose a field or Skip it</option>';
								foreach ( $this->field_array as $key => $lfield ) {
									/*if ($lfield["type"] == 'defined')
										continue;*/
									$form_fields .= '<option value="' . $lfield['slug'] . '">' . ucfirst( $lfield['display_name'] ) . '</option>';
								}
								//$form_fields .= '<option value="ticketmeta">Other Field</option>';
								$form_fields .= '</select>';
								echo balanceTags( $form_fields );
								?>
							</td>
							<td></td>
							<td class='rtlib-importer-dummy-data'
							    data-field-name="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $data[ $value ] ); ?></td>
						</tr>
					<?php } ?>
					</tbody>

				<?php
				} else {
					echo "<div class='error'><p>" . esc_html( __( 'Please Select File' ) ) . '</p></div>';

					return false;
				}
			} else {

				$form_id    = intval( $_REQUEST['mapSource'] );
				$post_type  = $_REQUEST['mapPostType'];
				$form_data  = RGFormsModel::get_form_meta( $form_id );
				$form_count = RGFormsModel::get_form_counts( $form_id );

				if ( ! $form_data ) {
					?>
					<div id="map_message" class="error">Invalid Form</div>
					<?php
					return false;
				}

				if ( ! $flag ) {
					return;
				}
				?>

			<div id="map_message" class="updated map_message">
				Form Selected : <strong><?php echo esc_html( $form_data['title'] ); ?></strong><br/> Total Entries:
				<strong><?php echo esc_html( $form_count['total'] ); ?></strong>
			</div>

			<form method="post" action="" id="rtlibMappingForm" name="rtlibMappingForm">
				<input type="hidden" name="mapSource" id="mapSource" value="<?php echo esc_attr( $form_id ); ?>"/>
				<input type="hidden" name="mapPostType" id="mapPostType" value="<?php echo esc_attr( $post_type ); ?>"/>
				<input type="hidden" name="mapSourceType" id="mapSourceType" value="<?php echo esc_attr( $_REQUEST['type'] ); ?>"/>
				<input type="hidden" name="mapEntryCount" id="mapEntryCount" value="<?php echo esc_attr( $form_count['total'] ); ?>"/>
				<table class="wp-list-table widefat fixed posts" >
					<thead>
						<tr>
							<th scope="row"><?php _e( 'Field Name' ); ?></th>
							<th scope="row"><?php _e( 'Rtbiz Module Column Name' ); ?></th>
							<th scope="row"><?php _e( 'Default Value' ); ?></th>
							<th scope="row"><a href="#dummyDataPrev"> << </a><?php _e( 'Sample' ); ?><a
									href="#dummyDataNext"> >> </a></th>
						</tr>
					</thead>
					<tbody style="  ">
				<?php
				$formdummydata = RGFormsModel::get_leads( $form_id, 0, 'ASC', '', 0, 1 );
				foreach ( $form_data['fields'] as &$field ) {
					?>
							<tr data-field-name="<?php echo esc_attr( $field['label'] ); ?>">
								<td><?php echo esc_html( ucfirst( $field['label'] ) ); ?>
									<input type="hidden" value="<?php echo esc_attr( ucfirst( $field['type'] ) ); ?>"/>
								</td>
								<td>
								<?php
								$form_fields = '<select name="field-' . $field['id'] . '"  id="field-' . $field['id'] . '" class="map_form_fields map_form_fixed_fields">';
								$form_fields .= '<option value="">Choose a field or Skip it</option>';
								foreach ( $this->field_array[ $post_type ] as $key => $lfield ) {
									/*if (isset($lfield["type"]) &&  $lfield["type"]== 'defined')
										continue;*/
									$form_fields .= '<option value="' . esc_attr( $lfield['slug'] ) . '">' . esc_html( ucfirst( $lfield['display_name'] ) ) . '</option>';
								}
								//                                                /$form_fields .= '<option value="ticketmeta">Other Field</option>';
								$form_fields .= '</select>';
								echo balanceTags( $form_fields );
								?>
								</td>
								<td></td>
								<td class='rtlib-importer-dummy-data'
							    data-field-name="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( ( isset( $formdummydata[0][ $field['id'] ] ) ) ? $formdummydata[0][ $field['id'] ] : '' ); ?></td>
							</tr><?php
				} ?>
						</tbody><?php
			} ?>

						<tfoot>
				<?php echo apply_filters( 'rtlib_add_mapping_field_ui', $post_type );  ?>
							<tr>
								<td>
									Date Format
								</td>
								<td>
									<input type="text" value="" name="dateformat"/> <a
										href='http://www.php.net/manual/en/datetime.createfromformat.php' target='_blank'>Refrence</a>
								</td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td>
									Title Prefix
								</td>
								<td>
									<input type="text" value="" name="titleprefix"/>
								</td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td>
									Title Suffix
								</td>
								<td>
									<input type="text" value="" name="titlesuffix"/>
								</td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td>
									<?php
									$form_fields = '<select name="otherfield0" class="other-field">';
									$form_fields .= '<option value="">Select</option>';
									foreach ( $this->field_array[ $post_type ] as $lfield ) {
										if ( isset( $lfield['type'] ) && 'defined' == $lfield['type'] ) {
											continue;
										}
										$form_fields .= '<option value="' . esc_attr( $lfield['slug'] ) . '">' . esc_html( ucfirst( $lfield['display_name'] ) ) . '</option>';
									}
									$form_fields .= '</select>';
									echo balanceTags( $form_fields );
									?>
								</td>
								<td>
									<input type="text" value="" id="otherfield0"/>
								</td>
								<td></td>
								<td></td>
							</tr>
							<tr>
								<td>

								</td>
								<td>
									<label><input type="checkbox" value="" id="forceimport"/>Also Import previously Imported
										Entry(Duplicate)</label>
								</td>
								<td>

								</td>
								<td></td>
							</tr>
						</tfoot>
					</table>
					<script>
				var transaction_id =<?php echo esc_attr( time() ); ?>;
				var arr_map_fields =<?php echo json_encode( $this->field_array[ $post_type ] ); ?>;
				<?php if ( 'gravity' == $_REQUEST['type'] ) { ?>
					var arr_lead_id = <?php $this->get_all_gravity_lead( $form_id ); ?>;
				<?php } else {
					$jsonArray = array();
					$rCount = 0;
	foreach ( $csv->data as $cdata ) {
		$jsonArray[] = array( 'id' => $rCount++ );
	}
				?>
					var arr_lead_id = <?php echo json_encode( $jsonArray ); ?>;
				<?php } ?>
					</script>
					<input type="button" name="map_mapping_import" id="map_mapping_import" value="Import" class="button button-primary"/>
			</form>
			<div id='startImporting'>
				<h2> <?php _e( esc_attr( sprintf( 'Importing %s ...', isset( $formname ) ? $formname : '' ) ) ); ?></h2>
				<div id="progressbar"></div>
				<div class="myupdate">
					<p> <?php _e( 'Successfully imported :' ); ?> <span
							id='sucessfullyImported'>0</span></p>
				</div>
				<div class="myerror">
					<p> <?php _e( 'Failed to import :' ); ?> <span id='failImported'>0</span></p>
				</div>
				<div class="importloading"></div>
				<div class="sucessmessage">
				<?php if ( 'gravity' == $_REQUEST['type'] ) {
					_e( 'Would u like to import future entries automatically?' );?> &nbsp;
					<input type='button' id='futureYes' value='Yes' class="button button-primary"/>&nbsp;
					<input type='button' id='futureNo' value='No' class="button "/>
				<?php } else { ?>
					<h3><?php _e( 'Done !' ); ?></h3>
					<span id="extra-data-importer"></span>
				<?php } ?>
				</div>

			</div><?php
			die();
		}

		function rtlib_map_import_callback(){

			if ( ! isset( $_REQUEST['gravity_lead_id'] ) ) {
				echo json_encode( array( array( 'status' => false ) ) );
				die( 0 );
			}
			global $bulkimport;
			$bulkimport         = true;
			$map_index_lead_id  = $_REQUEST['gravity_lead_id'];
			$map_source_form_id = $_REQUEST['map_form_id'];
			$map_data           = $_REQUEST['map_data'];
			if ( isset( $_REQUEST['forceimport'] ) && 'false' == $_REQUEST['forceimport'] ) {
				$forceImport = false;
			} else {
				$forceImport = true;
			}
			global $transaction_id;
			$transaction_id = $_REQUEST['trans_id'];
			$type           = $_REQUEST['mapSourceType'];
			return do_action( 'rtlib_map_import_callback', $map_data, $map_source_form_id, $map_index_lead_id, $type, $forceImport );
		}

		public function rtlib_add_custome_field( $data ){

			global $rtlib_gravity_fields_mapping_model;
			$form_id       = $data['id'];
			$form_mappings = $rtlib_gravity_fields_mapping_model->get_mapping( $form_id );

			$data = apply_filters( 'rtlib_gform_add_custome_field', $data, $form_mappings );

			return $data;
		}

		public function gravity_form_lead_meta( $form_id, $gr_lead ){
			global $rtlib_gravity_fields_mapping_model;
			$gr_lead_id = absint( $gr_lead['id'] );
			$mappings   = $rtlib_gravity_fields_mapping_model->get_mapping( $form_id );

			do_action( 'rtlib_gravity_form_lead_meta', $gr_lead_id, $mappings );

		}

		public function rtlib_auto_import( $lead, $form ){
			//gform_after_submission
			global $rtlib_gravity_fields_mapping_model;
			$form_id       = $form['id'];
			$form_mappings = $rtlib_gravity_fields_mapping_model->get_mapping( $form_id );
			foreach ( $form_mappings as $fm ) {
				$map_data = maybe_unserialize( $fm->mapping );
				if ( ! empty( $map_data ) && 'yes' == $fm->enable ) {
					global $gravity_auto_import;
					$gravity_auto_import = true;
					$forceImport         = false;
					$gravity_lead_id     = $lead['id'];
					$type                = 'gravity';

					do_action( 'rtlib_map_import_callback', $map_data, $form_id, $gravity_lead_id, $type, $forceImport, false );
				}
			}
		}

		/**
		 * get random gravity data
		 *
		 * @since
		 */
		public function get_random_gravity_data() {
			//mapSourceType

			header( 'Content-Type: application/json' );
			$form_id = $_REQUEST['map_form_id'];
			if ( isset( $_REQUEST['mapSourceType'] ) && 'gravity' == $_REQUEST['mapSourceType'] ) {
				$lead_id       = intval( $_REQUEST['dummy_lead_id'] );
				$formdummydata = RGFormsModel::get_lead( $lead_id );

				foreach ( $formdummydata as $key => $val ) {
					if ( false !== ( strpos( strval( $key ), '.' ) ) ) {
						$pieces = explode( '.', $key );

						if ( ! isset( $formdummydata[ intval( $pieces[0] ) ] ) ) {
							$formdummydata[ intval( $pieces[0] ) ] = '';
						}
						$formdummydata[ intval( $pieces[0] ) ] .= $val . ' ';
					}
				}
				echo json_encode( $formdummydata );
			} else {
				$lead_id = intval( $_REQUEST['dummy_lead_id'] );
				$csv     = new parseCSV();
				$csv->auto( $form_id );
				echo json_encode( $csv->data[ $lead_id ] );
			}
			die( 0 );
		}

		/**
		 * map import feature
		 *
		 * @since
		 */
		public function rtlib_map_import_feauture() {
			global $rtlib_gravity_fields_mapping_model;

			$response = array();
			header( 'Content-Type: application/json' );
			if ( ! isset( $_REQUEST['map_form_id'] ) ) {
				$response['status'] = false;
			} else {
				$form_id  = $_REQUEST['map_form_id'];
				$map_data = maybe_serialize( $_REQUEST['map_data'] );
				$post_type = $_REQUEST['mapPostType'];
				if ( isset( $this->post_type[ $post_type ] ) && ! empty( $this->post_type[ $post_type ]['module'] ) ){
					$module = $this->post_type[ $post_type ]['module'];
				} else {
					$module = '';
				}

				$mapping = $rtlib_gravity_fields_mapping_model->get_mapping( $form_id );
				if ( ! empty( $mapping ) ) {
					$data  = array(
						'mapping' => $map_data,
						'post_type' => $post_type,
						'module_id' => $module,);
					$where = array( 'form_id' => $form_id, );
					$rtlib_gravity_fields_mapping_model->update_mapping( $data, $where );
				} else {
					$data = array(
						'form_id' => $form_id,
						'mapping' => $map_data,
						'post_type' => $post_type,
						'module_id' => $module,
					);
					$rtlib_gravity_fields_mapping_model->add_mapping( $data );
				}
				$response['status'] = true;
			}
			echo json_encode( $response );
			die( 0 );
		}

		/**
		 * define map field value
		 *
		 * @since
		 */
		public function rtlib_defined_map_field_value() {
			$form_id = $_REQUEST['map_form_id'];
			if ( isset( $_REQUEST['mapSourceType'] ) && 'gravity' == $_REQUEST['mapSourceType'] ) {
				$field_id  = intval( $_REQUEST['field_id'] );
				$tableName = RGFormsModel::get_lead_details_table_name();
				global $wpdb;
				$result = $wpdb->get_results( $wpdb->prepare( "select distinct value from $tableName where form_id= %d and field_number = %d ", $form_id, $field_id ) );
			} else {
				$field_id = $_REQUEST['field_id'];
				$csv      = new parseCSV();
				$csv->auto( $form_id );
				$result   = array();
				$field_id = str_replace( '-s-', ' ', $field_id );
				foreach ( $csv->data as $cdt ) {
					$tmpArr = array( 'value' => $cdt[ $field_id ] );
					if ( ! in_array( $tmpArr, $result ) ) {
						$result[] = $tmpArr;
					}
					if ( count( $result ) > 15 ) {
						break;
					}
				}
			}
			header( 'Content-Type: application/json' );
			if ( count( $result ) < 15 ) {
				echo json_encode( $result );
			} else {
				echo json_encode( array() );
			}
			die( 0 );
		}

		/**
		 * function to handle lead meta
		 *
		 * @param $entry_id
		 * @param $meta_key
		 *
		 * @return bool|mixed
		 *
		 * @since
		 */
		public function gform_get_meta( $entry_id, $meta_key ) {
			global $wpdb, $_gform_lead_meta;

			//get from cache if available
			$cache_key = $entry_id . ' ' . $meta_key;
			if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
				return $_gform_lead_meta[ $cache_key ];
			}

			$table_name                     = RGFormsModel::get_lead_meta_table_name();
			$value                          = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table_name} WHERE lead_id=%d AND meta_key=%s", $entry_id, $meta_key ) );
			$meta_value                     = $value == null ? false : maybe_unserialize( $value );
			$_gform_lead_meta[ $cache_key ] = $meta_value;

			return $meta_value;
		}

		/**
		 * gform update meta
		 *
		 * @param $entry_id
		 * @param $meta_key
		 * @param $meta_value
		 *
		 * @since
		 */
		public function gform_update_meta( $entry_id, $meta_key, $meta_value ) {
			global $wpdb, $_gform_lead_meta;
			$table_name = RGFormsModel::get_lead_meta_table_name();

			$meta_value  = maybe_serialize( $meta_value );
			$meta_exists = gform_get_meta( $entry_id, $meta_key ) !== false;
			if ( $meta_exists ) {
				$wpdb->update( $table_name, array( 'meta_value' => $meta_value ), array(
					'lead_id'  => $entry_id,
					'meta_key' => $meta_key,
				), array( '%s' ), array( '%d', '%s' ) );
			} else {
				$wpdb->insert( $table_name, array(
					'lead_id'    => $entry_id,
					'meta_key'   => $meta_key,
					'meta_value' => $meta_value,
				), array( '%d', '%s', '%s' ) );
			}

			//updates cache
			$cache_key = $entry_id . '_' . $meta_key;
			if ( array_key_exists( $cache_key, $_gform_lead_meta ) ) {
				$_gform_lead_meta[ $cache_key ] = maybe_unserialize( $meta_value );
			}
		}

		/**
		 * gform delete meta
		 *
		 * @param        $entry_id
		 * @param string $meta_key
		 *
		 * @since
		 *
		 */
		public function gform_delete_meta( $entry_id, $meta_key = '' ) {
			global $wpdb, $_gform_lead_meta;
			$table_name  = RGFormsModel::get_lead_meta_table_name();
			$meta_filter = empty( $meta_key ) ? '' : $wpdb->prepare( 'AND meta_key=%s', $meta_key );

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE lead_id=%d {$meta_filter}", $entry_id ) );

			//clears cache.
			$_gform_lead_meta = array();
		}

		public function enqueue_scripts(){
			if ( ! wp_script_is( 'jquery-ui-progressbar' ) ) {
				wp_enqueue_script( 'jquery-ui-progressbar', '', array(
					'jquery-ui-widget',
					'jquery-ui-position',
				), '1.9.2' );
			}

			wp_enqueue_script( 'rt_importer', plugin_dir_url( __FILE__ ) . '/assets/rt_importer.js', array( 'jquery' ), null, true );
			wp_enqueue_script( 'rt_handlebars', plugin_dir_url( __FILE__ ) . '/assets/handlebars.js', array( 'jquery' ), null, true );

			wp_enqueue_style( 'jquery-ui-custom',  plugin_dir_url( __FILE__ ).'/assets/css/jquery-ui-1.9.2.custom.css' );
			wp_enqueue_style( 'importer-setting-css',  plugin_dir_url( __FILE__ ).'/assets/css/rt_importer.css' );
		}
	}
}
