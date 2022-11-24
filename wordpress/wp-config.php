<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'dba' );

/** MySQL database password */
define( 'DB_PASSWORD', 'P4EWaV9EzcQ' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'avSDX`Bbo1sI[1F9/Lgxt)-LSl2#SVI<DsFVz$T.FaU6X+@l.Aoml<S/7^ocu;+J' );
define( 'SECURE_AUTH_KEY',  'V=[1yQ#Sg!cE=*a-%5o4^5E9*a*ZSo~ZOiq O%l0>A$Vs1y,D%QUdx.Y0oSD##ZE' );
define( 'LOGGED_IN_KEY',    '*{h5r|2*lEaZhKeC@2+q$/8I`5TRsL(yh1)Y)pf2VFYj~KXA}q62]ivPnt%qwX-F' );
define( 'NONCE_KEY',        ':a^((t/Sr.AJml{R+BgW+$+D/ew=QZlj<r&?X8r~vLhuKILA IR[,W2D&7}iKzLg' );
define( 'AUTH_SALT',        'V53s7GZ*+Ya49vEd2+8we;87{8(pRW+E24Z)e:14^G%JE#J#%7l#?|1a!gOx`GoO' );
define( 'SECURE_AUTH_SALT', 'XVVBvD898Y/1b:Ey@nOhw2F*LIB7^aO?^%Xoma7D*/bjEA>#15hr.gD%t%i1BG4k' );
define( 'LOGGED_IN_SALT',   'IfuVu3fZgDjEYpY5;MmFxCh,(=is<.nE$rF5D@kE{@8JAZ64phA%eP~~cE3:;5$?' );
define( 'NONCE_SALT',       'XBDl2`w +1?dVJ!!jgI$$<Iu]RcQ*}9JcyfcRV[gajlTvwq=1)xg mz $1 .w53~' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

