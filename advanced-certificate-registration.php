<?php
/**
 * Plugin Name: Advanced Certificate Registration Ultimate Pro (Ultimate Control)
 * Description: المنظومة المتكاملة لتسجيل الشهادات - مع توليد 4 أنواع من الشهادات عبر الـ AJAX من صفحة التعديل.
 * Version: 8.1
 * Author: Web Designer & Developer
 * Text Domain: cert-reg-ultimate-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CRF_COURSE_MARKET_META_KEY', '_crf_course_market_type' );

function crf_get_course_market_options() {
    return array(
        'other'      => 'other',
        'experience' => 'خبرة حجامة و تدليك و  سم نحل و ابر صينية',
    );
}

function crf_get_tutor_courses_for_select() {
    $courses = get_posts( array(
        'post_type'      => 'courses',
        'post_status'    => array( 'publish', 'private', 'draft' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    return is_array( $courses ) ? $courses : array();
}

function crf_find_course_id_for_registration( $post_id ) {
    $course_id = absint( get_post_meta( $post_id, '_course_id', true ) );
    if ( $course_id && get_post_type( $course_id ) === 'courses' ) {
        return $course_id;
    }

    $course_name = get_post_meta( $post_id, '_course', true );
    if ( empty( $course_name ) ) {
        return 0;
    }

    $matched = get_page_by_title( $course_name, OBJECT, 'courses' );
    return $matched ? absint( $matched->ID ) : 0;
}

function crf_get_registration_market_type( $post_id ) {
    $course_id = crf_find_course_id_for_registration( $post_id );
    if ( ! $course_id ) {
        return 'other';
    }

    $market_type = get_post_meta( $course_id, CRF_COURSE_MARKET_META_KEY, true );
    return $market_type === 'experience' ? 'experience' : 'other';
}

function crf_get_generation_templates_for_registration( $post_id ) {
    if ( crf_get_registration_market_type( $post_id ) === 'experience' ) {
        return array( 'management', 'eagletogether', 'card', 'seat', 'experience' );
    }

    return array( 'management', 'eaglestate', 'card', 'seat' );
}

function crf_get_generation_labels() {
    return array(
        'card'          => 'كارنية',
        'management'    => 'المديرية',
        'eaglestate'    => 'مجلس',
        'eagletogether' => 'مجلس',
        'seat'          => 'شهادة الدورة',
        'experience'    => 'خبرة حجامة و تدليك و  سم نحل و ابر صينية',
    );
}

function crf_get_generation_filename( $post_id, $template ) {
    return 'request-' . absint( $post_id ) . '-' . sanitize_key( $template ) . '.jpg';
}

function crf_get_generation_dir() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'advanced-certificate-registration/certificates/';
}

function crf_get_generation_url( $filename ) {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['baseurl'] ) . 'advanced-certificate-registration/certificates/' . $filename;
}

function crf_get_generated_files_status( $post_id ) {
    $labels = crf_get_generation_labels();
    $dir    = crf_get_generation_dir();
    $items  = array();

    foreach ( crf_get_generation_templates_for_registration( $post_id ) as $template ) {
        $filename = crf_get_generation_filename( $post_id, $template );
        $path     = $dir . $filename;
        $items[]  = array(
            'template' => $template,
            'label'    => isset( $labels[ $template ] ) ? $labels[ $template ] : $template,
            'exists'   => file_exists( $path ),
            'url'      => crf_get_generation_url( $filename ),
            'filename' => $filename,
        );
    }

    return $items;
}

function crf_render_generated_files_status_html( $post_id ) {
    $items = crf_get_generated_files_status( $post_id );
    $html  = '<ul class="crf-generated-files-list">';

    foreach ( $items as $item ) {
        $status = $item['exists'] ? 'موجود' : 'غير موجود';
        $class  = $item['exists'] ? 'exists' : 'missing';
        $html  .= '<li class="' . esc_attr( $class ) . '"><span>' . esc_html( $item['label'] ) . '</span>: ';
        if ( $item['exists'] ) {
            $html .= '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $status ) . '</a>';
        } else {
            $html .= '<em>' . esc_html( $status ) . '</em>';
        }
        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}

add_action( 'add_meta_boxes_courses', 'crf_add_tutor_course_market_meta_box' );
function crf_add_tutor_course_market_meta_box() {
    add_meta_box(
        'crf_course_market_type',
        'course market type',
        'crf_render_tutor_course_market_meta_box',
        'courses',
        'side',
        'default'
    );
}

function crf_render_tutor_course_market_meta_box( $post ) {
    $value = get_post_meta( $post->ID, CRF_COURSE_MARKET_META_KEY, true );
    if ( ! in_array( $value, array( 'other', 'experience' ), true ) ) {
        $value = 'other';
    }

    wp_nonce_field( 'crf_save_course_market_type', 'crf_course_market_nonce' );
    foreach ( crf_get_course_market_options() as $option_value => $label ) :
        ?>
        <p>
            <label>
                <input type="radio" name="crf_course_market_type" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $value, $option_value ); ?>>
                <?php echo esc_html( $label ); ?>
            </label>
        </p>
        <?php
    endforeach;
}

add_action( 'save_post_courses', 'crf_save_tutor_course_market_type' );
function crf_save_tutor_course_market_type( $post_id ) {
    if ( ! isset( $_POST['crf_course_market_nonce'] ) || ! wp_verify_nonce( $_POST['crf_course_market_nonce'], 'crf_save_course_market_type' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $value = isset( $_POST['crf_course_market_type'] ) ? sanitize_key( $_POST['crf_course_market_type'] ) : 'other';
    update_post_meta( $post_id, CRF_COURSE_MARKET_META_KEY, $value === 'experience' ? 'experience' : 'other' );
}

add_action( 'admin_menu', 'crf_add_course_market_mapping_page' );
function crf_add_course_market_mapping_page() {
    add_submenu_page(
        'edit.php?post_type=cert_registration',
        'ربط الدورات بنوع التسويق',
        'ربط الدورات بنوع التسويق',
        'manage_options',
        'crf-course-market-map',
        'crf_render_course_market_mapping_page'
    );
}

function crf_render_course_market_mapping_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'صلاحيات غير كافية.' );
    }

    $updated_count = 0;
    if ( isset( $_POST['crf_course_market_map_nonce'] ) && wp_verify_nonce( $_POST['crf_course_market_map_nonce'], 'crf_save_course_market_map' ) ) {
        $submitted_values = isset( $_POST['crf_course_market'] ) && is_array( $_POST['crf_course_market'] )
            ? wp_unslash( $_POST['crf_course_market'] )
            : array();

        foreach ( crf_get_tutor_courses_for_select() as $course ) {
            $course_id = absint( $course->ID );
            $value     = isset( $submitted_values[ $course_id ] ) ? sanitize_key( $submitted_values[ $course_id ] ) : 'other';
            $value     = $value === 'experience' ? 'experience' : 'other';

            update_post_meta( $course_id, CRF_COURSE_MARKET_META_KEY, $value );
            $updated_count++;
        }

        echo '<div class="notice notice-success is-dismissible"><p>تم حفظ نوع التسويق لعدد ' . esc_html( $updated_count ) . ' دورة.</p></div>';
    }

    $courses = crf_get_tutor_courses_for_select();
    ?>
    <div class="wrap crf-course-market-page" dir="rtl">
        <h1>ربط دورات Tutor LMS بنوع التسويق</h1>
        <p>اختر هل الدورة من النوع العادي <strong>other</strong> أو نوع الخبرة <strong>experience</strong>. يتم حفظ الاختيار في meta key: <code><?php echo esc_html( CRF_COURSE_MARKET_META_KEY ); ?></code></p>

        <?php if ( empty( $courses ) ) : ?>
            <div class="notice notice-warning"><p>لا توجد دورات Tutor LMS حالياً.</p></div>
        <?php else : ?>
            <form method="post">
                <?php wp_nonce_field( 'crf_save_course_market_map', 'crf_course_market_map_nonce' ); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="text-align:right;">الدورة</th>
                            <th style="width:120px;text-align:right;">الحالة</th>
                            <th style="width:320px;text-align:right;">نوع التسويق</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $courses as $course ) : ?>
                            <?php
                            $value = get_post_meta( $course->ID, CRF_COURSE_MARKET_META_KEY, true );
                            if ( ! in_array( $value, array( 'other', 'experience' ), true ) ) {
                                $value = 'other';
                            }
                            $status_object = get_post_status_object( $course->post_status );
                            $status_label  = $status_object ? $status_object->label : $course->post_status;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( get_the_title( $course ) ); ?></strong>
                                    <div style="color:#646970;">ID: <?php echo esc_html( $course->ID ); ?></div>
                                </td>
                                <td><?php echo esc_html( $status_label ); ?></td>
                                <td>
                                    <?php foreach ( crf_get_course_market_options() as $option_value => $label ) : ?>
                                        <label style="display:inline-block;margin-left:18px;">
                                            <input type="radio" name="crf_course_market[<?php echo esc_attr( $course->ID ); ?>]" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $value, $option_value ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">حفظ ربط الدورات</button>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// 1. Register Custom Post Type
add_action( 'init', 'crf_ult_register_cpt' );
function crf_ult_register_cpt() {
    $labels = array(
        'name'               => 'الشهادات المسجلة',
        'singular_name'      => 'شهادة',
        'menu_name'          => 'تسجيل الشهادات',
        'all_items'          => 'كل التسجيلات',
        'add_new'            => 'إضافة يدوية',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'capability_type'    => 'post',
        'supports'           => array( 'title' ),
        'menu_icon'          => 'dashicons-id-alt',
    );
    register_post_type( 'cert_registration', $args );
}

// 2. Add Meta Boxes for Post Edit Screen
add_action( 'add_meta_boxes', 'crf_ult_add_fields_meta_boxes' );
function crf_ult_add_fields_meta_boxes() {
    // الصندوق الرئيسي للبيانات
    add_meta_box(
        'crf_cert_details',
        '🔍 تفاصيل وبيانات طلب التسجيل بالكامل',
        'crf_ult_render_meta_box_callback',
        'cert_registration',
        'normal',
        'high'
    );

    // الصندوق الجانبي الجديد لتوليد الشهادات الـ 4
    add_meta_box(
        'crf_cert_generator_box',
        '🎓 لوحة توليد الشهادات الذكية',
        'crf_ult_render_generator_box_callback',
        'cert_registration',
        'side',
        'high'
    );
}

function crf_ult_render_meta_box_callback( $post ) {
    // جلب القيم الحالية من قاعدة البيانات
    $nat_id      = get_post_meta( $post->ID, '_nat_id', true );
    $phone       = get_post_meta( $post->ID, '_phone', true );
    $location    = get_post_meta( $post->ID, '_location', true );
    $course      = get_post_meta( $post->ID, '_course', true );
    $course_id   = absint( get_post_meta( $post->ID, '_course_id', true ) );
    $date        = get_post_meta( $post->ID, '_date', true );
    $receipt     = get_post_meta( $post->ID, '_receipt', true );
    $att_receipt = get_post_meta( $post->ID, '_att_receipt', true );
    $offer       = get_post_meta( $post->ID, '_offer', true );
    $status      = get_post_meta( $post->ID, '_status', true );

    $img_id      = get_post_meta( $post->ID, '_id_photo_id', true );
    $img_url     = $img_id ? wp_get_attachment_url( $img_id ) : '';

    wp_nonce_field( 'crf_save_meta_box_data', 'crf_meta_box_nonce' );
    ?>
    <style>
        .crf-admin-container { display: flex; gap: 20px; direction: rtl; padding: 10px; font-family: system-ui, sans-serif; }
        .crf-admin-grid { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .crf-meta-item { display: flex; flex-direction: column; margin-bottom: 10px; }
        .crf-meta-item label { font-weight: bold; margin-bottom: 5px; color: #23282d; }
        .crf-meta-item input, .crf-meta-item select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .crf-status-select { font-weight: bold; height: 36px; }
        .crf-admin-image-side { width: 180px; text-align: center; background: #f9f9f9; padding: 15px; border: 1px solid #e5e5e5; border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .crf-admin-image-side label { font-weight: bold; margin-bottom: 10px; display: block; color: #23282d; }
        @media(max-width: 768px) { .crf-admin-container { flex-direction: column-reverse; } .crf-admin-image-side { width: auto; } }
    </style>

    <div class="crf-admin-container">
        <div class="crf-admin-grid">
            <div class="crf-meta-item">
                <label>الرقم القومي أو رقم جواز السفر:</label>
                <input type="text" name="cert_nat_id" value="<?php echo esc_attr($nat_id); ?>">
            </div>
            <div class="crf-meta-item">
                <label>رقم التليفون:</label>
                <input type="text" name="cert_phone" value="<?php echo esc_attr($phone); ?>">
            </div>
            <div class="crf-meta-item">
                <label>المحافظة (مقر الانعقاد):</label>
                <input type="text" name="cert_location" value="<?php echo esc_attr($location); ?>">
            </div>
            <div class="crf-meta-item">
                <label>اسم الدورة:</label>
                <input type="text" name="cert_course" value="<?php echo esc_attr($course); ?>">
                <input type="hidden" name="cert_course_id" value="<?php echo esc_attr($course_id); ?>">
            </div>
            <div class="crf-meta-item">
                <label>تاريخ الدورة:</label>
                <input type="text" name="cert_date" value="<?php echo esc_attr($date); ?>">
            </div>
            <div class="crf-meta-item">
                <label>رقم إيصال الشهادات:</label>
                <input type="text" name="cert_receipt" value="<?php echo esc_attr($receipt); ?>">
            </div>
            <div class="crf-meta-item">
                <label>رقم إيصال الحضور:</label>
                <input type="text" name="cert_att_receipt" value="<?php echo esc_attr($att_receipt); ?>">
            </div>
            <div class="crf-meta-item">
                <label>نوع العرض المحجوز:</label>
                <input type="text" name="cert_offer" value="<?php echo esc_attr($offer); ?>">
            </div>
            <div class="crf-meta-item" style="grid-column: span 2; background: #f1f7fa; padding: 12px; border-radius: 4px; border: 1px solid #d5e3ef;">
                <label style="color: #0073aa; font-size: 14px; margin-bottom: 8px;">🎛 التحكم في حالة الطلب والاعتماد:</label>
                <select name="cert_status" class="crf-status-select">
                    <option value="pending" <?php selected($status, 'pending'); ?>>⏳ قيد الانتظار والمراجعة</option>
                    <option value="approved" <?php selected($status, 'approved'); ?>>✅ مقبول ومعتمد</option>
                    <option value="rejected" <?php selected($status, 'rejected'); ?>>❌ مرفوض</option>
                </select>
            </div>
        </div>

        <div class="crf-admin-image-side">
            <label>📷 صورة الكارنية المرفوعة:</label>
            <?php if ( $img_url ): ?>
                <a href="<?php echo esc_url($img_url); ?>" target="_blank" class="button button-secondary">فتح الصورة بالحجم الكامل</a>
            <?php else: ?>
                <div style="background: #f1f1f1; padding: 10px; color:#999; font-size:12px;">لم يتم رفع صورة</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// واجهة صندوق الأزرار الأربعة الجانبي لتوليد الشهادات المختلفة
function crf_ult_render_generator_box_callback( $post ) {
    wp_nonce_field( 'crf_generation_nonce_action', 'crf_generation_nonce' );
    $market_type = crf_get_registration_market_type( $post->ID );
    ?>
    <style>
        .crf-gen-box { direction: rtl; text-align: right; font-family: system-ui, sans-serif; padding: 5px; }
        .crf-gen-btn { display: block; width: 100%; text-align: center; margin-bottom: 10px; padding: 10px !important; font-size: 13px !important; font-weight: bold !important; height: auto !important; }
        .crf-status-msg { margin-top: 8px; padding: 8px; border-radius: 4px; display: none; font-size: 12px; font-weight: bold; }
        .crf-status-msg.success { background: #e2f0d9; color: #385723; border: 1px solid #c5e0b4; }
        .crf-status-msg.error { background: #fce4d6; color: #c65911; border: 1px solid #f8cbad; }
        .crf-generated-files-list { margin: 10px 0 14px; padding: 0; }
        .crf-generated-files-list li { display: flex; justify-content: space-between; gap: 6px; margin: 0 0 6px; padding: 6px 8px; border-radius: 4px; background: #f6f7f7; font-size: 12px; }
        .crf-generated-files-list li.exists { border-right: 4px solid #46b450; }
        .crf-generated-files-list li.missing { border-right: 4px solid #d63638; }
        .crf-generated-files-list em { color: #777; font-style: normal; }
    </style>

    <div class="crf-gen-box">
        <p style="color: #666; font-size: 12px;">نوع تسويق الدورة: <strong><?php echo esc_html( $market_type ); ?></strong></p>
        <div id="crf-generated-files-status"><?php echo crf_render_generated_files_status_html( $post->ID ); ?></div>
        
        <button type="button" class="button button-primary crf-gen-btn" data-cert-action="card" style="background:#2ecc71; border-color:#27ae60;">توليد الكارنية</button>
        <button type="button" class="button button-primary crf-gen-btn" data-cert-action="management" style="background:#3498db; border-color:#2980b9;">توليد المديرية</button>
        <button type="button" class="button button-primary crf-gen-btn" data-cert-action="council" style="background:#9b59b6; border-color:#8e44ad;">توليد مجلس</button>
        <button type="button" class="button button-primary crf-gen-btn" data-cert-action="certificate" style="background:#f1c40f; border-color:#f39c12; color:#000;">توليد الشهادة</button>
        <button type="button" class="button button-secondary crf-gen-btn" data-cert-action="all">توليد الكل مرة واحدة</button>

        <div id="crf-generator-status" class="crf-status-msg"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.crf-gen-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var certAction = $btn.data('cert-action');
            var postId = '<?php echo $post->ID; ?>';
            var nonce = $('#crf_generation_nonce').val();
            var $statusBox = $('#crf-generator-status');

            if ($btn.prop('disabled')) return;

            // إعداد حالة التحميل
            $('.crf-gen-btn').prop('disabled', true);
            var originalText = $btn.text();
            $btn.text('🔄 جاري التوليد...');

            $statusBox.hide().removeClass('success error');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'crf_generate_admin_certificate',
                    post_id: postId,
                    cert_action: certAction,
                    security: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $statusBox.addClass('success').html('✅ ' + response.data.message).fadeIn();
                        if (response.data.status_html) {
                            $('#crf-generated-files-status').html(response.data.status_html);
                        }
                    } else {
                        $statusBox.addClass('error').text('❌ ' + response.data).fadeIn();
                    }
                },
                error: function() {
                    $statusBox.addClass('error').text('❌ حدث خطأ غير متوقع في الخادم.').fadeIn();
                },
                complete: function() {
                    $('.crf-gen-btn').prop('disabled', false);
                    $btn.text(originalText);
                }
            });
        });
    });
    </script>
    <?php
}

// 3. Handle AJAX Certificate Generation logic (دمج منطق الملف الخاص بك بالكامل)
add_action( 'wp_ajax_crf_generate_admin_certificate', 'crf_handle_admin_certificate_generation' );
function crf_handle_admin_certificate_generation() {
    check_ajax_referer( 'crf_generation_nonce_action', 'security' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'صلاحيات غير كافية.' );
    }

    $post_id     = intval($_POST['post_id']);
    $cert_action = isset( $_POST['cert_action'] ) ? sanitize_key( $_POST['cert_action'] ) : '';

    if ( ! $post_id || empty( $cert_action ) ) {
        wp_send_json_error( 'بيانات الطلب غير مكتملة.' );
    }

    $market_type = crf_get_registration_market_type( $post_id );
    $templates   = array();

    if ( $cert_action === 'all' ) {
        $templates = crf_get_generation_templates_for_registration( $post_id );
    } elseif ( $cert_action === 'card' ) {
        $templates = array( 'card' );
    } elseif ( $cert_action === 'management' ) {
        $templates = array( 'management' );
    } elseif ( $cert_action === 'council' ) {
        $templates = array( $market_type === 'experience' ? 'eagletogether' : 'eaglestate' );
    } elseif ( $cert_action === 'certificate' ) {
        $templates = array( $market_type === 'experience' ? 'experience' : 'seat' );
    }

    if ( empty( $templates ) ) {
        wp_send_json_error( 'نوع التوليد غير صحيح.' );
    }

    $generated = array();
    foreach ( $templates as $template ) {
        $result = crf_generate_confirm_cert_file( $post_id, $template );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $generated[] = $result;
    }

    wp_send_json_success(array(
        'message'     => 'تم توليد ' . count( $generated ) . ' ملف بنجاح.',
        'files'       => $generated,
        'status_html' => crf_render_generated_files_status_html( $post_id ),
    ));
}

function crf_generate_confirm_cert_file( $post_id, $template ) {
    $certificates_dir = crf_get_generation_dir();
    if ( ! file_exists( $certificates_dir ) ) {
        wp_mkdir_p( $certificates_dir );
    }

    if ( ! is_writable( $certificates_dir ) ) {
        return new WP_Error( 'crf_generation_dir_not_writable', 'مجلد ملفات confirm-certs/certificates غير قابل للكتابة.' );
    }

    $filename = crf_get_generation_filename( $post_id, $template );
    $save_url = plugins_url( 'confirm-certs/index.php', __FILE__ );
    $img_id   = get_post_meta( $post_id, '_id_photo_id', true );
    $photo    = $img_id ? wp_get_attachment_url( $img_id ) : '';
    $date     = get_post_meta( $post_id, '_date', true );
    $args     = array(
        'tpl'           => $template,
        'name'          => get_the_title( $post_id ),
        'course'        => get_post_meta( $post_id, '_course', true ),
        'date'          => $date,
        'governorate'   => get_post_meta( $post_id, '_location', true ),
        'national_id'   => get_post_meta( $post_id, '_nat_id', true ),
        'approval_date' => $date,
        'registration'  => get_post_meta( $post_id, '_receipt', true ),
        'photo'         => $photo,
        'save'          => '0',
    );

    $response = wp_remote_get( add_query_arg( $args, $save_url ), array(
        'timeout'     => 60,
        'redirection' => 3,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'advanced-certificate-registration: confirm-certs request failed: ' . $response->get_error_message() );
        return new WP_Error( 'crf_confirm_certs_request_failed', 'فشل الاتصال بسكريبت confirm-certs: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        $body    = wp_remote_retrieve_body( $response );
        $message = trim( wp_strip_all_tags( $body ) );
        if ( strlen( $message ) > 500 ) {
            $message = substr( $message, 0, 500 ) . '...';
        }

        error_log( 'advanced-certificate-registration: confirm-certs returned HTTP ' . $code . ' for template ' . $template . '. Body: ' . $message );

        return new WP_Error(
            'crf_confirm_certs_bad_response',
            'confirm-certs أعاد حالة HTTP غير ناجحة: ' . $code . ( $message ? ' - ' . $message : '' )
        );
    }

    $path = $certificates_dir . $filename;
    $body = wp_remote_retrieve_body( $response );
    if ( ! empty( $body ) ) {
        $written = file_put_contents( $path, $body );
        if ( false === $written ) {
            error_log( 'advanced-certificate-registration: failed writing generated certificate to ' . $path );
            return new WP_Error( 'crf_confirm_certs_write_failed', 'تعذر حفظ الملف الناتج: ' . $filename );
        }
    }

    if ( ! file_exists( $path ) ) {
        error_log( 'advanced-certificate-registration: generated certificate file missing at ' . $path );
        return new WP_Error( 'crf_confirm_certs_missing_file', 'لم يتم العثور على الملف الناتج: ' . $filename );
    }

    update_post_meta( $post_id, '_generated_cert_url_' . sanitize_key( $template ), crf_get_generation_url( $filename ) );

    return array(
        'template' => $template,
        'url'      => crf_get_generation_url( $filename ),
        'filename' => $filename,
    );
}

// Save Meta Box Data on Post Save
add_action( 'save_post_cert_registration', 'crf_ult_save_meta_box_fields' );
function crf_ult_save_meta_box_fields( $post_id ) {
    if ( ! isset( $_POST['crf_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['crf_meta_box_nonce'], 'crf_save_meta_box_data' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['cert_nat_id'] ) ) update_post_meta( $post_id, '_nat_id', sanitize_text_field( $_POST['cert_nat_id'] ) );
    if ( isset( $_POST['cert_phone'] ) ) update_post_meta( $post_id, '_phone', sanitize_text_field( $_POST['cert_phone'] ) );
    if ( isset( $_POST['cert_location'] ) ) update_post_meta( $post_id, '_location', sanitize_text_field( $_POST['cert_location'] ) );
    if ( isset( $_POST['cert_course'] ) ) update_post_meta( $post_id, '_course', sanitize_text_field( $_POST['cert_course'] ) );
    if ( isset( $_POST['cert_course_id'] ) ) update_post_meta( $post_id, '_course_id', absint( $_POST['cert_course_id'] ) );
    if ( isset( $_POST['cert_date'] ) ) update_post_meta( $post_id, '_date', sanitize_text_field( $_POST['cert_date'] ) );
    if ( isset( $_POST['cert_receipt'] ) ) update_post_meta( $post_id, '_receipt', sanitize_text_field( $_POST['cert_receipt'] ) );
    if ( isset( $_POST['cert_att_receipt'] ) ) update_post_meta( $post_id, '_att_receipt', sanitize_text_field( $_POST['cert_att_receipt'] ) );
    if ( isset( $_POST['cert_offer'] ) ) update_post_meta( $post_id, '_offer', sanitize_text_field( $_POST['cert_offer'] ) );
    if ( isset( $_POST['cert_status'] ) ) update_post_meta( $post_id, '_status', sanitize_text_field( $_POST['cert_status'] ) );
}

// 4. Load External Scripts and Styles for Front-End Form
add_action( 'wp_enqueue_scripts', 'crf_ult_enqueue_assets' );
function crf_ult_enqueue_assets() {
    wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
    wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true );
    wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css', array(), '1.12.1' );
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_script( 'jquery-validate', 'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js', array('jquery'), '1.19.5', true );
}

// 5. Shortcode Front-End Form [advanced_cert_form]
add_shortcode( 'advanced_cert_form', 'crf_ult_render_form' );
function crf_ult_render_form() {
    $states = ["القاهرة + الجيزة", "الشرقية", "الدقهلية", "دمياط", "بورسعيد", "الإسكندرية", "البحيرة", "الغربية", "بنها", "شبرا الخيمة", "المنوفية", "الاسماعيلية", "السويس", "كفر الشيخ", "مرسي مطروح", "بني سويف", "الفيوم", "المنيا", "اسيوط", "سوهاج", "الأقصر", "أسوان", "الوادي الجديد", "الغردقة", "العريش", "قنا"];
    $courses = ["الإسعافات الأولية", "التخاطب وتعديل السلوك", "نور البيان", "إدراة الموارد البشرية ( HR )", "التسويق", "المحاسبة الإلكترونية", "الحجامة", "الإبر الصينية", "سم النحل", "اعداد القادة", "التنمية البشرية", "التغذية العلاجية", "التحاليل الطبية", "التأهيل لسوق العمل", "الحساب الذهني", "التدليك و العناية الجسدية", "الصحافة و الاذاعة و الاعلام", "العلاقات العامة", "تدريب المدربين ( TOT )", "السلامة والصحة المهنية", "الضيافة الجوية", "المساحة و الخرائط", "الذكاء الاصطناعي", "البرمجة", "المقاييس النفسية"];
    $tutor_courses = crf_get_tutor_courses_for_select();

    ob_start(); ?>
    <style>
        .adv-cert-form { direction: rtl; text-align: right; max-width: 600px; margin: 20px auto; padding: 30px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eef2f5; font-family: system-ui, -apple-system, sans-serif; }
        .form-row { margin-bottom: 20px; }
        .form-row label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px; }
        .form-row input[type="text"], .form-row input[type="tel"], .form-row select { width: 100%; padding: 12px; border: 1px solid #cccccc; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border 0.3s; background: #fff; }
        .form-row input:focus, .form-row select:focus { border-color: #3498db; outline: none; }
        .select2-container--default .select2-selection--single { height: 45px; border: 1px solid #ccc; border-radius: 6px; direction: rtl; text-align: right; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 43px; padding-right: 12px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 43px; left: 10px; right: auto; }
        select.error + .select2-container .select2-selection--single { border-color: #e74c3c !important; }
        .file-upload-wrapper { display: flex; flex-direction: column; align-items: center; gap: 15px; background: #f8f9fa; padding: 15px; border: 2px dashed #ccc; border-radius: 8px; position: relative; }
        .file-upload-btn { background: #34495e; color: #fff; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; display: inline-block; }
        .image-preview-box { width: 60px; height: 60px; border-radius: 6px; border: 1px solid #ddd; background: #eaeded; background-size: cover; background-position: center; display: none; }
        .file-status-text { font-size: 13px; color: #7f8c8d; }
        label.error { color: #e74c3c !important; font-size: 13px !important; margin-top: 5px !important; display: block !important; }
        input.error, select.error { border-color: #e74c3c !important; }
        .submit-btn { background: #2ecc71; color: white; border: none; padding: 14px 25px; cursor: pointer; border-radius: 6px; width: 100%; font-size: 16px; font-weight: bold; }
        #form-status { margin-top: 15px; padding: 12px; border-radius: 6px; display: none; font-weight: bold; font-size: 14px; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .loading-box { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .error-box { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>

    <div class="adv-cert-form">
        <form id="advCertForm" enctype="multipart/form-data">
            <div class="form-row">
                <label for="full_name">الإسم رباعي طبقا لبطاقة الرقم القومي:</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-row">
                <label for="national_id">الرقم القومي كاملاً طبقا لبطاقة الرقم القومي:</label>
                <input type="text" id="national_id" name="national_id" required>
            </div>
            <div class="form-row">
                <label for="phone_number">رقم التليفون للإتصال:</label>
                <input type="tel" id="phone_number" name="phone_number" required>
            </div>
            <div class="form-row">
                <label for="course_location">المحافظة ( مقر انعقاد الدورة ) وليست محافظتك:</label>
                <select id="course_location" name="course_location" style="width: 100%;" required>
                    <option value="">-- اختر المحافظة --</option>
                    <?php foreach($states as $state): ?>
                        <option value="<?php echo esc_attr($state); ?>"><?php echo esc_html($state); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="course_name">إسم الدورة:</label>
                <select id="course_name" name="course_name" style="width: 100%;" required>
                    <option value="">-- اختر الدورة --</option>
                    <?php if ( ! empty( $tutor_courses ) ) : ?>
                        <?php foreach ( $tutor_courses as $course_post ) : ?>
                            <option value="<?php echo esc_attr( get_the_title( $course_post ) ); ?>" data-course-id="<?php echo esc_attr( $course_post->ID ); ?>"><?php echo esc_html( get_the_title( $course_post ) ); ?></option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php foreach($courses as $course): ?>
                            <option value="<?php echo esc_attr($course); ?>" data-course-id="0"><?php echo esc_html($course); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <input type="hidden" id="course_id" name="course_id" value="">
            </div>
            <div class="form-row">
                <label for="course_date">تاريخ الدورة:</label>
                <input type="text" id="course_date" name="course_date" autocomplete="off" readonly required>
            </div>
            <div class="form-row">
                <label for="receipt_number">الرقم المدون علي ايصال حجز الشهادات:</label>
                <input type="text" id="receipt_number" name="receipt_number" required>
            </div>
            <div class="form-row">
                <label for="attendance_receipt_number">الرقم المدون علي ايصال حجز الحضور:</label>
                <input type="text" id="attendance_receipt_number" name="attendance_receipt_number" required>
            </div>
            <div class="form-row">
                <label for="offer_type">برجاء اختيار نوع العرض الذي قمت بحجزة مع مسئول الحجز:</label>
                <select id="offer_type" name="offer_type" required>
                    <option value="عرض vip شامل جميع الشهادات والكارنية">عرض vip شامل جميع الشهادات والكارنية</option>
                </select>
            </div>
            <div class="form-row">
                <label>صورة الكارنية برجاء تحميل اي صورة من علي موبايلك:</label>
                <div class="file-upload-wrapper">
                    <label for="id_photo" class="file-upload-btn">اختر صورة 📷</label>
                    <input type="file" id="id_photo" name="id_photo" accept="image/*" required>
                    <div id="imagePreview" class="image-preview-box"></div>
                    <span id="fileStatusText" class="file-status-text">لم يتم اختيار صورة بعد</span>
                </div>
            </div>

            <?php wp_nonce_field( 'adv_cert_save', 'cert_nonce' ); ?>
            <button type="submit" class="submit-btn">إرسال طلب التسجيل</button>
            <div id="form-status"></div>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#course_location, #course_name').select2({ placeholder: "-- اختر من القائمة --", allowClear: true });
        $('#course_name').on('change', function() {
            $('#course_id').val($(this).find(':selected').data('course-id') || '');
        });
        $('#id_photo').on('change', function() {
            const file = this.files[0];
            if (file) {
                $('#fileStatusText').text(file.name);
                const reader = new FileReader();
                reader.onload = function(e) { $('#imagePreview').css('background-image', 'url(' + e.target.result + ')').show(); }
                reader.readAsDataURL(file);
            } else { $('#imagePreview').hide(); }
        });

        $.datepicker.regional['ar'] = {
            closeText: 'إغلاق', prevText: 'السابق', nextText: 'التالي', currentText: 'اليوم',
            monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
            dayNamesMin: ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'], dateFormat: 'yy-mm-dd', isRTL: true
        };
        $.datepicker.setDefaults($.datepicker.regional['ar']);
        $('#course_date').datepicker();

        $("#advCertForm").validate({
            rules: { 
                national_id: { required: true, minlength: 5, maxlength: 30 }
            },
            messages: {
                full_name: "برجاء إدخال الاسم رباعي كاملاً كما بالبطاقة.",
                national_id: "يجب إدخال الرقم القومي أو رقم جواز السفر بشكل صحيح.",
                phone_number: "برجاء إدخال رقم التليفون للاستصال.",
                course_location: "برجاء اختيار محافظة مقر الدورة.",
                course_name: "برجاء اختيار اسم الدورة.",
                course_date: "برجاء تحديد التاريخ.",
                receipt_number: "برجاء إدخال رقم إيصال الشهادات.",
                attendance_receipt_number: "برجاء إدخال رقم إيصال الحضور.",
                id_photo: "برجاء إرفاق الصورة."
            },
            errorPlacement: function(error, element) {
                if (element.attr("name") === "id_photo") { error.insertAfter(element.closest('.file-upload-wrapper')); }
                else if (element.hasClass('select2-hidden-accessible')) { error.insertAfter(element.next('.select2-container')); }
                else { error.insertAfter(element); }
            },
            submitHandler: function(form) {
                const statusDiv = document.getElementById('form-status');
                const formData = new FormData(form);
                formData.append('action', 'save_adv_cert_data');
                statusDiv.style.display = 'block'; statusDiv.className = 'loading-box'; statusDiv.innerHTML = '🔄 جاري إرسال البيانات...';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        statusDiv.className = 'success'; statusDiv.innerHTML = '✅ ' + data.data; form.reset();
                        $('#imagePreview').hide(); $('#course_location, #course_name').val(null).trigger('change');
                    } else { statusDiv.className = 'error-box'; statusDiv.innerHTML = '❌ ' + data.data; }
                });
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// 6. Handle AJAX Form Submission
add_action( 'wp_ajax_save_adv_cert_data', 'crf_ult_process' );
add_action( 'wp_ajax_nopriv_save_adv_cert_data', 'crf_ult_process' );
function crf_ult_process() {
    check_ajax_referer( 'adv_cert_save', 'cert_nonce' );

    $post_id = wp_insert_post( array(
        'post_title'  => sanitize_text_field( $_POST['full_name'] ),
        'post_type'   => 'cert_registration',
        'post_status' => 'publish'
    ) );

    if ( $post_id ) {
        update_post_meta( $post_id, '_nat_id', sanitize_text_field( $_POST['national_id'] ) );
        update_post_meta( $post_id, '_phone', sanitize_text_field( $_POST['phone_number'] ) );
        update_post_meta( $post_id, '_location', sanitize_text_field( $_POST['course_location'] ) );
        update_post_meta( $post_id, '_course', sanitize_text_field( $_POST['course_name'] ) );
        update_post_meta( $post_id, '_course_id', isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0 );
        update_post_meta( $post_id, '_date', sanitize_text_field( $_POST['course_date'] ) );
        update_post_meta( $post_id, '_receipt', sanitize_text_field( $_POST['receipt_number'] ) );
        update_post_meta( $post_id, '_att_receipt', sanitize_text_field( $_POST['attendance_receipt_number'] ) );
        update_post_meta( $post_id, '_offer', sanitize_text_field( $_POST['offer_type'] ) );
        update_post_meta( $post_id, '_status', 'pending' );

        if ( ! empty( $_FILES['id_photo']['name'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $attachment_id = media_handle_upload( 'id_photo', $post_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                update_post_meta( $post_id, '_id_photo_id', $attachment_id );
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
        wp_send_json_success( 'تم استلام بياناتك بنجاح وهي قيد المراجعة حالياً.' );
    }
    wp_send_json_error( 'حدث خطأ ما.' );
}

// 7. Admin Table Columns Setup
add_filter( 'manage_cert_registration_posts_columns', 'crf_ult_cols' );
function crf_ult_cols( $cols ) {
    return array(
        'cb'           => $cols['cb'],
        'title'        => 'الإسم رباعي',
        'phone'        => 'رقم التليفون',
        'course'       => 'اسم الدورة',
        'receipts'     => 'إيصالات الدفع',
        'photo'        => 'صورة الكارنية',
        'generated'    => 'الملفات المولدة',
        'req_status'   => 'حالة الطلب',
        'actions'      => 'الإجراءات السريعة',
    );
}

add_action( 'manage_cert_registration_posts_custom_column', 'crf_ult_col_content', 10, 2 );
function crf_ult_col_content( $col, $post_id ) {
    switch ( $col ) {
        case 'phone': echo esc_html(get_post_meta($post_id, '_phone', true)); break;
        case 'course': echo esc_html(get_post_meta($post_id, '_course', true)); break;
        case 'receipts': 
            echo '<b>الشهادات:</b> ' . esc_html(get_post_meta($post_id, '_receipt', true)) . '<br>';
            echo '<b>الحضور:</b> ' . esc_html(get_post_meta($post_id, '_att_receipt', true));
            break;
        case 'photo': 
            $img_id = get_post_meta($post_id, '_id_photo_id', true);
            if($img_id) echo '<a href="'.esc_url(wp_get_attachment_url($img_id)).'" target="_blank">'.wp_get_attachment_image($img_id, array(40, 40)).'</a>';
            break;
        case 'generated':
            echo crf_render_generated_files_status_html( $post_id );
            break;
        case 'req_status':
            $status = get_post_meta($post_id, '_status', true);
            if ($status == 'approved') echo '<span style="background:#2ecc71;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold;">مقبول</span>';
            elseif ($status == 'rejected') echo '<span style="background:#e74c3c;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold;">مرفوض</span>';
            else echo '<span style="background:#f1c40f;color:#000;padding:4px 8px;border-radius:4px;font-weight:bold;">قيد الانتظار</span>';
            break;
        case 'actions':
            $approve_url = wp_nonce_url( admin_url('admin-ajax.php?action=change_cert_status&post_id='.$post_id.'&status=approved'), 'change_status_nonce' );
            $reject_url  = wp_nonce_url( admin_url('admin-ajax.php?action=change_cert_status&post_id='.$post_id.'&status=rejected'), 'change_status_nonce' );
            echo '<a href="'.esc_url($approve_url).'" class="button button-small" style="background:#2ecc71;color:#fff;border:none;margin-left:4px;">قبول</a>';
            echo '<a href="'.esc_url($reject_url).'" class="button button-small" style="background:#e74c3c;color:#fff;border:none;">رفض</a>';
            break;
    }
}

add_action( 'admin_head-edit.php', 'crf_admin_generated_files_column_styles' );
function crf_admin_generated_files_column_styles() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'cert_registration' ) return;
    ?>
    <style>
        .column-generated { width: 190px; }
        .column-generated .crf-generated-files-list { margin: 0; padding: 0; }
        .column-generated .crf-generated-files-list li { margin: 0 0 4px; font-size: 12px; line-height: 1.4; }
        .column-generated .crf-generated-files-list li.exists a { color: #008a20; font-weight: 600; }
        .column-generated .crf-generated-files-list li.missing em { color: #777; font-style: normal; }
    </style>
    <?php
}

add_action( 'wp_ajax_change_cert_status', 'crf_handle_change_status' );
function crf_handle_change_status() {
    check_admin_referer( 'change_status_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die('صلاحيات غير كافية.');
    $post_id = intval($_GET['post_id']);
    $status  = sanitize_text_field($_GET['status']);
    if ( in_array($status, array('approved', 'rejected')) ) {
        update_post_meta( $post_id, '_status', $status );
    }
    wp_redirect( wp_get_referer() );
    exit;
}

// 8. Export functionality
add_action( 'manage_posts_extra_tablenav', 'crf_ult_add_export_button' );
function crf_ult_add_export_button( $which ) {
    global $typenow; if ( $typenow !== 'cert_registration' || $which !== 'top' ) return;
    $export_url = add_query_arg( array( 'action' => 'export_certs' ), admin_url('admin-ajax.php') );
    echo '<div class="alignleft actions"><a href="' . esc_url($export_url) . '" class="button button-primary" style="background:#1ab394;border-color:#1ab394;font-weight:bold;">تصدير الطلبات الحالية لـ Excel 📥</a></div>';
}

add_action( 'wp_ajax_export_certs', 'crf_ult_handle_export' );
function crf_ult_handle_export() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die('غير مسموح لك بالوصول.');
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=تسجيلات_الشهادات_' . date('Y-m-d') . '.xls');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    fwrite($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fwrite($output, "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>\n");
    fwrite($output, "<head>\n");
    fwrite($output, "<meta http-equiv='content-type' content='text/html; charset=utf-8'>\n");
    fwrite($output, "<style>td { border: 0.5pt solid #cccccc; text-align: right; font-family: Arial, sans-serif; } th { background-color: #f2f2f2; border: 0.5pt solid #cccccc; font-weight: bold; text-align: right; }</style>\n");
    fwrite($output, "</head>\n");
    fwrite($output, "<body>\n");
    fwrite($output, "<table>\n");
    
    fwrite($output, "<tr>\n");
    $headers = array('الإسم رباعي', 'الرقم القومي', 'رقم التليفون', 'المحافظة', 'اسم الدورة', 'تاريخ الدورة', 'إيصال الشهادات', 'إيصال الحضور', 'نوع العرض', 'حالة الطلب', 'رابط الصورة المرفوعة');
    foreach ( $headers as $header ) {
        fwrite($output, "<th>" . esc_html($header) . "</th>\n");
    }
    fwrite($output, "</tr>\n");

    $query = new WP_Query( array( 'post_type' => 'cert_registration', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
    while ( $query->have_posts() ) {
        $query->the_post(); 
        $pid = get_the_ID();
        
        $raw_status = get_post_meta($pid, '_status', true);
        $clean_status = ($raw_status === 'approved') ? 'مقبول' : (($raw_status === 'rejected') ? 'مرفوض' : 'قيد الانتظار');
        
        $img_id = get_post_meta($pid, '_id_photo_id', true);
        $img_url = $img_id ? wp_get_attachment_url($img_id) : 'لا توجد صورة';

        $row_data = array(
            get_the_title(), 
            get_post_meta($pid, '_nat_id', true), 
            get_post_meta($pid, '_phone', true),
            get_post_meta($pid, '_location', true), 
            get_post_meta($pid, '_course', true), 
            get_post_meta($pid, '_date', true),
            get_post_meta($pid, '_receipt', true), 
            get_post_meta($pid, '_att_receipt', true), 
            get_post_meta($pid, '_offer', true),
            $clean_status,
            $img_url
        );

        fwrite($output, "<tr>\n");
        foreach ( $row_data as $value ) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            if ( is_numeric($value) && strlen($value) > 10 ) {
                fwrite($output, "<td style='vnd.ms-excel.numberformat:@'>" . $value . "</td>\n");
            } else {
                fwrite($output, "<td>" . $value . "</td>\n");
            }
        }
        fwrite($output, "</tr>\n");
    }
    
    wp_reset_postdata();
    fwrite($output, "</table>\n");
    fwrite($output, "</body>\n");
    fwrite($output, "</html>\n");
    fclose($output); 
    exit;
}
