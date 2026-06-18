<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'nW]TlO!/w*XP_FKYlnapV~W[smD52E^DviX%BRCTuNx-r#0#kHG<U_14=`DZjA;r' );
define( 'SECURE_AUTH_KEY',   'lhTzvPkzP}ys)D4GsOleptSOrpF?[[|?V?7:*6 D/ab(6VV[Wq6>/nRf_0caN8kC' );
define( 'LOGGED_IN_KEY',     'g)-STct}l5@69]_@JRlSdar;lA0L7m&xkdF_PfQC%kTmPg^(.JM%>eekdB19d9G(' );
define( 'NONCE_KEY',         'K;.D0Ra!&^l}Pbdoy#?xu%W_ *:xA{JP;y)>xqvb.8(0=<(;24h&ZWkRCguD*iOE' );
define( 'AUTH_SALT',         '@*yCHf%n:9J&US)Q.+!rJ(K==rZ&d9~,;GuT0,nJQ&krR>0DdO4(l,?83l3#@063' );
define( 'SECURE_AUTH_SALT',  '=7V=s4S8_bWjMcJN(kPht.2@^5;[LYPnTZBb|`DP]DZB_l0qeM#9i&DA;{S[T|Vy' );
define( 'LOGGED_IN_SALT',    '/<@?&,~.dNN/xv0#>Xw1X-Cs-VYQKYSrV`8:w.l5G&[`zk`>zUkwLe79s~AfObiC' );
define( 'NONCE_SALT',        '^_!7^gjsr:L<kX0g4op$0SyAuZfsi?/,d<#bmrE]=  De<u2k_P/@mEy/4!NT:7c' );
define( 'WP_CACHE_KEY_SALT', '~iqJr#UzH%~C$ Y)f2~MxIUQ ,G%<p]19g;f>bR#Y=N^JnuO#G{:> CqK,tLV~B4' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */

define( 'WP_ENVIRONMENT_TYPE', 'local' );
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
