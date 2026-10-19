<?php
/**
 * Repeatable, additive installation. DDL is not treated as transactional.
 *
 * @package ParameterCommerce
 */

namespace ParameterCommerce;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Structured errors are escaped by the admin controller at rendering time.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Schema metadata and migrations cannot use object caching.
/** Owns the four custom tables; never alters WooCommerce tables. */
final class Schema {
	const VERSION = 1;

	/**
	 * Activate for this site only.
	 *
	 * @param bool $network_wide Network activation flag.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Network activation is not supported. Activate Parameter Commerce per site.', 'parameter-commerce' ) );
		}
		self::install();
	}

	/**
	 * Upgrade on staff/CLI requests, including plugin updates without activation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( (int) get_option( 'pc_schema_version', 0 ) === self::VERSION && (int) get_option( 'pc_dictionary_version', 0 ) === Metrics::VERSION ) {
			return;
		}
		if ( current_user_can( 'activate_plugins' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			try {
				self::install();
			} catch ( Failure $error ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only a category, never database details.
				error_log( '{"plugin":"parameter-commerce","event":"migration_failed"}' );
				add_action(
					'admin_notices',
					static function () {
						echo '<div class="notice notice-error"><p>' . esc_html__( 'Parameter Commerce installation failed. No claim editing is available. Check the database configuration and retry activation.', 'parameter-commerce' ) . '</p></div>';
					}
				);
			}
		}
	}

	/** Grant explicit publication rights when either plugin is activated. @return void */
	public static function grant_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( 'publish_pc_claims' );
			}
		}
	}

	/**
	 * Run the initial additive schema and seed without overwriting configuration.
	 *
	 * @return void
	 * @throws Failure If the lock, schema, engine or seed cannot be verified.
	 */
	public static function install() {
		global $wpdb;
		if ( (int) get_option( 'pc_schema_version', 0 ) > self::VERSION ) {
			throw new Failure( array( 'schema' => __( 'A newer schema exists. Restore matching plugin code; do not downgrade the database.', 'parameter-commerce' ) ), 503 );
		}
		$lock = 'pc-schema-' . md5( DB_NAME . $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			throw new Failure( array( 'schema' => __( 'Installation is busy. Retry shortly.', 'parameter-commerce' ) ), 503 );
		}
		try {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$prefix  = $wpdb->prefix;
			$collate = $wpdb->get_charset_collate();
			$sql     = array(
				"CREATE TABLE {$prefix}pc_metric_definitions (
metric_key varchar(64) NOT NULL,
data_type varchar(16) NOT NULL,
unit varchar(64) DEFAULT NULL,
label_override varchar(191) NOT NULL DEFAULT '',
is_filterable tinyint(1) NOT NULL DEFAULT 1,
applicability varchar(32) NOT NULL DEFAULT 'socks',
rules_json longtext NOT NULL,
revision bigint(20) unsigned NOT NULL DEFAULT 1,
schema_version int(11) NOT NULL DEFAULT 1,
created_at datetime NOT NULL,
PRIMARY KEY  (metric_key)
) ENGINE=InnoDB $collate;",
				"CREATE TABLE {$prefix}pc_evidence (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
public_title varchar(191) NOT NULL DEFAULT '',
attachment_id bigint(20) unsigned DEFAULT NULL,
source_url text NOT NULL,
public_url text NOT NULL,
visibility varchar(16) NOT NULL DEFAULT 'private',
source_org varchar(191) NOT NULL DEFAULT '',
note text NOT NULL,
created_by bigint(20) unsigned NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id)
) ENGINE=InnoDB $collate;",
				"CREATE TABLE {$prefix}pc_claims (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
wc_product_id bigint(20) unsigned NOT NULL,
wc_variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
size_scope varchar(191) NOT NULL DEFAULT '',
metric_key varchar(64) NOT NULL,
value_num decimal(20,6) DEFAULT NULL,
value_enum varchar(64) DEFAULT NULL,
unit varchar(64) NOT NULL DEFAULT '',
source_type varchar(32) NOT NULL DEFAULT 'supplier_declared',
method_code varchar(191) NOT NULL DEFAULT '',
method_version varchar(64) NOT NULL DEFAULT '',
conditions_json longtext NOT NULL,
sample_count int(11) unsigned DEFAULT NULL,
sample_batch varchar(191) NOT NULL DEFAULT '',
measured_at date DEFAULT NULL,
verified_by bigint(20) unsigned DEFAULT NULL,
status varchar(16) NOT NULL DEFAULT 'draft',
evidence_id bigint(20) unsigned DEFAULT NULL,
note text NOT NULL,
revision bigint(20) unsigned NOT NULL DEFAULT 1,
supersedes_id bigint(20) unsigned DEFAULT NULL,
supersedes_revision bigint(20) unsigned DEFAULT NULL,
published_slot varchar(191) DEFAULT NULL,
published_at datetime DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY published_scope (published_slot),
KEY metric_range (metric_key,status,value_num),
KEY product_scope (wc_product_id,wc_variation_id,status)
) ENGINE=InnoDB $collate;",
				"CREATE TABLE {$prefix}pc_claim_audit (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
claim_id bigint(20) unsigned NOT NULL DEFAULT 0,
actor_user_id bigint(20) unsigned NOT NULL,
action varchar(64) NOT NULL,
before_json longtext NOT NULL,
after_json longtext NOT NULL,
happened_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY claim_history (claim_id,id)
) ENGINE=InnoDB $collate;",
			);
			foreach ( $sql as $statement ) {
				dbDelta( $statement );
				preg_match( '/CREATE TABLE ([a-zA-Z0-9_]+)/', $statement, $table_match );
				preg_match_all( '/^([a-z_]+) (?:varchar|bigint|int|tinyint|decimal|longtext|text|datetime|date)\b/m', $statement, $column_matches );
				$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_match[1] ) );
				if ( array_diff( $column_matches[1], $columns ) ) {
					throw new Failure( array( 'schema' => __( 'Required columns are missing. Installation remains incomplete and can be retried.', 'parameter-commerce' ) ), 503 );
				}
			}
			foreach ( array( 'metric_definitions', 'evidence', 'claims', 'claim_audit' ) as $suffix ) {
				$table  = $prefix . 'pc_' . $suffix;
				$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
				if ( 'InnoDB' !== $engine ) {
					throw new Failure( array( 'schema' => __( 'All Parameter Commerce tables require InnoDB. Installation can be retried without deleting data.', 'parameter-commerce' ) ), 503 );
				}
			}
			foreach ( Metrics::defaults() as $key => $definition ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						'INSERT INTO %i (metric_key,data_type,unit,rules_json,created_at) VALUES (%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE data_type=VALUES(data_type),unit=VALUES(unit),rules_json=VALUES(rules_json)',
						$prefix . 'pc_metric_definitions',
						$key,
						$definition['type'],
						$definition['unit'] ?? '',
						wp_json_encode( $definition ),
						current_time( 'mysql', true )
					)
				);
				if ( false === $result ) {
					throw new Failure( array( 'schema' => __( 'Metric seeding failed. Retry installation.', 'parameter-commerce' ) ), 503 );
				}
			}
			$index = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $prefix . 'pc_claims', 'published_scope' ), ARRAY_A );
			if ( 1 !== count( $index ) || '0' !== (string) $index[0]['Non_unique'] ) {
				throw new Failure( array( 'schema' => __( 'The publication uniqueness index is missing.', 'parameter-commerce' ) ), 503 );
			}
			self::grant_capabilities();
			update_option( 'pc_dictionary_version', Metrics::VERSION, false );
			update_option( 'pc_schema_version', self::VERSION, false );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}
