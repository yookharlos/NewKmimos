<?php

$WP_path_load = dirname(dirname(dirname(dirname(__DIR__)))).'/wp-load.php';

include_once($WP_path_load);

//CLASS
include_once(dirname(__FILE__).'/includes/class/class_whitelabel.php');
include_once(dirname(__FILE__).'/includes/class/class_whitelabel-user.php');
$_wlabel = new Class_WhiteLabel();
$_wlabel_user = new Class_WhiteLabel_User();


//STYLE HEADER
//add_action('wp_head', 'WhiteLabel_ADDheader');
function WhiteLabel_ADDheader(){
    global $_wlabel;
    $_wlabel->Header();
}

/*STYLE FOOTER
add_action('wp_footer', 'WhiteLabel_ADDfooter');
*/function WhiteLabel_ADDfooter(){
    global $_wlabel;
    $_wlabel->Footer();
}

//USER REGISTER
//add_action('user_register', 'WhiteLabel_registration_save', 10, 1);
function WhiteLabel_registration_save($user_id){
    global $_wlabel;
    if ($_wlabel->wlabel_active){
        update_user_meta($user_id, '_wlabel', $_wlabel->wlabel);
    }
}

//ORDER PROSSESING
add_action('woocommerce_thankyou', 'WhiteLabel_custom_processing');
add_action('woocommerce_order_status_completed', 'WhiteLabel_custom_processing');
add_action('woocommerce_order_status_processing', 'WhiteLabel_custom_processing');
function WhiteLabel_custom_processing($order){
    global $_wlabel;
    $wlabel=$_wlabel->wlabel;

    if ($_wlabel->wlabel_active){
        update_post_meta($order, '_wlabel', $wlabel);
    }


    //WLABEL DEL USUARIO REGISTRADO
    if ($_wlabel->wlabel_active){
        $booking = new WP_Query(array('post_parent' => $order, 'post_type' => 'wc_booking'));
        if ( $booking->have_posts()){
            while($booking->have_posts()){
                $booking->the_post();
                $booking_id=get_the_id();
                $booking_author=get_post_field( 'post_author', $booking_id );

                $wlabel_user = get_user_meta($booking_author,'_wlabel', true);
                if(!empty($wlabel_user)){
                    update_post_meta($order, '_wlabel', $wlabel_user);
                }
            }
        }
    }


    /*//kmimos modified
    //$order_post = get_post($order);
    //$order_post_parent = $order_post->post_parent;//alrevves
    //$order_post_parent = get_post_ancestors($order);

    $order_post = new WP_Query(array('post_parent' => $order));
    if ( $order_post->have_posts()){
        while($order_post->have_posts()){
            $order_post->the_post();
            $order_post_parent=$order_post->ID;
            var_dump();

            $modified_order = get_post_meta($order_post_parent,'modificacion_de', true);//reserva_modificada
            if(!empty($modified_order)){
                $modified_post = get_post($modified_order);
                $modified_post_parent = $modified_post->post_parent;
                $modified_wlabel = get_post_meta($modified_post_parent,'_wlabel', true);
                if(!empty($modified_wlabel)){
                    update_post_meta($order, '_wlabel', $modified_wlabel);
                }
            }

            break;

        }
    }*/

}




//CREATE TEMPLATE
add_filter('page_template', 'WhiteLabel_Template');
function WhiteLabel_Template($template){
    if(is_page('wlabel')){
        $dirtemplate=dirname(__FILE__).'/index.php';
        if(file_exists($dirtemplate)){
            $template=$dirtemplate;
        }
    }
    return $template;
}


//CREATE PAGE
add_action('init', 'WhiteLabel_Page');
function WhiteLabel_Page(){
    global $user_ID;
    $post_name = 'wlabel';
    $post_title = 'Label';
    $post_content = '';
    $page = get_page_by_path($post_name);
    $post=array('post_author'=>$user_ID, 'post_content'=>$post_content, 'post_name'=>$post_name, 'post_status'=>'publish', 'post_title'=>$post_title, 'post_type'=>'page', 'post_parent'=>0, 'menu_order'=>0, 'to_ping'=> '', 'pinged'=>'');

    if($page->post_name!=$post_name){
        $insert = wp_insert_post($post);
        if(!$insert){
            wp_die('Error creando Post');
        }
    }else{
        //update_post_meta($page_id,'_wp_page_template', 'page_registro.php' );
    }
    return;
}


//RULES HTACCESS
add_action('generate_rewrite_rules', 'WhiteLabel_add_rewrite_rules');
function WhiteLabel_add_rewrite_rules($wp_rewrite){
    $new_rules = array(
        'label/?$' =>'index.php?pagename=wlabel',
        'label/([a-z]*)/?$' =>'index.php?pagename=wlabel&wlabel='.$wp_rewrite->preg_index(1));
    $wp_rewrite->rules=$new_rules+$wp_rewrite->rules;
}

add_filter('query_vars', 'WhiteLabel_query_vars');
function WhiteLabel_query_vars($query_vars){
    //$query_vars[]="wlabel";
    return $query_vars;
}

add_action('init', 'WhiteLabel_flush_rewrite_rules');
function WhiteLabel_flush_rewrite_rules(){
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
}

?>