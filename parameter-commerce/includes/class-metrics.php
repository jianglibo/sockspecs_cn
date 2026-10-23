<?php
/**
 * Sock metric dictionary and configurable presentation.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Types and validation rules remain versioned in code; labels are configurable. */
final class Metrics {
	const VERSION = 6;
	/**
	 * Return the sock definitions; no measurement method is invented here.
	 *
	 * @return array
	 */
	public static function defaults() {
		$definitions = array();
		$labels      = array(
			'cotton_pct'      => __( 'Cotton', 'parameter-commerce' ),
			'wool_pct'        => __( 'Wool', 'parameter-commerce' ),
			'merino_wool_pct' => __( 'Merino wool (part of total wool)', 'parameter-commerce' ),
			'nylon_pct'       => __( 'Nylon', 'parameter-commerce' ),
			'polyester_pct'   => __( 'Polyester', 'parameter-commerce' ),
			'elastane_pct'    => __( 'Elastane', 'parameter-commerce' ),
			'acrylic_pct'     => __( 'Acrylic', 'parameter-commerce' ),
			'lyocell_pct'     => __( 'Lyocell', 'parameter-commerce' ),
			'other_pct'       => __( 'Other / unknown fiber', 'parameter-commerce' ),
		);
		foreach ( $labels as $key => $label ) {
			$definitions[ $key ] = array(
				'label'    => $label,
				'type'     => 'decimal',
				'unit'     => '%',
				'min'      => 0,
				'max'      => 100,
				'category' => 'composition',
			);
		}
		$definitions += array(
			'pair_weight_g'               => array(
				'label' => __( 'Pair weight', 'parameter-commerce' ),
				'unit'  => 'g',
				'min'   => 0,
			),
			'foot_length_mm'              => array(
				'label' => __( 'Foot length', 'parameter-commerce' ),
				'unit'  => 'mm',
				'min'   => 0,
			),
			'leg_height_mm'               => array(
				'label' => __( 'Leg height', 'parameter-commerce' ),
				'unit'  => 'mm',
				'min'   => 0,
			),
			'fabric_thickness_mm'         => array(
				'label' => __( 'Fabric thickness', 'parameter-commerce' ),
				'unit'  => 'mm',
				'min'   => 0,
			),
			'needle_count'                => array(
				'label'    => __( 'Needle count', 'parameter-commerce' ),
				'type'     => 'integer',
				'unit'     => 'count',
				'min'      => 1,
				'category' => 'construction',
			),
			'toe_seam_type'               => array(
				'label'    => __( 'Toe seam', 'parameter-commerce' ),
				'type'     => 'enum',
				'unit'     => '',
				'category' => 'construction',
				'choices'  => array( 'flat', 'linked', 'standard', 'unknown' ),
			),
			'cushion_type'                => array(
				'label'    => __( 'Cushioning', 'parameter-commerce' ),
				'type'     => 'enum',
				'unit'     => '',
				'category' => 'construction',
				'choices'  => array( 'none', 'light', 'medium', 'heavy' ),
			),
			'abrasion_result'             => array(
				'label'    => __( 'Abrasion result', 'parameter-commerce' ),
				'unit'     => null,
				'min'      => 0,
				'category' => 'performance',
			),
			'wash_dimensional_change_pct' => array(
				'label'    => __( 'Wash dimensional change', 'parameter-commerce' ),
				'unit'     => '%',
				'category' => 'performance',
			),
			'air_permeability'            => array(
				'label'    => __( 'Air permeability', 'parameter-commerce' ),
				'unit'     => null,
				'min'      => 0,
				'category' => 'performance',
			),
		);
		$definitions += array(
			'heel_reinforcement'         => array(
				'label'    => __( 'Heel reinforcement', 'parameter-commerce' ),
				'type'     => 'enum',
				'unit'     => '',
				'category' => 'construction',
				'choices'  => array( 'none', 'reinforced', 'unknown' ),
			),
			'sock_height_type'           => array(
				'label'    => __( 'Sock height style', 'parameter-commerce' ),
				'type'     => 'enum',
				'unit'     => '',
				'category' => 'construction',
				'choices'  => array( 'no-show', 'ankle', 'crew', 'knee-high' ),
			),
			'cuff_circumference_mm'      => array(
				'label' => __( 'Cuff circumference', 'parameter-commerce' ),
				'unit'  => 'mm',
				'min'   => 0,
			),
			'cuff_height_mm'             => array(
				'label' => __( 'Cuff height', 'parameter-commerce' ),
				'unit'  => 'mm',
				'min'   => 0,
			),
			'pilling_result'             => array(
				'label'    => __( 'Pilling resistance result', 'parameter-commerce' ),
				'unit'     => null,
				'category' => 'performance',
			),
			'elastic_recovery_pct'       => array(
				'label'    => __( 'Elastic recovery', 'parameter-commerce' ),
				'unit'     => '%',
				'min'      => 0,
				'max'      => 100,
				'category' => 'performance',
			),
			'moisture_management_result' => array(
				'label'    => __( 'Moisture management result', 'parameter-commerce' ),
				'unit'     => null,
				'category' => 'performance',
			),
		);
		$definitions += array(
			'catalog_pair_weight_g' => array(
				'label'    => __( 'Catalog pair weight', 'parameter-commerce' ),
				'unit'     => 'g',
				'category' => 'measurement',
				'min'      => 0,
			),
			'catalog_height_mm'     => array(
				'label'    => __( 'Catalog sock height (measurement endpoints unconfirmed)', 'parameter-commerce' ),
				'unit'     => 'mm',
				'category' => 'measurement',
				'min'      => 0,
			),
			'listing_length_mm'     => array(
				'label'    => __( 'Listing package/item length', 'parameter-commerce' ),
				'unit'     => 'mm',
				'category' => 'measurement',
				'min'      => 0,
			),
			'listing_width_mm'      => array(
				'label'    => __( 'Listing package/item width', 'parameter-commerce' ),
				'unit'     => 'mm',
				'category' => 'measurement',
				'min'      => 0,
			),
			'listing_height_mm'     => array(
				'label'    => __( 'Listing package/item height', 'parameter-commerce' ),
				'unit'     => 'mm',
				'category' => 'measurement',
				'min'      => 0,
			),
			'knitting_method'       => array(
				'label'    => __( 'Knitting method', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'single-needle' ),
			),
			'cushion_location'      => array(
				'label'    => __( 'Terry distribution', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'no-terry', 'partial-terry', 'terry-sole', 'full-terry', 'terry-cushioning' ),
			),
			'mesh_panels'           => array(
				'label'    => __( 'Mesh panels', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'present' ),
			),
			'left_right_markings'   => array(
				'label'    => __( 'Left/right markings', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'present' ),
			),
			'arch_band'             => array(
				'label'    => __( 'Elastic arch band', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'present' ),
			),
			'knit_pattern'          => array(
				'label'    => __( 'Knit pattern', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'jacquard', 'tonal-jacquard' ),
			),
			'cuff_shape'            => array(
				'label'    => __( 'Cuff shape', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'shaped' ),
			),
			'heel_tab'              => array(
				'label'    => __( 'Raised heel tab', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'construction',
				'type'     => 'enum',
				'choices'  => array( 'present' ),
			),
			'cotton_yarn'           => array(
				'label'    => __( 'Cotton preparation', 'parameter-commerce' ),
				'unit'     => '',
				'category' => 'material',
				'type'     => 'enum',
				'choices'  => array( 'carded', 'combed' ),
				'help'     => __( 'Preparation method, separate from cotton fibre length and yarn count.', 'parameter-commerce' ),
			),
		);

		$definitions += array(
			'cotton_staple_class'     => array(
				'label'   => __( 'Cotton staple class', 'parameter-commerce' ),
				'type'    => 'enum',
				'unit'    => '',
				'choices' => array( 'short-staple', 'medium-staple', 'long-staple', 'extra-long-staple' ),
				'help'    => __( 'The documented fibre-length classification; combed cotton is not automatically long-staple.', 'parameter-commerce' ),
			),
			'cotton_staple_length_mm' => array(
				'label' => __( 'Cotton staple length', 'parameter-commerce' ),
				'unit'  => 'mm',
				'help'  => __( 'Cotton fibre length, using the stated measurement basis. This is not yarn thickness.', 'parameter-commerce' ),
			),
			'wool_type'               => array(
				'label'   => __( 'Wool type', 'parameter-commerce' ),
				'type'    => 'enum',
				'unit'    => '',
				'choices' => array( 'merino', 'other-sheep-breed', 'mixed-sheep-breeds' ),
				'help'    => __( 'Documented sheep breed or breed group. Merino does not specify fibre diameter or wool percentage.', 'parameter-commerce' ),
			),
			'wool_fibre_diameter_um'  => array(
				'label' => __( 'Mean wool fibre diameter', 'parameter-commerce' ),
				'unit'  => 'μm',
				'help'  => __( 'Average thickness of the wool fibres, including Merino when documented. This is separate from yarn count.', 'parameter-commerce' ),
			),
			'wool_staple_length_mm'   => array(
				'label' => __( 'Wool staple length', 'parameter-commerce' ),
				'unit'  => 'mm',
				'help'  => __( 'Documented wool staple length; compare the same measurement basis.', 'parameter-commerce' ),
			),
			'wool_yarn_system'        => array(
				'label'   => __( 'Wool yarn processing', 'parameter-commerce' ),
				'type'    => 'enum',
				'unit'    => '',
				'choices' => array( 'woollen', 'semi-worsted', 'worsted' ),
				'help'    => __( 'Woollen, semi-worsted or worsted processing, when documented.', 'parameter-commerce' ),
			),
			'yarn_count'              => array(
				'label'         => __( 'Yarn count', 'parameter-commerce' ),
				'unit'          => null,
				'allowed_units' => array( 'Ne', 'Nm', 'tex', 'dtex', 'denier' ),
				'help'          => __( 'Count of one yarn strand in the stated numbering system. Different systems are not directly comparable; ply is recorded separately.', 'parameter-commerce' ),
			),
			'yarn_ply'                => array(
				'label' => __( 'Yarn ply', 'parameter-commerce' ),
				'type'  => 'integer',
				'unit'  => 'ply',
				'min'   => 1,
				'help'  => __( 'Number of strands combined in the documented yarn.', 'parameter-commerce' ),
			),
			'yarn_spinning_method'    => array(
				'label'   => __( 'Yarn spinning method', 'parameter-commerce' ),
				'type'    => 'enum',
				'unit'    => '',
				'choices' => array( 'ring-spun', 'compact-ring-spun', 'rotor-spun', 'air-jet-spun' ),
				'help'    => __( 'The documented spinning process, separate from fibre preparation and knitting needle count.', 'parameter-commerce' ),
			),
		);
		foreach ( array( 'cotton_staple_class', 'cotton_staple_length_mm', 'wool_type', 'wool_fibre_diameter_um', 'wool_staple_length_mm', 'wool_yarn_system', 'yarn_count', 'yarn_ply', 'yarn_spinning_method' ) as $key ) {
			$definitions[ $key ] += array(
				'category'       => 'material',
				'min'            => 0.000001,
				'material_scope' => true,
			);
		}
		$definitions['material_trade_name']      = array(
			'label'          => __( 'Material trade name', 'parameter-commerce' ),
			'type'           => 'enum',
			'unit'           => '',
			'choices'        => array( 'COOLMAX' ),
			'category'       => 'material',
			'material_scope' => true,
			'filterable'     => false,
			'help'           => __( 'A declared material trade name does not establish fibre percentages, certification or tested performance.', 'parameter-commerce' ),
		);
		$definitions['thickness_grade']          = array(
			'label'    => __( 'Thickness', 'parameter-commerce' ),
			'type'     => 'enum',
			'unit'     => '',
			'category' => 'construction',
			'choices'  => array( 'thin', 'moderate', 'thick' ),
			'help'     => __( 'Relative wearing thickness, not a quality score or a conversion from pair weight. Compare using the stated assessment method.', 'parameter-commerce' ),
		);
		$definitions['footwear_fit']             = array(
			'label'    => __( 'Shoe pairing', 'parameter-commerce' ),
			'type'     => 'enum',
			'unit'     => '',
			'category' => 'construction',
			'choices'  => array( 'close-fitting-shoes', 'regular-fit-shoes', 'roomier-shoes' ),
			'help'     => __( 'Guidance about room inside the shoe, not a guarantee of fit. Record the shoes and sizes assessed.', 'parameter-commerce' ),
		);
		$definitions['cushion_location']['help'] = __( 'Where terry loops are present. Unspecified terry does not establish full-sole coverage or overall thickness.', 'parameter-commerce' );
		foreach ( $definitions as &$definition ) {
			$definition['applicability']     = 'socks';
			$definition['comparison_policy'] = 'performance' === ( $definition['category'] ?? 'measurement' ) ? 'matching_method_version_conditions_unit' : 'same_unit_and_scope';
			$definition                     += array(
				'type'       => 'decimal',
				'category'   => 'measurement',
				'choices'    => array(),
				'min'        => null,
				'max'        => null,
				'filterable' => true,
			);
		}
		return $definitions;
	}

	/**
	 * Read configurable labels/filter flags, retaining fixed semantic rules.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$definitions = self::defaults();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Small plugin dictionary; avoid stale configuration.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT metric_key, label_override, is_filterable, revision FROM %i', $wpdb->prefix . 'pc_metric_definitions' ), ARRAY_A );
		foreach ( $rows as $row ) {
			if ( isset( $definitions[ $row['metric_key'] ] ) ) {
				if ( '' !== $row['label_override'] ) {
					$definitions[ $row['metric_key'] ]['label'] = $row['label_override'];
				}
				$definitions[ $row['metric_key'] ]['filterable']     = (bool) $row['is_filterable'];
				$definitions[ $row['metric_key'] ]['label_override'] = $row['label_override'];
				$definitions[ $row['metric_key'] ]['revision']       = (int) $row['revision'];
			}
		}
		return $definitions;
	}
}
