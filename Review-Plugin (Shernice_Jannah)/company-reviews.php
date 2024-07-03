<?php
/*
Plugin Name: Company Reviews
Description: Plugin for managing company reviews with ratings.
Version: 1.1
Author: Your Name
*/

// Plugin activation hook
register_activation_hook(__FILE__, 'cr_create_reviews_table');

// Create custom database table on plugin activation
function cr_create_reviews_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'company_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        rating tinyint(1) NOT NULL,
        review text NOT NULL,
        created_at datetime NOT NULL DEFAULT current_timestamp(),
        published tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue styles
function cr_enqueue_styles() {
    wp_enqueue_style('cr-styles', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'cr_enqueue_styles');
add_action('admin_enqueue_scripts', 'cr_enqueue_styles');

// Hook into admin menu
add_action('admin_menu', 'cr_admin_menu');

// Admin menu setup
function cr_admin_menu() {
    add_menu_page(
        'Company Reviews',
        'Company Reviews',
        'manage_options',
        'cr_reviews',
        'cr_reviews_page'
    );
}

// Admin page content
function cr_reviews_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'company_reviews';

    // Handle form submissions (approve/reject/delete/edit)
    if (isset($_GET['action']) && in_array($_GET['action'], array('approve', 'reject', 'delete', 'edit')) && isset($_GET['review_id'])) {
        $review_id = intval($_GET['review_id']);
        if ($_GET['action'] === 'delete') {
            $wpdb->delete($table_name, array('id' => $review_id), array('%d'));
        } elseif ($_GET['action'] === 'edit' && isset($_POST['cr_edit_review'])) {
            check_admin_referer('cr_edit_review_' . $review_id);

            $name = sanitize_text_field($_POST['cr_name']);
            $rating = intval($_POST['cr_rating']);
            $review = sanitize_textarea_field($_POST['cr_review']);
            $wpdb->update(
                $table_name,
                array(
                    'name' => $name,
                    'rating' => $rating,
                    'review' => $review,
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

    // Handle new review submission
    if (isset($_POST['cr_admin_submit_review'])) {
        cr_save_admin_review();
    }

    // Handle email sending
    if (isset($_POST['cr_send_email'])) {
        check_admin_referer('cr_send_email_action');
        
        $email = sanitize_email($_POST['cr_email']);
        $subject = 'Check out our reviews';
        $message = 'Please visit our website to see the latest reviews: ' . get_site_url();
        wp_mail($email, $subject, $message);
        echo '<div class="updated"><p>Email sent successfully to ' . esc_html($email) . '.</p></div>';
    }

    // Fetch reviews
    $reviews = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Company Reviews</h1>
        <h2>Add New Review</h2>
        <form method="post">
            <?php wp_nonce_field('cr_admin_add_review_action'); ?>
            <p>
                <label for="cr-name">Your Name *</label>
                <input type="text" name="cr_name" id="cr-name" required>
            </p>
            <p>
                <label for="cr-rating">Rating *</label>
                <select name="cr_rating" id="cr-rating" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                </select>
            </p>
            <p>
                <label for="cr-review">Your Review *</label>
                <textarea name="cr_review" id="cr-review" rows="5" required></textarea>
            </p>
            <p>
                <input type="submit" name="cr_admin_submit_review" value="Add Review">
            </p>
        </form>

        <h2>Send Email</h2>
        <form method="post">
            <?php wp_nonce_field('cr_send_email_action'); ?>
            <p>
                <label for="cr-email">Recipient Email *</label>
                <input type="email" name="cr_email" id="cr-email" required>
            </p>
            <p>
                <input type="submit" name="cr_send_email" value="Send Email">
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
                                    <form method="post" action="?page=cr_reviews&action=edit&review_id=<?php echo $review->id; ?>">
                                        <?php wp_nonce_field('cr_edit_review_' . $review->id); ?>
                                        <p>
                                            <label for="cr-name">Your Name *</label>
                                            <input type="text" name="cr_name" id="cr-name" value="<?php echo esc_attr($review->name); ?>" required>
                                        </p>
                                        <p>
                                            <label for="cr-rating">Rating *</label>
                                            <select name="cr_rating" id="cr-rating" required>
                                                <option value="1" <?php selected($review->rating, 1); ?>>1</option>
                                                <option value="2" <?php selected($review->rating, 2); ?>>2</option>
                                                <option value="3" <?php selected($review->rating, 3); ?>>3</option>
                                                <option value="4" <?php selected($review->rating, 4); ?>>4</option>
                                                <option value="5" <?php selected($review->rating, 5); ?>>5</option>
                                            </select>
                                        </p>
                                        <p>
                                            <label for="cr-review">Your Review *</label>
                                            <textarea name="cr_review" id="cr-review" rows="5" required><?php echo esc_textarea($review->review); ?></textarea>
                                        </p>
                                        <p>
                                            <input type="submit" name="cr_edit_review" value="Update Review">
                                        </p>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        } else {
                            // Display review information
                            ?>
                            <tr>
                                <td><?php echo $review->id; ?></td>
                                <td><?php echo esc_html($review->name); ?></td>
                                <td><?php echo str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating); ?></td>
                                <td><?php echo esc_html($review->review); ?></td>
                                <td><?php echo esc_html($review->created_at); ?></td>
                                <td><?php echo ($review->published == 1) ? 'Published' : 'Unpublished'; ?></td>
                                <td>
                                    <?php if ($review->published == 0) : ?>
                                        <a href="?page=cr_reviews&action=approve&review_id=<?php echo $review->id; ?>">Approve</a> |
                                    <?php endif; ?>
                                    <?php if ($review->published == 1) : ?>
                                        <a href="?page=cr_reviews&action=reject&review_id=<?php echo $review->id; ?>">Reject</a> |
                                    <?php endif; ?>
                                    <a href="?page=cr_reviews&action=edit&review_id=<?php echo $review->id; ?>">Edit</a> |
                                    <a href="?page=cr_reviews&action=delete&review_id=<?php echo $review->id; ?>" onclick="return confirm('Are you sure you want to delete this review?')">Delete</a>
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

// Save admin-added review
function cr_save_admin_review() {
    if (isset($_POST['cr_admin_submit_review'])) {
        check_admin_referer('cr_admin_add_review_action');

        global $wpdb;
        $table_name = $wpdb->prefix . 'company_reviews';

        $name = sanitize_text_field($_POST['cr_name']);
        $rating = intval($_POST['cr_rating']);
        $review = sanitize_textarea_field($_POST['cr_review']);

        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'rating' => $rating,
                'review' => $review,
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

// Shortcode to display reviews
function cr_display_reviews($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'company_reviews';

    $reviews = $wpdb->get_results("SELECT * FROM $table_name WHERE published = 1 ORDER BY created_at DESC");

    ob_start();
    ?>
    <div class="cr-reviews">
        <?php
        if ($reviews) {
            foreach ($reviews as $review) {
                ?>
                <div class="cr-review">
                    <h3><?php echo esc_html($review->name); ?></h3>
                    <div class="cr-rating"><?php echo str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating); ?></div>
                    <p><?php echo esc_html($review->review); ?></p>
                </div>
                <?php
            }
        } else {
            ?>
            <p>No reviews found.</p>
            <?php
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('company_reviews', 'cr_display_reviews');

// Shortcode to display the review submission form
function cr_review_form_shortcode() {
    ob_start();
    ?>
    <form method="post">
        <?php wp_nonce_field('cr_add_review_action'); ?>
        <p>
            <label for="cr-name">Your Name *</label>
            <input type="text" name="cr_name" id="cr-name" required>
        </p>
        <p>
            <label for="cr-rating">Rating *</label>
            <select name="cr_rating" id="cr-rating" required>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
            </select>
        </p>
        <p>
            <label for="cr-review">Your Review *</label>
            <textarea name="cr_review" id="cr-review" rows="5" required></textarea>
        </p>
        <p>
            <input type="submit" name="cr_submit_review" value="Submit Review">
        </p>
    </form>
    <?php
    if (isset($_POST['cr_submit_review'])) {
        cr_save_review();
    }
    return ob_get_clean();
}
add_shortcode('cr_review_form', 'cr_review_form_shortcode');

// Save user-submitted review
function cr_save_review() {
    if (isset($_POST['cr_submit_review'])) {
        check_admin_referer('cr_add_review_action');

        global $wpdb;
        $table_name = $wpdb->prefix . 'company_reviews';

        $name = sanitize_text_field($_POST['cr_name']);
        $rating = intval($_POST['cr_rating']);
        $review = sanitize_textarea_field($_POST['cr_review']);

        $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'rating' => $rating,
                'review' => $review,
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
?>
