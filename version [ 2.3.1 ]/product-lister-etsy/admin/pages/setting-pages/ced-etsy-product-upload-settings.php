	<div class="ced_etsy_heading">
		<?php echo esc_html_e( get_etsy_instuctions_html( 'Product Export Settings' ) ); ?>
		<div class="ced_etsy_child_element default_modal">
			<?php wp_nonce_field( 'global_settings', 'global_settings_submit' ); ?>
			
				
					<?php
					/**
					 * -------------------------------------
					 *  INCLUDING PRODUCT FIELDS ARRAY FILE
					 * -------------------------------------
					 */
					$this->shop_name = isset( $_GET['shop_name'] ) ? sanitize_text_field( wp_unslash( $_GET['shop_name'] ) ) : '';

					$ced_h           = Cedhandler::get_instance();
					$ced_h->dir_name = 'admin/partials/';
					$ced_h->ced_require( 'product-fields' );
					$product_field_instance = Ced_Etsy_Product_Fields::get_instance();
					$settings               = $product_field_instance->get_custom_products_fields();
					$requiredInAnyCase      = array( '_umb_id_type', '_umb_id_val', '_umb_brand' );
					$marketPlace            = 'ced_etsy_required_common';
					$productID              = 0;
					$categoryID             = '';
					$indexToUse             = 0;
					$attributes             = wc_get_attribute_taxonomies();
					$attr_options           = array();
					$added_meta_keys        = get_option( 'ced_etsy_selected_metakeys', array() );
					$added_meta_keys        = array_merge( $added_meta_keys, array( '_woocommerce_title', '_woocommerce_short_description', '_woocommerce_description' ) );
					$select_dropdown_html   = '';

					if ( $added_meta_keys && count( $added_meta_keys ) > 0 ) {
						foreach ( $added_meta_keys as $meta_key ) {
							$attr_options[ $meta_key ] = $meta_key;
						}
					}
					if ( ! empty( $attributes ) ) {
						foreach ( $attributes as $attributes_object ) {
							$attr_options[ 'umb_pattr_' . $attributes_object->attribute_name ] = $attributes_object->attribute_label;
						}
					}

					if ( ! empty( $settings ) ) {
						$ced_etsy_settings_category             = get_option( 'ced_etsy_settings_category', array() );
						$ced_etsy_settings_category['required'] = 'on';
						echo '<thead>';
						echo '<tr><td>';
						$settings_category = array_keys( $settings );
						echo '<p><b><i><u>Attributes to display</u></i> </b></p>';
						foreach ( $settings_category as $value ) {
							$checked  = '';
							$disabled = '';
							if ( isset( $ced_etsy_settings_category[ $value ] ) && 'on' == $ced_etsy_settings_category[ $value ] ) {
								$checked = 'checked';
							}
							if ( 'required' == $value ) {
								$disabled = 'disabled';
							}

							echo "<span class='setting_label'><span>" . esc_attr( strtoupper( $value ) ) . '</span>';
							echo "<span><label class='switch'><label class=''><input type='checkbox' class='ced_etsy_setting_sections' data-target='" . esc_attr( $value ) . "' name='ced_etsy_settings_category[" . esc_attr( $value ) . "]' " . esc_attr( $checked ) . ' ' . esc_attr( $disabled ) . '><span class="slider round"></span></label></label></span></span>';
						}
						echo '</td></tr>';
						echo '</thead>';
						$product_specific_attribute_key = get_option( 'ced_etsy_product_specific_attribute_key', array() );
						foreach ( $settings as $section => $product_fields ) {
							$style = '';
							if ( ! isset( $ced_etsy_settings_category[ $section ] ) ) {
								$style = 'display:none;';
							}
							echo "<table class='wp-list-table ced_etsy_global_settings' style='" . esc_attr( $style ) . "' id='" . esc_attr( $section ) . "' class='ced_etsy_setting_body'>";
							echo "<thead><tr class='ced_etsy_settings_label " . esc_attr( $section ) . "'>";

							echo '</tr></thead>';
							echo '<tbody>';
							?>
					<tr>
						<td class="setting_label <?php echo esc_attr( $section ); ?>"><b><?php echo esc_attr( ucwords( $section ) ); ?> Attributes</b></td>
						<td><b>Default Value</b></td>
							<?php
							if ( 'required' == $section ) {
								echo '<td></td>';
							} else {
								echo '<td><b>Pick Value From Custom field or Attribute</b></td>';
							}
							?>
						
					</tr>
							<?php
							foreach ( $product_fields as $field_data ) {

								$is_text = false;
								echo '<tr>';
								// Don't show category specifiction option
								if ( '_umb_etsy_category' == $field_data['id'] ) {
									continue;
								}

								$check    = false;
								$field_id = isset( $field_data['id'] ) ? $field_data['id'] : '';
								if ( empty( $product_specific_attribute_key ) ) {
									$product_specific_attribute_key = array( $field_id );
								} else {
									foreach ( $product_specific_attribute_key as $key => $product_key ) {
										if ( $product_key == $field_id ) {
											$check = true;
											break;
										}
									}
									if ( false == $check ) {
										$product_specific_attribute_key[] = $field_id;
									}
								}

								$ced_etsy_global_data = get_option( 'ced_etsy_global_settings', array() );
								if ( ! empty( $ced_etsy_global_data ) ) {
									$data = isset( $ced_etsy_global_data[ $this->shop_name ]['product_data'] ) ? $ced_etsy_global_data[ $this->shop_name ]['product_data'] : array();
								}
								update_option( 'ced_etsy_product_specific_attribute_key', $product_specific_attribute_key );
								echo '<tr class="form-field _umb_id_type_field ">';
								$label        = isset( $field_data['fields']['label'] ) ? $field_data['fields']['label'] : '';
								$field_id     = trim( $field_id, '_' );
								$category_id  = '';
								$product_id   = '';
								$market_place = 'ced_etsy_required_common';
								$description  = isset( $field_data['fields']['description'] ) ? $field_data['fields']['description'] : '';
								$required     = isset( $field_data['fields']['is_required'] ) ? (bool) $field_data['fields']['is_required'] : '';
								$index_to_use = 0;
								$default      = isset( $data[ $field_data['fields']['id'] ]['default'] ) ? $data[ $field_data['fields']['id'] ]['default'] : $field_data['fields']['default'];
								$field_value  = array(
									'case'  => 'profile',
									'value' => $default,
								);

								if ( '_text_input' == $field_data['type'] ) {
									$is_text = true;
									$product_field_instance->renderInputTextHTML( $field_id, $label, $category_id, $product_id, $market_place, $description, $index_to_use, $field_value, $required );

								} elseif ( '_select' == $field_data['type'] ) {
									$value_for_dropdown = $field_data['fields']['options'];
									$product_field_instance->renderDropdownHTML( $field_id, $label, $value_for_dropdown, $category_id, $product_id, $market_place, $description, $index_to_use, $field_value, $required );
								}
								echo '<td>';
								if ( $is_text ) {
									$previous_selected_value = 'null';
									if ( isset( $data[ $field_data['fields']['id'] ]['metakey'] ) && 'null' != $data[ $field_data['fields']['id'] ]['metakey'] ) {
										$previous_selected_value = $data[ $field_data['fields']['id'] ]['metakey'];
									}
									$select_id = $field_data['fields']['id'] . '_attribute_meta';
									?>
						<select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $select_id ); ?>">
							<option value="null" selected> -- select -- </option>
									<?php
									if ( is_array( $attr_options ) ) {
										foreach ( $attr_options as $attr_key => $attr_name ) :
											if ( trim( $previous_selected_value ) == $attr_key ) {
												$selected = 'selected';
											} else {
												$selected = '';
											}
											?>
									<option value="<?php echo esc_attr( $attr_key ); ?>"<?php echo esc_attr( $selected ); ?>><?php echo esc_attr( $attr_name ); ?></option>
											<?php
										endforeach;
									}
									?>
						</select>
									<?php
								}
								echo '</td>';
								echo '</tr>';

							}
							echo '</tbody>';
							echo '</table>';
						}
					}
					?>
			
	</div>
</div>
