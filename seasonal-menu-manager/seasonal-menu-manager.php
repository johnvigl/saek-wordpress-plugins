<?php
/*
Plugin Name: Seasonal Menu Manager
Description: Easily hide menus and front page sections manually or on a scheduled date range.
Version: 1.4
Author: Dimitra
*/

if (!defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------
// Helper: Check if an item should be hidden today
// ---------------------------------------------------------
function smm_is_currently_hidden($is_disabled, $hide_from, $hide_until) {
    if ($is_disabled === 'yes') {
        return true; 
    }
    if (!empty($hide_from) && !empty($hide_until)) {
        $today = current_time('Y-m-d');
        if ($today >= $hide_from && $today <= $hide_until) {
            return true; 
        }
    }
    return false;
}

// ---------------------------------------------------------
// 1. Register the Admin Menu Page
// ---------------------------------------------------------
add_action('admin_menu', 'smm_add_admin_menu');

function smm_add_admin_menu() {
    add_menu_page('Seasonal Menus', 'Seasonal Menus', 'manage_options', 'seasonal-menu-manager', 'smm_render_admin_page', 'dashicons-calendar-alt', 60);
}

// ---------------------------------------------------------
// 2. Render the Admin Page & Handle Saves
// ---------------------------------------------------------
function smm_render_admin_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['smm_save']) && check_admin_referer('smm_nonce_action', 'smm_nonce')) {
        
        // Save Menus
        $disabled_items = isset($_POST['disabled_items']) ? $_POST['disabled_items'] : [];
        $hide_from      = isset($_POST['smm_menu_hide_from']) ? $_POST['smm_menu_hide_from'] : [];
        $hide_until     = isset($_POST['smm_menu_hide_until']) ? $_POST['smm_menu_hide_until'] : [];
        
        $all_items = get_posts(['post_type' => 'nav_menu_item', 'numberposts' => -1]);

        foreach ($all_items as $item) {
            $id = $item->ID;
            in_array($id, $disabled_items) ? update_post_meta($id, '_smm_disabled', 'yes') : delete_post_meta($id, '_smm_disabled');
            update_post_meta($id, '_smm_hide_from', sanitize_text_field($hide_from[$id] ?? ''));
            update_post_meta($id, '_smm_hide_until', sanitize_text_field($hide_until[$id] ?? ''));
        }

        // Save Anchors
        $registered_anchors = isset($_POST['smm_registered_anchors']) ? sanitize_textarea_field(wp_unslash($_POST['smm_registered_anchors'])) : '';
        update_option('smm_registered_anchors', $registered_anchors);

        $disabled_anchors = isset($_POST['smm_disabled_anchors']) ? array_map('sanitize_text_field', wp_unslash($_POST['smm_disabled_anchors'])) : [];
        update_option('smm_disabled_anchors', $disabled_anchors);

        $anchor_hide_from = isset($_POST['smm_anchor_hide_from']) ? array_map('sanitize_text_field', wp_unslash($_POST['smm_anchor_hide_from'])) : [];
        update_option('smm_anchor_hide_from', $anchor_hide_from);

        $anchor_hide_until = isset($_POST['smm_anchor_hide_until']) ? array_map('sanitize_text_field', wp_unslash($_POST['smm_anchor_hide_until'])) : [];
        update_option('smm_anchor_hide_until', $anchor_hide_until);

        echo '<div class="notice notice-success is-dismissible"><p>Seasonal settings successfully updated.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Seasonal Content Manager</h1>';
    echo '<p>Check the box to force-hide an item immediately, <strong>OR</strong> set dates to hide it automatically during an off-season.</p>';
    
    echo '<form method="post" action="">';
    wp_nonce_field('smm_nonce_action', 'smm_nonce');

    // --- FRONT PAGE SECTIONS UI (Checkboxes Only) ---
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Front Page Sections</h2>';
    
    $registered_anchors_text = get_option('smm_registered_anchors', '');
    
    if (!empty($registered_anchors_text)) {
        $lines = explode("\n", $registered_anchors_text);
        $disabled_anchors = get_option('smm_disabled_anchors', []);
        $saved_anchor_from = get_option('smm_anchor_hide_from', []);
        $saved_anchor_until = get_option('smm_anchor_hide_until', []);

        echo '<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; max-width: 770px;">';
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $name = trim($parts[0]);
                $clean_anchor = ltrim(trim($parts[1]), '#'); 
                
                $checked = in_array($clean_anchor, $disabled_anchors) ? 'checked="checked"' : '';
                $from_val = esc_attr($saved_anchor_from[$clean_anchor] ?? '');
                $until_val = esc_attr($saved_anchor_until[$clean_anchor] ?? '');
                
                echo '<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #eee;">';
                echo '<label style="flex: 1;"><input type="checkbox" name="smm_disabled_anchors[]" value="' . esc_attr($clean_anchor) . '" ' . $checked . '> <strong>' . esc_html($name) . '</strong></label>';
                echo '<span style="font-size:12px; color:#666;">Hide From:</span> <input type="date" name="smm_anchor_hide_from[' . esc_attr($clean_anchor) . ']" value="' . $from_val . '">';
                echo '<span style="font-size:12px; color:#666;">Until:</span> <input type="date" name="smm_anchor_hide_until[' . esc_attr($clean_anchor) . ']" value="' . $until_val . '">';
                echo '</div>';
            }
        }
        echo '</div>';
    } else {
        echo '<p style="color: #666;"><em>No front page sections registered yet.</em></p>';
    }

    // --- MENU UI ---
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Navigation Menus</h2>';

    $menus = wp_get_nav_menus();
    if ($menus) {
        foreach ($menus as $menu) {
            echo '<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px; max-width: 770px;">';
            echo '<h3 style="margin-top:0;">' . esc_html($menu->name) . '</h3>';
            
            $items = wp_get_nav_menu_items($menu->term_id);
            if ($items) {
                foreach ($items as $item) {
                    $is_disabled = get_post_meta($item->ID, '_smm_disabled', true) === 'yes';
                    $checked_html = $is_disabled ? 'checked="checked"' : '';
                    
                    $from_val = esc_attr(get_post_meta($item->ID, '_smm_hide_from', true));
                    $until_val = esc_attr(get_post_meta($item->ID, '_smm_hide_until', true));

                    $indent = ($item->menu_item_parent != 0) ? '&mdash; ' : '';
                    $title = empty($item->title) ? $item->post_title : $item->title;

                    echo '<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">';
                    echo '<label style="flex: 1;"><input type="checkbox" name="disabled_items[]" class="smm-checkbox" data-item-id="' . esc_attr($item->ID) . '" data-parent-id="' . esc_attr($item->menu_item_parent) . '" value="' . esc_attr($item->ID) . '" ' . $checked_html . '> ' . $indent . esc_html($title) . '</label>';
                    echo '<span style="font-size:12px; color:#666;">Hide From:</span> <input type="date" name="smm_menu_hide_from[' . esc_attr($item->ID) . ']" value="' . $from_val . '">';
                    echo '<span style="font-size:12px; color:#666;">Until:</span> <input type="date" name="smm_menu_hide_until[' . esc_attr($item->ID) . ']" value="' . $until_val . '">';
                    echo '</div>';
                }
            } else {
                echo '<p>No items in this menu.</p>';
            }
            echo '</div>';
        }
    }

    echo '<br><input type="submit" name="smm_save" class="button button-primary button-hero" value="Save All Changes">';
    
    // --- HIDDEN REGISTRATION MODAL ---
    echo '<hr style="margin: 40px 0 20px 0;">';
    echo '<p><a href="#" id="smm-open-modal" style="color: #a00; text-decoration: none;">⚙️ Advanced: Manage Section Anchors</a></p>';

    echo '<div id="smm-anchor-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6);">';
    echo '  <div style="background-color:#fff; margin:10% auto; padding:30px; border-radius:8px; width:90%; max-width:600px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">';
    echo '    <h3 style="margin-top:0;">Register Front Page Anchors</h3>';
    echo '    <p>Format: <strong>Friendly Name | anchor-id</strong> (one per line).</p>';
    echo '    <textarea name="smm_registered_anchors" rows="6" style="width:100%; margin-bottom:15px; font-family:monospace;" placeholder="Spring Promo | spring-section">' . esc_textarea($registered_anchors_text) . '</textarea>';
    echo '    <p style="text-align: right; margin-bottom:0;">';
    echo '      <button type="button" class="button" id="smm-close-modal">Close</button>';
    echo '      <span style="font-size: 12px; color:#666; margin-left: 10px;">(Click "Save All Changes" on main page to apply)</span>';
    echo '    </p>';
    echo '  </div>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';

    // UI Scripts (Modal + Checkbox Cascade)
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Modal logic
        $('#smm-open-modal').on('click', function(e) {
            e.preventDefault();
            $('#smm-anchor-modal').fadeIn(200);
        });
        $('#smm-close-modal').on('click', function(e) {
            e.preventDefault();
            $('#smm-anchor-modal').fadeOut(200);
        });

        // Cascading Checkboxes
        $('.smm-checkbox').on('change', function() {
            var isChecked = $(this).is(':checked');
            var itemId = $(this).data('item-id');
            function toggleChildren(parentId, state) {
                $('.smm-checkbox[data-parent-id="' + parentId + '"]').each(function() {
                    $(this).prop('checked', state);
                    toggleChildren($(this).data('item-id'), state);
                });
            }
            toggleChildren(itemId, isChecked);
        });
    });
    </script>
    <?php
}

// ---------------------------------------------------------
// 3. Filter the Frontend Menu Rendering
// ---------------------------------------------------------
add_filter('wp_nav_menu_objects', 'smm_filter_frontend_menu_items', 10, 2);

function smm_filter_frontend_menu_items($sorted_menu_items, $args) {
    $disabled_ids = [];
    
    foreach ($sorted_menu_items as $item) {
        $is_disabled = get_post_meta($item->ID, '_smm_disabled', true);
        $hide_from = get_post_meta($item->ID, '_smm_hide_from', true);
        $hide_until = get_post_meta($item->ID, '_smm_hide_until', true);

        if (smm_is_currently_hidden($is_disabled, $hide_from, $hide_until)) {
            $disabled_ids[] = $item->ID;
        }
    }

    foreach ($sorted_menu_items as $key => $item) {
        if (in_array($item->ID, $disabled_ids)) {
            unset($sorted_menu_items[$key]);
            continue;
        }
        if (in_array($item->menu_item_parent, $disabled_ids)) {
            $disabled_ids[] = $item->ID; 
            unset($sorted_menu_items[$key]);
        }
    }
    
    return $sorted_menu_items;
}

// ---------------------------------------------------------
// 4. Inject CSS to Hide Front Page Sections
// ---------------------------------------------------------
add_action('wp_head', 'smm_hide_front_page_sections');

function smm_hide_front_page_sections() {
    if (is_front_page() || is_home()) {
        $registered_anchors_text = get_option('smm_registered_anchors', '');
        if (empty($registered_anchors_text)) return;

        $disabled_anchors = get_option('smm_disabled_anchors', []);
        $saved_anchor_from = get_option('smm_anchor_hide_from', []);
        $saved_anchor_until = get_option('smm_anchor_hide_until', []);
        
        $anchors_to_hide = [];
        $lines = explode("\n", $registered_anchors_text);
        
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $clean_anchor = ltrim(trim($parts[1]), '#');
                
                $is_disabled = in_array($clean_anchor, $disabled_anchors) ? 'yes' : 'no';
                $hide_from = $saved_anchor_from[$clean_anchor] ?? '';
                $hide_until = $saved_anchor_until[$clean_anchor] ?? '';

                if (smm_is_currently_hidden($is_disabled, $hide_from, $hide_until)) {
                    $anchors_to_hide[] = $clean_anchor;
                }
            }
        }
        
        if (!empty($anchors_to_hide)) {
            echo "\n\n";
            echo "<style type='text/css' id='smm-seasonal-styles'>\n";
            foreach ($anchors_to_hide as $anchor) {
                $clean_anchor = esc_attr($anchor);
                echo "  #" . $clean_anchor . ", #" . $clean_anchor . " + * { display: none !important; }\n";
            }
            echo "</style>\n\n";
        }
    }
}
