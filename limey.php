<?php
/*
Plugin Name: Limey Social App
Plugin URI: https://limeytt.com/
Description: A custom social media app with integrated wallet and chat features.
Version: 1.0
Author: Murrencorp LTD.
Author URI: https://murrencorpltd.com/
Text Domain: limey
*/

defined('ABSPATH') or die('No script kiddies please!');
global $wpdb;

/**
 * Activation hook: create custom tables
 */
function limey_create_tables() {
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // Table: profiles
    $sql_profiles = "CREATE TABLE {$wpdb->prefix}limey_profiles (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL UNIQUE,
        username VARCHAR(255) NOT NULL UNIQUE,
        display_name VARCHAR(255),
        bio TEXT,
        avatar_url TEXT,
        video_count INT DEFAULT 0,
        likes_received INT DEFAULT 0,
        trini_credits DECIMAL(10,2) DEFAULT 0.00,
        is_verified TINYINT(1) DEFAULT 0,
        is_creator TINYINT(1) DEFAULT 0,
        location VARCHAR(255) DEFAULT 'Trinidad & Tobago',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        delete_at DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        deleted_at DATETIME,
        deactivated TINYINT(1) DEFAULT 0,
        is_admin TINYINT(1) DEFAULT 0,
        phone_number VARCHAR(20),
        phone_verified TINYINT(1) DEFAULT 0,
        verification_code VARCHAR(255),
        verification_code_expires DATETIME,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: chats
    $sql_chats = "CREATE TABLE {$wpdb->prefix}limey_chats (
        id CHAR(36) NOT NULL,
        sender_id CHAR(36) NOT NULL,
        receiver_id CHAR(36) NOT NULL,
        last_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_for_sender TINYINT(1) DEFAULT 0,
        deleted_for_receiver TINYINT(1) DEFAULT 0,
        unread_count_sender INT DEFAULT 0,
        unread_count_receiver INT DEFAULT 0,
        typing_user_id CHAR(36),
        is_typing TINYINT(1) DEFAULT 0,
        typing_sender TINYINT(1) DEFAULT 0,
        typing_receiver TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: messages
    $sql_messages = "CREATE TABLE {$wpdb->prefix}limey_messages (
        id CHAR(36) NOT NULL,
        sender_id CHAR(36) NOT NULL,
        receiver_id CHAR(36) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        chat_id CHAR(36),
        deleted_for_everyone TINYINT(1) DEFAULT 0,
        deleted_for_sender TINYINT(1) DEFAULT 0,
        deleted_for_receiver TINYINT(1) DEFAULT 0,
        read_at DATETIME,
        read_by_receiver TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: ad_clicks
    $sql_ad_clicks = "CREATE TABLE {$wpdb->prefix}limey_ad_clicks (
        id CHAR(36) NOT NULL,
        sponsored_ad_id CHAR(36) NOT NULL,
        viewer_id CHAR(36),
        clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: ad_impressions
    $sql_ad_impressions = "CREATE TABLE {$wpdb->prefix}limey_ad_impressions (
        id CHAR(36) NOT NULL,
        sponsored_ad_id CHAR(36) NOT NULL,
        viewer_id CHAR(36),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        view_duration INT DEFAULT 0,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: ad_views
    $sql_ad_views = "CREATE TABLE {$wpdb->prefix}limey_ad_views (
        id CHAR(36) NOT NULL,
        ad_id CHAR(36) NOT NULL,
        user_id CHAR(36),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: ads
    $sql_ads = "CREATE TABLE {$wpdb->prefix}limey_ads (
        id CHAR(36) NOT NULL,
        business_name VARCHAR(255) NOT NULL,
        ad_title VARCHAR(255) NOT NULL,
        ad_description TEXT,
        ad_video_url TEXT,
        ad_image_url TEXT,
        target_audience TEXT,
        budget DECIMAL(10,2) NOT NULL,
        cost_per_view DECIMAL(10,2) NOT NULL,
        total_views INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        start_date DATETIME NOT NULL,
        end_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: boost_transactions
    $sql_boost_transactions = "CREATE TABLE {$wpdb->prefix}limey_boost_transactions (
        id CHAR(36) NOT NULL,
        sponsored_ad_id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        admin_id CHAR(36),
        amount DECIMAL(10,2) NOT NULL,
        transaction_type VARCHAR(50) DEFAULT 'boost_payment',
        status VARCHAR(50) DEFAULT 'completed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: comment_likes
    $sql_comment_likes = "CREATE TABLE {$wpdb->prefix}limey_comment_likes (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        comment_id CHAR(36) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: comments
    $sql_comments = "CREATE TABLE {$wpdb->prefix}limey_comments (
        id CHAR(36) NOT NULL,
        content TEXT NOT NULL,
        user_id CHAR(36) NOT NULL,
        video_id CHAR(36) NOT NULL,
        parent_id CHAR(36),
        like_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: gift_transactions
    $sql_gift_transactions = "CREATE TABLE {$wpdb->prefix}limey_gift_transactions (
        id CHAR(36) NOT NULL,
        sender_id CHAR(36) NOT NULL,
        receiver_id CHAR(36) NOT NULL,
        video_id CHAR(36),
        gift_id CHAR(36) NOT NULL,
        quantity INT DEFAULT 1,
        total_amount DECIMAL(10,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: gifts
    $sql_gifts = "CREATE TABLE {$wpdb->prefix}limey_gifts (
        id CHAR(36) NOT NULL,
        name VARCHAR(255) NOT NULL,
        icon_url TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: live_streams
    $sql_live_streams = "CREATE TABLE {$wpdb->prefix}limey_live_streams (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        stream_url TEXT,
        thumbnail_url TEXT,
        is_active TINYINT(1) DEFAULT 1,
        viewer_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: notifications
    $sql_notifications = "CREATE TABLE {$wpdb->prefix}limey_notifications (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        sender_id CHAR(36),
        notification_type VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        reference_id CHAR(36),
        reference_type VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: transactions
    $sql_transactions = "CREATE TABLE {$wpdb->prefix}limey_transactions (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        reference_id CHAR(36),
        status VARCHAR(50) DEFAULT 'completed',
        ttpaypal_transaction_id VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: trincredits_transactions
    $sql_trincredits_transactions = "CREATE TABLE {$wpdb->prefix}limey_trincredits_transactions (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        balance_before DECIMAL(10,2) NOT NULL,
        balance_after DECIMAL(10,2) NOT NULL,
        description TEXT,
        reference_id VARCHAR(255),
        status VARCHAR(50) DEFAULT 'completed',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: user_sessions
    $sql_user_sessions = "CREATE TABLE {$wpdb->prefix}limey_user_sessions (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        session_id TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT (NOW() + INTERVAL 1 DAY),
        session_data JSON,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: user_settings
    $sql_user_settings = "CREATE TABLE {$wpdb->prefix}limey_user_settings (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL UNIQUE,
        privacy_settings JSON DEFAULT '{\"duet\": \"everyone\", \"stitch\": \"everyone\", \"comments\": \"everyone\", \"messages\": \"everyone\", \"liked_videos\": \"everyone\", \"private_account\": false}',
        notification_settings JSON DEFAULT '{\"likes\": true, \"follows\": true, \"comments\": true, \"mentions\": true, \"direct_messages\": true, \"live_notifications\": true}',
        content_preferences JSON DEFAULT '{\"restricted_mode\": false, \"content_languages\": [\"en\"], \"interested_categories\": []}',
        account_settings JSON DEFAULT '{\"dark_mode\": false, \"accessibility\": {\"screen_reader\": false, \"closed_captions\": false}, \"autoplay_videos\": true, \"data_saver_mode\": false}',
        language VARCHAR(10) DEFAULT 'en',
        region VARCHAR(255) DEFAULT 'Trinidad & Tobago',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: video_likes
    $sql_video_likes = "CREATE TABLE {$wpdb->prefix}limey_video_likes (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        video_id CHAR(36) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Table: video_views
    $sql_video_views = "CREATE TABLE {$wpdb->prefix}limey_video_views (
        id CHAR(36) NOT NULL,
        video_id CHAR(36),
        viewer_id CHAR(36),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        creator_id CHAR(36) NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

// Table: videos
$sql_videos = "CREATE TABLE {$wpdb->prefix}limey_videos (
    id CHAR(36) NOT NULL,
    uuid CHAR(36) NOT NULL,
    title TEXT,
    description TEXT,
    video_url TEXT,
    thumbnail_url TEXT,
    duration INTEGER,
    category TEXT,
    tags TEXT[], -- Postgres ARRAY; if MySQL, consider JSON or serialized data
    view_count BIGINT DEFAULT 0,
    like_count BIGINT DEFAULT 0,
    share_count INTEGER DEFAULT 0,
    save_count INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    user_id CHAR(36),
    username TEXT,
    avatar_url TEXT,
    profiles JSONB,
    PRIMARY KEY (id)
) {$charset_collate};";

    // Table: wallet_links
    $sql_wallet_links = "CREATE TABLE {$wpdb->prefix}limey_wallet_links (
        id CHAR(36) NOT NULL,
        linked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id CHAR(36),
        PRIMARY KEY (id)
    ) {$charset_collate};";

    // Run all table creations
    dbDelta($sql_profiles);
    dbDelta($sql_chats);
    dbDelta($sql_messages);
    dbDelta($sql_ad_clicks);
    dbDelta($sql_ad_impressions);
    dbDelta($sql_ad_views);
    dbDelta($sql_ads);
    dbDelta($sql_boost_transactions);
    dbDelta($sql_comment_likes);
    dbDelta($sql_comments);
    dbDelta($sql_gift_transactions);
    dbDelta($sql_gifts);
    dbDelta($sql_live_streams);
    dbDelta($sql_notifications);
    dbDelta($sql_transactions);
    dbDelta($sql_trincredits_transactions);
    dbDelta($sql_user_sessions);
    dbDelta($sql_user_settings);
    dbDelta($sql_video_likes);
    dbDelta($sql_video_views);
    dbDelta($sql_videos);
    dbDelta($sql_wallet_links);
}
register_activation_hook(__FILE__, 'limey_create_tables');

// Create pages on plugin activation
function limey_create_pages() {
    $pages = [
        ['title' => 'Ad Stats', 'slug' => 'ad-stats', 'content' => '<h1>Ad Stats</h1><p>Content coming soon.</p>'],
        ['title' => 'Admin Dashboard', 'slug' => 'admin-dashboard', 'content' => '<h1>Admin Dashboard</h1><p>Content coming soon.</p>'],
        ['title' => 'Boost', 'slug' => 'boost', 'content' => '<h1>Boost</h1><p>Content coming soon.</p>'],
        ['title' => 'Campaign Detail', 'slug' => 'campaign-detail', 'content' => '<h1>Campaign Detail</h1><p>Content coming soon.</p>'],
        ['title' => 'Chat', 'slug' => 'chat', 'content' => '<h1>Chat</h1><p>Content coming soon.</p>'],
        ['title' => 'Create Video', 'slug' => 'create-video', 'content' => '<h1>Create Video</h1><p>Content coming soon.</p>'],
        ['title' => 'Deactivated', 'slug' => 'deactivated', 'content' => '<h1>Deactivated</h1><p>Content coming soon.</p>'],
        ['title' => 'Edit Profile', 'slug' => 'edit-profile', 'content' => '<h1>Edit Profile</h1><p>Content coming soon.</p>'],
        ['title' => 'Feed', 'slug' => 'feed', 'content' => '<h1>Feed</h1><p>Content coming soon.</p>'],
        ['title' => 'Feed Backup', 'slug' => 'feed-backup', 'content' => '<h1>Feed Backup</h1><p>Content coming soon.</p>'],
        ['title' => 'Feed New', 'slug' => 'feed-new', 'content' => '<h1>Feed New</h1><p>Content coming soon.</p>'],
        ['title' => 'Feed Updated', 'slug' => 'feed-updated', 'content' => '<h1>Feed Updated</h1><p>Content coming soon.</p>'],
        ['title' => 'Friends', 'slug' => 'friends', 'content' => '<h1>Friends</h1><p>Content coming soon.</p>'],
        ['title' => 'Inbox', 'slug' => 'inbox', 'content' => '<h1>Inbox</h1><p>Content coming soon.</p>'],
        // Main Index page
        ['title' => 'Index', 'slug' => 'index', 'content' => '<h1>Index</h1><p>
<div class="min-h-screen flex items-center justify-center bg-background">
  <div class="text-center space-y-8">
    <div class="limey-logo show-circle" style="width:192px; height:192px;">
      <img src="path-to-your-logo.png" alt="Limey Logo" style="width:100%; height:100%; border-radius:50%;">
    </div>
    <div class="space-y-4">
      <h2 class="text-2xl text-foreground">Trinbago\'s Home for Creators</h2>
      <p class="text-muted-foreground max-w-md mx-auto text-center px-[20px]">
        Share your moments, discover local talent, and connect with the Caribbean community.
      </p>
    </div>
    <div class="space-y-3">
      <a href="/signup" class="btn btn-neon w-64">Join Limey</a>
      <div>
        <a href="/login" class="btn btn-outline w-64 py-0">Sign In</a>
      </div>
      <div class="text-sm text-muted-foreground">
        By signing up, you agree to our <a href="/terms" class="text-primary hover:underline">Terms of Service</a> and <a href="/privacy" class="text-primary hover:underline">Privacy Policy</a>.
      </div>
    </div>
  </div>
</div>
</p>'],
        // Other pages...
        ['title' => 'Link Account', 'slug' => 'link-account', 'content' => '<h1>Link Account</h1><p>Content coming soon.</p>'],
        ['title' => 'Live', 'slug' => 'live', 'content' => '<h1>Live</h1><p>Content coming soon.</p>'],
        // Login page with callback
        ['title' => 'Login', 'slug' => 'login', 'callback' => 'limey_login_page_callback'],
        ['title' => 'Message', 'slug' => 'message', 'content' => '<h1>Message</h1><p>Content coming soon.</p>'],
        // ... other pages ...
        ['title' => 'Wallet', 'slug' => 'wallet', 'content' => '<h1>Wallet</h1><p>Content coming soon.</p>'],
    ];

    foreach ($pages as $page) {
        if ( ! get_page_by_path( $page['slug'] ) ) {
            wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
            // Save callback if exists
            if ( isset($page['callback']) ) {
                $post_id = get_page_by_path($page['slug'])->ID;
                update_post_meta($post_id, '_page_callback', $page['callback']);
            }
        }
    }
}
register_activation_hook(__FILE__, 'limey_create_pages');

function create_video_create_page_shortcode() {
    ob_start();
    ?>
    <div style="max-width: 600px; margin: 40px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center;">
        <!-- Icon and Title -->
        <div style="margin-bottom: 10px;">
            <div style="width: 64px; height: 64px; background-color: #5ccc45; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 2em;">
                <!-- Video recorder icon -->
                <img draggable="false" role="img" class="emoji" alt="🎥" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f3a5.svg">
            </div>
            <h3 style="margin-top: 10px; font-weight: bold;">Create Your Video</h3>
        </div>
        <!-- Instructions -->
        <p style="margin-bottom: 20px;">Press the button below to record a video using your device's camera. After recording, confirm to upload or retry to record again.</p>
        <!-- Video preview -->
        <video id="videoPreview" controls style="width: 100%; max-height: 300px; display:none; margin-bottom: 15px;"></video>
        <!-- Record Button -->
        <form id="camera-form" method="post" action="/your-upload-page" enctype="multipart/form-data">
            <input type="file" id="video-input" accept="video/*" capture="camera" style="display:none;" name="video">
            <button type="button" id="record-btn" style="padding:10px 20px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;">Record Video</button>
            <button type="button" id="cancel-btn" style="padding:10px 20px; background:#000; color:#fff; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
            <button type="button" id="upload-btn" style="padding:10px 20px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer; display:none; margin-left:10px;">Upload</button>
            <button type="button" id="retry-btn" style="padding:10px 20px; background:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer; display:none; margin-left:10px;">Retry</button>
        </form>
    </div>

    <script>
	const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';

	const videoInput = document.getElementById('video-input');
	const videoPreview = document.getElementById('videoPreview');
	const recordBtn = document.getElementById('record-btn');
	const cancelBtn = document.getElementById('cancel-btn');
	const uploadBtn = document.getElementById('upload-btn');
	const retryBtn = document.getElementById('retry-btn');

	document.getElementById('record-btn').onclick = () => {
		videoInput.click();
	};

	videoInput.onchange = () => {
		if (videoInput.files && videoInput.files.length > 0) {
			const file = videoInput.files[0];
			const url = URL.createObjectURL(file);
			videoPreview.src = url;
			videoPreview.style.display = 'block';
			uploadBtn.style.display = 'inline-block';
			retryBtn.style.display = 'inline-block';
			document.getElementById('record-btn').style.display = 'none';
		}
	};

	retryBtn.onclick = () => {
		videoInput.value = '';
		videoPreview.src = '';
		videoPreview.style.display = 'none';
		uploadBtn.style.display = 'none';
		retryBtn.style.display = 'none';
		document.getElementById('record-btn').style.display = 'inline-block';
	};

	cancelBtn.onclick = () => {
		window.location.href = '/post'; // Replace with your actual post page URL
	};

	uploadBtn.onclick = () => {
		if (videoInput.files && videoInput.files.length > 0) {
			const formData = new FormData();
			formData.append('video', videoInput.files[0]);
			formData.append('action', 'handle_video_upload');

			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
			})
			.then(res => res.json())
			.then(response => {
				if (response.success) {
					alert('Video uploaded successfully!');
					window.location.href = '<?php echo esc_url( home_url('/for-you') ); ?>';
				} else {
					alert('Upload failed: ' + (response.data.message || 'Unknown error'));
				}
			})
			.catch(() => {
				alert('Error uploading video. Please try again.');
			});
		}
	};
	</script>
    <?php
    return ob_get_clean();
}
add_shortcode('create_video_page', 'create_video_create_page_shortcode');

function limey_upload_page_shortcode() {
    global $wpdb;

    // Handle non-AJAX form submission (fallback, in case JS is disabled)
    if ( isset($_POST['submit_video']) && isset($_FILES['videoFile']) && ! isset($_POST['ajax'])) {
        $allowed_types = ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp', 'video/x-msvideo', 'video/mpeg'];
        $file = $_FILES['videoFile'];

        if (in_array($file['type'], $allowed_types)) {
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'limey_videos/';
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            $filename = wp_unique_filename($target_dir, basename($file['name']));
            $target_path = $target_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $id = wp_generate_uuid4();
                $uuid = wp_generate_uuid4();
                $video_url = $upload_dir['baseurl'] . '/limey_videos/' . $filename;
                $title = sanitize_text_field($_POST['video_title']);
                $description = sanitize_textarea_field($_POST['video_description']);
                $category = sanitize_text_field($_POST['video_category']);
                $tags_input = sanitize_text_field($_POST['video_tags']);
                $tags = $tags_input ? explode(',', $tags_input) : [];
                $current_user = wp_get_current_user();
                $user_id = $current_user->ID ? $current_user->ID : '';
                $username = $current_user->user_login;
                $avatar_url = get_avatar_url($user_id);
                $wpdb->insert(
                    "{$wpdb->prefix}limey_videos",
                    [
                        'id' => $id,
                        'uuid' => $uuid,
                        'title' => $title,
                        'description' => $description,
                        'video_url' => $video_url,
                        'category' => $category,
                        'tags' => maybe_serialize($tags),
                        'created_at' => current_time('mysql', 1),
                        'user_id' => $user_id,
                        'username' => $username,
                        'avatar_url' => $avatar_url,
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );
                $success_message = "Video uploaded successfully!";
            } else {
                $error_message = "Error moving uploaded file.";
            }
        } else {
            $error_message = "Invalid file type.";
        }
    }

    ob_start();
    ?>
    <style>
        /* Toast message style */
        #toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #28a745; /* green */
            color: #fff;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: bold;
            display: none;
            z-index: 9999;
        }
        /* Rest of styles from previous code */
        .upload-container {
            max-width: 600px;
            margin: 40px auto 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        #videoPreview {
            width: 100%;
            max-height: 300px;
            margin-top: 15px;
            display: none;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #5ccc45;
            color: #fff;
        }
        .btn-secondary {
            background-color: #000000;
            color: #fff;
            border: 3px solid #5ccc45;
        }
        .video-details {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        input[type="text"], textarea, select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .message {
            margin-top: 15px;
            font-weight: bold;
        }
        .message-success {
            color: green;
        }
        .message-error {
            color: red;
        }
        /* Custom video controls styles */
.video-controls {
  display: flex;
  align-items: center;
  width: 100%;
}

.progress-container {
  flex: 1;
  height: 5px;
  background: #444;
  margin-left: 10px;
  cursor: pointer;
  border-radius: 2px;
  overflow: hidden;
}
.progress {
  background: #5ccc45;
  height: 100%;
  width: 0%;
}
    </style>

    <div id="toast"></div>

    <div class="upload-container">
        <h3>Upload Your Video</h3>
        <?php if (isset($success_message)) : ?>
            <div class="message message-success"><?php echo esc_html($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)) : ?>
            <div class="message message-error"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>

        <!-- Video Preview -->
        <video id="videoPreview"></video>
<!-- Custom controls -->
<div class="video-controls" style="display:none; position: relative; max-width: 100%; margin-top:10px;">
  <button id="playPauseBtn" class="btn btn-primary">Play</button>
  <div class="progress-container" id="progressContainer" style="flex:1; height:5px; background:#444; margin-left:10px; cursor:pointer; display:inline-block; vertical-align:middle;">
    <div class="progress" id="progressBar" style="background:#5ccc45; height:100%; width:0%;"></div>
  </div>
</div>

        <!-- Select Video Button -->
        <button class="btn btn-primary" id="selectVideoBtn">Select Video</button>
        <input type="file" id="videoInput" accept=".mp4,.mov,.webm,.3gp,.avi,.mpeg" style="display:none;" />

        <!-- Change Video Button -->
        <div id="previewControls" style="display:none; margin-top:15px; border:1px; border-color:#5ccc45;">
            <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
        </div>

        <!-- Video Details Form -->
        <form method="post" id="videoDetailsForm" class="video-details" style="display:none; margin-top:20px;">
            <input type="text" name="video_title" placeholder="Video Title" required />
            <textarea name="video_description" placeholder="Description"></textarea>
            <input type="text" name="video_tags" placeholder="Tags (comma separated)" />
            <select name="video_category" required>
                <option value="">Select A Category</option>
                <option value="All">All</option>
                <option value="AI">AI</option>
                <option value="Bar Limes">Bar Limes</option>
                <option value="Carnival">Carnival</option>
                <option value="Anime">Anime</option>
                <option value="Educational">Educational</option>
                <option value="How-To's">How-To's</option>
                <option value="Music">Music</option>
                <option value="News">News</option>
                <option value="Outdoors">Outdoors</option>
                <option value="TriniStar">TriniStar</option>
                <option value="Tutorial">Tutorial</option>
            </select>
            <button type="submit" name="submit_video" class="btn btn-primary">Upload Video</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectBtn = document.getElementById('selectVideoBtn');
        const fileInput = document.getElementById('videoInput');
        const videoPreview = document.getElementById('videoPreview');
        const cancelBtn = document.getElementById('cancelBtn');
        const previewControls = document.getElementById('previewControls');
        const detailsForm = document.getElementById('videoDetailsForm');
        const toastEl = document.getElementById('toast');

        function showToast(message, bgColor = '#28a745') {
            toastEl.innerText = message;
            toastEl.style.backgroundColor = bgColor;
            toastEl.style.display = 'block';
            setTimeout(() => { toastEl.style.display = 'none'; }, 3000);
        }

        selectBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                const url = URL.createObjectURL(file);
                videoPreview.src = url;
                videoPreview.style.display = 'block';
                previewControls.style.display = 'block';
                detailsForm.style.display = 'block';
            }
        });
        cancelBtn.onclick = () => {
            window.location.href = '/post'; 
        };
        // Toggle play/pause
const playPauseBtn = document.getElementById('playPauseBtn');

playPauseBtn.addEventListener('click', () => {
    if (video.paused || video.ended) {
        video.play();
        playPauseBtn.innerText = 'Pause';
    } else {
        video.pause();
        playPauseBtn.innerText = 'Play';
    }
});

video.addEventListener('ended', () => {
    playPauseBtn.innerText = 'Play';
});

        // AJAX form submit
        document.getElementById('videoDetailsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'handle_video_upload');

            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.data.message || 'Video uploaded successfully!', '#28a745');
                    loadVideos();

                    // Redirect to homepage after a short delay
                    setTimeout(() => {
                        window.location.href = '/'; // Redirect to homepage
                    }, 1500);
                } else {
                    // Show error banner
                    showToast(data.message || 'Upload failed', '#dc3545');
                }
            })
            .catch(() => {
                showToast('Error uploading video', '#dc3545');
            });
        });
        const video = document.getElementById('videoPreview');
        const progressBar = document.getElementById('progressBar');
        const progressContainer = document.getElementById('progressContainer');
        const controlsContainer = document.querySelector('.video-controls');

        video.addEventListener('loadedmetadata', () => {
        controlsContainer.style.display = 'flex';
        });

        video.addEventListener('timeupdate', () => {
        const percent = (video.currentTime / video.duration) * 100;
        progressBar.style.width = percent + '%';
        });

        progressContainer.addEventListener('click', (e) => {
        const rect = progressContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const seekTime = (clickX / width) * video.duration;
        video.currentTime = seekTime;
        });

        function loadVideos() {
            fetch('<?php echo esc_url( add_query_arg( array( 'action' => 'get_videos' ) ) ); ?>')
            .then(res => res.text())
            .then(html => {
                const feedContainer = document.querySelector('.video-feed');
                if (feedContainer) {
                    feedContainer.innerHTML = html;
                } else {
                    console.error('Element with class "video-feed" not found.');
                }
            });
        }

        // Initial load
        loadVideos();
    });
    </script>
    <?php
    return ob_get_clean();
}
// Register the shortcode
add_shortcode('limey_upload_page', 'limey_upload_page_shortcode');

function handle_ajax_video_upload() {
    // Check user permissions
    $current_user = wp_get_current_user();
    if ( ! ( $current_user->ID && in_array( 'customer', (array) $current_user->roles, true )) ) {
        wp_send_json_error(['message' => 'You do not have permission to upload videos.']);
        wp_die();
    }

    // Verify action
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'handle_video_upload' ) {
        wp_send_json_error( ['message' => 'Invalid request'] );
        wp_die();
    }

    // Check file and title
    if ( isset($_FILES['videoFile']) && isset($_POST['video_title']) ) {
        $allowed_types = ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp', 'video/x-msvideo', 'video/mpeg'];
        $file = $_FILES['videoFile'];

        if ( in_array( $file['type'], $allowed_types ) ) {
            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'limey_videos/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $filename = wp_unique_filename( $target_dir, basename( $file['name'] ) );
            $target_path = $target_dir . $filename;

            if ( move_uploaded_file( $file['tmp_name'], $target_path ) ) {
                // Generate UUIDs
                $id = wp_generate_uuid4();
                $uuid = wp_generate_uuid4();

                $video_url = $upload_dir['baseurl'] . '/limey_videos/' . $filename;
                $title = sanitize_text_field( $_POST['video_title'] );
                $description = sanitize_textarea_field( $_POST['video_description'] );
                $category = sanitize_text_field( $_POST['video_category'] );
                $tags_input = sanitize_text_field( $_POST['video_tags'] );
                $tags = $tags_input ? explode(',', $tags_input) : [];

                global $wpdb;
                $table_name = $wpdb->prefix . 'limey_videos';

                $insert_result = $wpdb->insert(
                    $table_name,
                    [
                        'id' => $id,
                        'uuid' => $uuid,
                        'title' => $title,
                        'description' => $description,
                        'video_url' => $video_url,
                        'category' => $category,
                        'tags' => maybe_serialize($tags),
                        'created_at' => current_time('mysql', 1),
                        'user_id' => $current_user->ID,
                        'username' => $current_user->user_login,
                        'avatar_url' => get_avatar_url( $current_user->ID ),
                    ],
                    [ '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ]
                );

                if ($insert_result !== false) {
                    wp_send_json_success(['message' => 'Video uploaded successfully!']);
                } else {
                    wp_send_json_error(['message' => 'Failed to save video info into database.']);
                }
                wp_die();
            } else {
                wp_send_json_error(['message' => 'Error moving uploaded file.']);
                wp_die();
            }
        } else {
            wp_send_json_error(['message' => 'Invalid file type.']);
            wp_die();
        }
    } else {
        wp_send_json_error(['message' => 'Missing file or title']);
        wp_die();
    }
}
add_action('wp_ajax_nopriv_handle_video_upload', 'handle_ajax_video_upload');
add_action('wp_ajax_handle_video_upload', 'handle_ajax_video_upload');

function limey_video_feed_shortcode() {
    global $wpdb;

    // Fetch videos from database - customize your query as needed
    $videos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}limey_videos ORDER BY created_at DESC LIMIT 10");

    ob_start();

    // CSS for custom controls
    ?>
    <style>
        .video-item {
            margin-bottom: 30px;
        }
        .video-wrapper {
            position: relative;
            max-width: 100%;
            margin: 0 auto;
        }
        video {
            width: 100%;
            height: auto;
            display: block;
        }
        /* Custom controls below each video */
        .video-controls {
            display: flex;
            align-items: center;
            margin-top: 8px;
        }
        .video-controls button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            background-color: #5ccc45;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
        }
        .progress-container {
            flex: 1;
            height: 5px;
            background: #444;
            margin-left: 10px;
            cursor: pointer;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress {
            background: #5ccc45;
            height: 100%;
            width: 0%;
        }
    </style>
    <?php

    if (empty($videos)) {
        echo "<p>No videos found.</p>";
        return;
    }

    foreach ($videos as $video) {
        $video_url = esc_url($video->video_url);
        ?>
        <div class="video-item">
            <div class="video-wrapper">
                <video class="video-player" src="<?php echo $video_url; ?>" preload="metadata"></video>
                <div class="video-controls">
                    <button class="play-pause-btn">Play</button>
                    <div class="progress-container">
                        <div class="progress"></div>
                    </div>
                </div>
            </div>
            <h4><?php echo esc_html($video->title); ?></h4>
            <p><?php echo esc_html($video->description); ?></p>
        </div>
        <?php
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Select all video items
            const videoItems = document.querySelectorAll('.video-item');

            videoItems.forEach(item => {
                const video = item.querySelector('.video-player');
                const playBtn = item.querySelector('.play-pause-btn');
                const progressBar = item.querySelector('.progress');

                // Reset progress bar when metadata is loaded
                video.addEventListener('loadedmetadata', () => {
                    progressBar.style.width = '0%';
                    playBtn.textContent = 'Play';
                });

                // Play/Pause toggle
                playBtn.addEventListener('click', () => {
                    if (video.paused || video.ended) {
                        video.play();
                    } else {
                        video.pause();
                    }
                });

                // Update button text based on play/pause
                video.addEventListener('play', () => {
                    playBtn.textContent = 'Pause';
                });
                video.addEventListener('pause', () => {
                    playBtn.textContent = 'Play';
                });

                // Update progress bar as video plays
                video.addEventListener('timeupdate', () => {
                    const percent = (video.currentTime / video.duration) * 100;
                    progressBar.style.width = percent + '%';
                });

                // Seek video on progress bar click
                const progressContainer = item.querySelector('.progress-container');
                progressContainer.addEventListener('click', (e) => {
                    const rect = progressContainer.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const width = rect.width;
                    const seekTime = (clickX / width) * video.duration;
                    video.currentTime = seekTime;
                });
            });
        });
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('limey_video_feed', 'limey_video_feed_shortcode');

// Add filter to execute callback functions
add_filter('the_content', 'limey_execute_page_callback');

function limey_execute_page_callback($content) {
    if ( is_page() ) {
        $page_id = get_the_ID();
        $callback = get_post_meta($page_id, '_page_callback', true);
        if ( $callback && function_exists($callback) ) {
            ob_start();
            $callback();
            return ob_get_clean();
        }
    }
    return $content;
}

  
?>