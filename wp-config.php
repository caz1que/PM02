<?php

/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'wordpress_user' );

/** Database password */
define( 'DB_PASSWORD', 'password' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

define('AUTH_KEY',         '(gOjsX5O.%Mh=$D](/HQXi;}@|y>Uh}f~pCein`$XTVrPNoQ`m [6<cW#@JuM:yv');
define('SECURE_AUTH_KEY',  '`zM ^_.;P0JIigO+]h.-e~20scnw@JG!2<]J+CU|@L9]wc~I(]6vDiGn,4bK@[/2');
define('LOGGED_IN_KEY',    'J>[=}$`&5w[9|j-cf`]jIH={fINb(|%hO}DrVpE^R>/gN05qMu&S3hJ!rM}kS %M');
define('NONCE_KEY',        'W23/-z3j7-r&}G7q6;0md+zH&.02jP.<y}5mfxO~|^LvT`^TN@f|yHij^ aK+?jp');
define('AUTH_SALT',        '-iz<PUU%W!JG-OuSjVKG dz s{dhueG!t_<V6%eU6d13XM>E[As|+^c$!m5AY?C$');
define('SECURE_AUTH_SALT', 'Ym.=1Sb@Eo@8`o.c$x 3UsMhVXncwZT4u,Q~7RI=SVSnDzp5cb[nSF%-]|8/V-li');
define('LOGGED_IN_SALT',   'Qtw<U}>e4?~5(?+[Hy:lkeD-x$BJef-q!H(~RFuNe<$]SNP,HS0Vvly1|Mt[lPf{');
define('NONCE_SALT',       '2}+->+7.nW`bKg |X;eH-lyJ:XVH<VnK;fwZ(WIj?%s27IwXjS=r!0/i3-=|u1,5');

/**#@-*/

/**
 * WordPress database table prefix.
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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

define('WPSITEURL','https://192.168.201.143/');
define('WPHOME','https://192.168.201.143/');
