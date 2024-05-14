<?php
/**
 * Plugin Name:       Multiple Currencies for Paid Memberships Pro
 * Description:       Change currencies depending on Paid Memberships Pro level.
 * Version:           0.0.3
 * Author:            Louis Wolmarans
 * Author URI:        https://letmefreelance.co.za/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  paid-memberships-pro
 */

 // Add a currency setting on the level edit screen
function lmf_action_pmpro_membership_level_after_other_settings() {

	global $pmpro_currencies;

    if ( isset( $_REQUEST['edit'] ) && '-1' !== $_REQUEST['edit'] ) {
        $edit = intval( $_REQUEST['edit'] );
        $selected_custom_currency = get_pmpro_membership_level_meta( $edit, 'pmpro_custom_currency', true );
    } else {
        $selected_custom_currency = "DEFAULT";
    }
    ?>

    <h2 class="title"><?php esc_html_e( 'Currency Settings', 'pmpro-multiple-currencies' ); ?></h2>

    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row" valign="top">
                    <label><?php _e('Choose a currency for this membership level', 'pmpro-multiple-currencies' );?>:</label>
                </th>
                <td>
                    <select name="pmpro_custom_currency">
                        <option value="DEFAULT" <?php if ( $selected_custom_currency == "DEFAULT" ) { ?>selected="selected"<?php } ?>><?php _e( 'Use the default currency', 'pmpro-multiple-currencies' ); ?></option>
                        <?php
                            foreach ( $pmpro_currencies as $key => $val) {
                                if ( is_array( $val ) ) {
                                    $cdescription = $val['name'];
                                    $custom_symbol = ( !empty( $val['symbol'] ) ) ? $val['symbol'] : $val['name'];
                                } else {
                                    $cdescription = $val;
                                    $custom_symbol = $key;
                                    if ( strpos( $cdescription, '(' ) !== false && strpos( $cdescription, ')' ) !== false ) {
                                        preg_match('#\((.*?)\)#', $cdescription, $match);
                                        $custom_symbol = $match[1];
                                    }
                                }
                                $selected = "";
                                if ( $selected_custom_currency !== "DEFAULT" ) {
                                    $c_arr = explode( ",", $selected_custom_currency );
                                    if ( $c_arr[0] == $key ) {
                                        $selected = "selected";
                                    }
                                }

                                $option_value = $key.",".$custom_symbol;
                            ?>
                            <option value="<?php echo esc_attr( $option_value ); ?>" <?php echo $selected; ?>><?php echo $cdescription; ?></option>
                            <?php
                            }
                        ?>
                    </select>
                    <p class="description"><?php _e( 'Make sure your payment gateway supports the selected currency.', 'pmpro-multiple-currencies' ); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <?php

}
add_action( "pmpro_membership_level_after_other_settings", "lmf_action_pmpro_membership_level_after_other_settings", 10, 0 );

// save the custom currency setting in level meta
function lmf_pmpro_save_membership_level( $level_id ) {

    if ( $level_id <= 0 ) return;

	$pmpro_custom_currency = sanitize_text_field( $_REQUEST['pmpro_custom_currency'] );
	update_pmpro_membership_level_meta( $level_id, 'pmpro_custom_currency', $pmpro_custom_currency );

}
add_action( "pmpro_save_membership_level", "lmf_pmpro_save_membership_level" );

// main function to check for a currency level and update currencies
function lmf_update_currency_per_level( $level_id ) {

	global $pmpro_currency, $pmpro_currency_symbol, $level_currencies;

    $selected_custom_currency = get_pmpro_membership_level_meta( $level_id, 'pmpro_custom_currency', true );

    if (
        $selected_custom_currency !== false && 
        ! empty ( $selected_custom_currency ) && 
        $selected_custom_currency !== "DEFAULT" 
    ) {
        $level_currency = explode( ",", $selected_custom_currency );
        $pmpro_currency = $level_currency[0];
        if ( empty ( $level_currency[1] ) ) {
            $pmpro_currency_symbol = $level_currency[0];
        } else {
            $pmpro_currency_symbol = $level_currency[1];
        }
    }

}

// change currency on checkout page
function lmf_pmpro_checkout_level( $level ) {

	lmf_update_currency_per_level( $level->id );
	return $level;

}
add_filter( "pmpro_checkout_level", "lmf_pmpro_checkout_level" );

// change currency when sent as a request param
function lmf_init_currency_check() {

	if ( ! empty( $_REQUEST['level'] ) )
		return lmf_update_currency_per_level( intval( $_REQUEST['level'] ) );

}
add_action( "init", "lmf_init_currency_check" );

// params in the admin
function lmf_admin_init_currency_check() {

	if (
        ! empty( $_REQUEST['edit'] ) &&
        ! empty( $_REQUEST['page'] ) &&
        'pmpro-membershiplevels' == $_REQUEST['page'] &&
        '-1' !== $_REQUEST['edit']
    )
	
    return lmf_update_currency_per_level( intval( $_REQUEST['edit'] ) );

}
add_action( "admin_init", "lmf_admin_init_currency_check" );

function lmf_pmpro_level_cost_text( $r, $level, $tags, $short ) {

    global $pmpro_currency, $pmpro_currency_symbol, $pmpro_currencies;
    $selected_custom_currency = get_pmpro_membership_level_meta( $level->id, 'pmpro_custom_currency', true );

    if ( 
        $selected_custom_currency !== false && 
        ! empty ( $selected_custom_currency ) && 
        $selected_custom_currency !== "DEFAULT" 
    ) {
        $level_currency = explode( ",", $selected_custom_currency );
        $curr = empty( $level_currency[1] ) ? $level_currency[0] : $level_currency[1];
        return str_replace($pmpro_currency_symbol, $curr, $r);
    }
    
    return $r;
	
}
add_filter( "pmpro_level_cost_text", "lmf_pmpro_level_cost_text", 10, 4 );

// Invoice and confirmation pages
function lmf_pmpro_change_currency_for_invoice( $pmpro_price_parts_with_total, $pmpro_invoice ) {
	$level_id = (int) $pmpro_invoice->membership_id;
	$custom_currency = explode( ',', get_pmpro_membership_level_meta( $level_id, 'pmpro_custom_currency', true ) );
	
	if ( ! empty( $custom_currency[ 1 ] ) ) {
		$pmpro_price_parts_with_total['total']['value'] = lmf_pmpro_formatPrice( $pmpro_invoice->total, $pmpro_invoice );
	}

	return $pmpro_price_parts_with_total;
}
add_filter( 'pmpro_get_price_parts_with_total', 'lmf_pmpro_change_currency_for_invoice', 10, 2 );

// Copied from PMPro core, and added $pmpro_invoice param.
function lmf_pmpro_formatPrice( $price, $pmpro_invoice ) {
	global $pmpro_currency, $pmpro_currency_symbol, $pmpro_currencies;

	// Edits start
	$custom_currency_symbol = $pmpro_currency_symbol;

	$level_id = (int) $pmpro_invoice->membership_id;
	$custom_currency = explode( ',', get_pmpro_membership_level_meta( $level_id, 'pmpro_custom_currency', true ) );

	if ( ! empty( $custom_currency[ 1 ] ) ) {
		$custom_currency_symbol = $custom_currency[1];
	}
	// Edits end - will now use $custom_currency_symbol instead of $pmpro_currency_symbol

	// start with the rounded price
	$formatted = pmpro_round_price( $price );

	$decimals = isset( $pmpro_currencies[ $pmpro_currency ]['decimals'] ) ? (int) $pmpro_currencies[ $pmpro_currency ]['decimals'] : pmpro_get_decimal_place();
	$decimal_separator = isset( $pmpro_currencies[ $pmpro_currency ]['decimal_separator'] ) ? $pmpro_currencies[ $pmpro_currency ]['decimal_separator'] : '.';
	$thousands_separator = isset( $pmpro_currencies[ $pmpro_currency ]['thousands_separator'] ) ? $pmpro_currencies[ $pmpro_currency ]['thousands_separator'] : ',';
	$symbol_position = isset( $pmpro_currencies[ $pmpro_currency ]['position'] ) ? $pmpro_currencies[ $pmpro_currency ]['position'] : 'left';

	// settings stored in array?
	if ( ! empty( $pmpro_currencies[ $pmpro_currency ] ) && is_array( $pmpro_currencies[ $pmpro_currency ] ) ) {
		// format number do decimals, with decimal_separator and thousands_separator
		$formatted = number_format(
			$formatted,
			$decimals,
			$decimal_separator,
			$thousands_separator
		);

		// which side is the symbol on?
		if ( ! empty( $symbol_position ) && $symbol_position == 'left' ) {
			$formatted = $custom_currency_symbol . $formatted;
		} else {
			$formatted = $formatted . $custom_currency_symbol;
		}
	} else {
		// default to symbol on the left, 2 decimals using . and ,
		$formatted = $custom_currency_symbol . number_format( $formatted, pmpro_get_decimal_place() );
	}

	// Trim the trailing zero values.
	$formatted = pmpro_trim_trailing_zeroes( $formatted, $decimals, $decimal_separator, $custom_currency_symbol, $symbol_position );

	// filter
	return apply_filters( 'pmpro_format_price', $formatted, $price, $pmpro_currency, $custom_currency_symbol );
}
