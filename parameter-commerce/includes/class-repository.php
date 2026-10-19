<?php
/**
 * Authorized claim/evidence operations with optimistic revisions and audit.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Structured errors are escaped by the admin controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- These custom-table writes and locking reads must bypass caches.
/** All mutation entry points enforce authorization independently of the UI. */
final class Repository {
	/**
	 * Require staff access and a usable schema/dependency.
	 *
	 * @param bool $publish Require publication capability too.
	 * @return void
	 * @throws Failure On forbidden access or unavailable storage.
	 */
	public static function authorize( $publish = false ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) || ( $publish && ! current_user_can( 'publish_pc_claims' ) ) ) {
			throw new Failure( array( 'permission' => __( 'You are not allowed to perform this operation.', 'parameter-commerce' ) ), 403 );
		}
		if ( ! Plugin::has_woocommerce() || Schema::VERSION !== (int) get_option( 'pc_schema_version', 0 ) ) {
			throw new Failure( array( 'schema' => __( 'WooCommerce and the current Parameter Commerce schema are required.', 'parameter-commerce' ) ), 503 );
		}
	}

	/**
	 * Get a staff-only claim including immutable evidence.
	 *
	 * @param int $id Claim ID.
	 * @return array
	 * @throws Failure If missing or unauthorized.
	 */
	public static function find( $id ) {
		self::authorize();
		return self::read( $id );
	}

	/**
	 * Bounded staff listing with optional product filter.
	 *
	 * @param int $product_id Product filter, zero for all.
	 * @param int $page Page number.
	 * @return array
	 */
	public static function listing( $product_id = 0, $page = 1 ) {
		self::authorize();
		global $wpdb;
		$offset = ( max( 1, min( 100000, (int) $page ) ) - 1 ) * 20;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE (%d = 0 OR wc_product_id = %d) ORDER BY id DESC LIMIT 21 OFFSET %d', $wpdb->prefix . 'pc_claims', $product_id, $product_id, $offset ), ARRAY_A );
	}

	/**
	 * Bounded immutable audit history for staff.
	 *
	 * @param int $id Claim ID.
	 * @param int $page Page number.
	 * @return array
	 */
	public static function history( $id, $page = 1 ) {
		self::authorize();
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE claim_id = %d ORDER BY id DESC LIMIT 21 OFFSET %d', $wpdb->prefix . 'pc_claim_audit', $id, ( max( 1, min( 100000, (int) $page ) ) - 1 ) * 20 ), ARRAY_A );
	}

	/**
	 * Prepare grouped specifications for one exact scope, for staff integration use.
	 *
	 * This M1 contract is not a public route or a storefront visibility decision.
	 * Only explicit model-wide declarations may inherit to variations. Numeric values remain
	 * decimal strings; absent values remain null with a separate display label.
	 *
	 * @param int $product_id WooCommerce parent or simple product ID.
	 * @param int $variation_id Zero for product-level records, otherwise an exact child.
	 * @return array Scope metadata and groups of registry-keyed specification rows.
	 * @throws Failure On invalid scope, unauthorized access or storage failure.
	 */
	public static function specifications( $product_id, $variation_id = 0 ) {
		self::authorize();
		return self::specifications_data( $product_id, $variation_id );
	}

	/**
	 * Public read boundary: visibility is checked on every request, even for staff.
	 *
	 * @param int $product_id Parent or simple product.
	 * @param int $variation_id Exact variation, or zero for parent scope.
	 * @return array
	 * @throws Failure When dependencies or public scope are unavailable.
	 */
	public static function public_specifications( $product_id, $variation_id = 0 ) {
		if ( ! Plugin::has_woocommerce() || Schema::VERSION !== (int) get_option( 'pc_schema_version', 0 ) ) {
			throw new Failure( array( 'specifications' => __( 'Specifications are temporarily unavailable. Please try again.', 'parameter-commerce' ) ), 503 );
		}
		if ( ! Public_Specifications::visible( $product_id, $variation_id ) ) {
			throw new Failure( array( 'scope' => __( 'Specifications are not available for this selection.', 'parameter-commerce' ) ), 404 );
		}
		return self::specifications_data( $product_id, $variation_id );
	}

	/**
	 * Shared projection; callers must enforce staff or public authorization first.
	 *
	 * @param int $product_id Parent product.
	 * @param int $variation_id Exact child or zero.
	 * @return array
	 * @throws Failure When scope or storage is invalid.
	 */
	private static function specifications_data( $product_id, $variation_id ) {
		foreach ( array( $product_id, $variation_id ) as $id ) {
			if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (float) $id > 2147483647 ) {
				throw new Failure( array( 'scope' => __( 'Invalid record ID or revision.', 'parameter-commerce' ) ) );
			}
		}
		$product_id   = (int) $product_id;
		$variation_id = (int) $variation_id;
		$product      = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || $product->is_type( 'variation' ) || 'trash' === $product->get_status() ) {
			throw new Failure( array( 'wc_product_id' => __( 'Choose an existing WooCommerce parent or simple product.', 'parameter-commerce' ) ) );
		}
		$scope_product = $product;
		if ( $variation_id ) {
			$scope_product = wc_get_product( $variation_id );
			if ( ! $product->is_type( 'variable' ) || ! $scope_product || ! $scope_product->is_type( 'variation' ) || (int) $scope_product->get_parent_id() !== $product_id || 'trash' === $scope_product->get_status() ) {
				throw new Failure( array( 'wc_variation_id' => __( 'The variation must belong to the selected product.', 'parameter-commerce' ) ) );
			}
		}
		$definitions = Metrics::all();
		$slots       = array();
		foreach ( array_keys( $definitions ) as $key ) {
			$slots[] = $product_id . ':' . $variation_id . ':' . $key;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $slots ), '%s' ) );
		// Use the unique publication-slot index: at most one row per registered metric.
		// Never select the protected evidence source URL, organization or internal notes.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Prepared arguments are merged arrays. Only generated %s placeholders are interpolated; all values are prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.*, e.public_title, e.visibility, e.public_url, e.attachment_id FROM %i c INNER JOIN %i e ON e.id = c.evidence_id WHERE c.published_slot IN ($placeholders) AND c.wc_product_id = %d AND c.wc_variation_id = %d AND c.status = %s AND c.verified_by > 0 AND c.published_at IS NOT NULL AND (c.value_num IS NOT NULL OR c.value_enum IS NOT NULL)", array_merge( array( $wpdb->prefix . 'pc_claims', $wpdb->prefix . 'pc_evidence' ), $slots, array( $product_id, $variation_id, 'published' ) ) ), ARRAY_A );
		if ( $wpdb->last_error ) {
			throw new Failure( array( 'database' => __( 'Specifications could not be loaded.', 'parameter-commerce' ) ), 503 );
		}
		$claims = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $definitions[ $row['metric_key'] ] ) || $row['published_slot'] !== $product_id . ':' . $variation_id . ':' . $row['metric_key'] ) {
				continue;
			}
			$row['evidence']              = array_intersect_key( $row, array_flip( array( 'public_title', 'visibility', 'public_url', 'attachment_id' ) ) );
			$claims[ $row['metric_key'] ] = $row;
		}
		$groups = array();
		foreach ( array(
			'composition'  => __( 'Material composition', 'parameter-commerce' ),
			'material'     => __( 'Fibres & yarns', 'parameter-commerce' ),
			'construction' => __( 'Construction', 'parameter-commerce' ),
			'measurement'  => __( 'Physical measurements', 'parameter-commerce' ),
			'performance'  => __( 'Performance metrics', 'parameter-commerce' ),
		) as $key => $label ) {
			$groups[ $key ] = array(
				'label'   => $label,
				'metrics' => array(),
			);
		}
		$source_labels = array(
			'supplier_declared' => __( 'Manufacturer-declared', 'parameter-commerce' ),
			'platform_measured' => __( 'Platform-measured', 'parameter-commerce' ),
			'third_party_lab'   => __( 'Third-party-tested', 'parameter-commerce' ),
			'supplier_report'   => __( 'Supplier-provided laboratory report', 'parameter-commerce' ),
		);
		foreach ( $definitions as $key => $definition ) {
			$claim = $claims[ $key ] ?? null;
			if ( $claim && ! isset( $source_labels[ $claim['source_type'] ] ) ) {
				$claim = null;
			}
			$groups[ $definition['category'] ]['metrics'][ $key ] = array(
				'key'           => $key,
				'label'         => $definition['label'],
				'type'          => $definition['type'],
				'help'          => $definition['help'] ?? '',
				'value'         => $claim ? ( $claim['value_num'] ?? $claim['value_enum'] ) : null,
				'unit'          => $claim ? $claim['unit'] : $definition['unit'],
				'availability'  => $claim ? 'available' : 'not_yet_measured',
				'missing_label' => __( 'Not yet measured', 'parameter-commerce' ),
				'source_label'  => $claim ? $source_labels[ $claim['source_type'] ] : null,
				'record'        => $claim ? self::preview( $claim ) : null,
			);
		}
		$inherited = false;
		if ( $variation_id ) {
			$parent_spec = self::specifications_data( $product_id, 0 );
			foreach ( $parent_spec['groups'] as $group_key => $group ) {
				foreach ( $group['metrics'] as $key => $metric ) {
					if ( null === $groups[ $group_key ]['metrics'][ $key ]['value'] && self::model_wide( $metric['record'] ) ) {
						$groups[ $group_key ]['metrics'][ $key ] = $metric;
						$inherited                               = true;
					}
				}
			}
		}
		return array(
			'wc_product_id'           => $product_id,
			'wc_variation_id'         => $variation_id,
			'scope_type'              => $variation_id ? 'variation' : 'product',
			'sku'                     => $scope_product->get_sku(),
			'inherits_parent_records' => $inherited,
			'groups'                  => $groups,
		);
	}

	/**
	 * Only explicit model-wide declarations can apply to a selected variation.
	 *
	 * @param array|null $record Safe record or database claim.
	 * @return bool
	 */
	public static function model_wide( $record ) {
		if ( ! $record || 'supplier_declared' !== $record['source_type'] || (int) $record['wc_variation_id'] ) {
			return false;
		}
		$definition = Metrics::defaults()[ $record['metric_key'] ] ?? array();
		$conditions = json_decode( $record['conditions_json'], true );
		return in_array( $definition['category'] ?? '', array( 'composition', 'construction', 'material' ), true ) && 'all_variations' === ( $conditions['applicability'] ?? '' );
	}

	/**
	 * Create or edit a draft. Client revisions are mandatory for updates.
	 *
	 * @param array $input Claim fields.
	 * @param array $evidence Evidence fields.
	 * @param int   $id Zero for a new claim.
	 * @param int   $revision Expected revision when editing.
	 * @return int Claim ID.
	 * @throws Failure On invalid input, conflict or write failure.
	 */
	public static function save( array $input, array $evidence, $id = 0, $revision = 0 ) {
		self::authorize();
		$data          = Validator::claim( $input );
		$evidence_data = Validator::evidence( $evidence );
		return self::transaction(
			$data['wc_product_id'],
			$data['wc_variation_id'],
			static function () use ( $data, $evidence_data, $id, $revision ) {
				global $wpdb;
				$before = $id ? self::read( $id, true ) : null;
				if ( $before ) {
					self::expect( $before, $revision );
					if ( 'draft' !== $before['status'] ) {
						throw new Failure( array( 'status' => __( 'Only drafts can be edited. Return a reviewed claim to draft, or create a revision of a published claim.', 'parameter-commerce' ) ), 409 );
					}
					foreach ( array( 'wc_product_id', 'wc_variation_id', 'metric_key' ) as $field ) {
						if ( (string) $before[ $field ] !== (string) $data[ $field ] ) {
							throw new Failure( array( $field => __( 'A saved claim’s product, variation, and metric cannot be changed. Create a new claim.', 'parameter-commerce' ) ), 409 );
						}
					}
				}
				$now = current_time( 'mysql', true );
				self::written(
					$wpdb->insert(
						$wpdb->prefix . 'pc_evidence',
						$evidence_data + array(
							'created_by' => get_current_user_id(),
							'created_at' => $now,
						)
					)
				);
				$data['evidence_id'] = (int) $wpdb->insert_id;
				$data['updated_at']  = $now;
				$data['revision']    = $before ? (int) $before['revision'] + 1 : 1;
				if ( $before ) {
					self::written(
						$wpdb->update(
							$wpdb->prefix . 'pc_claims',
							$data,
							array(
								'id'       => $id,
								'revision' => $revision,
							)
						)
					);
				} else {
					self::written( $wpdb->insert( $wpdb->prefix . 'pc_claims', $data + array( 'created_at' => $now ) ) );
					$id = (int) $wpdb->insert_id;
				}
				self::audit( $id, $before ? 'draft_updated' : 'draft_created', $before, self::read( $id ) );
				return $id;
			}
		);
	}

	/**
	 * Copy an immutable published/retracted claim into a new draft.
	 *
	 * @param int $id Source claim.
	 * @param int $revision Expected source revision.
	 * @return int New draft ID.
	 */
	public static function fork( $id, $revision ) {
		self::authorize();
		$source = self::read( $id );
		return self::transaction(
			$source['wc_product_id'],
			$source['wc_variation_id'],
			static function () use ( $id, $revision ) {
				global $wpdb;
				$source = self::read( $id, true );
				self::expect( $source, $revision );
				if ( ! in_array( $source['status'], array( 'published', 'retracted' ), true ) ) {
					throw new Failure( array( 'status' => __( 'Create revisions only from published or retracted claims.', 'parameter-commerce' ) ), 409 );
				}
				$copy = $source;
				unset( $copy['id'], $copy['evidence'] );
				$copy['supersedes_id']       = $id;
				$copy['supersedes_revision'] = $revision;
				$copy['revision']            = 1;
				$copy['status']              = 'draft';
				$copy['published_slot']      = null;
				$copy['published_at']        = null;
				$copy['verified_by']         = null;
				$copy['created_at']          = current_time( 'mysql', true );
				$copy['updated_at']          = $copy['created_at'];
				self::written( $wpdb->insert( $wpdb->prefix . 'pc_claims', $copy ) );
				$new_id = (int) $wpdb->insert_id;
				self::audit( $new_id, 'revision_created', $source, self::read( $new_id ) );
				return $new_id;
			}
		);
	}

	/**
	 * Review, return to draft, publish or retract with an explicit revision.
	 *
	 * @param int    $id Claim ID.
	 * @param int    $revision Expected revision.
	 * @param string $target Target status.
	 * @return int Claim ID.
	 */
	public static function transition( $id, $revision, $target ) {
		self::authorize( in_array( $target, array( 'published', 'retracted' ), true ) );
		$source = self::read( $id );
		return self::transaction(
			$source['wc_product_id'],
			$source['wc_variation_id'],
			static function () use ( $id, $revision, $target ) {
				global $wpdb;
				$before = self::read( $id, true );
				self::expect( $before, $revision );
				if ( in_array( $target, array( 'reviewed', 'published' ), true ) ) {
					// Revalidate against live WC scope and current evidence rules on every approval.
					$normalized = Validator::claim( $before + array( 'value' => $before['value_num'] ?? $before['value_enum'] ?? '' ) );
					$evidence   = Validator::evidence( $before['evidence'] );
					Validator::publishable( $normalized, $evidence );
				}
				$allowed = array(
					'draft'     => array( 'reviewed' ),
					'reviewed'  => array( 'draft', 'published' ),
					'published' => array( 'retracted' ),
					'retracted' => array(),
				);
				if ( ! in_array( $target, $allowed[ $before['status'] ], true ) ) {
					throw new Failure( array( 'status' => __( 'Invalid transition. Save draft → mark reviewed → publish. Published claims can only be retracted or revised.', 'parameter-commerce' ) ), 409 );
				}
				$changes = array(
					'status'     => $target,
					'revision'   => $revision + 1,
					'updated_at' => current_time( 'mysql', true ),
				);
				if ( 'reviewed' === $target ) {
					$changes['verified_by'] = get_current_user_id();
				}
				if ( 'draft' === $target ) {
					$changes['verified_by'] = null;
				}
				if ( 'published' === $target ) {
					$slot   = $before['wc_product_id'] . ':' . $before['wc_variation_id'] . ':' . $before['metric_key'];
					$active = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM %i WHERE published_slot = %s FOR UPDATE', $wpdb->prefix . 'pc_claims', $slot ), ARRAY_A );
					if ( $before['supersedes_id'] ) {
						$old = self::read( $before['supersedes_id'], true );
						self::expect( $old, $before['supersedes_revision'] );
						if ( $active && (int) $active['id'] !== (int) $old['id'] ) {
							self::conflict();
						}
						if ( 'published' === $old['status'] ) {
							self::written(
								$wpdb->update(
									$wpdb->prefix . 'pc_claims',
									array(
										'status'         => 'retracted',
										'published_slot' => null,
										'revision'       => (int) $old['revision'] + 1,
										'updated_at'     => $changes['updated_at'],
									),
									array( 'id' => $old['id'] )
								)
							);
							self::audit( $old['id'], 'superseded', $old, self::read( $old['id'] ) );
						}
					} elseif ( $active ) {
						throw new Failure( array( 'status' => __( 'A current published claim already exists. Create a revision of that claim instead.', 'parameter-commerce' ) ), 409 );
					}
					self::check_composition( $before );
					$changes['published_slot'] = $slot;
					$changes['published_at']   = $changes['updated_at'];
				}
				if ( 'retracted' === $target ) {
					$changes['published_slot'] = null;
				}
				self::written(
					$wpdb->update(
						$wpdb->prefix . 'pc_claims',
						$changes,
						array(
							'id'       => $id,
							'revision' => $revision,
						)
					)
				);
				self::audit( $id, $target, $before, self::read( $id ) );
				return $id;
			}
		);
	}

	/**
	 * Change dictionary presentation using a revision, and record an audit entry.
	 *
	 * @param string $key Metric key.
	 * @param int    $revision Expected registry revision.
	 * @param string $label Optional translated/operator label.
	 * @param bool   $filterable Include in later filtering.
	 * @return void
	 * @throws Failure On unauthorized access or invalid configuration.
	 */
	public static function configure_metric( $key, $revision, $label, $filterable ) {
		self::authorize();
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new Failure( array( 'permission' => __( 'Only administrators may configure metrics.', 'parameter-commerce' ) ), 403 );
		}
		if ( ! isset( Metrics::defaults()[ $key ] ) || mb_strlen( $label ) > 191 ) {
			throw new Failure( array( 'metric_key' => __( 'Invalid metric or label length.', 'parameter-commerce' ) ) );
		}
		self::transaction(
			0,
			0,
			static function () use ( $key, $revision, $label, $filterable ) {
				global $wpdb;
				$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE metric_key = %s FOR UPDATE', $wpdb->prefix . 'pc_metric_definitions', $key ), ARRAY_A );
				self::expect( $before, $revision );
				$changes = array(
					'label_override' => sanitize_text_field( $label ),
					'is_filterable'  => $filterable ? 1 : 0,
					'revision'       => $revision + 1,
				);
				self::written( $wpdb->update( $wpdb->prefix . 'pc_metric_definitions', $changes, array( 'metric_key' => $key ) ) );
				self::audit( 0, 'metric_configured', $before, array_merge( $before, $changes ) );
			}
		);
	}

	/**
	 * Project a staff preview without internal evidence fields or private links.
	 *
	 * @param array $claim Staff claim record.
	 * @return array Safe preview fields.
	 */
	public static function preview( array $claim ) {
		$evidence = $claim['evidence'];
		$public   = 'public' === $evidence['visibility'];
		return array(
			'metric_key'      => $claim['metric_key'],
			'value'           => $claim['value_num'] ?? $claim['value_enum'] ?? __( 'Not tested', 'parameter-commerce' ),
			'unit'            => $claim['unit'],
			'status'          => $claim['status'],
			'source_type'     => $claim['source_type'],
			'wc_product_id'   => $claim['wc_product_id'],
			'wc_variation_id' => $claim['wc_variation_id'],
			'size_scope'      => $claim['size_scope'],
			'method_code'     => $claim['method_code'],
			'method_version'  => $claim['method_version'],
			'conditions_json' => $claim['conditions_json'],
			'sample_count'    => $claim['sample_count'],
			'sample_batch'    => $claim['sample_batch'],
			'measured_at'     => $claim['measured_at'],
			'note'            => $claim['note'],
			'evidence_title'  => $evidence['public_title'],
			'evidence_url'    => $public ? $evidence['public_url'] : '',
			'attachment_url'  => $public && $evidence['attachment_id'] ? self::public_attachment_url( $evidence['attachment_id'] ) : '',
		);
	}

	/**
	 * Recheck explicit excerpt attachment access if its metadata changes later.
	 *
	 * @param int $id Attachment ID.
	 * @return string
	 */
	private static function public_attachment_url( $id ) {
		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! in_array( $attachment->post_status, array( 'inherit', 'publish' ), true ) || $attachment->post_password ) {
			return '';
		}
		if ( $attachment->post_parent ) {
			$parent = get_post( $attachment->post_parent );
			if ( ! $parent || 'publish' !== $parent->post_status || $parent->post_password ) {
				return '';
			}
			if ( in_array( $parent->post_type, array( 'product', 'product_variation' ), true ) && ! Public_Specifications::visible( 'product_variation' === $parent->post_type ? $parent->post_parent : $parent->ID, 'product_variation' === $parent->post_type ? $parent->ID : 0 ) ) {
				return '';
			}
		}
		$url = wp_get_attachment_url( $id );
		return $url ? $url : '';
	}

	/**
	 * Read the row and its snapshot, optionally locking the claim.
	 *
	 * @param int  $id Claim ID.
	 * @param bool $lock Lock for update.
	 * @return array
	 * @throws Failure If missing.
	 */
	private static function read( $id, $lock = false ) {
		global $wpdb;
		$sql = $lock ? 'SELECT * FROM %i WHERE id = %d FOR UPDATE' : 'SELECT * FROM %i WHERE id = %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is one of the two fixed strings above.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $wpdb->prefix . 'pc_claims', $id ), ARRAY_A );
		if ( ! $row ) {
			throw new Failure( array( 'id' => __( 'Claim not found.', 'parameter-commerce' ) ), 404 );
		}
		$row['evidence'] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $wpdb->prefix . 'pc_evidence', $row['evidence_id'] ), ARRAY_A );
		if ( ! $row['evidence'] ) {
			throw new Failure( array( 'evidence' => __( 'Evidence snapshot is missing; restore data before editing.', 'parameter-commerce' ) ), 503 );
		}
		return $row;
	}

	/**
	 * Check current composition under the shared SKU-scope lock.
	 *
	 * @throws Failure On an invalid composition or tolerance.
	 *
	 * @param array $claim Candidate claim.
	 * @return void
	 */
	private static function check_composition( array $claim ) {
		if ( 'composition' !== Metrics::defaults()[ $claim['metric_key'] ]['category'] ) {
			return;
		}
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT metric_key,value_num FROM %i WHERE wc_product_id = %d AND wc_variation_id = %d AND status = %s', $wpdb->prefix . 'pc_claims', $claim['wc_product_id'], $claim['wc_variation_id'], 'published' ), ARRAY_A );
		$values = array();
		foreach ( $rows as $row ) {
			if ( 'composition' === Metrics::defaults()[ $row['metric_key'] ]['category'] ) {
				$values[ $row['metric_key'] ] = (float) $row['value_num'];
			}
		}
		$values[ $claim['metric_key'] ] = (float) $claim['value_num'];
		// Explicit operator configuration via a documented filter; strict zero tolerance by default.
		$tolerance = apply_filters( 'pc_composition_tolerance', 0.0 );
		if ( ! is_numeric( $tolerance ) || ! is_finite( (float) $tolerance ) || $tolerance < 0 || $tolerance > 100 ) {
			throw new Failure( array( 'configuration' => __( 'Composition tolerance must be a finite number between 0 and 100 percentage points.', 'parameter-commerce' ) ), 503 );
		}
		Validator::composition( $values, (float) $tolerance );
	}

	/**
	 * Append an immutable audit snapshot in the same transaction as its change.
	 *
	 * @param int        $id Claim ID, zero for registry configuration.
	 * @param string     $action Action key.
	 * @param array|null $before Previous state.
	 * @param array      $after New state.
	 * @return void
	 */
	private static function audit( $id, $action, $before, array $after ) {
		global $wpdb;
		self::written(
			$wpdb->insert(
				$wpdb->prefix . 'pc_claim_audit',
				array(
					'claim_id'      => $id,
					'actor_user_id' => get_current_user_id(),
					'action'        => $action,
					'before_json'   => wp_json_encode( $before ),
					'after_json'    => wp_json_encode( $after ),
					'happened_at'   => current_time( 'mysql', true ),
				)
			)
		);
	}

	/**
	 * Serialize each SKU scope, including the first publication with no prior row.
	 *
	 * @param int      $product Product ID.
	 * @param int      $variation Variation ID.
	 * @param callable $callback Transaction body.
	 * @return mixed
	 * @throws \Throwable Callback errors are rethrown after rollback.
	 */
	private static function transaction( $product, $variation, callable $callback ) {
		global $wpdb;
		$lock            = 'pc-write-' . md5( DB_NAME . $wpdb->prefix . ':' . $product . ':' . $variation );
		$previous_errors = $wpdb->suppress_errors( true );
		$locked          = false;
		try {
			$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
			if ( ! $locked ) {
				self::conflict();
			}
			self::written( $wpdb->query( 'START TRANSACTION' ), false );
			$result = $callback();
			self::written( $wpdb->query( 'COMMIT' ), false );
			return $result;
		} catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error;
		} finally {
			if ( $locked ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
			$wpdb->suppress_errors( $previous_errors );
		}
	}

	/**
	 * Reject stale revisions.
	 *
	 * @param array|null $row Current row.
	 * @param int        $revision Expected revision.
	 * @return void
	 */
	private static function expect( $row, $revision ) {
		if ( ! $row || (int) $row['revision'] !== (int) $revision ) {
			self::conflict();
		}
	}

	/**
	 * Throw a safe conflict response.
	 *
	 * @return never
	 * @throws Failure Always.
	 */
	private static function conflict() {
		throw new Failure( array( 'revision' => __( 'This record changed or is busy. Reload and review the latest version before retrying.', 'parameter-commerce' ) ), 409 );
	}

	/**
	 * Require a successful write, including at least one changed row for mutations.
	 *
	 * @param int|false $result Database result.
	 * @param bool      $changed Whether zero rows indicate a failure.
	 * @return void
	 * @throws Failure When persistence fails.
	 */
	private static function written( $result, $changed = true ) {
		if ( false === $result || ( $changed && 0 === $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the category, never SQL or evidence.
			error_log( '{"plugin":"parameter-commerce","event":"write_failed"}' );
			throw new Failure( array( 'database' => __( 'The change could not be saved. No partial changes were committed; retry or contact an administrator.', 'parameter-commerce' ) ), 503 );
		}
	}
}
