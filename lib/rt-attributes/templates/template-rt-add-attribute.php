<?php
/**
 * Created by PhpStorm.
 * User: faishal
 * Date: 24/02/14
 * Time: 3:51 PM
 */
/**
 * Don't load this file directly!
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template : Add Attribute
 *
 * Created by PhpStorm.
 * User: udit
 * Date: 2/20/14
 * Time: 4:05 AM
 */
?>
<div class="wrap">
	<h2><i class="icon-tags"></i> <?php _e( 'Attributes' ); ?></h2>
	<br class="clear"/>

	<div id="col-container">
		<div id="col-right">
			<div class="col-wrap">
				<table class="widefat fixed" style="width:100%">
					<thead>
					<tr>
						<th scope="col"><?php _e( 'Name' ); ?></th>
						<th scope="col"><?php _e( 'Slug' ); ?></th>
						<?php if ( $this->storage_type_required ) { ?>
							<th scope="col"><?php _e( 'Store As' ); ?></th>
						<?php } ?>
						<?php if ( $this->render_type_required ) { ?>
							<th scope="col"><?php _e( 'Render Type' ); ?></th>
						<?php } ?>
						<?php if ( $this->orderby_required ) { ?>
							<th scope="col"><?php _e( 'Order by' ); ?></th>
						<?php } ?>
						<?php if ( ! empty( $this->post_type ) ) { ?>
							<th scope="col"><!-- Configure Terms --></th>
						<?php } ?>
					</tr>
					</thead>
					<tbody>
					<?php
					$attribute_taxonomies = $this->attributes_db_model->get_all_attributes();
					$relation_attr_ids    = array();
					if ( ! empty( $this->post_type ) ) {
						$relations = $this->attributes_relationship_model->get_relations_by_post_type( $this->post_type );
						foreach ( $relations as $relation ) {
							$relation_attr_ids[] = $relation->attr_id;
						}
					}
					if ( $attribute_taxonomies ) {
						foreach ( $attribute_taxonomies as $tax ) {
							if ( ! empty( $this->post_type ) && ! in_array( $tax->id, $relation_attr_ids ) ) {
								continue;
							}
							?>
							<tr>
								<td>
									<?php echo esc_html( $tax->attribute_label ); ?>
									<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( add_query_arg( 'edit', $tax->id ) ); ?>"><?php _e( 'Edit' ); ?></a> |
									</span>
									<span class="delete">
										<a class="delete"
										   href="<?php echo esc_url( add_query_arg( 'delete', $tax->id ) ); ?>"><?php _e( 'Delete' ); ?></a>
									</span>
									</div>
								</td>
								<td><?php echo esc_html( $tax->attribute_name ); ?></td>
								<?php if ( $this->storage_type_required ) { ?>
									<td><?php echo esc_html( ucwords( str_replace( '-', ' ', $tax->attribute_store_as ) ) ); ?></td>
								<?php } ?>
								<?php if ( $this->render_type_required ) { ?>
									<td><?php echo esc_html( ucwords( str_replace( '-', ' ', $tax->attribute_render_type ) ) ); ?></td>
								<?php } ?>
								<?php if ( $this->orderby_required ) { ?>
									<td>
										<?php
										switch ( $tax->attribute_orderby ) {
											case 'name' :
												_e( 'Name' );
												break;
											case 'id' :
												_e( 'Term ID' );
												break;
											default:
												_e( 'Custom ordering' );
												break;
										}
										?>
									</td>
								<?php } ?>
								<?php if ( ! empty( $this->post_type ) && 'taxonomy' === $tax->attribute_store_as ) { ?>
									<td>
										<a href="<?php echo esc_html( admin_url( 'edit-tags.php?taxonomy=' . $this->get_taxonomy_name( $tax->attribute_name ) . '&post_type=' . $this->post_type ) ); ?>"
										   class="button alignright configure-terms"><?php _e( 'Terms' ); ?></a>
									</td>
								<?php } ?>
							</tr>
							<?php
						}
					} else {
						?>
						<tr>
							<td><?php _e( 'No attributes currently exist.' ); ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<div id="col-left">
			<div class="col-wrap">
				<div class="form-wrap">
					<h3><?php _e( 'Add New Attribute' ) ?></h3>

					<p><?php _e( '' ); ?></p>

					<form action="" method="post">
						<div class="form-field">
							<label for="attribute_label"><?php _e( 'Name' ); ?></label>
							<input name="attribute_label" id="attribute_label" type="text" value=""/>

							<p class="description"><?php _e( 'Name for the attribute (shown on the front-end).' ); ?></p>
						</div>

						<div class="form-field">
							<label for="attribute_name"><?php _e( 'Slug' ); ?></label>
							<input name="attribute_name" id="attribute_name" type="text" value="" maxlength="28"/>

							<p class="description"><?php _e( 'Unique slug/reference for the attribute; must be shorter than 28 characters.' ); ?></p>
						</div>
						<?php if ( $this->storage_type_required ) { ?>
							<div class="form-field">
								<label for="attribute_store_as"><?php _e( 'Store As' ); ?></label>
								<select name="attribute_store_as" id="attribute_store_as">
									<option value="taxonomy"><?php _e( 'Taxonomy' ); ?></option>
									<option value="meta"><?php _e( 'Meta Value' ); ?></option>
									<?php do_action( 'rt_wp_attributes_admin_attribute_store_as' ); ?>
								</select>

								<p class="description"><?php _e( 'Determines the sort order on the frontend for this attribute.' ); ?></p>
							</div>
						<?php } ?>
						<?php if ( $this->render_type_required ) { ?>
							<div class="form-field">
								<label for="attribute_render_type"><?php _e( 'Render Type' ); ?></label>
								<select name="attribute_render_type" id="attribute_render_type">
									<optgroup label="Taxonomy">
										<option value="autocomplete"><?php _e( 'Autocomplete' ); ?></option>
										<option value="dropdown"><?php _e( 'Dropdown' ); ?></option>
										<option value="checklist"><?php _e( 'Checklist' ); ?></option>
										<option value="radio"><?php _e( 'Radio' ); ?></option>
										<option value="rating-stars"><?php _e( 'Rating Stars' ); ?></option>
									</optgroup>
									<optgroup label="Meta">
										<option value="date"><?php _e( 'Date' ); ?></option>
										<option value="datetime"><?php _e( 'Date & Time' ); ?></option>
										<option value="currency"><?php _e( 'Currency' ); ?></option>
										<option value="text"><?php _e( 'Text' ); ?></option>
										<option value="richtext"><?php _e( 'Rich Text' ); ?></option>
									</optgroup>
									<?php do_action( 'rt_wp_attributes_admin_attribute_render_types' ); ?>
								</select>

								<p class="description"><?php _e( 'Determines the sort order on the frontend for this attribute.' ); ?></p>
							</div>
						<?php } ?>
						<?php if ( $this->orderby_required ) { ?>
							<div class="form-field">
								<label for="attribute_orderby"><?php _e( 'Default sort order' ); ?></label>
								<select name="attribute_orderby" id="attribute_orderby">
									<option value="menu_order"><?php _e( 'Custom ordering' ); ?></option>
									<option value="name"><?php _e( 'Name' ); ?></option>
									<option value="id"><?php _e( 'Term ID' ); ?></option>
								</select>

								<p class="description"><?php _e( 'Determines the sort order on the frontend for this attribute.' ); ?></p>
							</div>
						<?php } ?>
						<?php if ( ! empty( $this->post_type ) ) { ?>
							<input type="hidden" name="attribute_post_types[]"
							       value="<?php echo esc_html( $this->post_type ); ?>"/>
						<?php } else { ?>
							<div>
								<label for="attribute_post_types"><?php _e( 'Post Types' ); ?></label>
								<?php $all_post_types = get_post_types( '', 'objects' ); ?>
								<?php foreach ( $all_post_types as $pt ) { ?>
									<label><input type="checkbox" name="attribute_post_types[]"
									              value="<?php echo esc_html( $pt->name ); ?>"/><?php echo esc_html( $pt->labels->name ); ?>
									</label>
								<?php } ?>

								<p class="description"><?php _e( 'Determines the mapping between post types and attribute.' ); ?></p>
							</div>
						<?php } ?>
						<p class="submit"><input type="submit" name="add_new_attribute" id="submit" class="button"
						                         value="<?php _e( 'Add Attribute' ); ?>"></p>
						<?php //nonce ?>
					</form>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		/* <![CDATA[ */

		jQuery('a.delete').click(function () {
			//noinspection UnnecessaryLocalVariableJS
			var answer = confirm("<?php _e( 'Are you sure you want to delete this attribute?' ); ?>");
			return answer;
		});

		/* ]]> */
	</script>
</div>
