<?php
/*
Plugin Name: Reviews Plugin (RP)
Plugin URI: https://renoku2.azharapp.com/reviews/
Description: Reviews for Renoku's Website 
Version: 1.0
Author:Shernice, Jannah
Author URI: https://github.com/oxnsher , https://github.com/nrjbms
License: GPL2
*/

// Plugin activation hook (Shernice & Jannah)
register_activation_hook(__FILE__, 'rp_create_reviews_table');

// Create custom database table on plugin activation (Shernice & Jannah)
function rp_create_reviews_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rp_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        rating tinyint(1) NOT NULL,
        comment text NOT NULL,
        created_at datetime NOT NULL DEFAULT current_timestamp(),
        published tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue styles (Shernice & Jannah)
function rp_enqueue_styles() {
    wp_enqueue_style('rp-styles', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'rp_enqueue_styles');
add_action('admin_enqueue_scripts', 'rp_enqueue_styles');

// Hook into admin menu (Shernice & Jannah)
add_action('admin_menu', 'rp_admin_menu');

// Admin menu setup (Shernice & Jannah)
function rp_admin_menu() {
    add_menu_page(
        'Renoku Reviews',
        'Renoku Reviews',
        'manage_options',
        'rp_reviews',
        'rp_reviews_page'
    );
}

// Admin page content 
function rp_reviews_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rp_reviews';

    // Handle form submissions - approve/reject/delete/edit (Shernice & Jannah)
    if (isset($_GET['action']) && in_array($_GET['action'], array('approve', 'reject', 'delete', 'edit')) && isset($_GET['review_id'])) {
        $review_id = intval($_GET['review_id']);
        if ($_GET['action'] === 'delete') {
            $wpdb->delete($table_name, array('id' => $review_id), array('%d'));
        } elseif ($_GET['action'] === 'edit' && isset($_POST['rp_edit_review'])) {
            check_admin_referer('rp_edit_review_' . $review_id);

            $name = sanitize_text_field($_POST['rp_name']);
            $rating = intval($_POST['rp_rating']);
            $comment = sanitize_textarea_field($_POST['rp_comment']);
            $wpdb->update(
                $table_name,
                array(
                    'name' => $name,
                    'rating' => $rating,
                    'comment' => $comment,
                ),
                array('id' => $review_id),
                array('%s', '%d', '%s'),
                array('%d')
            );
        } else {
            $status = ($_GET['action'] === 'approve') ? 1 : 0;
            $wpdb->update($table_name, array('published' => $status), array('id' => $review_id), array('%d'), array('%d'));
        }
    }

    // Handle new review submission (Shernice)
    if (isset($_POST['rp_admin_submit_review'])) {
        rp_save_admin_review();
    }

    // Handle email sending (Jannah)
    if (isset($_POST['rp_send_email'])) {
        check_admin_referer('rp_send_email_action');
        
        $email = sanitize_email($_POST['rp_email']);
        $subject = 'Check out our reviews';
        $message = 'Please visit our website to see the latest reviews: ' . get_site_url();
        wp_mail($email, $subject, $message);
        echo '<div class="updated"><p>Email sent successfully to ' . esc_html($email) . '.</p></div>';
    }

    // Fetch reviews (Shernice & Jannah)
    $reviews = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Renoku Reviews</h1>
        <h2>Add New Review</h2>
        <form method="post">
            <?php wp_nonce_field('rp_admin_add_review_action'); ?>
            <p>
                <label for="rp-name">Your Name *</label>
                <input type="text" name="rp_name" id="rp-name" required>
            </p>
            <p>
                <label for="rp-rating">Rating *</label>
                <select name="rp_rating" id="rp-rating" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </p>
            <p>
                <label for="rp-comment">Your Review *</label>
                <textarea name="rp_comment" id="rp-comment" rows="5" required></textarea>
            </p>
            <p>
                <input type="submit" name="rp_admin_submit_review" value="Add Review">
            </p>
        </form>

        <h2>Send Email</h2>
        <form method="post">
            <?php wp_nonce_field('rp_send_email_action'); ?>
            <p>
                <label for="rp-email">Recipient Email *</label>
                <input type="email" name="rp_email" id="rp-email" required>
            </p>
            <p>
                <input type="submit" name="rp_send_email" value="Send Email">
            </p>
        </form>
        
        <h2>Manage Reviews</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($reviews) {
                    foreach ($reviews as $review) {
                        if (isset($_GET['action']) && $_GET['action'] === 'edit' && $_GET['review_id'] == $review->id) {
                            // Display edit form for the selected review
                            ?>
                            <tr>
                                <td colspan="7">
                                    <form method="post" action="?page=rp_reviews&action=edit&review_id=<?php echo $review->id; ?>">
                                        <?php wp_nonce_field('rp_edit_review_' . $review->id); ?>
                                        <p>
                                            <label for="rp-name">Your Name *</label>
                                            <input type="text" name="rp_name" id="rp-name" value="<?php echo esc_attr($review->name); ?>" required>
                                        </p>
                                        <p>
                                            <label for="rp-rating">Rating *</label>
                                            <select name="rp_rating" id="rp-rating" required>
                                                <option value="1" <?php selected($review->rating, 1); ?>>1</option>
                                                <option value="2" <?php selected($review->rating, 2); ?>>2</option>
                                                <option value="3" <?php selected($review->rating, 3); ?>>3</option>
                                                <option value="4" <?php selected($review->rating, 4); ?>>4</option>
                                                <option value="5" <?php selected($review->rating, 5); ?>>5</option>
                                            </select>
                                        </p>
                                        <p>
                                            <label for="rp-comment">Your Review *</label>
                                            <textarea name="rp_comment" id="rp-comment" rows="5" required><?php echo esc_textarea($review->comment); ?></textarea>
                                        </p>
                                        <p>
                                            <input type="submit" name="rp_edit_review" value="Update Review">
                                        </p>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        } else {
                            // Display review information (Jannah)
                            ?>
                            <tr>
                                <td><?php echo $review->id; ?></td>
                                <td><?php echo esc_html($review->name); ?></td>
                                <td><?php echo str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating); ?></td>
                                <td><?php echo esc_html($review->comment); ?></td>
                                <td><?php echo esc_html($review->created_at); ?></td>
                                <td><?php echo ($review->published == 1) ? 'Published' : 'Unpublished'; ?></td>
                                <td>
                                    <?php if ($review->published == 0) : ?>
                                        <a href="?page=rp_reviews&action=approve&review_id=<?php echo $review->id; ?>">Approve</a> |
                                    <?php endif; ?>
                                    <?php if ($review->published == 1) : ?>
                                        <a href="?page=rp_reviews&action=reject&review_id=<?php echo $review->id; ?>">Reject</a> |
                                    <?php endif; ?>
                                    <a href="?page=rp_reviews&action=edit&review_id=<?php echo $review->id; ?>">Edit</a> |
                                    <a href="?page=rp_reviews&action=delete&review_id=<?php echo $review->id; ?>" onclick="return confirm('Are you sure you want to delete this review?')">Delete</a>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="7">No reviews found.</td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Save admin-added review (Shernice)
function rp_save_admin_review() {
    if (isset($_POST['rp_admin_submit_review'])) {
        check_admin_referer('rp_admin_add_review_action');

        global $wpdb;
        $table_name = $wpdb->prefix . 'rp_reviews';

        $name = sanitize_text_field($_POST['rp_name']);
        $rating = intval($_POST['rp_rating']);
        $comment = sanitize_textarea_field($_POST['rp_comment']);

        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'rating' => $rating,
                'comment' => $comment,
                'published' => 0
            ),
            array(
                '%s',
                '%d',
                '%s',
                '%d'
            )
        );
    }
}

// Shortcode to display reviews (Jannah)
function rp_display_reviews($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rp_reviews';

    $reviews = $wpdb->get_results("SELECT * FROM $table_name WHERE published = 1 ORDER BY created_at DESC");

    ob_start();
    ?>
    <div class="rp-reviews-container">
        <div class="rp-reviews-row">
            <?php
            if ($reviews) {
                foreach ($reviews as $review) {
                    ?>
                    <div class="rp-review">
                        <img width="30" height="30" src="https://img.icons8.com/ios-filled/50/quote-right.png" alt="quote-right"/>
                        <div class="rp-rating">
                            <?php echo str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating); ?>
                        </div>
                        <p class="rp-review-text"><?php echo esc_html($review->comment); ?></p>
                        <b><p class="rp-review-title"><?php echo esc_html($review->name); ?></p></b>
                    </div>
                    <?php
                }
            } else {
                ?>
                <p class="rp-no-reviews">No reviews found.</p>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}




add_shortcode('rp_display_reviews', 'rp_display_reviews');

// Shortcode to display the review submission form (Shernice)
function rp_review_form_shortcode() {
    ob_start();
    ?>
    <form method="post">
        <?php wp_nonce_field('rp_add_review_action'); ?>
        <p>
            <label for="rp-name">Your Name *</label>
            <input type="text" name="rp_name" id="rp-name" required>
        </p>
        <p>
            <label for="rp-rating">Rating *</label>
            <select name="rp_rating" id="rp-rating" required>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
        </p>
        <p>
            <label for="rp-comment">Your Review *</label>
            <textarea name="rp_comment" id="rp-comment" rows="5" required></textarea>
        </p>
        <p>
            <input type="submit" name="rp_submit_review" value="Submit Review">
        </p>
    </form>
    <?php
    if (isset($_POST['rp_submit_review'])) {
        rp_save_review();
    }
    return ob_get_clean();
}
add_shortcode('rp_review_form', 'rp_review_form_shortcode');

    // Save user-submitted review (Shernice)
    function rp_save_review() {
        if (isset($_POST['rp_submit_review'])) {
            check_admin_referer('rp_add_review_action');

            global $wpdb;
            $table_name = $wpdb->prefix . 'rp_reviews';

            $name = sanitize_text_field($_POST['rp_name']);
            $rating = intval($_POST['rp_rating']);
            $comment = sanitize_textarea_field($_POST['rp_comment']);

            $wpdb->insert(
                $table_name,
                array(
                    'name' => $name,
                    'rating' => $rating,
                    'comment' => $comment,
                    'published' => 0
                ),
                array(
                    '%s',
                    '%d',
                    '%s',
                    '%d'
                )
            );

            // Redirect to the thank you page
            wp_redirect('http://localhost/wordpress_testingfyp123/46-2/');
            exit;
        }
    }
?>
