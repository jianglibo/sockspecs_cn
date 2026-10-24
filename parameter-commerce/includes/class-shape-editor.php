<?php
/**
 * Native product editor for the shared specification records.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Native WooCommerce notices escape Failure messages.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Groups measurements by all non-color options, with explicit batch scope. */
final class Shape_Editor {
	const KEYS = array( 'leg_height_mm', 'foot_length_mm', 'pair_weight_g' );

	/**
	 * Read exact published records; never inherit another size's measurements.
	 *
	 * @param int $product Parent ID.
	 * @param int $variation Child ID or zero.
	 * @return array
	 */
	public static function records( $product, $variation ) {
		$out = array();
		foreach ( array_merge( self::KEYS, array( 'catalog_height_mm' ) ) as $key ) {
			$out[ $key ] = Repository::current_claim(
				array(
					'wc_product_id'   => $product,
					'wc_variation_id' => $variation,
					'metric_key'      => $key,
				)
			);
		}
		return $out;
	}

	/**
	 * Staff form groups. Different styles and cuff lengths always stay separate.
	 *
	 * @param \WC_Product $product Product.
	 * @return array
	 */
	public static function groups( $product ) {
		Repository::authorize();
		$groups = array();
		$ids    = $product->is_type( 'variable' ) ? $product->get_children() : array( 0 );
		foreach ( $ids as $id ) {
			$child = $id ? wc_get_product( $id ) : $product;
			if ( ! $child || 'trash' === $child->get_status() ) {
				continue;
			}
			$options                          = $id ? Size_Shape::option_attributes( $child ) : array();
			$key                              = md5( wp_json_encode( $options ) );
			$groups[ $key ]['label']          = $options ? wp_strip_all_tags( wc_get_formatted_variation( $options, true, false ) ) : __( '商品整体', 'parameter-commerce' );
			$groups[ $key ]['members'][ $id ] = array(
				'label'      => $id ? wp_strip_all_tags( wc_get_formatted_variation( $child, true, false ) ) : __( '商品整体', 'parameter-commerce' ),
				'attributes' => $child->get_attributes(),
				'records'    => self::records( $product->get_id(), $id ),
			);
		}
		foreach ( $groups as &$group ) {
			foreach ( self::KEYS as $metric ) {
				$values = array();
				foreach ( $group['members'] as $member ) {
					$record = $member['records'][ $metric ];
					if ( 'leg_height_mm' === $metric && ! $record ) {
						$record = $member['records']['catalog_height_mm'];
					}
					$values[] = $record ? self::display_value( $metric, $record['value_num'] ) : '';
				}
				$group['values'][ $metric ] = count( array_unique( $values ) ) === 1 ? $values[0] : '';
				$group['mixed'][ $metric ]  = count( array_unique( $values ) ) > 1;
			}
		}
		unset( $group );
		return $groups;
	}

	/**
	 * Centimeters in the editor; the dictionary remains millimeters.
	 *
	 * @param string $key Metric.
	 * @param mixed  $value Dictionary value.
	 * @return string
	 */
	private static function display_value( $key, $value ) {
		return wc_format_decimal( (float) $value / ( 'pair_weight_g' === $key ? 1 : 10 ), 2, true );
	}

	/**
	 * Bind a rendered form to exact scopes and record revisions.
	 *
	 * @param int   $id Product ID.
	 * @param array $groups Form data.
	 * @return string
	 */
	public static function token( $id, array $groups ) {
		return hash_hmac( 'sha256', $id . ':' . wp_json_encode( $groups ), wp_salt( 'nonce' ) );
	}

	/** Native General tab fields. @return void */
	public static function settings() {
		global $product_object;
		if ( ! $product_object || ! $product_object->get_id() || ! current_user_can( 'publish_pc_claims' ) ) {
			return;
		}
		$groups      = self::groups( $product_object );
		$attempt_key = 'pc_shape_attempt_' . get_current_user_id() . '_' . $product_object->get_id();
		$attempt     = get_transient( $attempt_key );
		delete_transient( $attempt_key );
		$attempt = is_array( $attempt ) ? $attempt : array();
		$token   = $attempt['pc_shape_token'] ?? self::token( $product_object->get_id(), $groups );

		echo '<style>
		#woocommerce-product-data .pc-shape-editor { padding: 12px; }
		#woocommerce-product-data .pc-shape-editor p { padding: 0 !important; margin: 12px 0; }
		#woocommerce-product-data .pc-shape-editor label,
		#woocommerce-product-data .pc-shape-editor legend { float: none; width: auto; margin: 0 0 6px; display: block; }
		#woocommerce-product-data .pc-shape-editor fieldset { min-width: 0; margin: 16px 0 !important; }
		#woocommerce-product-data .pc-shape-editor input:not([type=checkbox]):not([type=hidden]),
		#woocommerce-product-data .pc-shape-editor select,
		#woocommerce-product-data .pc-shape-editor textarea { float: none; width: 100%; max-width: 320px; }
		#woocommerce-product-data .pc-shape-editor input[type=checkbox] { float: none; margin: 0 6px 0 0; }
		#woocommerce-product-data .pc-shape-editor .description { display: block; margin: 6px 0; }
		#woocommerce-product-data .pc-shape-editor summary { cursor: pointer; }
		</style>';
		echo '<div class="options_group pc-shape-editor"><p><strong>' . esc_html__( '尺码与外形 · 每双净重', 'parameter-commerce' ) . '</strong></p>';
		echo '<p>' . esc_html__( '更新商品时，同步保存并发布修改的参数。留空不删除原值。长度用厘米，净重用克。', 'parameter-commerce' ) . '</p>';
		wp_nonce_field( 'pc_shape_edit_' . $product_object->get_id(), 'pc_shape_nonce' );
		echo '<input type="hidden" name="pc_shape_token" value="' . esc_attr( $token ) . '">';
		foreach ( $groups as $key => $group ) {
			echo '<fieldset style="margin:16px;padding:12px;border:1px solid #ddd"><legend><strong>' . esc_html( $group['label'] ) . '</strong></legend>';
			foreach ( array(
				'leg_height_mm'  => '袜筒长度（cm）',
				'foot_length_mm' => '袜底长度（cm）',
				'pair_weight_g'  => '每双净重（g）',
			) as $metric => $label ) {
				woocommerce_wp_text_input(
					array(
						'id'                => 'pc_shape_' . $key . '_' . $metric,
						'name'              => 'pc_shape_rows[' . $key . '][' . $metric . ']',
						'label'             => $label,
						'value'             => $attempt['pc_shape_rows'][ $key ][ $metric ] ?? $group['values'][ $metric ],
						'type'              => 'number',
						'placeholder'       => $group['mixed'][ $metric ] ? __( '颜色间有差异，展开查看', 'parameter-commerce' ) : __( '未填写', 'parameter-commerce' ),
						'custom_attributes' => array(
							'min'  => '0.01',
							'max'  => 'pair_weight_g' === $metric ? '2000' : '100',
							'step' => '0.01',
						),
					)
				);
			}
			echo '<p><label><input type="checkbox" name="pc_shape_rows[' . esc_attr( $key ) . '][confirm]" value="1" ' . checked( ! empty( $attempt['pc_shape_rows'][ $key ]['confirm'] ), true, false ) . '> ' . esc_html__( '本行修改适用于该尺码／款式下所有已列颜色', 'parameter-commerce' ) . '</label></p>';
			echo '<p><label><input type="checkbox" name="pc_shape_rows[' . esc_attr( $key ) . '][refresh]" value="1" ' . checked( ! empty( $attempt['pc_shape_rows'][ $key ]['refresh'] ), true, false ) . '> ' . esc_html__( '数值不变，但更新本行已填参数的来源和测量记录', 'parameter-commerce' ) . '</label></p>';
			echo '<details><summary>' . esc_html__( '查看颜色及当前记录', 'parameter-commerce' ) . '</summary><ul style="margin:12px">';
			foreach ( $group['members'] as $id => $member ) {
				echo '<li>' . esc_html( $member['label'] ) . ' — ';
				foreach ( array( 'leg_height_mm', 'catalog_height_mm', 'foot_length_mm', 'pair_weight_g' ) as $metric ) {
					$record = $member['records'][ $metric ];
					if ( $record ) {
						$url = add_query_arg(
							array(
								'page'     => 'pc-specs',
								'claim_id' => $record['id'],
							),
							admin_url( 'admin.php' )
						);
						echo '<a href="' . esc_url( $url ) . '">' . esc_html( Metrics::defaults()[ $metric ]['label'] . ': ' . self::display_value( $metric, $record['value_num'] ) . ( 'pair_weight_g' === $metric ? ' g' : ' cm' ) ) . '</a> ';
					}
				}
				echo '</li>';
			}
			echo '</ul></details></fieldset>';
		}
		woocommerce_wp_select(
			array(
				'id'      => 'pc_shape_source',
				'value'   => $attempt['pc_shape_source'] ?? '',
				'label'   => __( '本次修改来源', 'parameter-commerce' ),
				'options' => array(
					''                  => __( '请选择', 'parameter-commerce' ),
					'platform_measured' => __( '本店样品实测', 'parameter-commerce' ),
					'supplier_declared' => __( '厂家资料', 'parameter-commerce' ),
				),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => 'pc_shape_date',
				'label' => __( '测量／资料日期', 'parameter-commerce' ),
				'type'  => 'date',
				'value' => $attempt['pc_shape_date'] ?? current_time( 'Y-m-d' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'                => 'pc_shape_count',
				'label'             => __( '实测样品双数', 'parameter-commerce' ),
				'type'              => 'number',
				'value'             => $attempt['pc_shape_count'] ?? '1',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'description'       => __( '每行实际测量的样品数；厂家资料无需填写。', 'parameter-commerce' ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => 'pc_shape_note',
				'value'       => $attempt['pc_shape_note'] ?? '',
				'label'       => __( '测量／资料说明（选填）', 'parameter-commerce' ),
				'description' => __( '可补充实测颜色、测量工具、厂家资料名称或特殊情况。', 'parameter-commerce' ),
			)
		);
		echo '<p><label><input type="checkbox" name="pc_shape_protocol" value="1" ' . checked( ! empty( $attempt['pc_shape_protocol'] ), true, false ) . '> ' . esc_html__( '本次实测：干燥、自然平铺不拉伸；袜筒从后跟分界线到袜口，不含脚跟弧度；袜底从后跟最后端至袜尖；重量为一双，不含包装。', 'parameter-commerce' ) . '</label></p>';
		echo '<p>' . esc_html__( '厂家袜筒数据仍按目录尺寸保存，不会自动标为上述实测口径。单一颜色的例外可在“商品参数”中编辑。', 'parameter-commerce' ) . '</p></div>';
	}

	/**
	 * Validate a complete native form and publish all changed scopes atomically.
	 *
	 * @param \WC_Product $product Product.
	 * @param array       $post Unslashed input.
	 * @return int Number of edited records.
	 * @throws Failure On stale form, permission or invalid input.
	 */
	public static function apply( $product, array $post ) {
		Repository::authorize( true );
		if ( ! current_user_can( 'edit_post', $product->get_id() ) || ! isset( $post['pc_shape_nonce'] ) || ! is_string( $post['pc_shape_nonce'] ) || ! wp_verify_nonce( $post['pc_shape_nonce'], 'pc_shape_edit_' . $product->get_id() ) ) {
			throw new Failure( array( 'permission' => __( '尺寸未保存：请重新打开商品编辑页。', 'parameter-commerce' ) ), 403 );
		}
		$groups = self::groups( $product );
		$rows   = $post['pc_shape_rows'] ?? array();
		if ( ! is_array( $rows ) || array_keys( $rows ) !== array_keys( $groups ) || ! is_string( $post['pc_shape_token'] ?? null ) || ! hash_equals( self::token( $product->get_id(), $groups ), $post['pc_shape_token'] ) ) {
			throw new Failure( array( 'revision' => __( '尺寸或变体已发生变化，请重新加载后再编辑。', 'parameter-commerce' ) ), 409 );
		}
		$changes = array();
		foreach ( $groups as $key => $group ) {
			foreach ( self::KEYS as $metric ) {
				$value = $rows[ $key ][ $metric ] ?? null;
				if ( ! is_string( $value ) ) {
					throw new Failure( array( 'value' => __( '尺寸表单不完整，请重新加载。', 'parameter-commerce' ) ) );
				}
				if ( '' === $value ) {
					continue;
				}
				if ( ! preg_match( '/^\d{1,4}(?:\.\d{1,2})?$/D', $value ) || (float) $value <= 0 || (float) $value > ( 'pair_weight_g' === $metric ? 2000 : 100 ) ) {
					throw new Failure( array( 'value' => __( '长度请输入0到100厘米之间的正数，重量请输入0到2000克之间的正数，最多两位小数。', 'parameter-commerce' ) ) );
				}
				if ( ! $group['mixed'][ $metric ] && '' !== $group['values'][ $metric ] && (float) $value === (float) $group['values'][ $metric ] && empty( $rows[ $key ]['refresh'] ) ) {
					continue;
				}
				if ( empty( $rows[ $key ]['confirm'] ) ) {
					throw new Failure( array( 'scope' => __( '请确认修改适用于本行已列颜色。', 'parameter-commerce' ) ) );
				}
				$changes[] = array( $key, $metric, $value );
			}
		}
		if ( ! $changes ) {
			return 0;
		}
		$source = $post['pc_shape_source'] ?? '';
		$date   = $post['pc_shape_date'] ?? '';
		$note   = $post['pc_shape_note'] ?? '';
		if ( ! in_array( $source, array( 'platform_measured', 'supplier_declared' ), true ) || ! is_string( $date ) || ! is_string( $note ) ) {
			throw new Failure( array( 'source' => __( '请选择本次修改的来源并填写日期。', 'parameter-commerce' ) ) );
		}
		$measured = 'platform_measured' === $source;
		if ( $measured && ( empty( $post['pc_shape_protocol'] ) || ! is_string( $post['pc_shape_count'] ?? null ) || ! preg_match( '/^[1-9]\d{0,4}$/D', $post['pc_shape_count'] ) ) ) {
			throw new Failure( array( 'method' => __( '请确认实测方法并填写实际测量的样品双数。', 'parameter-commerce' ) ) );
		}
		$plans = array();
		foreach ( $changes as list( $key, $metric, $value ) ) {
			$target = ! $measured && 'leg_height_mm' === $metric ? 'catalog_height_mm' : $metric;
			foreach ( $groups[ $key ]['members'] as $id => $member ) {
				$conditions = array(
					'basis'         => $measured ? 'manual_sample' : 'supplier_document',
					'applicability' => 'listed_variations',
					'group_options' => Size_Shape::option_attributes( $id ? wc_get_product( $id ) : $product ),
				);
				if ( $measured ) {
					$conditions['state']          = 'dry_relaxed';
					$conditions['leg_endpoints']  = 'heel_leg_boundary_to_cuff_excluding_heel_curve';
					$conditions['foot_endpoints'] = 'rear_heel_to_toe_tip';
				}
				if ( 'pair_weight_g' === $metric ) {
					$conditions['packaging'] = 'excluded';
					$conditions['quantity']  = 'one_pair';
				}
				$input = array(
					'wc_product_id'   => (string) $product->get_id(),
					'wc_variation_id' => (string) $id,
					'metric_key'      => $target,
					'value'           => (string) ( (float) $value * ( 'pair_weight_g' === $metric ? 1 : 10 ) ),
					'unit'            => 'pair_weight_g' === $metric ? 'g' : 'mm',
					'source_type'     => $source,
					'measured_at'     => $date,
					'size_scope'      => $groups[ $key ]['label'],
					'note'            => $note,
					'conditions_json' => wp_json_encode( $conditions ),
				);
				if ( $measured ) {
					$input += array(
						'method_code'    => 'manual_flat_v1',
						'method_version' => '1',
						'sample_count'   => $post['pc_shape_count'],
					);
				}
				$evidence = array(
					'visibility'   => 'private',
					'public_title' => $measured ? '本店样品尺寸与净重记录' : '厂家尺寸与净重资料',
					'source_org'   => $measured ? '索克派' : '供应商',
					'note'         => 'Stored source SHA-256: ' . hash( 'sha256', wp_json_encode( $input ) ) . '. ' . $note,
				);
				Validator::publishable( Validator::claim( $input ), Validator::evidence( $evidence ) );
				$plans[] = array( $input, $evidence, $member['records'][ $target ] );
			}
		}
		$ids = array();
		foreach ( $groups as $group ) {
			$ids = array_merge( $ids, array_keys( $group['members'] ) );
		}
		return Repository::atomic_scopes(
			$product->get_id(),
			$ids,
			static function () use ( $product, $groups, $plans ) {
				if ( self::token( $product->get_id(), $groups ) !== self::token( $product->get_id(), self::groups( wc_get_product( $product->get_id() ) ) ) ) {
					throw new Failure( array( 'revision' => __( '尺寸或变体已发生变化，请重新加载后再编辑。', 'parameter-commerce' ) ), 409 );
				}
				foreach ( $plans as list( $input, $evidence, $old ) ) {
					$id       = (int) ( $old['id'] ?? 0 );
					$revision = (int) ( $old['revision'] ?? 0 );
					Repository::edit( $input, $evidence, $id, $revision, true, $id, $revision );
				}
				return count( $plans );
			}
		);
	}

	/**
	 * Native product update handler; other product fields keep their normal behavior.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	public static function save( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- apply() checks this product-bound nonce before writes.
		if ( ! isset( $_POST['pc_shape_nonce'] ) ) {
			return;
		}
		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- apply() verifies the product-bound nonce and capabilities.
			self::apply( $product, wp_unslash( $_POST ) );
		} catch ( Failure $error ) {
			if ( 422 === $error->status ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only retain shape fields after apply() has rejected validation; output uses native escaping.
				$attempt = array_intersect_key( wp_unslash( $_POST ), array_flip( array( 'pc_shape_token', 'pc_shape_rows', 'pc_shape_source', 'pc_shape_date', 'pc_shape_note', 'pc_shape_count', 'pc_shape_protocol' ) ) );
				foreach ( $attempt as $key => &$value ) {
					if ( 'pc_shape_rows' === $key && is_array( $value ) ) {
						foreach ( $value as &$row ) {
							$row = is_array( $row ) ? array_map( 'sanitize_text_field', $row ) : array();
						}
						unset( $row );
					} else {
						$value = sanitize_textarea_field( $value );
					}
				}
				unset( $value );
				set_transient( 'pc_shape_attempt_' . get_current_user_id() . '_' . $product->get_id(), $attempt, 300 );
			}

			\WC_Admin_Meta_Boxes::add_error( __( '尺寸与净重未保存：', 'parameter-commerce' ) . $error->getMessage() );
		}
	}
}
