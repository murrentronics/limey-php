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
        PRIMARY KEY (id)
    ) {$charset_collate};";
    
    // Table: videos (matching your existing structure)
    $sql_videos = "CREATE TABLE {$wpdb->prefix}limey_videos (
        id CHAR(36) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_url TEXT NOT NULL,
        thumbnail_url TEXT,
        duration INT DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        tags JSON DEFAULT NULL,
        view_count BIGINT DEFAULT 0,
        like_count BIGINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id CHAR(36) NOT NULL,
        username VARCHAR(255) DEFAULT NULL,
        avatar_url TEXT,
        profiles JSON DEFAULT NULL,
        share_count INT DEFAULT 0,
        save_count INT DEFAULT 0,
        comment_count INT DEFAULT 0,
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
    
    // Table: video_saves
    $sql_video_saves = "CREATE TABLE {$wpdb->prefix}limey_video_saves (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        video_id CHAR(36) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user_video_save (user_id, video_id)
    ) {$charset_collate};";
    
    // Table: video_shares
    $sql_video_shares = "CREATE TABLE {$wpdb->prefix}limey_video_shares (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL,
        video_id CHAR(36) NOT NULL,
        share_type VARCHAR(50) DEFAULT 'link',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    
    // Table: account_deletions
    $sql_account_deletions = "CREATE TABLE {$wpdb->prefix}limey_account_deletions (
        id CHAR(36) NOT NULL,
        user_id CHAR(36) NOT NULL UNIQUE,
        scheduled_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Run table creations
    dbDelta($sql_profiles);
    dbDelta($sql_videos);
    dbDelta($sql_video_likes);
    dbDelta($sql_video_saves);
    dbDelta($sql_video_shares);
    dbDelta($sql_account_deletions);
}

register_activation_hook(__FILE__, 'limey_create_tables');

// Create pages on plugin activation
function limey_create_pages() {
    $pages = [
        ['title' => 'Login', 'slug' => 'login', 'callback' => 'limey_login_page_callback'],
        ['title' => 'Upload Video', 'slug' => 'upload-video', 'content' => '[limey_upload_page]'],
    ];
    
    foreach ($pages as $page) {
        if ( ! get_page_by_path( $page['slug'] ) ) {
            $post_id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
            
            // Save callback if exists
            if ( isset($page['callback']) && $post_id ) {
                update_post_meta($post_id, '_page_callback', $page['callback']);
            }
        }
    }
}

register_activation_hook(__FILE__, 'limey_create_pages');

// Login page callback function
function limey_login_page_callback() {
    // Handle login form submission
    if (isset($_POST['limey_login'])) {
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        
        $user = wp_authenticate($username, $password);
        
        if (!is_wp_error($user)) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            
            // Create or update Limey profile
            limey_create_user_profile($user->ID);
            
            // Let popup login system handle redirect
            // wp_redirect(home_url('/feed-2'));
            // exit;
        } else {
            $login_error = $user->get_error_message();
        }
    }
    
    // Handle registration form submission
    if (isset($_POST['limey_register'])) {
        $username = sanitize_user($_POST['reg_username']);
        $email = sanitize_email($_POST['reg_email']);
        $password = $_POST['reg_password'];
        $display_name = sanitize_text_field($_POST['display_name']);
        
        // Check if user exists
        if (username_exists($username) || email_exists($email)) {
            $register_error = "Username or email already exists.";
        } else {
            // Create WordPress user
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                // Update user meta
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $display_name,
                    'role' => 'subscriber'
                ]);
                
                // Create Limey profile
                limey_create_user_profile($user_id, $username, $display_name);
                
                // Auto login
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id, true);
                
                // Let popup login system handle redirect
                // wp_redirect(home_url('/feed-2'));
                // exit;
            } else {
                $register_error = $user_id->get_error_message();
            }
        }
    }
    
    ob_start();
    ?>
    <style>
    .limey-auth-container {
        max-width: 400px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .limey-logo {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #5ccc45, #4CAF50);
        border-radius: 50%;
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2em;
        color: white;
    }
    
    .auth-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .auth-tab {
        flex: 1;
        padding: 10px;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: bold;
        color: #666;
        border-bottom: 2px solid transparent;
    }
    
    .auth-tab.active {
        color: #5ccc45;
        border-bottom-color: #5ccc45;
    }
    
    .auth-form {
        display: none;
    }
    
    .auth-form.active {
        display: block;
    }
    
    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }
    
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #5ccc45;
        box-shadow: 0 0 0 2px rgba(92, 204, 69, 0.2);
    }
    
    .btn-limey {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #5ccc45, #4CAF50);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 10px;
    }
    
    .btn-limey:hover {
        background: linear-gradient(135deg, #4CAF50, #45a049);
    }
    
    .error-message {
        background: #ffebee;
        color: #c62828;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        font-size: 14px;
    }
    </style>
    
    <div class="limey-auth-container">
        <div class="limey-logo">🎬</div>
        <h2>Welcome to Limey</h2>
        <p>Trinbago's Home for Creators</p>
        
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="switchTab('login')">Sign In</button>
            <button class="auth-tab" onclick="switchTab('register')">Join Limey</button>
        </div>
        
        <!-- Login Form -->
        <form class="auth-form active" id="login-form" method="post">
            <?php if (isset($login_error)): ?>
                <div class="error-message"><?php echo esc_html($login_error); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" name="limey_login" class="btn-limey">Sign In</button>
        </form>
        
        <!-- Registration Form -->
        <form class="auth-form" id="register-form" method="post">
            <?php if (isset($register_error)): ?>
                <div class="error-message"><?php echo esc_html($register_error); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="reg_username">Username</label>
                <input type="text" id="reg_username" name="reg_username" required>
            </div>
            
            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name" required>
            </div>
            
            <div class="form-group">
                <label for="reg_email">Email</label>
                <input type="email" id="reg_email" name="reg_email" required>
            </div>
            
            <div class="form-group">
                <label for="reg_password">Password</label>
                <input type="password" id="reg_password" name="reg_password" required>
            </div>
            
            <button type="submit" name="limey_register" class="btn-limey">Join Limey</button>
        </form>
    </div>
    
    <script>
    function switchTab(tab) {
        // Remove active class from all tabs and forms
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding form
        event.target.classList.add('active');
        document.getElementById(tab + '-form').classList.add('active');
    }
    </script>
    <?php
    return ob_get_clean();
}

// Function to create/update Limey user profile
function limey_create_user_profile($user_id, $username = null, $display_name = null) {
    global $wpdb;
    
    $user = get_user_by('ID', $user_id);
    if (!$user) return false;
    
    $username = $username ?: $user->user_login;
    $display_name = $display_name ?: $user->display_name;
    
    // Generate UUID for WordPress user ID
    $user_uuid = wp_generate_uuid4();
    
    // Check if profile already exists
    $existing_profile = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}limey_profiles WHERE username = %s",
        $username
    ));
    
    if (!$existing_profile) {
        // Create new profile
        $profile_id = wp_generate_uuid4();
        $avatar_url = get_avatar_url($user_id);
        
        $wpdb->insert(
            "{$wpdb->prefix}limey_profiles",
            [
                'id' => $profile_id,
                'user_id' => $user_uuid,
                'username' => $username,
                'display_name' => $display_name,
                'avatar_url' => $avatar_url,
                'created_at' => current_time('mysql', 1)
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Store the mapping between WordPress user ID and UUID
        update_user_meta($user_id, 'limey_user_uuid', $user_uuid);
    } else {
        // Get existing UUID
        $user_uuid = $existing_profile->user_id;
        update_user_meta($user_id, 'limey_user_uuid', $user_uuid);
    }
    
    return true;
}

// Video upload shortcode with fixed white text inputs
function limey_upload_page_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="/login">login</a> to upload videos.</p>';
    }
    
    ob_start();
    ?>
    <style>
    .limey-upload-container {
        min-height: 100vh;
        background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
        padding-bottom: 80px;
        padding-top: 96px;
        color: white;
    }
    
    .limey-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 16px;
    }
    
    .limey-header h1 {
        font-size: 32px;
        font-weight: 900;
        color: white;
        letter-spacing: 0.15em;
        margin: 0;
        filter: drop-shadow(0 0 8px #5ccc45);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .upload-content {
        padding: 16px;
        max-width: 512px;
        margin: 0 auto;
    }
    
    .upload-card {
        background: rgba(255, 255, 255, 0.05);
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 32px;
        text-align: center;
        transition: border-color 0.3s ease;
        margin-bottom: 24px;
    }
    
    .upload-card:hover {
        border-color: #5ccc45;
    }
    
    .upload-icon {
        width: 64px;
        height: 64px;
        background: rgba(92, 204, 69, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 32px;
    }
    
    .video-preview {
        width: 100%;
        max-width: 288px;
        margin: 0 auto 16px;
        aspect-ratio: 9/16;
        background: black;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .video-preview video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        background-color: black;
    }
    
    .btn-limey {
        background: linear-gradient(135deg, #5ccc45, #4CAF50);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 8px;
    }
    
    .btn-limey:hover {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(92, 204, 69, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        border: 2px solid rgba(255, 255, 255, 0.2);
        color: white;
        border-radius: 8px;
        padding: 10px 22px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-outline:hover {
        border-color: #5ccc45;
        background: rgba(92, 204, 69, 0.1);
    }
    
    .form-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 24px;
        margin-top: 24px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: white;
        margin-bottom: 8px;
    }
    
    .form-input, .form-textarea, .form-select {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        color: white !important;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: #5ccc45;
        box-shadow: 0 0 0 2px rgba(92, 204, 69, 0.2);
        background: rgba(0, 0, 0, 0.5);
    }
    
    .form-input::placeholder, .form-textarea::placeholder {
        color: rgba(255, 255, 255, 0.7) !important;
    }
    
    .form-select option {
        background: #1a1a1a;
        color: white;
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .char-count {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 4px;
        text-align: right;
    }
    
    .content-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .badge {
        background: rgba(92, 204, 69, 0.2);
        color: #5ccc45;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-outline {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: rgba(255, 255, 255, 0.8);
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .hidden {
        display: none;
    }
    
    .toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #dc3545;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1000;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-size: 14px;
        max-width: 400px;
        text-align: center;
    }
    
    .toast.success {
        background: #5ccc45;
    }
    
    .toast.error {
        background: #dc3545;
    }
    
    .tips-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        padding: 16px;
        margin-top: 24px;
    }
    </style>
    
    <div class="limey-upload-container">
        <!-- Header -->
        <div class="limey-header">
            <h1>Upload</h1>
        </div>
        
        <!-- Toast -->
        <div id="toast" class="toast"></div>
        
        <div class="upload-content">
            <!-- File Upload Area -->
            <div class="upload-card" id="uploadCard">
                <div id="uploadPrompt">
                    <div class="upload-icon">📁</div>
                    <h3 style="color: white; margin-bottom: 8px;">Create or Upload Your Content</h3>
                    <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 24px;">Use the Create button to record a new video or Upload to select an existing video to share with the Limey community.</p>
                    
                    <!-- Hidden file inputs -->
                    <input type="file" id="createInput" accept="video/*" capture="environment" class="hidden">
                    <input type="file" id="uploadInput" accept="video/*" class="hidden">
                    
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button class="btn-limey" onclick="handleCreate()">🎨 Create</button>
                        <button class="btn-limey" onclick="handleUpload()">➕ Upload</button>
                    </div>
                </div>
                
                <div id="previewSection" class="hidden">
                    <h3 id="previewTitle" style="color: #5ccc45; font-size: 18px; font-weight: 600; margin-bottom: 16px; text-shadow: 0 0 8px rgba(92, 204, 69, 0.5);">Upload</h3>
                    <div class="video-preview">
                        <video id="videoPreview" controls preload="metadata"></video>
                    </div>
                    <p id="fileInfo" style="font-size: 12px; color: rgba(255, 255, 255, 0.6); margin-top: 8px;"></p>
                    
                    <input type="file" id="changeInput" accept="video/*" class="hidden">
                    <div style="margin-top: 16px;">
                        <button class="btn-outline" onclick="handleChangeVideo()">Change Video</button>
                    </div>
                </div>
            </div>
            
            <!-- Upload Form -->
            <div id="uploadForm" class="form-card hidden">
                <form id="videoUploadForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" id="videoTitle" class="form-input" placeholder="Give your content a catchy title..." maxlength="100" required>
                        <div class="char-count"><span id="titleCount">0</span>/100 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="videoCategory" class="form-select" required>
                            <option value="All">All</option>
                            <option value="Anime">Anime</option>
                            <option value="Bar Limes">Bar Limes</option>
                            <option value="Carnival">Carnival</option>
                            <option value="Cartoon">Cartoon</option>
                            <option value="Comedy">Comedy</option>
                            <option value="DIY Project">DIY Project</option>
                            <option value="Educational">Educational</option>
                            <option value="Fact">Fact</option>
                            <option value="Festival">Festival</option>
                            <option value="FoodFun">FoodFun</option>
                            <option value="Music">Music</option>
                            <option value="News">News</option>
                            <option value="Outdoor">Outdoor</option>
                            <option value="PlacesFun">PlacesFun</option>
                            <option value="Sports">Sports</option>
                            <option value="TriniStar">TriniStar</option>
                            <option value="Tutorial">Tutorial</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea id="videoDescription" class="form-textarea" placeholder="Tell viewers about your content..." maxlength="100" rows="4"></textarea>
                        <div class="char-count"><span id="descCount">0</span>/100 characters</div>
                    </div>
                    
                    <!-- Content Type Badges -->
                    <div class="content-badges">
                        <span class="badge">🎬 Video</span>
                        <span class="badge-outline">Trinidad & Tobago</span>
                    </div>
                    
                    <!-- Upload Button -->
                    <div class="form-actions">
                        <button type="button" class="btn-outline" onclick="handleCancel()">Cancel</button>
                        <button type="submit" class="btn-limey" id="submitBtn" style="flex: 1;">Share to Limey 🚀</button>
                    </div>
                </form>
            </div>
            
            <!-- Upload Tips -->
            <div class="tips-card">
                <h4 style="font-weight: 500; color: white; margin-bottom: 12px; text-align: center;">📝 Upload Tips</h4>
                <ul style="font-size: 14px; color: rgba(255, 255, 255, 0.7); line-height: 1.6; list-style: none; padding: 0;">
                    <li style="margin-bottom: 8px; text-align: center;">• <strong>Max video duration:</strong> 5 minutes</li>
                    <li style="margin-bottom: 8px; text-align: center;">• <strong>Max file size:</strong> 50 MB</li>
                    <li style="margin-bottom: 8px; text-align: center;">• <strong>Supported video formats:</strong> mp4, mov, webm, 3gp, avi</li>
                    <li style="margin-bottom: 8px; text-align: center;">• <strong>iPhone users:</strong> All iPhone camera formats supported (mov, mp4, m4v)</li>
                    <li style="margin-bottom: 8px; text-align: center;">• <strong>Android users:</strong> All standard Android formats supported (mp4, 3gp, webm)</li>
                    <li style="margin-bottom: 8px; text-align: center;">• If your video is too large, use free apps like <strong>CapCut</strong>, <strong>InShot</strong>, or your phone's built-in editor to compress the video before uploading.</li>
                    <li style="margin-bottom: 8px; text-align: center;">• Keep videos under 60 seconds for best engagement</li>
                    <li style="margin-bottom: 8px; text-align: center;">• Use good lighting and clear audio</li>
                    <li style="margin-bottom: 8px; text-align: center;">• Add hashtags in your description to reach more viewers</li>
                    <li style="margin-bottom: 8px; text-align: center;">• Upload during peak hours (6-9 PM) for maximum views</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
    let selectedFile = null;
    let isUploading = false;
    
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        if (!toast) {
            // Create toast if it doesn't exist
            const newToast = document.createElement('div');
            newToast.id = 'toast';
            newToast.className = 'toast';
            document.body.appendChild(newToast);
        }
        
        const toastElement = document.getElementById('toast');
        toastElement.innerHTML = isError ? 
            `<span style="margin-right: 8px;">❌</span>${message}` : 
            `<span style="margin-right: 8px;">🎉</span>${message}`;
        toastElement.className = 'toast' + (isError ? ' error' : ' success');
        toastElement.style.display = 'block';
        toastElement.style.opacity = '1';
        
        setTimeout(() => { 
            toastElement.style.opacity = '0';
            setTimeout(() => {
                toastElement.style.display = 'none';
            }, 300);
        }, 3000);
    }
    
    function handleCreate() {
        document.getElementById('createInput').click();
    }
    
    function handleUpload() {
        document.getElementById('uploadInput').click();
    }
    
    function handleChangeVideo() {
        document.getElementById('changeInput').click();
    }
    
    function handleCancel() {
        // Stop and clear the video
        const videoPreview = document.getElementById('videoPreview');
        if (videoPreview.src) {
            videoPreview.pause();
            videoPreview.currentTime = 0;
            URL.revokeObjectURL(videoPreview.src);
            videoPreview.src = '';
        }
        
        // Clear file inputs
        document.getElementById('createInput').value = '';
        document.getElementById('uploadInput').value = '';
        document.getElementById('changeInput').value = '';
        
        // Reset state
        selectedFile = null;
        
        // Redirect to feed page
        window.location.href = '/feed-2';
    }
    
    function validateFile(file) {
        const supportedTypes = ['video/mp4', 'video/webm', 'video/quicktime', 'video/mov', 'video/3gpp', 'video/avi'];
        
        if (!supportedTypes.includes(file.type)) {
            showToast('Unsupported video format. Please select MP4, MOV, WebM, 3GP, or AVI files.', true);
            return false;
        }
        
        if (file.size > 50 * 1024 * 1024) {
            showToast('File too large. Maximum size is 50MB.', true);
            return false;
        }
        
        return true;
    }
    
    function handleFileSelect(file, source = 'upload') {
        if (!validateFile(file)) return;
        
        selectedFile = file;
        
        // Update the preview title based on source
        const previewTitle = document.getElementById('previewTitle');
        if (source === 'create') {
            previewTitle.textContent = 'Create';
        } else {
            previewTitle.textContent = 'Upload';
        }
        
        const preview = document.getElementById('videoPreview');
        const url = URL.createObjectURL(file);
        preview.src = url;
        
        const fileInfo = document.getElementById('fileInfo');
        fileInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        
        const titleInput = document.getElementById('videoTitle');
        if (!titleInput.value) {
            const name = file.name.split('.')[0];
            titleInput.value = name.charAt(0).toUpperCase() + name.slice(1);
            updateCharCounts();
        }
        
        document.getElementById('uploadPrompt').classList.add('hidden');
        document.getElementById('previewSection').classList.remove('hidden');
        document.getElementById('uploadForm').classList.remove('hidden');
    }
    
    function updateCharCounts() {
        const titleInput = document.getElementById('videoTitle');
        const descInput = document.getElementById('videoDescription');
        const titleCount = document.getElementById('titleCount');
        const descCount = document.getElementById('descCount');
        
        if (titleCount) titleCount.textContent = titleInput.value.length;
        if (descCount) descCount.textContent = descInput.value.length;
    }
    
    // Event listeners
    document.getElementById('createInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) handleFileSelect(file, 'create');
    });
    
    document.getElementById('uploadInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) handleFileSelect(file, 'upload');
    });
    
    document.getElementById('changeInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) handleFileSelect(file, 'upload');
    });
    
    document.getElementById('videoTitle').addEventListener('input', updateCharCounts);
    document.getElementById('videoDescription').addEventListener('input', updateCharCounts);
    
    // Handle form submission
    document.getElementById('videoUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!selectedFile || isUploading) return;
        
        const title = document.getElementById('videoTitle').value.trim();
        if (!title) {
            showToast('Please add a title for your video.', true);
            return;
        }
        
        isUploading = true;
        
        // Disable all form elements
        const formElements = document.querySelectorAll('#videoUploadForm input, #videoUploadForm textarea, #videoUploadForm select, #videoUploadForm button');
        formElements.forEach(element => element.disabled = true);
        
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.querySelector('#uploadForm .btn-outline');
        
        // Create progress display
        submitBtn.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><div style="width: 16px; height: 16px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div><span id="uploadProgress">Uploading... 0%</span></div>';
        
        // Add CSS for spinner animation
        if (!document.getElementById('spinnerStyle')) {
            const style = document.createElement('style');
            style.id = 'spinnerStyle';
            style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
            document.head.appendChild(style);
        }
        
        const formData = new FormData();
        formData.append('action', 'limey_upload_video');
        formData.append('video_file', selectedFile);
        formData.append('title', title);
        formData.append('description', document.getElementById('videoDescription').value);
        formData.append('category', document.getElementById('videoCategory').value);
        
        // Store upload data for background processing
        const uploadData = {
            title: title,
            description: document.getElementById('videoDescription').value,
            category: document.getElementById('videoCategory').value,
            fileName: selectedFile.name,
            fileSize: selectedFile.size
        };
        
        // Create XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();
        
        // Track upload progress
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                const progressSpan = document.getElementById('uploadProgress');
                if (progressSpan) {
                    progressSpan.textContent = `Uploading... ${percentComplete}%`;
                }
                
                // At 90%, show background message
                if (percentComplete >= 90) {
                    showBackgroundUploadMessage(uploadData);
                }
            }
        });
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        // Store success data for banner
                        localStorage.setItem('limey_upload_success', JSON.stringify({
                            title: uploadData.title,
                            timestamp: Date.now(),
                            videoId: data.data?.video_id || 'new'
                        }));
                        
                        showToast('Upload completed successfully! 🎉');
                        
                        // Redirect after short delay
                        setTimeout(() => {
                            window.location.href = '/feed-2';
                        }, 1500);
                    } else {
                        handleUploadError(data.data || 'Upload failed');
                    }
                } catch (error) {
                    handleUploadError('Invalid response from server');
                }
            } else {
                handleUploadError('Server error: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            handleUploadError('Network error occurred');
        };
        
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
        xhr.send(formData);
        
        function showBackgroundUploadMessage(data) {
            const message = `
                <div style="text-align: center; padding: 20px; background: rgba(92, 204, 69, 0.1); border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #5ccc45; margin-bottom: 10px;">🚀 Upload in Progress!</h4>
                    <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 15px;">Your video "${data.title}" is uploading. You can continue browsing while it completes in the background.</p>
                    <button onclick="continueInBackground()" style="background: #5ccc45; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Continue to For You Page</button>
                </div>
            `;
            
            const uploadForm = document.getElementById('uploadForm');
            if (!document.getElementById('backgroundMessage')) {
                const messageDiv = document.createElement('div');
                messageDiv.id = 'backgroundMessage';
                messageDiv.innerHTML = message;
                uploadForm.appendChild(messageDiv);
            }
        }
        
        function handleUploadError(errorMessage) {
            showToast('Upload Failed: ' + errorMessage, true);
            
            // Re-enable form elements
            formElements.forEach(element => element.disabled = false);
            submitBtn.innerHTML = 'Share to Limey 🚀';
            isUploading = false;
        }
    });
    
    // Function to continue in background
    window.continueInBackground = function() {
        // Store upload progress data for background tracking
        const progressSpan = document.getElementById('uploadProgress');
        const currentProgress = progressSpan ? progressSpan.textContent : 'Uploading...';
        
        localStorage.setItem('limey_background_upload', JSON.stringify({
            title: document.getElementById('videoTitle').value,
            progress: currentProgress,
            timestamp: Date.now()
        }));
        
        showToast('Upload continuing in background...');
        setTimeout(() => {
            window.location.href = '/feed-2';
        }, 1000);
    };
    
    updateCharCounts();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('limey_upload_page', 'limey_upload_page_shortcode');

// Handle video upload AJAX
function limey_handle_video_upload() {
    // Enable error logging
    error_log('Limey Upload: Starting upload process');
    
    if (!is_user_logged_in()) {
        error_log('Limey Upload: User not logged in');
        wp_send_json_error('You must be logged in to upload videos.');
        return;
    }
    
    error_log('Limey Upload: FILES data: ' . print_r($_FILES, true));
    error_log('Limey Upload: POST data: ' . print_r($_POST, true));
    
    if (!isset($_FILES['video_file']) || !isset($_POST['title'])) {
        error_log('Limey Upload: Missing required fields');
        wp_send_json_error('Missing required fields.');
        return;
    }
    
    $allowed_types = ['video/mp4', 'video/quicktime', 'video/webm', 'video/avi', 'video/mov', 'video/3gpp'];
    $file = $_FILES['video_file'];
    
    error_log('Limey Upload: File type: ' . $file['type']);
    error_log('Limey Upload: File size: ' . $file['size']);
    
    if (!in_array($file['type'], $allowed_types)) {
        error_log('Limey Upload: Invalid file type: ' . $file['type']);
        wp_send_json_error('Invalid file type. Supported: MP4, MOV, WebM, AVI, 3GP');
        return;
    }
    
    if ($file['size'] > 50 * 1024 * 1024) {
        error_log('Limey Upload: File too large: ' . $file['size']);
        wp_send_json_error('File too large. Maximum size is 50MB.');
        return;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('Limey Upload: Upload error: ' . $file['error']);
        wp_send_json_error('File upload error: ' . $file['error']);
        return;
    }
    
    // Upload file
    $upload_dir = wp_upload_dir();
    $limey_dir = $upload_dir['basedir'] . '/limey_videos/';
    
    error_log('Limey Upload: Upload directory: ' . $limey_dir);
    
    if (!file_exists($limey_dir)) {
        if (!wp_mkdir_p($limey_dir)) {
            error_log('Limey Upload: Failed to create directory: ' . $limey_dir);
            wp_send_json_error('Failed to create upload directory.');
            return;
        }
    }
    
    $filename = wp_unique_filename($limey_dir, $file['name']);
    $filepath = $limey_dir . $filename;
    
    error_log('Limey Upload: Target filepath: ' . $filepath);
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log('Limey Upload: File moved successfully');
        
        global $wpdb;
        $current_user = wp_get_current_user();
        
        // Ensure profile exists and get UUID
        limey_create_user_profile($current_user->ID);
        $user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
        
        if (!$user_uuid) {
            error_log('Limey Upload: No user UUID found');
            wp_send_json_error('User profile not properly created.');
            return;
        }
        
        $video_url = str_replace('http://', 'https://', $upload_dir['baseurl'] . '/limey_videos/' . $filename);
        $avatar_url = str_replace('http://', 'https://', get_avatar_url($current_user->ID));
        
        $video_data = [
            'id' => wp_generate_uuid4(),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'All'),
            'video_url' => $video_url,
            'user_id' => $user_uuid,
            'username' => $current_user->user_login,
            'avatar_url' => $avatar_url,
            'created_at' => current_time('mysql', 1)
        ];
        
        error_log('Limey Upload: Video data: ' . print_r($video_data, true));
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'limey_videos',
            $video_data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        error_log('Limey Upload: Insert result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));
        error_log('Limey Upload: Last error: ' . $wpdb->last_error);
        error_log('Limey Upload: Last query: ' . $wpdb->last_query);
        
        if ($result !== false) {
            // Update user's video count using UUID
            $profile_update = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}limey_profiles SET video_count = video_count + 1 WHERE user_id = %s",
                $user_uuid
            ));
            
            error_log('Limey Upload: Profile update result: ' . $profile_update);
            
            wp_send_json_success([
                'message' => 'Video uploaded successfully!',
                'video_id' => $video_data['id'],
                'title' => $video_data['title']
            ]);
        } else {
            error_log('Limey Upload: Database insert failed: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save video to database: ' . $wpdb->last_error);
        }
    } else {
        error_log('Limey Upload: Failed to move uploaded file from ' . $file['tmp_name'] . ' to ' . $filepath);
        wp_send_json_error('Failed to upload file. Check file permissions.');
    }
}

add_action('wp_ajax_limey_upload_video', 'limey_handle_video_upload');

// Handle like toggle
function limey_handle_like_toggle() {
    global $wpdb;
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    $video_id = sanitize_text_field($_POST['video_id']);
    $user_id = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
    
    if (!$user_id || !$video_id) {
        wp_send_json_error('Invalid data');
        return;
    }
    
    // Check if already liked
    $existing_like = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}limey_video_likes WHERE user_id = %s AND video_id = %s",
        $user_id, $video_id
    ));
    
    if ($existing_like) {
        // Unlike
        $wpdb->delete(
            "{$wpdb->prefix}limey_video_likes",
            ['user_id' => $user_id, 'video_id' => $video_id],
            ['%s', '%s']
        );
        
        // Decrease like count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}limey_videos SET like_count = GREATEST(0, like_count - 1) WHERE id = %s",
            $video_id
        ));
        
        $liked = false;
    } else {
        // Like
        $wpdb->insert(
            "{$wpdb->prefix}limey_video_likes",
            [
                'id' => wp_generate_uuid4(),
                'user_id' => $user_id,
                'video_id' => $video_id
            ],
            ['%s', '%s', '%s']
        );
        
        // Increase like count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}limey_videos SET like_count = like_count + 1 WHERE id = %s",
            $video_id
        ));
        
        $liked = true;
    }
    
    // Get updated count
    $new_count = $wpdb->get_var($wpdb->prepare(
        "SELECT like_count FROM {$wpdb->prefix}limey_videos WHERE id = %s",
        $video_id
    ));
    
    wp_send_json_success(['liked' => $liked, 'count' => intval($new_count)]);
}

add_action('wp_ajax_limey_toggle_like', 'limey_handle_like_toggle');

// Handle save toggle
function limey_handle_save_toggle() {
    global $wpdb;
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    $video_id = sanitize_text_field($_POST['video_id']);
    $user_id = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
    
    if (!$user_id || !$video_id) {
        wp_send_json_error('Invalid data');
        return;
    }
    
    // Check if already saved
    $existing_save = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}limey_video_saves WHERE user_id = %s AND video_id = %s",
        $user_id, $video_id
    ));
    
    if ($existing_save) {
        // Unsave
        $delete_result = $wpdb->delete(
            "{$wpdb->prefix}limey_video_saves",
            ['user_id' => $user_id, 'video_id' => $video_id],
            ['%s', '%s']
        );
        
        if ($delete_result !== false) {
            // Decrease save count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}limey_videos SET save_count = GREATEST(0, save_count - 1) WHERE id = %s",
                $video_id
            ));
        }
        
        $saved = false;
    } else {
        // Save
        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}limey_video_saves",
            [
                'id' => wp_generate_uuid4(),
                'user_id' => $user_id,
                'video_id' => $video_id
            ],
            ['%s', '%s', '%s']
        );
        
        if ($insert_result !== false) {
            // Only increase save count if insert was successful
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}limey_videos SET save_count = save_count + 1 WHERE id = %s",
                $video_id
            ));
            $saved = true;
        } else {
            // Insert failed (probably due to unique constraint)
            // Check if it was already saved by another request
            $recheck_save = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}limey_video_saves WHERE user_id = %s AND video_id = %s",
                $user_id, $video_id
            ));
            $saved = $recheck_save ? true : false;
        }
    }
    
    // Get updated count
    $new_count = $wpdb->get_var($wpdb->prepare(
        "SELECT save_count FROM {$wpdb->prefix}limey_videos WHERE id = %s",
        $video_id
    ));
    
    wp_send_json_success(['saved' => $saved, 'count' => intval($new_count)]);
}

add_action('wp_ajax_limey_toggle_save', 'limey_handle_save_toggle');

// Handle share tracking
function limey_handle_share_track() {
    global $wpdb;
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    $video_id = sanitize_text_field($_POST['video_id']);
    $share_type = sanitize_text_field($_POST['share_type'] ?? 'link');
    $user_id = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
    
    if (!$user_id || !$video_id) {
        wp_send_json_error('Invalid data');
        return;
    }
    
    // Record share
    $wpdb->insert(
        "{$wpdb->prefix}limey_video_shares",
        [
            'id' => wp_generate_uuid4(),
            'user_id' => $user_id,
            'video_id' => $video_id,
            'share_type' => $share_type
        ],
        ['%s', '%s', '%s', '%s']
    );
    
    // Increase share count
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}limey_videos SET share_count = share_count + 1 WHERE id = %s",
        $video_id
    ));
    
    // Get updated count
    $new_count = $wpdb->get_var($wpdb->prepare(
        "SELECT share_count FROM {$wpdb->prefix}limey_videos WHERE id = %s",
        $video_id
    ));
    
    wp_send_json_success(['count' => intval($new_count)]);
}

add_action('wp_ajax_limey_track_share', 'limey_handle_share_track');

// Get user's saved videos
function limey_get_user_saved_videos() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    global $wpdb;
    $user_id = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user');
        return;
    }
    
    $saved_video_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT video_id FROM {$wpdb->prefix}limey_video_saves WHERE user_id = %s",
        $user_id
    ));
    
    wp_send_json_success($saved_video_ids ?: []);
}

add_action('wp_ajax_limey_get_user_saved_videos', 'limey_get_user_saved_videos');

// Delete video
function limey_delete_video() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    $video_id = sanitize_text_field($_POST['video_id']);
    
    if (!$current_user_uuid || !$video_id) {
        wp_send_json_error('Invalid data');
        return;
    }
    
    // Check if the video belongs to the current user
    $video = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}limey_videos WHERE id = %s AND user_id = %s",
        $video_id, $current_user_uuid
    ));
    
    if (!$video) {
        wp_send_json_error('Video not found or you do not have permission to delete it');
        return;
    }
    
    // Delete the video file if it exists
    if ($video->video_url) {
        $upload_dir = wp_upload_dir();
        $video_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $video->video_url);
        if (file_exists($video_path)) {
            unlink($video_path);
        }
    }
    
    // Delete from database tables
    $wpdb->delete("{$wpdb->prefix}limey_video_likes", ['video_id' => $video_id], ['%s']);
    $wpdb->delete("{$wpdb->prefix}limey_video_saves", ['video_id' => $video_id], ['%s']);
    $wpdb->delete("{$wpdb->prefix}limey_video_shares", ['video_id' => $video_id], ['%s']);
    $wpdb->delete("{$wpdb->prefix}limey_videos", ['id' => $video_id], ['%s']);
    
    // Update user's video count
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}limey_profiles SET video_count = GREATEST(0, video_count - 1) WHERE user_id = %s",
        $current_user_uuid
    ));
    
    wp_send_json_success('Video deleted successfully');
}

add_action('wp_ajax_limey_delete_video', 'limey_delete_video');

// Get video data for modal
function limey_get_video_data() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    $video_id = sanitize_text_field($_POST['video_id']);
    $tab_type = sanitize_text_field($_POST['tab_type']);
    
    if (!$current_user_uuid || !$video_id) {
        wp_send_json_error('Invalid data');
        return;
    }
    
    // Get videos based on tab type
    if ($tab_type === 'saved') {
        // Get saved videos
        $videos = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, p.display_name, p.is_verified, p.username as profile_username, p.avatar_url as profile_avatar
            FROM {$wpdb->prefix}limey_video_saves s
            JOIN {$wpdb->prefix}limey_videos v ON s.video_id = v.id
            LEFT JOIN {$wpdb->prefix}limey_profiles p ON v.user_id = p.user_id
            WHERE s.user_id = %s 
            ORDER BY s.created_at DESC",
            $current_user_uuid
        ));
    } else {
        // Get user's own videos
        $videos = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, p.display_name, p.is_verified, p.username as profile_username, p.avatar_url as profile_avatar
            FROM {$wpdb->prefix}limey_videos v
            LEFT JOIN {$wpdb->prefix}limey_profiles p ON v.user_id = p.user_id
            WHERE v.user_id = %s 
            ORDER BY v.created_at DESC",
            $current_user_uuid
        ));
    }
    
    $video_data = [];
    $current_index = 0;
    
    foreach ($videos as $index => $video) {
        $video_data[] = [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'video_url' => $video->video_url,
            'view_count' => $video->view_count,
            'like_count' => $video->like_count,
            'save_count' => $video->save_count,
            'share_count' => $video->share_count,
            'username' => $video->profile_username ?: $video->username,
            'avatar_url' => $video->profile_avatar ?: $video->avatar_url,
            'display_name' => $video->display_name
        ];
        
        if ($video->id === $video_id) {
            $current_index = $index;
        }
    }
    
    wp_send_json_success([
        'videos' => $video_data,
        'currentIndex' => $current_index
    ]);
}

add_action('wp_ajax_limey_get_video_data', 'limey_get_video_data');

// Get video counts
function limey_get_video_counts() {
    global $wpdb;
    $video_id = sanitize_text_field($_POST['video_id']);
    
    if (!$video_id) {
        wp_send_json_error('Invalid video ID');
        return;
    }
    
    $video = $wpdb->get_row($wpdb->prepare(
        "SELECT like_count, save_count, share_count FROM {$wpdb->prefix}limey_videos WHERE id = %s",
        $video_id
    ));
    
    if ($video) {
        wp_send_json_success([
            'like_count' => intval($video->like_count),
            'save_count' => intval($video->save_count),
            'share_count' => intval($video->share_count)
        ]);
    } else {
        wp_send_json_error('Video not found');
    }
}

add_action('wp_ajax_limey_get_video_counts', 'limey_get_video_counts');

// Profile page shortcode
function limey_profile_page_shortcode($atts = []) {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="/login">login</a> to view profiles.</p>';
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    
    // Extract username from attributes or URL
    $username = $atts['username'] ?? null;
    if (!$username) {
        // Try to get username from URL path
        $request_uri = $_SERVER['REQUEST_URI'];
        if (preg_match('/\/profile\/([^\/]+)/', $request_uri, $matches)) {
            $username = $matches[1];
        }
    }
    
    $is_own_profile = true;
    $profile_user_uuid = $current_user_uuid;
    $page_title = 'Profile';
    
    if ($username) {
        // Get profile by username
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}limey_profiles WHERE username = %s",
            $username
        ));
        
        if ($profile) {
            $profile_user_uuid = $profile->user_id;
            $is_own_profile = ($profile_user_uuid === $current_user_uuid);
            $page_title = $is_own_profile ? 'Profile' : 'Viewing ' . ($profile->display_name ?: $profile->username);
        } else {
            return '<p>Profile not found.</p>';
        }
    } else {
        // Get current user's profile
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}limey_profiles WHERE user_id = %s",
            $current_user_uuid
        ));
    }
    
    // Get user videos
    $videos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}limey_videos WHERE user_id = %s ORDER BY created_at DESC",
        $profile_user_uuid
    ));
    
    // Get saved videos (only for own profile)
    $saved_videos = [];
    if ($is_own_profile) {
        $saved_videos = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, p.display_name, p.is_verified, p.username as profile_username, p.avatar_url as profile_avatar
            FROM {$wpdb->prefix}limey_video_saves s
            JOIN {$wpdb->prefix}limey_videos v ON s.video_id = v.id
            LEFT JOIN {$wpdb->prefix}limey_profiles p ON v.user_id = p.user_id
            WHERE s.user_id = %s 
            ORDER BY s.created_at DESC",
            $current_user_uuid
        ));
    }
    
    // Check if user is admin
    $is_admin = current_user_can('administrator');
    
    ob_start();
    ?>
    <style>
    .limey-profile-container {
        min-height: 100vh;
        background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
        color: white;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        padding-bottom: 80px;
        padding-top: 80px;
    }
    
    .profile-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 16px;
    }
    
    .profile-header h1 {
        font-size: 24px;
        font-weight: 900;
        color: white;
        letter-spacing: 0.15em;
        margin: 0;
        filter: drop-shadow(0 0 8px #5ccc45);
        text-align: center;
    }
    
    .profile-content {
        padding: 12px 24px 24px 24px;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .profile-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-bottom: 32px;
    }
    .profile-avatar1 {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid green;
        object-fit: cover;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    
    .profile-avatar1:hover {
        opacity: 0.9;
    }
    
     .profile-avatar1-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(92, 204, 69, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: bold;
        color: #5ccc45;
        margin-bottom: 16px;
        border: 3px solid #5ccc45;
        cursor: pointer;
    }
    //-------need this-----//
    .profile-avatar {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        border: 2px solid green;
        object-fit: cover;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    .profile-avatar:hover {
        opacity: 0.9;
    }
    
     .profile-avatar-placeholder {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        background: rgba(92, 204, 69, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: bold;
        color: #5ccc45;
        margin-bottom: 16px;
        border: 3px solid #5ccc45;
        cursor: pointer;
    }
    
    .avatar-edit-icon {
        position: absolute;
        bottom: 16px;
        right: -8px;
        width: 32px;
        height: 32px;
        background: #5ccc45;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        border: 3px solid #1a1a1a;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    
    .avatar-edit-icon:hover {
        background: #4CAF50;
        transform: scale(1.1);
    }
    
    .profile-name {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 4px;
        color: white;
    }
    
    .profile-username {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 16px;
    }
    
    .profile-bio {
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 16px;
        max-width: 300px;
    }
    
    .trini-credits {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .credits-badge {
        background: rgba(22, 163, 74, 0.2);
        color: #4ade80;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 600;
    }
    
    .profile-buttons {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .btn-profile {
        flex: 1;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-outline {
        background: transparent;
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
    }
    
    .btn-outline:hover {
        border-color: #5ccc45;
        background: rgba(92, 204, 69, 0.1);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #5ccc45, #4CAF50);
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        transform: translateY(-1px);
    }
    
    .btn-wallet {
        background: rgba(255, 215, 0, 0.2);
        border: 2px solid #ffd700;
        color: #ffd700;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-wallet:hover {
        background: rgba(255, 215, 0, 0.3);
        transform: translateY(-1px);
    }
    
    .profile-stats {
        display: flex;
        justify-content: center;
        gap: 32px;
        margin-bottom: 32px;
    }
    
    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        transition: color 0.3s ease;
    }
    
    .stat-item:hover {
        color: #5ccc45;
    }
    
    .stat-number {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 4px;
    }
    
    .stat-label {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .profile-tabs {
        margin-bottom: 24px;
    }
    
    .tab-list {
        display: flex;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 4px;
        margin-bottom: 24px;
    }
    
    .tab-button {
        flex: 1;
        padding: 12px 16px;
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .tab-button.active {
        background: #5ccc45;
        color: white;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .videos-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    
    .video-item {
        aspect-ratio: 9/16;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        position: relative;
        transition: transform 0.2s ease;
    }
    
    .video-item:hover:not(.modal-video-item) {
        transform: scale(1.02);
    }
    
    .video-thumbnail {
        position: relative;
        aspect-ratio: 9/16;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        transition: transform 0.2s ease;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-thumbnail:hover {
        transform: scale(1.02);
    }
    
    .video-thumbnail video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .video-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,1));
        padding: 8px;
        color: white;
        font-size: 12px;
    }
    
    .video-menu {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 24px;
        height: 24px;
        background: rgba(0, 0, 0, 0.7);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.2s ease;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-menu:hover {
        background: rgba(0, 0, 0, 0.9);
    }
    
    .video-creator {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 2;
    }
    
    .creator-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid white;
        object-fit: cover;
        background: #333;
    }
    
    .creator-avatar-placeholder {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid white;
        background: #5ccc45;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: bold;
        color: white;
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    
    .creator-avatar, .creator-avatar-placeholder {
        cursor: pointer;
        transition: transform 0.2s ease;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .creator-avatar:hover, .creator-avatar-placeholder:hover {
        transform: scale(1.1);
    }
    
    .video-menu-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
    }
    
    .video-menu-modal.active {
        display: flex;
    }
    
    .video-menu-content {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 20px;
        min-width: 200px;
        text-align: center;
    }
    
    .video-menu-button {
        display: block;
        width: 100%;
        padding: 12px 16px;
        margin-bottom: 8px;
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        color: white;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .video-menu-button:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: #5ccc45;
    }
    
    .video-menu-button.delete {
        border-color: #dc3545;
        color: #dc3545;
    }
    
    .video-menu-button.delete:hover {
        background: rgba(220, 53, 69, 0.1);
    }
    
    .video-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    display: none; /* or block when active */
    background-color: rgba(0, 0, 0, 1);
    overflow: hidden;
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;

    /* Add Flexbox centering */
    display: flex;
    justify-content: center;
    align-items: center;
}
    
    .video-modal.active {
        display: flex;
    }
    
    .video-modal-container {
        width: 100%;
        height: 100vh;
        overflow-y: auto;
        background-color: rgba(0, 0, 0, 1);
        scroll-snap-type: y mandatory;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Remove all tap highlights and selection from modal */
    .video-modal *, .video-modal *:before, .video-modal *:after {
        -webkit-tap-highlight-color: transparent !important;
        -webkit-touch-callout: none !important;
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }
    
    .video-modal *:focus {
        outline: none !important;
        box-shadow: none !important;
    }
    
    .video-modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
        background-color: rgba(0, 0, 0, 1);
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease;
        z-index: 10001;
        backdrop-filter: blur(10px);
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-modal-close:hover {
        background: rgba(0, 0, 0, 0.9);
    }
    
    /* Modal video item - exactly like feed */
    .video-modal .video-item {
        position: relative;
        width: 100%;
        height: 100vh;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        scroll-snap-align: start;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-modal .video-player {
        width: 100%;
        height: 100%;
        object-fit: cover;
        background: #000;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-modal .play-pause-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80px;
        height: 80px;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        cursor: pointer;
        z-index: 100;
        transition: all 0.3s ease;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-modal .video-item.paused .play-pause-overlay {
        display: flex;
    }
    
    /* Video actions - positioned like home feed */
    .video-modal .video-actions {
        position: absolute;
        right: 12px;
        top: 60%;
        transform: translateY(-50%);
        display: flex;
        flex-direction: column;
        gap: 20px;
        align-items: center;
        z-index: 200;
    }
    
    .video-modal .action-group {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .video-modal .action-btn {
        width: 48px;
        height: 48px;
        border: none;
        background: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        border-radius: 50%;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    .video-modal .action-btn:hover {
        transform: scale(1.2);
    }
    
    .video-modal .action-count {
        font-size: 12px;
        color: white;
        font-weight: 600;
        text-align: center;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid green;
        object-fit: cover;
        background: #333;
    }
    
    .user-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid white;
        background: #5ccc45;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: bold;
        color: white;
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    
    .user-avatar:hover {
        border-color: #4CAF50;
    }
    
    
    .video-modal .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid white;
        object-fit: cover;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    /* Video info - positioned like home feed */
    .video-modal .video-info {
        position: absolute;
        bottom: 80px;
        left: 12px;
        right: 80px;
        color: white;
        z-index: 200;
    }
    
    .video-modal .username {
        font-size: 14px;
        font-weight: 600;
        color: white;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    }
    
    .video-modal .video-title {
        font-size: 14px;
        font-weight: 400;
        margin: 4px 0;
        color: white;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        line-height: 1.3;
    }
    
    .video-modal .video-description {
        font-size: 13px;
        opacity: 0.9;
        color: white;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
        line-height: 1.3;
        margin-top: 4px;
    }
    
    /* Duration progress - positioned like home feed */
    .video-modal .video-duration {
        position: absolute;
        bottom: 17px;
        left: 12px;
        right: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 200;
    }
    
    .video-modal .duration-progress {
        flex: 1;
        height: 3px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
        cursor: pointer;
        position: relative;
        padding: 8px 0;
        margin: -8px 0;
    }
    
    .video-modal .duration-progress-bar {
        height: 100%;
        background: #5ccc45;
        border-radius: 2px;
        width: 0%;
        transition: width 0.1s ease;
    }
    
    .video-modal .duration-seek-handle {
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        width: 12px;
        height: 12px;
        background: white;
        border-radius: 50%;
        opacity: 1;
        pointer-events: none;
        z-index: 10;
    }
    
    .video-modal .duration-time {
        color: white;
        font-size: 11px;
        font-weight: 600;
        min-width: 35px;
        text-align: right;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    }
    
    .video-modal-item .video-container {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .video-modal-item .video-player {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #000;
    }
    
    .video-modal-item .play-pause-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80px;
        height: 80px;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        cursor: pointer;
        z-index: 100;
    }
    
    .video-modal-item.paused .play-pause-overlay {
        display: flex;
    }
    
    .video-modal-item .video-duration-slider {
        position: absolute;
        bottom: 100px;
        left: 20px;
        right: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 200;
    }
    
    .video-modal-item .duration-progress {
        flex: 1;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
        cursor: pointer;
    }
    
    .video-modal-item .duration-progress-bar {
        height: 100%;
        background: #5ccc45;
        border-radius: 2px;
        width: 0%;
        transition: width 0.1s ease;
    }
    
    .video-modal-item .duration-time {
        color: white;
        font-size: 12px;
        font-weight: 600;
        min-width: 40px;
    }
    
    .video-modal-item .video-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.8));
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        z-index: 200;
    }
    
    .video-modal-item .video-info {
        flex: 1;
        color: white;
    }
    
    .video-modal-item .video-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 8px;
        color: white;
    }
    
    .video-modal-item .video-description {
        font-size: 14px;
        opacity: 0.9;
        color: white;
    }
    
    .video-modal-item .video-actions {
        display: flex;
        flex-direction: column;
        gap: 20px;
        align-items: center;
    }
    
    .video-modal-item .action-group {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .video-modal-item .action-btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .video-modal-item .action-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }
    
    .video-modal-item .action-count {
        font-size: 12px;
        color: white;
        font-weight: 600;
    }
    
    .video-modal-item .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid green;
        object-fit: cover;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }
    
    .video-modal-item .user-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(92, 204, 69, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: bold;
        color: #5ccc45;
        margin-bottom: 16px;
        border: 3px solid #5ccc45;
        cursor: pointer;
    }
    
    .video-modal-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease;
        z-index: 300;
    }
    
    .video-modal-nav:hover {
        background: rgba(0, 0, 0, 0.8);
    }
    
    .video-modal-nav.prev {
        left: 20px;
    }
    
    .video-modal-nav.next {
        right: 20px;
    }
    
    .delete-confirm-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10002;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
    }
    
    .delete-confirm-modal.active {
        display: flex;
    }
    
    .delete-confirm-content {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        color: white;
    }
    
    .delete-confirm-content h3 {
        margin: 0 0 16px 0;
        color: #dc3545;
        font-size: 20px;
    }
    
    .delete-confirm-content p {
        margin: 0 0 24px 0;
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.5;
    }
    
    .delete-confirm-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }
    
    .btn-cancel, .btn-delete {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-cancel {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-cancel:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    .btn-delete {
        background: #dc3545;
        color: white;
    }
    
    .btn-delete:hover {
        background: #c82333;
    }
    
    /* Mobile responsive styles */
    @media (max-width: 768px) {
        .video-modal-nav {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .video-modal-nav.prev {
            left: 10px;
        }
        
        .video-modal-nav.next {
            right: 10px;
        }
        
        .video-modal-info {
            padding: 15px;
        }
        
        .video-menu-content {
            margin: 20px;
            min-width: auto;
        }
        
        .videos-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
        }
    }
    
    .edit-profile-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
    }
    
    .edit-profile-modal.active {
        display: flex;
    }
    
    .image-upload-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10001;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(4px);
    }
    
    .image-upload-modal.active {
        display: flex;
    }
    
    .image-modal-content {
        background: #1a1a1a;
        border-radius: 16px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        color: white;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .crop-container {
        position: relative;
        width: 100%;
        max-width: 400px;
        margin: 20px auto;
        background: #000;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .crop-image {
        width: 100%;
        height: auto;
        display: block;
    }
    
    .crop-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        pointer-events: none;
    }
    
    .crop-circle {
        position: absolute;
        border: 3px solid #5ccc45;
        border-radius: 50%;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        pointer-events: none;
    }
    
    .upload-area {
        border: 2px dashed rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.3s ease;
        margin: 20px 0;
    }
    
    .upload-area:hover {
        border-color: #5ccc45;
    }
    
    .upload-area.dragover {
        border-color: #5ccc45;
        background: rgba(92, 204, 69, 0.1);
    }
    
    .file-input {
        display: none;
    }
    
    .image-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #5ccc45;
        margin: 20px auto;
        display: block;
    }
    
    .modal-content {
        background: #1a1a1a;
        border-radius: 16px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        color: white;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: white;
    }
    
    .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    
    .close-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: white;
        margin-bottom: 8px;
    }
    
    .form-input, .form-textarea {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        color: white !important;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: #5ccc45;
        box-shadow: 0 0 0 2px rgba(92, 204, 69, 0.2);
        color: white !important;
    }
    
    .form-input::placeholder, .form-textarea::placeholder {
        color: rgba(255, 255, 255, 0.5) !important;
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    
    @media (max-width: 768px) {
        .profile-content {
            padding: 8px 16px 16px 16px;
        }
        
        .profile-stats {
            gap: 24px;
        }
        
        .videos-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
        }
    }
    
    /* Global Toast Styles */
    .toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #dc3545;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1000;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-size: 14px;
        max-width: 400px;
        text-align: center;
    }
    
    .toast.success {
        background: #5ccc45;
    }
    
    .toast.error {
        background: #dc3545;
    }
    /* Avatar styles */
/* Lightbox modal styles */
.video-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8);
    align-items: center; justify-content: center;
    z-index: 10000;
}
.video-modal.active {
    display: flex;
}
#lightboxImage {
    max-width: 90%; max-height: 90%;
    border-radius: 10px;
    cursor: grab;
    transition: transform 0.3s ease;
}

  /* Profile Image Upload */
  #imageUploader {
    display: block;
    margin: 20px auto;
  }

  #profileImage {
    display: block;
    width: 200px;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #4CAF50; /* green border */
    margin: 10px auto;
  }

  /* Crop Container (for cropping overlay) */
  #cropContainer {
    display: none;
    position: relative;
    margin: 20px auto;
  }

  #cropImage {
    max-width: 100%;
    border-radius: 50%; /* optional: show circle overlay */
  }
    </style>
    
    <div class="limey-profile-container">
    <!-- Toast -->
    <div id="toast" class="toast"></div>

    <div class="profile-content">
        <!-- Profile Info -->
        <div class="profile-info">
            <!-- Avatar -->
                <div style="position: relative; display: inline-block;">
                    <?php if ($profile && $profile->avatar_url): ?>
                        <img src="<?php echo esc_url($profile->avatar_url); ?>" alt="Profile" class="profile-avatar" <?php echo $is_own_profile ? 'onclick="openProfileImageModal()"' : ''; ?>>
                    <?php else: ?>
                        <div class="profile-avatar-placeholder" <?php echo $is_own_profile ? 'onclick="openProfileImageModal()"' : ''; ?>>
                            <?php echo strtoupper(substr($profile->username ?? 'U', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                <!-- Pencil icon -->
                <?php if ($is_own_profile): ?>
                    <div class="avatar-edit-icon" onclick="openProfileImageEditor()">✏️</div>
                <?php endif; ?>
            </div>
            <!-- Lightbox Modal -->
            <div id="avatarLightbox" class="video-modal">
                <button class="video-modal-close" color="white" onclick="closeAvatarLightbox()">✖️</button>
                <div class="video-modal-container" style="display:flex; justify-content:center; align-items:center;">
        <img id="lightboxImage" src="" alt="Profile" style="max-width: 90%; max-height: 90%; border-radius: 10px; cursor: grab;">
    </div>
</div>
            <!-- Name and Username -->
            <h2 class="profile-name"><?php echo esc_html($profile->display_name ?: $profile->username ?: 'User'); ?></h2>
            <p class="profile-username">@<?php echo esc_html($profile->username ?: 'user'); ?></p>
            <!-- Bio -->
            <?php if ($profile && $profile->bio): ?>
                <p class="profile-bio"><?php echo esc_html($profile->bio); ?></p>
            <?php endif; ?>
            <!-- TrinECredits (only for own profile) -->
            <?php if ($is_own_profile): ?>
                <div class="trini-credits">
                    <span style="font-size: 14px; color: rgba(255, 255, 255, 0.7);">TrinECredits:</span>
                    <span class="credits-badge">TT$<?php echo number_format($profile->trini_credits ?? 0, 2); ?></span>
                    <button class="btn-wallet" onclick="openWalletModal()">💰 Wallet</button>
                </div>
                <!-- Profile Buttons -->
                <div class="profile-buttons">
                    <button class="btn-profile btn-outline" onclick="openEditProfileModal()">Edit</button>
                    <a href="/boost" class="btn-profile btn-primary">Boost</a>
                </div>
            <?php else: ?>
                <!-- Follow/Message buttons for other users -->
                <div class="profile-buttons">
                    <button class="btn-profile btn-primary" onclick="toggleFollowUser('<?php echo esc_js($profile->user_id); ?>')">Follow</button>
                    <button class="btn-profile btn-outline" onclick="openMessageModal('<?php echo esc_js($profile->username); ?>')">Message</button>
                </div>
            <?php endif; ?>
            <!-- Stats -->
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($videos); ?></span>
                    <span class="stat-label">Videos</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Following</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Followers</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($profile->likes_received ?? 0); ?></span>
                    <span class="stat-label">Likes</span>
                </div>
            </div>
        </div>
            
            <!-- Tabs -->
            <div class="profile-tabs">
                <div class="tab-list">
                    <?php if ($is_own_profile && $is_admin): ?>
                        <button class="tab-button active" onclick="switchTab('push')">🔔 Push</button>
                        <button class="tab-button" onclick="switchTab('sent')">📤 Sent</button>
                        <button class="tab-button" onclick="switchTab('ads')">📈 My Ads</button>
                    <?php elseif ($is_own_profile): ?>
                        <button class="tab-button active" onclick="switchTab('videos')">🎬 Videos</button>
                        <button class="tab-button" onclick="switchTab('saved')">🔖 Saved</button>
                        <button class="tab-button" onclick="switchTab('ads')">📈 My Ads</button>
                    <?php else: ?>
                        <!-- Other user's profile - only show videos -->
                        <button class="tab-button active" onclick="switchTab('videos')">🎬 Videos</button>
                    <?php endif; ?>
                </div>
                
                <!-- Videos Tab -->
                <div class="tab-content <?php echo (!$is_own_profile || !$is_admin) ? 'active' : ''; ?>" id="videos-tab">
                    <?php if (!empty($videos)): ?>
                        <div class="videos-grid">
                            <?php foreach ($videos as $video): ?>
                                <div class="video-thumbnail" onclick="openVideoModal('<?php echo esc_js($video->id); ?>', 'videos')">
                                    <video preload="metadata" muted>
                                        <source src="<?php echo esc_url($video->video_url); ?>#t=0.1" type="video/mp4">
                                    </video>
                                    <div class="video-overlay">
                                        <div style="font-size: 11px; opacity: 0.8;"><?php echo number_format($video->view_count ?? 0); ?> views</div>
                                    </div>
                                    <?php if ($is_own_profile): ?>
                                    <div class="video-menu" onclick="event.stopPropagation(); showVideoMenu('<?php echo esc_js($video->id); ?>', '<?php echo esc_js($video->title); ?>', '<?php echo esc_js($video->video_url); ?>')">
                                        <span>⋮</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                            <div style="font-size: 48px; margin-bottom: 16px;">🎬</div>
                            <p>No videos yet</p>
                            <a href="/upload-video" class="btn-profile btn-primary" style="margin-top: 16px;">Upload Your First Video</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Saved Tab (only for own profile) -->
                <?php if ($is_own_profile && !$is_admin): ?>
                <div class="tab-content" id="saved-tab">
                    <?php if (!empty($saved_videos)): ?>
                        <div class="videos-grid">
                            <?php foreach ($saved_videos as $video): ?>
                                <div class="video-thumbnail" onclick="openVideoModal('<?php echo esc_js($video->id); ?>', 'saved')">
                                    <video preload="metadata" muted>
                                        <source src="<?php echo esc_url($video->video_url); ?>#t=0.1" type="video/mp4">
                                    </video>
                                    <div class="video-overlay">
                                        <div style="font-size: 11px; opacity: 0.8;"><?php echo number_format($video->view_count ?? 0); ?> views</div>
                                    </div>
                                    <div class="video-creator">
                                        <?php if ($video->profile_avatar): ?>
                                            <img src="<?php echo esc_url($video->profile_avatar); ?>" alt="Creator"  class="profile-avatar1" onclick="event.stopPropagation(); navigateToProfile('<?php echo esc_js($video->profile_username ?: $video->username); ?>')">
                                        <?php else: ?>
                                            <div class="profile-avatar-placeholder1" onclick="event.stopPropagation(); navigateToProfile('<?php echo esc_js($video->profile_username ?: $video->username); ?>')">
                                                <?php echo strtoupper(substr($video->profile_username ?: $video->username ?: 'U', 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔖</div>
                            <p>No saved videos yet</p>
                            <p style="font-size: 14px; opacity: 0.8; margin-top: 8px;">Videos you save will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- My Ads Tab (only for own profile) -->
                <?php if ($is_own_profile): ?>
                <div class="tab-content" id="ads-tab">
                    <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                        <div style="font-size: 48px; margin-bottom: 16px;">📈</div>
                        <p>No sponsored ads yet</p>
                        <a href="/create-ad" class="btn-profile btn-primary" style="margin-top: 16px;">Create Your First Ad</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Push Tab (Admin Only - Own Profile) -->
                <?php if ($is_own_profile && $is_admin): ?>
                <div class="tab-content active" id="push-tab">
                    <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔔</div>
                        <p>Push Notifications</p>
                        <p style="font-size: 14px;">Admin panel for sending notifications</p>
                    </div>
                </div>
                
                <!-- Sent Tab (Admin Only - Own Profile) -->
                <div class="tab-content" id="sent-tab">
                    <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                        <div style="font-size: 48px; margin-bottom: 16px;">📤</div>
                        <p>Sent Notifications</p>
                        <p style="font-size: 14px;">History of sent notifications</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Video Menu Modal -->
    <div class="video-menu-modal" id="videoMenuModal">
        <div class="video-menu-content">
            <button class="video-menu-button" onclick="downloadVideo()">📥 Download</button>
            <button class="video-menu-button delete" onclick="deleteVideo()">🗑️ Delete</button>
            <button class="video-menu-button" onclick="closeVideoMenu()">❌ Cancel</button>
        </div>
    </div>
    
    <!-- Video Modal -->
    <div class="video-modal" id="videoModal">
        <!-- Close button -->
        <button class="video-modal-close" onclick="closeVideoModal()">×</button>
        
        <!-- Video container with scroll support -->
        <div class="video-modal-container" id="modalContainer">
            <div class="video-item modal-video-item" id="modalVideoItem" data-video-id="" onclick="toggleModalVideoPlay()">
                <video class="video-player" id="modalVideo" loop muted preload="metadata" 
                       onloadedmetadata="initModalVideo(this)" 
                       ontimeupdate="updateModalVideoProgress(this)"
                       oncanplay="hideVideoPlaceholder(this)"
                       onloadstart="showVideoPlaceholder(this)"
                       poster="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjU2OCIgdmlld0JveD0iMCAwIDMyMCA1NjgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMyMCIgaGVpZ2h0PSI1NjgiIGZpbGw9IiMwMDAwMDAiLz48L3N2Zz4="
                       playsinline webkit-playsinline>
                    <source id="modalVideoSource" src="" type="video/mp4">
                </video>
                
                <!-- Play overlay (only shows when paused) -->
                <div class="play-pause-overlay" onclick="event.stopPropagation(); toggleModalVideoPlay()">
                    <div class="play-icon" id="modalPlayIcon">▶️</div>
                    <div class="pause-icon" id="modalPauseIcon" style="display: none;">⏸️</div>
                </div>
                
                <!-- Video Actions (right side) -->
                <div class="video-actions">
                    <!-- User Avatar -->
                    <div class="action-group">
                        <img id="modalUserAvatar" src="" alt="User" class="user-avatar" onclick="event.stopPropagation(); navigateToModalProfile()">
                    </div>
                    
                    <!-- Like -->
                    <div class="action-group">
                        <button class="action-btn" id="modalLikeBtn" onclick="event.stopPropagation(); toggleModalLike()">
                            ❤️
                        </button>
                        <span class="action-count" id="modalLikeCount">0</span>
                    </div>
                    
                    <!-- Save -->
                    <div class="action-group">
                        <button class="action-btn" id="modalSaveBtn" onclick="event.stopPropagation(); toggleModalSave()">
                            🔖
                        </button>
                        <span class="action-count" id="modalSaveCount">0</span>
                    </div>
                    
                    <!-- Share -->
                    <div class="action-group">
                        <button class="action-btn" onclick="event.stopPropagation(); shareModalVideo()">
                            📤
                        </button>
                        <span class="action-count" id="modalShareCount">0</span>
                    </div>
                    
                    <!-- Mute Toggle -->
                    <div class="action-group">
                        <button class="action-btn" id="modalMuteBtn" onclick="event.stopPropagation(); toggleModalMute()">
                            🔊
                        </button>
                    </div>
                </div>
                
                <!-- Video Info (bottom left) -->
                <div class="video-info">
                    <div class="video-title" id="modalVideoTitle"></div>
                    <div class="video-description" id="modalVideoDescription"></div>
                </div>
                
                <!-- Duration Progress -->
                <div class="video-duration">
                    <div class="duration-progress" onclick="event.stopPropagation(); seekModalVideo(event)" 
                         onmousedown="event.stopPropagation(); showSeekHandle(event)" 
                         onmousemove="event.stopPropagation(); updateSeekHandle(event)" 
                         onmouseup="event.stopPropagation(); hideSeekHandle()" 
                         ontouchstart="event.stopPropagation(); showSeekHandle(event)" 
                         ontouchmove="event.stopPropagation(); updateSeekHandle(event)" 
                         ontouchend="event.stopPropagation(); hideSeekHandle()">
                        <div class="duration-progress-bar" id="modalProgressBar"></div>
                        <div class="duration-seek-handle" id="modalSeekHandle"></div>
                    </div>
                    <div class="duration-time" id="modalDurationTime">0:00</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="delete-confirm-modal" id="deleteConfirmModal">
        <div class="delete-confirm-content">
            <h3>Delete Video</h3>
            <p>Are you sure you want to delete this video? This action cannot be undone.</p>
            <div class="delete-confirm-buttons">
                <button class="btn-cancel" onclick="closeDeleteConfirm()">No, Cancel</button>
                <button class="btn-delete" onclick="confirmDeleteVideo()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <!-- Profile Image Upload Modal -->
    <div class="image-upload-modal" id="imageUploadModal">
        <div class="image-modal-content">
            <div class="modal-header">
                <h3>Update Profile Picture</h3>
                <button class="close-btn" onclick="closeImageUploadModal()">×</button>
            </div>
            
            <!-- Upload Area -->
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('imageInput').click()">
                <div style="font-size: 48px; margin-bottom: 16px;">📷</div>
                <p>Click to upload or drag and drop</p>
                <p style="font-size: 12px; color: rgba(255, 255, 255, 0.6);">JPG, PNG, GIF up to 5MB</p>
            </div>
            
            <input type="file" id="imageInput" class="file-input" accept="image/*">
            
            <!-- Crop Area -->
            <div id="cropArea" style="display: none;">
                <div class="crop-container">
                    <img id="cropImage" class="crop-image" style="display: none;">
                    <canvas id="cropCanvas" style="display: none;"></canvas>
                </div>
                
                <!-- Crop Controls -->
                <div style="text-align: center; margin: 20px 0;">
                    <p style="font-size: 14px; color: rgba(255, 255, 255, 0.7); margin-bottom: 16px;">
                        Drag to reposition • Scroll to zoom
                    </p>
                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                        <button type="button" class="btn-profile btn-outline" onclick="zoomOut()">−</button>
                        <span style="font-size: 12px; color: rgba(255, 255, 255, 0.6);">Zoom</span>
                        <button type="button" class="btn-profile btn-outline" onclick="zoomIn()">+</button>
                    </div>
                </div>
            </div>
            
            <!-- Preview Area -->
<div id="previewArea">
    <p style="font-size: 14px; color: rgba(255, 255, 255, 0.7); margin-bottom: 16px;">Preview:</p>
    <img id="previewImage" class="image-preview" />
</div>
            
            <!-- Modal Buttons -->
            <div class="modal-buttons">
                <button type="button" class="btn-profile btn-outline" onclick="closeImageUploadModal()">Cancel</button>
                <button type="button" class="btn-profile btn-primary" id="saveImageBtn" onclick="saveProfileImage()" style="display: none;">Save Image</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
<div class="edit-profile-modal" id="editProfileModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button class="close-btn" onclick="closeEditProfileModal()">×</button>
        </div>
        <form id="editProfileForm">
            <div class="form-group">
                <label class="form-label">Display Name</label>
                <input type="text" class="form-input" id="displayName" maxlength="20" 
                    value="<?php echo esc_attr($profile->display_name ?? ''); ?>" 
                    placeholder="Your display name" 
                    oninput="updateDisplayNameCharCount()">
                <div style="text-align: right; font-size: 12px; color: rgba(255, 255, 255, 0.6); margin-top: 4px;">
                    <span id="displayNameCharCount"><?php echo strlen($profile->display_name ?? ''); ?></span>/20 characters
                </div>
            </div> 
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea class="form-textarea" id="bio" placeholder="Tell people about yourself..." maxlength="50" oninput="updateBioCharCount()"><?php echo esc_textarea($profile->bio ?? ''); ?></textarea>
                <div style="text-align: right; font-size: 12px; color: rgba(255, 255, 255, 0.6); margin-top: 4px;">
                    <span id="bioCharCount"><?php echo strlen($profile->bio ?? ''); ?></span>/50 characters
                </div>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-profile btn-outline" onclick="closeEditProfileModal()">Cancel</button>
                <button type="submit" class="btn-profile btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {
    // Initialize display name char count
    updateDisplayNameCharCount();
    // Initialize bio char count
    updateBioCharCount();
});

// Function to update display name character count
function updateDisplayNameCharCount() {
    const input = document.getElementById('displayName');
    const countSpan = document.getElementById('displayNameCharCount');
    const maxChars = 20;

    // Enforce max length
    if (input.value.length > maxChars) {
        input.value = input.value.substring(0, maxChars);
    }

    const remaining = maxChars - input.value.length;
    countSpan.textContent = remaining;
}

function updateBioCharCount() {
    const textarea = document.getElementById('bio');
    const countSpan = document.getElementById('bioCharCount');
    const maxChars = 50;

    // Enforce max length
    if (textarea.value.length > maxChars) {
        textarea.value = textarea.value.substring(0, maxChars);
    }

    // Remaining characters
    const remaining = maxChars - textarea.value.length;
    countSpan.textContent = remaining;
}

// Initialize bio counter on page load
document.addEventListener('DOMContentLoaded', () => {
    updateBioCharCount();
});

// Function to open the profile image editor modal (assuming exists)
function openProfileImageEditor() {
    const modal = document.getElementById('imageUploadModal');
    if (modal) {
        modal.classList.add('active');
        // Initialize or reset image cropping if needed
    } else {
        showToast('Profile image editor modal not found.', true);
    }
}

// Function to close image upload modal
function closeImageUploadModal() {
    document.getElementById('imageUploadModal').classList.remove('active');
    resetImageUpload();
}

// Toast function
function showToast(message, isError = false) {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = isError ? `<span style="margin-right:8px;">❌</span>${message}` : `<span style="margin-right:8px;">🎉</span>${message}`;
    toast.className = 'toast' + (isError ? ' error' : ' success');
    toast.style.display = 'block';
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => { toast.style.display='none'; }, 300);
    }, 3000);
}

// Open avatar in lightbox
function openProfileImageModal() {
    const modal = document.getElementById('avatarLightbox');
    const img = document.getElementById('lightboxImage');
    const avatarImg = document.querySelector('.profile-avatar');
    if (avatarImg) {
        img.src = avatarImg.src;
        modal.classList.add('active');
        enablePinchZoom(img);
    }
}

function closeAvatarLightbox() {
    document.getElementById('avatarLightbox').classList.remove('active');
}

function enablePinchZoom(img) {
    let scale = 1;
    let startDist = 0;

    // Wheel zoom
    img.onwheel = (e) => {
        e.preventDefault();
        const delta = Math.sign(e.deltaY);
        scale += delta * -0.1;
        scale = Math.min(Math.max(1, scale), 4);
        img.style.transform = `scale(${scale})`;
    };

    // Touch pinch zoom
    let startDist2 = 0;
    img.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            startDist2 = getDistance(e.touches[0], e.touches[1]);
        }
    });
    img.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2) {
            const currentDist = getDistance(e.touches[0], e.touches[1]);
            const deltaDist = currentDist - startDist2;
            scale += deltaDist * 0.005;
            scale = Math.min(Math.max(1, scale), 4);
            img.style.transform = `scale(${scale})`;
            startDist2 = currentDist;
        }
    });

    function getDistance(t1, t2) {
        const dx = t2.clientX - t1.clientX;
        const dy = t2.clientY - t1.clientY;
        return Math.hypot(dx, dy);
    }
}

// Switch tabs
function switchTab(tabName) {
    document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
}

// Open and close edit profile modal
function openEditProfileModal() {
    document.getElementById('editProfileModal').classList.add('active');
    updateBioCharCount();
}
function closeEditProfileModal() {
    document.getElementById('editProfileModal').classList.remove('active');
}

// Handle form submission
document.getElementById('editProfileForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const displayName = document.getElementById('displayName').value;
    const bio = document.getElementById('bio').value;

    try {
        const formData = new FormData();
        formData.append('action', 'limey_update_profile');
        formData.append('display_name', displayName);
        formData.append('bio', bio);

        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('Profile updated successfully!', false);
            closeEditProfileModal();
            window.location.reload();
        } else {
            showToast('Error updating profile: ' + (result.data || 'Unknown error'), true);
        }
    } catch (error) {
        showToast('Error updating profile. Please try again.', true);
    }
});
    
    // Image upload variables
    let currentImage = null;
    let cropData = {
        x: 0,
        y: 0,
        scale: 1,
        isDragging: false,
        startX: 0,
        startY: 0
    };
    function openProfileImageModal() {
        const modal = document.getElementById('avatarLightbox');
        const img = document.getElementById('lightboxImage');
        const avatarImg = document.querySelector('.profile-avatar');
        if (avatarImg) {
            img.src = avatarImg.src;
            modal.classList.add('active');
            enablePinchZoom(img);
        }
    }
    function closeAvatarLightbox() {
        document.getElementById('avatarLightbox').classList.remove('active');
    }
    
    // Reset the image upload modal to initial state
function resetImageUpload() {
    document.getElementById('uploadArea').style.display = 'block';
    document.getElementById('cropArea').style.display = 'none';
    document.getElementById('previewArea').style.display = 'none';
    document.getElementById('saveImageBtn').style.display = 'none';
    document.getElementById('cropImage').style.display = 'none';
    currentImage = null;
    cropData = { x: 0, y: 0, scale: 1, isDragging: false, startX: 0, startY: 0 };
}

// Handle file input change
document.getElementById('imageInput').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        if (file.type.startsWith('image/')) {
            // Valid image file, process for cropping
            handleImageFile(file);
        } else {
            alert('Please select a valid image file (JPG, PNG, GIF).');
            this.value = ''; // Reset input
            document.getElementById('cropArea').style.display = 'none';
            document.getElementById('previewArea').style.display = 'none';
        }
    }
});

// Handle drag and drop for images
const uploadArea = document.getElementById('uploadArea');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});
uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
});
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        handleImageFile(file);
    }
});

// Process selected image file
function handleImageFile(file) {
    if (file.size > 5 * 1024 * 1024) {
        showToast('File size must be less than 5MB', true);
        return;
    }
    if (!file.type.startsWith('image/')) {
        showToast('Please select an image file', true);
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        currentImage = e.target.result; // Store the image data URL
        showCropArea(currentImage); // Load into crop area
    };
    reader.readAsDataURL(file);
}

// Show crop area with the selected image
function showCropArea(imageUrl) {
    document.getElementById('uploadArea').style.display = 'none';
    document.getElementById('cropArea').style.display = 'block';
    document.getElementById('saveImageBtn').style.display = 'inline-flex';

    const cropImage = document.getElementById('cropImage');
    cropImage.src = imageUrl; // Load the selected image into crop UI
    cropImage.style.display = 'block';

    initializeCrop(); // Setup cropping interactions
}

// Initialize cropping interactions
function initializeCrop() {
    const cropImage = document.getElementById('cropImage');
    const container = cropImage.parentElement;

    cropImage.onload = function() {
        cropData = { x: 0, y: 0, scale: 1, isDragging: false, startX: 0, startY: 0 };
        updateCropDisplay();
    };

    cropImage.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);

    cropImage.addEventListener('touchstart', startDrag);
    document.addEventListener('touchmove', drag);
    document.addEventListener('touchend', endDrag);

    container.addEventListener('wheel', handleZoom);
}

// Drag handlers for cropping
function startDrag(e) {
    e.preventDefault();
    cropData.isDragging = true;
    const clientX = e.clientX || e.touches[0].clientX;
    const clientY = e.clientY || e.touches[0].clientY;
    cropData.startX = clientX - cropData.x;
    cropData.startY = clientY - cropData.y;
}

function drag(e) {
    if (!cropData.isDragging) return;
    e.preventDefault();
    const clientX = e.clientX || e.touches[0].clientX;
    const clientY = e.clientY || e.touches[0].clientY;
    cropData.x = clientX - cropData.startX;
    cropData.y = clientY - cropData.startY;
    updateCropDisplay();
}

function endDrag() {
    cropData.isDragging = false;
}

// Zoom handlers
function handleZoom(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    cropData.scale = Math.max(0.5, Math.min(3, cropData.scale + delta));
    updateCropDisplay();
}

function zoomIn() {
    cropData.scale = Math.min(3, cropData.scale + 0.2);
    updateCropDisplay();
}

function zoomOut() {
    cropData.scale = Math.max(0.5, cropData.scale - 0.2);
    updateCropDisplay();
}

// Update the crop image transform
function updateCropDisplay() {
    const cropImage = document.getElementById('cropImage');
    cropImage.style.transform = `translate(${cropData.x}px, ${cropData.y}px) scale(${cropData.scale})`;
}

// Save the cropped profile image
async function saveProfileImage() {
    if (!currentImage) return;

    // Setup canvas for cropping
    const canvas = document.getElementById('cropCanvas');
    const ctx = canvas.getContext('2d');
    const cropImage = document.getElementById('cropImage');

    const outputSize = 200;
    canvas.width = outputSize;
    canvas.height = outputSize;

    const img = new Image();
    img.onload = function() {
        const containerRect = cropImage.parentElement.getBoundingClientRect();
        const imageRect = cropImage.getBoundingClientRect();

        const centerX = containerRect.width / 2;
        const centerY = containerRect.height / 2;
        const radius = Math.min(containerRect.width, containerRect.height) / 2 - 20;

        const scaleX = img.naturalWidth / imageRect.width;
        const scaleY = img.naturalHeight / imageRect.height;

        const sourceX = (centerX - imageRect.left + containerRect.left - cropData.x - radius) * scaleX;
        const sourceY = (centerY - imageRect.top + containerRect.top - cropData.y - radius) * scaleY;
        const sourceSize = radius * 2 * scaleX / cropData.scale;

        ctx.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, outputSize, outputSize);

        // Convert the cropped image to Blob and upload
        canvas.toBlob(function(blob) {
            uploadProfileImage(blob);
        }, 'image/jpeg', 0.9);
    };
    img.src = currentImage;
}

// Upload the cropped image blob
async function uploadProfileImage(blob) {
    try {
        const formData = new FormData();
        formData.append('action', 'limey_upload_profile_image');
        formData.append('profile_image', blob, 'profile.jpg');

        const saveBtn = document.getElementById('saveImageBtn');
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;

        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            updateAllProfileImages(result.data.avatar_url);
            closeImageUploadModal();
            showToast('Profile image updated successfully!', false);
        } else {
            showToast('Error uploading image: ' + (result.data || 'Unknown error'), true);
        }
    } catch (error) {
        showToast('Error uploading image. Please try again.', true);
    } finally {
        // Reset button
        const saveBtn = document.getElementById('saveImageBtn');
        saveBtn.textContent = 'Save Image';
        saveBtn.disabled = false;
    }
}
    
    
// Function to update all profile images on the page
function updateAllProfileImages(newAvatarUrl) {
    const profileAvatar = document.querySelector('.profile-avatar');
    if (profileAvatar) {
        profileAvatar.src = newAvatarUrl;
    }
    document.querySelectorAll('.user-avatar').forEach(avatar => {
        avatar.src = newAvatarUrl;
    });
    // Force cache refresh if needed
    const timestamp = new Date().getTime();
    document.querySelectorAll(`img[src*="${newAvatarUrl}"]`).forEach(img => {
        img.src = newAvatarUrl + '?t=' + timestamp;
    });
}
    
    function openWalletModal() {
        showToast('Wallet functionality coming soon!', false);
    }
    
    function toggleFollowUser(userId) {
        showToast('Follow functionality coming soon!', false);
    }
    
    function openMessageModal(username) {
        showToast('Message functionality coming soon!', false);
    }
    
    function updateBioCharCount() {
    const textarea = document.getElementById('bio');
    const countSpan = document.getElementById('bioCharCount');
    const maxChars = 50;

    // Enforce max length
    if (textarea.value.length > maxChars) {
        textarea.value = textarea.value.substring(0, maxChars);
    }

    // Remaining characters
    const remaining = maxChars - textarea.value.length;
    countSpan.textContent = remaining;
}

// Initialize bio counter on page load
document.addEventListener('DOMContentLoaded', () => {
    updateBioCharCount();
});
    
    function openVideo(videoId) {
        // Legacy function - redirect to video feed
        window.location.href = '/feed-2?video=' + videoId;
    }
    
    function navigateToProfile(username) {
        if (username) {
            window.location.href = '/profile/' + username;
        }
    }
    
    let currentModalUsername = null;
    let currentModalUserId = null;
    
    function navigateToModalProfile() {
        // Get current user's UUID from PHP
        const currentUserUuid = '<?php echo esc_js(get_user_meta(get_current_user_id(), "limey_user_uuid", true)); ?>';
        
        if (currentModalUserId === currentUserUuid) {
            // It's the current user's video, go to own profile
            window.location.href = '/profile';
        } else if (currentModalUsername) {
            // It's another user's video, go to their profile
            window.location.href = '/profile/' + currentModalUsername;
        }
    }
    
    // Close modal when clicking outside
    document.getElementById('editProfileModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditProfileModal();
        }
    });
    
    document.getElementById('imageUploadModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeImageUploadModal();
        }
    });
    
    // Video menu and modal functionality
    let currentVideoData = null;
    let currentVideoList = [];
    let currentVideoIndex = 0;
    
    function showVideoMenu(videoId, title, videoUrl) {
        currentVideoData = { id: videoId, title: title, url: videoUrl };
        document.getElementById('videoMenuModal').classList.add('active');
    }
    
    function closeVideoMenu() {
        document.getElementById('videoMenuModal').classList.remove('active');
        // Don't clear currentVideoData here as it might be needed for delete
    }
    
    function downloadVideo() {
        if (currentVideoData) {
            const link = document.createElement('a');
            link.href = currentVideoData.url;
            link.download = currentVideoData.title + '.mp4';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast('Download started!', false);
        }
        closeVideoMenu();
    }
    
    function deleteVideo() {
        if (currentVideoData) {
            document.getElementById('deleteConfirmModal').classList.add('active');
            closeVideoMenu(); // Close the menu but keep currentVideoData
        } else {
            closeVideoMenu();
        }
    }
    
    function closeDeleteConfirm() {
        document.getElementById('deleteConfirmModal').classList.remove('active');
        currentVideoData = null; // Clear it when delete modal closes
    }
    
    async function confirmDeleteVideo() {
        console.log('confirmDeleteVideo called, currentVideoData:', currentVideoData);
        
        if (!currentVideoData) {
            showToast('No video selected for deletion', true);
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'limey_delete_video');
            formData.append('video_id', currentVideoData.id);
            
            console.log('Sending delete request for video ID:', currentVideoData.id);
            
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('Delete response:', result);
            
            if (result.success) {
                showToast('Video deleted successfully!', false);
                closeDeleteConfirm();
                // Reload the page to update the video grid
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast('Error deleting video: ' + (result.data || 'Unknown error'), true);
                closeDeleteConfirm();
            }
        } catch (error) {
            console.error('Error deleting video:', error);
            showToast('Error deleting video. Please try again.', true);
            closeDeleteConfirm();
        }
    }
    
    async function openVideoModal(videoId, tabType) {
        try {
            // Get video data from server
            const formData = new FormData();
            formData.append('action', 'limey_get_video_data');
            formData.append('video_id', videoId);
            formData.append('tab_type', tabType);
            
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success && result.data.videos) {
                currentVideoList = result.data.videos;
                currentVideoIndex = result.data.currentIndex || 0;
                
                loadVideoInModal(currentVideoIndex);
                document.getElementById('videoModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                showToast('Error loading video', true);
            }
        } catch (error) {
            console.error('Error opening video modal:', error);
            showToast('Error loading video', true);
        }
    }
    
    function closeVideoModal() {
        document.getElementById('videoModal').classList.remove('active');
        document.body.style.overflow = '';
        
        // Pause the video
        const modalVideo = document.getElementById('modalVideo');
        modalVideo.pause();
        modalVideo.currentTime = 0;
    }
    
    function toggleModalVideoPlay() {
        const modalVideo = document.getElementById('modalVideo');
        const modalVideoItem = document.getElementById('modalVideoItem');
        
        console.log('Toggle video play, paused:', modalVideo.paused);
        
        if (modalVideo.paused) {
            modalVideo.play().then(() => {
                modalVideoItem.classList.remove('paused');
                console.log('Video playing');
            }).catch(e => {
                console.error('Play failed:', e);
                modalVideoItem.classList.add('paused');
            });
        } else {
            modalVideo.pause();
            modalVideoItem.classList.add('paused');
            console.log('Video paused');
        }
    }
    
    function initModalVideo(video) {
        // Initialize video when metadata is loaded
        video.muted = false; // Unmute for modal
        
        // Update mute button to reflect initial state
        const muteBtn = document.getElementById('modalMuteBtn');
        if (muteBtn) {
            muteBtn.textContent = video.muted ? '🔇' : '🔊';
        }
        
        console.log('Modal video initialized, muted:', video.muted);
    }
    
    function updateModalVideoProgress(video) {
        const progressBar = document.getElementById('modalProgressBar');
        const durationTime = document.getElementById('modalDurationTime');
        
        if (progressBar && video.duration) {
            const progress = (video.currentTime / video.duration) * 100;
            progressBar.style.width = progress + '%';
            
            // Update seek handle position if not dragging
            if (!isDragging) {
                updateSeekHandlePosition(video.currentTime / video.duration);
            }
        }
        
        if (durationTime && video.duration) {
            const minutes = Math.floor(video.currentTime / 60);
            const seconds = Math.floor(video.currentTime % 60);
            durationTime.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
    }
    
    function seekModalVideo(event) {
        event.stopPropagation();
        const modalVideo = document.getElementById('modalVideo');
        const progressContainer = event.currentTarget;
        const rect = progressContainer.getBoundingClientRect();
        const clickX = event.clientX || (event.touches && event.touches[0].clientX);
        const percentage = (clickX - rect.left) / rect.width;
        
        if (modalVideo.duration) {
            modalVideo.currentTime = Math.max(0, Math.min(percentage * modalVideo.duration, modalVideo.duration));
            updateSeekHandlePosition(percentage);
        }
    }
    
    let seekHandleTimeout = null;
    let isDragging = false;
    
    function showSeekHandle(event) {
        event.stopPropagation();
        isDragging = true;
    }
    
    function updateSeekHandle(event) {
        if (!isDragging) return;
        event.stopPropagation();
        
        const modalVideo = document.getElementById('modalVideo');
        const progressContainer = event.currentTarget;
        const rect = progressContainer.getBoundingClientRect();
        const clientX = event.clientX || (event.touches && event.touches[0].clientX);
        const percentage = Math.max(0, Math.min((clientX - rect.left) / rect.width, 1));
        
        updateSeekHandlePosition(percentage);
        
        if (modalVideo.duration) {
            modalVideo.currentTime = percentage * modalVideo.duration;
        }
    }
    
    function hideSeekHandle() {
        isDragging = false;
    }
    
    function updateSeekHandlePosition(percentage) {
        const handle = document.getElementById('modalSeekHandle');
        if (handle) {
            handle.style.left = (percentage * 100) + '%';
        }
    }
    
    async function toggleModalLike() {
        const videoId = document.getElementById('modalVideoItem').getAttribute('data-video-id');
        const modalLikeBtn = document.getElementById('modalLikeBtn');
        
        if (videoId && modalLikeBtn) {
            // Prevent multiple clicks
            if (modalLikeBtn.disabled) return;
            modalLikeBtn.disabled = true;
            
            try {
                // Use the global toggleLike function
                const formData = new FormData();
                formData.append('action', 'limey_toggle_like');
                formData.append('video_id', videoId);
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update modal count and button state
                    document.getElementById('modalLikeCount').textContent = formatCount(result.data.count);
                    
                    if (result.data.liked) {
                        likedVideos.add(videoId);
                        modalLikeBtn.style.color = '#ff3040';
                    } else {
                        likedVideos.delete(videoId);
                        modalLikeBtn.style.color = 'white';
                    }
                }
            } catch (error) {
                console.error('Error toggling like in modal:', error);
            } finally {
                modalLikeBtn.disabled = false;
            }
        }
    }
    
    async function toggleModalSave() {
        const videoId = document.getElementById('modalVideoItem').getAttribute('data-video-id');
        const modalSaveBtn = document.getElementById('modalSaveBtn');
        
        if (videoId && modalSaveBtn) {
            // Prevent multiple clicks
            if (modalSaveBtn.disabled) return;
            modalSaveBtn.disabled = true;
            
            try {
                // Use the global toggleSave function
                const formData = new FormData();
                formData.append('action', 'limey_toggle_save');
                formData.append('video_id', videoId);
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update modal count and button state
                    document.getElementById('modalSaveCount').textContent = formatCount(result.data.count);
                    
                    if (result.data.saved) {
                        savedVideos.add(videoId);
                        modalSaveBtn.style.color = '#ffd700';
                    } else {
                        savedVideos.delete(videoId);
                        modalSaveBtn.style.color = 'white';
                    }
                }
            } catch (error) {
                console.error('Error toggling save in modal:', error);
            } finally {
                modalSaveBtn.disabled = false;
            }
        }
    }
    
    function shareModalVideo() {
        const videoId = document.getElementById('modalVideoItem').getAttribute('data-video-id');
        if (videoId && window.shareVideo) {
            window.shareVideo(videoId);
        }
    }
    
    function toggleModalMute() {
        const modalVideo = document.getElementById('modalVideo');
        const muteBtn = document.getElementById('modalMuteBtn');
        
        if (modalVideo && muteBtn) {
            modalVideo.muted = !modalVideo.muted;
            
            // Update button icon with smooth transition
            muteBtn.style.transform = 'scale(1.1)';
            muteBtn.textContent = modalVideo.muted ? '🔇' : '🔊';
            setTimeout(() => {
                muteBtn.style.transform = 'scale(1)';
            }, 150);
        }
    }
    
    // WebView optimization functions
    function showVideoPlaceholder(video) {
        const container = video.closest('.video-container');
        const placeholder = container?.querySelector('.video-loading-placeholder');
        if (placeholder) {
            placeholder.style.display = 'flex';
        }
    }
    
    function hideVideoPlaceholder(video) {
        const container = video.closest('.video-container');
        const placeholder = container?.querySelector('.video-loading-placeholder');
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }
    
    function formatCount(count) {
        if (count >= 1000000) {
            return (count / 1000000).toFixed(1) + 'M';
        } else if (count >= 1000) {
            return (count / 1000).toFixed(1) + 'K';
        }
        return count.toString();
    }
    
    function loadVideoInModal(index) {
        if (index < 0 || index >= currentVideoList.length) return;
        
        const video = currentVideoList[index];
        const modalVideo = document.getElementById('modalVideo');
        const modalVideoSource = document.getElementById('modalVideoSource');
        const modalVideoItem = document.getElementById('modalVideoItem');
        
        console.log('Loading video in modal:', video);
        
        // Update video source
        modalVideoSource.src = video.video_url;
        modalVideo.load();
        
        // Update video item data
        modalVideoItem.setAttribute('data-video-id', video.id);
        
        // Auto-play when loaded
        modalVideo.addEventListener('loadeddata', function() {
            modalVideo.play().catch(e => {
                console.log('Auto-play failed, user interaction required');
                modalVideoItem.classList.add('paused');
            });
        }, { once: true });
        
        // Update video info
        const modalVideoTitle = document.getElementById('modalVideoTitle');
        const modalVideoDescription = document.getElementById('modalVideoDescription');
        const modalUserAvatar = document.getElementById('modalUserAvatar');
        
        if (modalVideoTitle) modalVideoTitle.textContent = video.title || '';
        if (modalVideoDescription) modalVideoDescription.textContent = video.description || '';
        
        // Store username and user ID for profile navigation
        currentModalUsername = video.username || video.profile_username || null;
        currentModalUserId = video.user_id || null;
        
        // Fix profile image loading
        if (modalUserAvatar) {
            if (video.avatar_url && video.avatar_url !== '' && !video.avatar_url.includes('default-avatar')) {
                modalUserAvatar.src = video.avatar_url;
                modalUserAvatar.style.display = 'block';
                modalUserAvatar.onerror = function() {
                    // Hide if image fails to load
                    this.style.display = 'none';
                };
            } else {
                // Hide avatar if no valid image
                modalUserAvatar.style.display = 'none';
            }
        }
        
        // Update action counts and sync with current state
        updateModalActionCounts(video.id);
        
        // No navigation buttons needed for swipe navigation
        
        currentVideoIndex = index;
    }
    
    function previousVideo() {
        if (currentVideoIndex > 0) {
            loadVideoInModal(currentVideoIndex - 1);
        }
    }
    
    function nextVideo() {
        if (currentVideoIndex < currentVideoList.length - 1) {
            loadVideoInModal(currentVideoIndex + 1);
        }
    }
    
    async function updateModalActionCounts(videoId) {
        try {
            // Get current counts from server
            const formData = new FormData();
            formData.append('action', 'limey_get_video_counts');
            formData.append('video_id', videoId);
            
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const modalLikeCount = document.getElementById('modalLikeCount');
                const modalSaveCount = document.getElementById('modalSaveCount');
                const modalShareCount = document.getElementById('modalShareCount');
                const modalLikeBtn = document.getElementById('modalLikeBtn');
                const modalSaveBtn = document.getElementById('modalSaveBtn');
                
                if (modalLikeCount) modalLikeCount.textContent = formatCount(result.data.like_count || 0);
                if (modalSaveCount) modalSaveCount.textContent = formatCount(result.data.save_count || 0);
                if (modalShareCount) modalShareCount.textContent = formatCount(result.data.share_count || 0);
                
                // Update button states based on user's interactions
                if (modalLikeBtn) {
                    modalLikeBtn.style.color = likedVideos.has(videoId) ? '#ff3040' : 'white';
                }
                if (modalSaveBtn) {
                    modalSaveBtn.style.color = savedVideos.has(videoId) ? '#ffd700' : 'white';
                }
            }
        } catch (error) {
            console.error('Error updating modal action counts:', error);
        }
    }
    
    // Close modals when clicking outside
    document.getElementById('videoMenuModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideoMenu();
        }
    });
    
    document.getElementById('videoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideoModal();
        }
    });
    
    document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteConfirm();
        }
    });
    
    // Keyboard navigation for video modal
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('videoModal').classList.contains('active')) {
            if (e.key === 'Escape') {
                closeVideoModal();
            } else if (e.key === 'ArrowUp') {
                previousVideo();
            } else if (e.key === 'ArrowDown') {
                nextVideo();
            }
        }
    });
    
    // Touch/swipe support for mobile - vertical swipes like home feed
    let touchStartY = 0;
    let touchEndY = 0;
    
    document.getElementById('videoModal').addEventListener('touchstart', function(e) {
        touchStartY = e.changedTouches[0].screenY;
    });
    
    document.getElementById('videoModal').addEventListener('touchend', function(e) {
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartY - touchEndY;
        
        if (Math.abs(diff) > swipeThreshold) {
            if (diff > 0) {
                // Swiped up - next video
                nextVideo();
            } else {
                // Swiped down - previous video
                previousVideo();
            }
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('limey_profile_page', 'limey_profile_page_shortcode');

// Handle profile update
function limey_handle_profile_update() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    
    $display_name = sanitize_text_field($_POST['display_name']);
    $bio = sanitize_textarea_field($_POST['bio']);
    
    $result = $wpdb->update(
        "{$wpdb->prefix}limey_profiles",
        [
            'display_name' => $display_name,
            'bio' => $bio,
            'updated_at' => current_time('mysql', 1)
        ],
        ['user_id' => $current_user_uuid],
        ['%s', '%s', '%s'],
        ['%s']
    );
    
    if ($result !== false) {
        wp_send_json_success('Profile updated successfully');
    } else {
        wp_send_json_error('Failed to update profile');
    }
}

add_action('wp_ajax_limey_update_profile', 'limey_handle_profile_update');

// Handle profile image upload
function limey_handle_profile_image_upload() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    if (!isset($_FILES['profile_image'])) {
        wp_send_json_error('No image file provided');
        return;
    }
    
    $file = $_FILES['profile_image'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('File upload error');
        return;
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error('File too large. Maximum size is 5MB.');
        return;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = wp_check_filetype($file['name']);
    
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error('Invalid file type. Please upload JPG, PNG, GIF, or WebP.');
        return;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $limey_dir = $upload_dir['basedir'] . '/limey_avatars';
    
    if (!file_exists($limey_dir)) {
        wp_mkdir_p($limey_dir);
    }
    
    // Generate unique filename
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $current_user_uuid . '_' . time() . '.' . $extension;
    $file_path = $limey_dir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wp_send_json_error('Failed to save uploaded file');
        return;
    }
    
    // Generate URL
    $avatar_url = $upload_dir['baseurl'] . '/limey_avatars/' . $filename;
    
    // Update database
    global $wpdb;
    $result = $wpdb->update(
        "{$wpdb->prefix}limey_profiles",
        ['avatar_url' => $avatar_url],
        ['user_id' => $current_user_uuid],
        ['%s'],
        ['%s']
    );
    
    if ($result !== false) {
        // Also update WordPress user meta for consistency
        update_user_meta($current_user->ID, 'limey_avatar_url', $avatar_url);
        
        wp_send_json_success([
            'avatar_url' => $avatar_url,
            'message' => 'Profile image updated successfully'
        ]);
    } else {
        wp_send_json_error('Failed to update profile in database');
    }
}

add_action('wp_ajax_limey_upload_profile_image', 'limey_handle_profile_image_upload');

// Add rewrite rules for profile URLs
function limey_add_rewrite_rules() {
    add_rewrite_rule('^profile/([^/]+)/?$', 'index.php?pagename=profile&username=$matches[1]', 'top');
}
add_action('init', 'limey_add_rewrite_rules');

// Add query vars
function limey_add_query_vars($vars) {
    $vars[] = 'username';
    return $vars;
}
add_filter('query_vars', 'limey_add_query_vars');

// Handle profile page with username parameter
function limey_handle_profile_page($content) {
    if (is_page('profile')) {
        $username = get_query_var('username');
        if ($username) {
            return limey_profile_page_shortcode(['username' => $username]);
        }
    }
    return $content;
}
add_filter('the_content', 'limey_handle_profile_page');

// Video feed shortcode
function limey_video_feed_shortcode() {
    global $wpdb;
    
    $videos = $wpdb->get_results("
        SELECT v.*, p.display_name, p.is_verified, p.username as profile_username, p.avatar_url as profile_avatar
        FROM {$wpdb->prefix}limey_videos v 
        LEFT JOIN {$wpdb->prefix}limey_profiles p ON v.user_id = p.user_id 
        ORDER BY v.created_at DESC 
        LIMIT 20
    ");
    
    // Update video avatars with latest profile data
    foreach ($videos as $video) {
        if ($video->profile_avatar) {
            $video->avatar_url = $video->profile_avatar;
        }
    }
    

    
    if (empty($videos)) {
        return '<div class="limey-feed-container"><div class="no-videos"><div class="no-videos-icon">🎬</div><h3>No videos yet</h3><p>Be the first to upload a video!</p><a href="/upload-video" class="upload-btn">Upload Your First Video</a></div></div>';
    }
    
    ob_start();
    ?>
    <style>
    * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    *:focus {
        outline: none;
    }
    
    button:focus,
    button:active {
        outline: none;
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        -webkit-tap-highlight-color: transparent;
    }
    
    .limey-feed-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #000;
        overflow-y: auto;
        scroll-snap-type: y mandatory;
        -webkit-overflow-scrolling: touch;
    }
    
    .limey-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 8px 16px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .limey-logo {
        font-size: 24px;
        font-weight: 900;
        color: white;
        letter-spacing: 0.15em;
        filter: drop-shadow(0 0 8px #5ccc45);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .header-btn {
        background: transparent;
        border: none;
        color: white;
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
        font-size: 16px;
    }
    
    .header-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .global-mute-btn {
        position: fixed;
        top: 100px;
        right: 16px;
        z-index: 40;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        transition: background-color 0.2s;
    }
    
    .global-mute-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .video-item {
        position: relative;
        height: 100vh;
        scroll-snap-align: start;
        scroll-snap-stop: always;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .video-top-spacer {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: #000;
        z-index: 10;
    }
    
    .video-container {
        position: absolute;
        top: 70px;
        bottom: 96px;
        left: 0;
        right: 0;
        overflow: hidden;
    }
    
    .video-player {
        width: 100%;
        height: 90%;
        
        background: #000;
        border-radius: 8px;
    }
    
    /* Hide default video controls in WebView */
    .video-player::-webkit-media-controls {
        display: none !important;
    }
    
    .video-player::-webkit-media-controls-panel {
        display: none !important;
    }
    
    .video-player::-webkit-media-controls-play-button {
        display: none !important;
    }
    
    .video-player::-webkit-media-controls-start-playback-button {
        display: none !important;
    }
    
    .video-player::-webkit-media-controls-overlay-play-button {
        display: none !important;
    }
    
    .video-player::-webkit-media-controls-fullscreen-button {
        display: none !important;
    }
    
    /* Additional WebView optimizations */
    .video-player {
        -webkit-appearance: none;
        appearance: none;
    }
    
    /* Custom loading placeholder */
    .video-loading-placeholder {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 90%;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        z-index: 5;
    }
    
    .loading-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top: 3px solid #5ccc45;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .video-separator {
        position: absolute;
        bottom: 96px;
        left: 0;
        right: 0;
        height: 1px;
        background: rgba(255, 255, 255, 0.2);
        z-index: 10;
    }
    
    .video-text-area {
        position: absolute;
        bottom: 96px;
        left: 0;
        right: 0;
        height: 64px;
        background: #000;
        z-index: 10;
    }
    
    .video-bottom-spacer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 64px;
        background: #000;
        z-index: 10;
    }
    
    .video-duration-slider {
        position: absolute;
        bottom: 64px;
        left: 16px;
        right: 16px;
        z-index: 25;
        display: flex;
        align-items: center;
        gap: 8px;
        color: green;
        font-size: 12px;
        font-weight: 600;
    }
    
    .duration-progress {
        flex: 1;
        height: 3px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
        overflow: hidden;
        cursor: pointer;
    }
    
    .duration-progress-bar {
        height: 100%;
        background: #5ccc45;
        width: 0%;
        transition: width 0.1s linear;
        border-radius: 2px;
    }
    
    .duration-time {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.8);
        min-width: 35px;
        text-align: right;
    }
    
    .video-overlay {
        position: absolute;
        bottom: 96px;
        left: 0;
        right: 0;
        padding: 16px;
        color: white;
        z-index: 20;
        display: flex;
        justify-content: space-between;
        align-items: end;
    }
    
    .video-info {
        flex: 1;
        margin-right: 16px;
    }
    
    .video-title {
        color: white;
        font-weight: 600;
        font-size: 16px;
        line-height: 1.3;
        margin-bottom: 8px;
    }
    
    .video-description {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        line-height: 1.4;
    }
    
    .video-actions {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    
    .action-group {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .action-btn {
        background: transparent;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0;
        transition: transform 0.2s;
        font-size: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .action-btn:hover {
        transform: scale(1.1);
    }
    
    .action-btn:active {
        transform: scale(0.95);
    }
    
    .action-count {
        color: white;
        font-size: 12px;
        font-weight: 500;
    }
    
    
    .follow-btn {
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        width: 28px;
        height: 28px;
        background: #dc2626;
        border-radius: 50%;
        border: 2px solid white;
        color: white;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    }
    
    .follow-btn:hover {
        background: #b91c1c;
        transform: translateX(-50%) scale(1.1);
    }
    
    .follow-btn.following {
        background: #16a34a;
    }
    
    .follow-btn.following:hover {
        background: #15803d;
    }
    
    .play-pause-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 25;
        background: rgba(0, 0, 0, 0.7);
        border-radius: 50%;
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        cursor: pointer;
        transition: all 0.3s ease;
        opacity: 0;
        pointer-events: none;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .video-item.paused .play-pause-overlay {
        opacity: 1;
        pointer-events: auto;
    }
    
    .play-pause-overlay:hover {
        background: rgba(0, 0, 0, 0.9);
        transform: translate(-50%, -50%) scale(1.1);
        border-color: rgba(255, 255, 255, 0.5);
    }
    
    .play-pause-overlay:active {
        transform: translate(-50%, -50%) scale(0.95);
    }
    
    .no-videos {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100vh;
        text-align: center;
        color: white;
        padding: 20px;
    }
    
    .no-videos-icon {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.7;
    }
    
    .no-videos h3 {
        font-size: 24px;
        margin-bottom: 8px;
        color: white;
    }
    
    .no-videos p {
        font-size: 16px;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 24px;
    }
    
    .upload-btn {
        background: white;
        color: black;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.2s;
    }
    
    .upload-btn:hover {
        background: rgba(255, 255, 255, 0.9);
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Add top margin to body when progress banner is visible */
    body.has-progress-banner {
        margin-top: 40px;
    }
    
    /* Share Modal Styles */
    .share-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .share-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
    }
    
    .share-modal-content {
        position: relative;
        background: #1a1a1a;
        border-radius: 16px;
        padding: 24px;
        max-width: 400px;
        width: 90%;
        color: white;
    }
    
    .share-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .share-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    
    .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    
    .close-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .share-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 16px;
    }
    
    .share-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 12px;
        padding: 16px 12px;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 12px;
        font-weight: 500;
    }
    
    .share-option:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }
    
    .share-option:active {
        transform: translateY(0);
    }
    
    /* Toast Message Styles */
    .toast-message {
        animation: toastSlideIn 0.3s ease-out;
    }
    
    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.8);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
    }
    
    /* Pulse animation for live button */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .video-overlay {
            padding: 12px;
        }
        
        .video-actions {
            gap: 20px;
        }
        
        .action-btn {
            font-size: 24px;
        }
        
        
        
        .follow-btn {
            width: 20px;
            height: 20px;
            font-size: 12px;
        }
        
        .share-modal-content {
            padding: 20px;
        }
        
        .share-options {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .video-duration-slider {
            bottom: 68px;
            left: 12px;
            right: 12px;
        }
        
        .duration-time {
            font-size: 10px;
            min-width: 30px;
        }
    }
    
    /* Global Toast Styles */
    .toast {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #dc3545;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        z-index: 1000;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-size: 14px;
        max-width: 400px;
        text-align: center;
    }
    
    .toast.success {
        background: #5ccc45;
    }
    
    .toast.error {
        background: #dc3545;
    }
    .limey-glow-outline {
    font-size: 30px; /* Reduced from 72px to 40px */
    font-family: Arial, sans-serif;
    color: #fff; /* Fill color white */
    display: inline-block;
    padding: -10px 20px; /* Reduced padding for less height and space */
    /* Thinner green outline */
    -webkit-text-stroke: 1px #28a745; /* For modern browsers */
    text-stroke: 1px #28a745;

    /* Fallback for browsers without text-stroke */
    text-shadow:
        -1px -1px 0 #28a745,
        1px -1px 0 #28a745,
        -1px 1px 0 #28a745,
        1px 1px 0 #28a745;

    /* Pulsing glow effect with smaller shadows for less height */
    filter: drop-shadow(0 0 10px #28a745)
            drop-shadow(0 0 30px #28a745);
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% {
        filter: drop-shadow(0 0 10px #28a745)
                drop-shadow(0 0 30px #28a745);
    }
    50% {
        filter: drop-shadow(0 0 20px #28a745)
                drop-shadow(0 0 60px #28a745);
    }
}
    </style>
    
    <div class="limey-feed-container">
        <!-- Toast -->
        <div id="toast" class="toast"></div>
    

        <!-- Global Mute Button -->
        <button class="global-mute-btn" onclick="toggleGlobalMute()" id="globalMuteBtn">
            �
        </button>

        <!-- Thin Upload Progress Banner (Fixed at top) -->
        <div id="uploadProgressBanner" style="display: none; position: fixed; top: 102px; left: 0; right: 0; z-index: 1000; background: linear-gradient(135deg, #5ccc45, #4CAF50); color: white; height: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; padding: 0 20px; font-size: 14px; font-weight: 600;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top: 2px solid white; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span id="progressBannerText">Uploading...</span>
                    <div onclick="closeProgressBanner(event)" style="margin-left: 15px; width: 20px; height: 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px;">×</div>
                </div>
            </div>
        </div>

        <!-- Video Feed -->
        <?php foreach ($videos as $video): ?>
            <div class="video-item" data-video-id="<?php echo esc_attr($video->id); ?>" onclick="toggleVideoPlay(this)">
                <!-- Top spacer for header -->
                <div class="video-top-spacer"></div>
                
                <!-- Video container -->
                <div class="video-container">
                    <video class="video-player" loop muted preload="metadata" 
                           onloadedmetadata="initVideo(this)" 
                           ontimeupdate="updateVideoProgress(this)"
                           oncanplay="hideVideoPlaceholder(this)"
                           onloadstart="showVideoPlaceholder(this)"
                           data-video-url="<?php echo esc_attr($video->video_url); ?>"
                           poster="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjU2OCIgdmlld0JveD0iMCAwIDMyMCA1NjgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjMyMCIgaGVpZ2h0PSI1NjgiIGZpbGw9IiMwMDAwMDAiLz48L3N2Zz4="
                           playsinline webkit-playsinline>
                        <source src="<?php echo esc_url($video->video_url); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    
                    <!-- Custom loading placeholder -->
                    <div class="video-loading-placeholder" style="display: none;">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
                
                <!-- Duration Slider -->
                <div class="video-duration-slider">
                    <div class="duration-progress" onclick="seekVideo(event, '<?php echo esc_js($video->id); ?>')">
                        <div class="duration-progress-bar" data-video-id="<?php echo esc_attr($video->id); ?>"></div>
                    </div>
                    <div class="duration-time" data-video-id="<?php echo esc_attr($video->id); ?>">0:00</div>
                </div>

                <!-- Separator line -->
                <div class="video-separator"></div>

                <!-- Text area -->
                <div class="video-text-area"></div>

                <!-- Bottom spacer -->
                <div class="video-bottom-spacer"></div>

                <!-- Video overlay info -->
                <div class="video-overlay">
                    <!-- Left side - Video info -->
                    <div class="video-info">
                        <?php if ($video->title): ?>
                            <div class="video-title"><?php echo esc_html($video->title); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($video->description): ?>
                            <div class="video-description"><?php echo esc_html($video->description); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Right side - Actions -->
                    <div class="video-actions">
                        <!-- Profile -->
                        <div class="action-group">
                            <div style="position: relative;">
                                <img src="<?php echo esc_url($video->profile_avatar ?: $video->avatar_url ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDgiIGhlaWdodD0iNDgiIHZpZXdCb3g9IjAgMCA0OCA0OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjQiIGN5PSIyNCIgcj0iMjEiIGZpbGw9IiM0NDQ0NDQiIHN0cm9rZT0iIzVjY2M0NSIgc3Ryb2tlLXdpZHRoPSIzIi8+CjxwYXRoIGQ9Ik0zMiAzM3YtMmE0IDQgMCAwIDAtNC00aC04YTQgNCAwIDAgMC00IDR2MiIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPGNpcmNsZSBjeD0iMjQiIGN5PSIxOSIgcj0iNCIgc3Ryb2tlPSJ3aGl0ZSIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+'); ?>" 
                                     alt="<?php echo esc_attr($video->profile_username ?: $video->username); ?>" 
                                     class="user-avatar"
                                     onclick="event.stopPropagation(); navigateToProfile('<?php echo esc_js($video->user_id); ?>', '<?php echo esc_js($video->profile_username ?: $video->username); ?>')">
                                <?php 
                                // Only show follow button if this is not the current user's video
                                $current_user_uuid = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
                                if ($current_user_uuid && $current_user_uuid !== $video->user_id): 
                                ?>
                                    <button class="follow-btn" onclick="event.stopPropagation(); toggleFollow('<?php echo esc_js($video->user_id); ?>', this)">
                                        +
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Like -->
                        <div class="action-group">
                            <button class="action-btn" onclick="event.stopPropagation(); toggleLike('<?php echo esc_js($video->id); ?>')">
                                ❤️
                            </button>
                            <span class="action-count"><?php echo number_format($video->like_count ?: 0); ?></span>
                        </div>

                        <!-- Comment -->
                        <div class="action-group">
                            <button class="action-btn" onclick="event.stopPropagation(); openComments('<?php echo esc_js($video->id); ?>')">
                                💬
                            </button>
                            <span class="action-count"><?php echo number_format($video->comment_count ?: 0); ?></span>
                        </div>

                        <!-- Save -->
                        <div class="action-group">
                            <button class="action-btn" onclick="event.stopPropagation(); toggleSave('<?php echo esc_js($video->id); ?>')">
                                🔖
                            </button>
                            <span class="action-count"><?php echo number_format($video->save_count ?: 0); ?></span>
                        </div>

                        <!-- Share -->
                        <div class="action-group">
                            <button class="action-btn" onclick="event.stopPropagation(); shareVideo('<?php echo esc_js($video->id); ?>')">
                                📤
                            </button>
                            <span class="action-count"><?php echo number_format($video->share_count ?: 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    // Check for upload success and show banner
    document.addEventListener('DOMContentLoaded', function() {
        // Check for background upload progress
        const backgroundUpload = localStorage.getItem('limey_background_upload');
        if (backgroundUpload) {
            try {
                const data = JSON.parse(backgroundUpload);
                const timeDiff = Date.now() - data.timestamp;
                
                // Show progress banner if upload is recent (within 10 minutes)
                if (timeDiff < 10 * 60 * 1000) {
                    showUploadProgressBanner(data);
                    // Simulate progress updates (in real implementation, you'd poll the server)
                    simulateProgressUpdates(data);
                }
            } catch (error) {
                console.error('Error parsing background upload data:', error);
                localStorage.removeItem('limey_background_upload');
            }
        }
        
        const uploadSuccess = localStorage.getItem('limey_upload_success');
        if (uploadSuccess) {
            try {
                const data = JSON.parse(uploadSuccess);
                const timeDiff = Date.now() - data.timestamp;
                
                // Show banner if upload was recent (within 5 minutes)
                if (timeDiff < 5 * 60 * 1000) {
                    showUploadSuccessBanner(data);
                }
                
                // Clear the stored data
                localStorage.removeItem('limey_upload_success');
            } catch (error) {
                console.error('Error parsing upload success data:', error);
                localStorage.removeItem('limey_upload_success');
            }
        }
    });
    
    function showUploadSuccessBanner(data) {
        const banner = document.getElementById('uploadSuccessBanner');
        const titleElement = document.getElementById('bannerTitle');
        
        if (banner && titleElement) {
            titleElement.textContent = data.title;
            banner.style.display = 'block';
            
            // Add click handler to go to video
            banner.onclick = function(e) {
                if (e.target.closest('[onclick*="closeBanner"]')) return;
                // For now, just scroll to top of feed - you can enhance this to find the specific video
                window.scrollTo({ top: 0, behavior: 'smooth' });
                closeBanner();
            };
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                if (banner.style.display !== 'none') {
                    banner.style.opacity = '0';
                    banner.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        banner.style.display = 'none';
                    }, 500);
                }
            }, 10000);
        }
    }
    
    function showUploadProgressBanner(data) {
        const banner = document.getElementById('uploadProgressBanner');
        const textElement = document.getElementById('progressBannerText');
        
        if (banner && textElement) {
            textElement.textContent = data.progress || 'Uploading...';
            banner.style.display = 'block';
            document.body.classList.add('has-progress-banner');
        }
    }
    
    function simulateProgressUpdates(data) {
        // Extract current progress percentage
        let currentProgress = 0;
        const progressMatch = data.progress.match(/(\d+)%/);
        if (progressMatch) {
            currentProgress = parseInt(progressMatch[1]);
        }
        
        // Simulate progress updates
        const interval = setInterval(() => {
            currentProgress += Math.random() * 5; // Random increment
            if (currentProgress >= 100) {
                currentProgress = 100;
                clearInterval(interval);
                
                // Show completion and hide banner after delay
                const textElement = document.getElementById('progressBannerText');
                if (textElement) {
                    textElement.textContent = '✅ Upload Complete!';
                }
                
                setTimeout(() => {
                    closeProgressBanner();
                    // Store success data for success banner
                    localStorage.setItem('limey_upload_success', JSON.stringify({
                        title: data.title,
                        timestamp: Date.now()
                    }));
                    // Clear background upload data
                    localStorage.removeItem('limey_background_upload');
                    // Refresh page to show success banner
                    window.location.reload();
                }, 2000);
            } else {
                const textElement = document.getElementById('progressBannerText');
                if (textElement) {
                    textElement.textContent = `Uploading "${data.title}" - ${Math.round(currentProgress)}%`;
                }
            }
        }, 1000);
    }
    
    function closeProgressBanner(event) {
        if (event) event.stopPropagation();
        const banner = document.getElementById('uploadProgressBanner');
        if (banner) {
            banner.style.display = 'none';
            document.body.classList.remove('has-progress-banner');
        }
        // Clear background upload data
        localStorage.removeItem('limey_background_upload');
    }
    
    function closeBanner(event) {
        if (event) event.stopPropagation();
        const banner = document.getElementById('uploadSuccessBanner');
        if (banner) {
            banner.style.opacity = '0';
            banner.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                banner.style.display = 'none';
            }, 300);
        }
    }
    
    // Global variables for video management
    let currentPlayingVideo = null;
    let globalMuted = true;
    let likedVideos = new Set();
    let savedVideos = new Set();
    let userHasInteracted = false; // Track if user has interacted with any video
    
    // Initialize video functionality
    function initVideo(video) {
        video.muted = globalMuted;
        
        // WebView optimizations
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        video.controls = false;
        
        // Hide placeholder when ready
        hideVideoPlaceholder(video);
        
        console.log('Initializing video:', video.dataset.videoUrl);
        
        video.addEventListener('loadedmetadata', function() {
            console.log('Video metadata loaded:', video.dataset.videoUrl, 'Duration:', video.duration);
            hideVideoPlaceholder(video);
        });
        
        video.addEventListener('loadstart', function() {
            console.log('Video load started:', video.dataset.videoUrl);
            showVideoPlaceholder(video);
        });
        
        video.addEventListener('error', function(e) {
            console.error('Video error:', video.dataset.videoUrl, e);
        });
        
        video.addEventListener('canplay', function() {
            console.log('Video can play:', video.dataset.videoUrl);
        });
        
        // Add event listeners for user interaction tracking
        video.addEventListener('play', function() {
            console.log('Video playing:', video.dataset.videoUrl);
            userHasInteracted = true;
            currentPlayingVideo = video;
        });
        
        video.addEventListener('pause', function() {
            console.log('Video paused:', video.dataset.videoUrl);
            if (currentPlayingVideo === video) {
                currentPlayingVideo = null;
            }
        });
    }
    
    // Update video progress (for future features)
    function updateVideoProgress(video) {
        // This can be used for analytics or progress tracking
    }
    
    // Toggle video play/pause
    window.toggleVideoPlay = function(videoItem) {
        const video = videoItem.querySelector('.video-player');
        const playPauseOverlay = videoItem.querySelector('.play-pause-overlay');
        const playIcon = playPauseOverlay?.querySelector('.play-icon');
        const pauseIcon = playPauseOverlay?.querySelector('.pause-icon');
        
        if (!video) return;
        
        // Stop all other videos first
        document.querySelectorAll('.video-player').forEach(v => {
            if (v !== video && !v.paused) {
                v.pause();
                const vItem = v.closest('.video-item');
                if (vItem) {
                    vItem.classList.add('paused');
                    // Update other video icons
                    const otherOverlay = vItem.querySelector('.play-pause-overlay');
                    const otherPlayIcon = otherOverlay?.querySelector('.play-icon');
                    const otherPauseIcon = otherOverlay?.querySelector('.pause-icon');
                    if (otherPlayIcon && otherPauseIcon) {
                        otherPlayIcon.style.display = 'block';
                        otherPauseIcon.style.display = 'none';
                    }
                }
            }
        });
        
        // Toggle current video
        if (video.paused) {
            video.play().then(() => {
                userHasInteracted = true; // Mark that user has interacted
                currentPlayingVideo = video;
                videoItem.classList.remove('paused');
                
                // Update icons
                if (playIcon && pauseIcon) {
                    playIcon.style.display = 'none';
                    pauseIcon.style.display = 'block';
                }
            }).catch(error => {
                console.error('Error playing video:', error);
                // Show play button if autoplay fails
                videoItem.classList.add('paused');
                if (playIcon && pauseIcon) {
                    playIcon.style.display = 'block';
                    pauseIcon.style.display = 'none';
                }
            });
        } else {
            video.pause();
            videoItem.classList.add('paused');
            if (currentPlayingVideo === video) {
                currentPlayingVideo = null;
            }
            
            // Update icons
            if (playIcon && pauseIcon) {
                playIcon.style.display = 'block';
                pauseIcon.style.display = 'none';
            }
        }
    }
    
    // Global mute toggle
    window.toggleGlobalMute = function() {
        globalMuted = !globalMuted;
        const muteBtn = document.getElementById('globalMuteBtn');
        
        // Update all videos immediately
        document.querySelectorAll('.video-player').forEach(video => {
            video.muted = globalMuted;
        });
        
        // Update button icon with smooth transition
        if (muteBtn) {
            muteBtn.style.transform = 'scale(1.1)';
            muteBtn.textContent = globalMuted ? '🔇' : '🔊';
            setTimeout(() => {
                muteBtn.style.transform = 'scale(1)';
            }, 150);
        }
        
        // Show feedback to user
        showToast(globalMuted ? 'Videos muted' : 'Videos unmuted');

    // Share video functionality
    window.shareVideo = function(videoId) {
        const videoItem = document.querySelector(`[data-video-id="${videoId}"]`);
        const videoTitle = videoItem.querySelector('.video-title')?.textContent || 'Check out this video';
        const videoUrl = window.location.origin + window.location.pathname + '?video=' + videoId;
        
        // Create share modal
        const modal = document.createElement('div');
        modal.className = 'share-modal';
        modal.innerHTML = `
            <div class="share-modal-overlay" onclick="closeShareModal()"></div>
            <div class="share-modal-content">
                <div class="share-header">
                    <h3>Share Video</h3>
                    <button onclick="closeShareModal()" class="close-btn">×</button>
                </div>
                <div class="share-options">
                    <button class="share-option" onclick="shareToWhatsApp('${videoUrl}', '${videoTitle}')">
                        <span style="font-size: 24px;">📱</span>
                        <span>WhatsApp</span>
                    </button>
                    <button class="share-option" onclick="shareToFacebook('${videoUrl}')">
                        <span style="font-size: 24px;">📘</span>
                        <span>Facebook</span>
                    </button>
                    <button class="share-option" onclick="shareToTwitter('${videoUrl}', '${videoTitle}')">
                        <span style="font-size: 24px;">🐦</span>
                        <span>Twitter</span>
                    </button>
                    <button class="share-option" onclick="copyLink('${videoUrl}')">
                        <span style="font-size: 24px;">🔗</span>
                        <span>Copy Link</span>
                    </button>
                    <button class="share-option" onclick="downloadVideo('${videoId}')">
                        <span style="font-size: 24px;">💾</span>
                        <span>Download</span>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Update share count
        const shareBtn = document.querySelector(`[onclick*="shareVideo('${videoId}')"]`);
        const countSpan = shareBtn.parentElement.querySelector('.action-count');
        if (countSpan) {
            let currentCount = parseInt(countSpan.textContent.replace(/,/g, '')) || 0;
            countSpan.textContent = formatCount(currentCount + 1);
        }
    }
    
    // Share modal functions
    window.closeShareModal = function() {
        const modal = document.querySelector('.share-modal');
        if (modal) {
            modal.remove();
        }
    }
    
    window.shareToWhatsApp = function(url, title) {
        window.open(`https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`, '_blank');
        closeShareModal();
    }
    
    window.shareToFacebook = function(url) {
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
        closeShareModal();
    }
    
    window.shareToTwitter = function(url, title) {
        window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`, '_blank');
        closeShareModal();
    }
    
    window.copyLink = function(url) {
        navigator.clipboard.writeText(url).then(() => {
            showToast('Link copied to clipboard!');
            closeShareModal();
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast('Link copied to clipboard!');
            closeShareModal();
        });
    }
    
    window.downloadVideo = function(videoId) {
        const videoItem = document.querySelector(`[data-video-id="${videoId}"]`);
        const video = videoItem.querySelector('.video-player source');
        if (video) {
            const link = document.createElement('a');
            link.href = video.src;
            link.download = `limey-video-${videoId}.mp4`;
            link.click();
            showToast('Download started!');
        }
        closeShareModal();
    }
    
    // Toggle follow functionality
    window.toggleFollow = function(userId) {
        const followBtn = document.querySelector(`[onclick*="toggleFollow('${userId}')"]`);
        if (!followBtn) return;
        
        const isFollowing = followBtn.classList.contains('following');
        
        if (isFollowing) {
            followBtn.classList.remove('following');
            followBtn.textContent = '+';
            followBtn.style.background = '#dc2626';
        } else {
            followBtn.classList.add('following');
            followBtn.textContent = '✓';
            followBtn.style.background = '#16a34a';
        }
        
        // Add animation
        followBtn.style.transform = 'scale(1.2)';
        setTimeout(() => {
            followBtn.style.transform = 'scale(1)';
        }, 200);
        
        showToast(isFollowing ? 'Unfollowed' : 'Following!');
    }
    
    // Open comments (placeholder for now)
    window.openComments = function(videoId) {
        showToast('Comments feature coming soon!');
    }
    
    // Utility functions
    function formatCount(count) {
        if (count >= 1000000) {
            return (count / 1000000).toFixed(1) + 'M';
        } else if (count >= 1000) {
            return (count / 1000).toFixed(1) + 'K';
        }
        return count.toString();
    }
    
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.innerHTML = isError ? 
            `<span style="margin-right: 8px;">❌</span>${message}` : 
            `<span style="margin-right: 8px;">🎉</span>${message}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: ${isError ? '#dc3545' : '#5ccc45'};
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            z-index: 10000;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            text-align: center;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Auto-play video when in viewport (only after user interaction)
    function handleVideoIntersection() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('.video-player');
                const videoItem = entry.target;
                const playPauseOverlay = videoItem.querySelector('.play-pause-overlay');
                const playIcon = playPauseOverlay?.querySelector('.play-icon');
                const pauseIcon = playPauseOverlay?.querySelector('.pause-icon');
                
                if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                    // Stop all other videos first
                    document.querySelectorAll('.video-player').forEach(v => {
                        if (v !== video && !v.paused) {
                            v.pause();
                            const vItem = v.closest('.video-item');
                            if (vItem) {
                                vItem.classList.add('paused');
                                // Update other video icons
                                const otherOverlay = vItem.querySelector('.play-pause-overlay');
                                const otherPlayIcon = otherOverlay?.querySelector('.play-icon');
                                const otherPauseIcon = otherOverlay?.querySelector('.pause-icon');
                                if (otherPlayIcon && otherPauseIcon) {
                                    otherPlayIcon.style.display = 'block';
                                    otherPauseIcon.style.display = 'none';
                                }
                            }
                        }
                    });
                    
                    // Only auto-play if user has interacted with a video before
                    if (video && video.paused && userHasInteracted) {
                        video.play().then(() => {
                            currentPlayingVideo = video;
                            videoItem.classList.remove('paused');
                            // Update icons
                            if (playIcon && pauseIcon) {
                                playIcon.style.display = 'none';
                                pauseIcon.style.display = 'block';
                            }
                        }).catch(error => {
                            console.error('Auto-play failed:', error);
                            videoItem.classList.add('paused');
                            if (playIcon && pauseIcon) {
                                playIcon.style.display = 'block';
                                pauseIcon.style.display = 'none';
                            }
                        });
                    } else if (!userHasInteracted) {
                        // Show play button for first video
                        videoItem.classList.add('paused');
                        if (playIcon && pauseIcon) {
                            playIcon.style.display = 'block';
                            pauseIcon.style.display = 'none';
                        }
                    }
                } else if (video && !video.paused) {
                    // Pause videos that are out of view
                    video.pause();
                    videoItem.classList.add('paused');
                    if (currentPlayingVideo === video) {
                        currentPlayingVideo = null;
                    }
                    // Update icons
                    if (playIcon && pauseIcon) {
                        playIcon.style.display = 'block';
                        pauseIcon.style.display = 'none';
                    }
                }
            });
        }, { 
            threshold: 0.5,
            rootMargin: '0px 0px -10% 0px' // Trigger slightly before video is fully in view
        });
        
        document.querySelectorAll('.video-item').forEach(item => {
            observer.observe(item);
        });
    }
    
    // Test that JavaScript is loading
    console.log('Limey video feed JavaScript loaded!');
    
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, checking videos...');
        
        const videoElements = document.querySelectorAll('.video-player');
        const videoItems = document.querySelectorAll('.video-item');
        
        console.log('Found', videoElements.length, 'video elements');
        console.log('Found', videoItems.length, 'video items');
        
        // Set initial mute state for all videos
        videoElements.forEach((video, index) => {
            console.log(`Video ${index}:`, video.dataset.videoUrl);
            video.muted = globalMuted;
        });
        
        // Show play button on first video initially
        const firstVideo = document.querySelector('.video-item');
        if (firstVideo) {
            console.log('Setting first video to paused state');
            firstVideo.classList.add('paused');
        } else {
            console.log('No first video found!');
        }
        
        // Initialize video intersection observer
        handleVideoIntersection();
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.code === 'Space') {
                e.preventDefault();
                const visibleVideo = document.querySelector('.video-item:not(.paused)') || document.querySelector('.video-item');
                if (visibleVideo) {
                    toggleVideoPlay(visibleVideo);
                }
            } else if (e.code === 'KeyM') {
                e.preventDefault();
                toggleGlobalMute();
            }
        });
    });

    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('limey_video_feed', 'limey_video_feed_shortcode');
add_shortcode('limey_settings', 'limey_settings_shortcode');

// Settings page shortcode
function limey_settings_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access settings.</p>';
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $current_user_uuid = get_user_meta($current_user->ID, 'limey_user_uuid', true);
    
    // Get user profile
    $profile = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}limey_profiles WHERE user_id = %s",
        $current_user_uuid
    ));
    
    ob_start();
    ?>
    <style>
    .settings-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 16px;
        background: #000;
        color: white;
        min-height: 100vh;
    }
    
    .settings-header {
        text-align: center;
        margin-bottom: 24px;
        padding: 16px 0;
    }
    
    .settings-header h1 {
        color: #5ccc45;
        font-size: 24px;
        font-weight: 600;
        margin: 0;
    }
    
    .settings-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
    }
    
    .settings-card:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(92, 204, 69, 0.3);
    }
    
    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .card-icon {
        font-size: 24px;
        width: 32px;
        text-align: center;
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 600;
        color: white;
        margin: 0;
    }
    
    .card-description {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 16px;
    }
    
    .settings-field {
        margin-bottom: 16px;
    }
    
    .settings-field label {
        display: block;
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .settings-input {
        width: 100%;
        padding: 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        color: white;
        font-size: 16px;
        box-sizing: border-box;
    }
    
    .settings-input:focus {
        outline: none;
        border-color: #5ccc45;
        background: rgba(255, 255, 255, 0.15);
    }
    
    .settings-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .password-field {
        position: relative;
    }
    
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        cursor: pointer;
        font-size: 18px;
        padding: 4px;
    }
    
    .password-toggle:hover {
        color: white;
    }
    
    .toggle-switch {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
    }
    
    .toggle-switch label {
        margin: 0;
        flex: 1;
    }
    
    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 20px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.3);
        transition: .4s;
        border-radius: 24px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 14px;
        width: 14px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #5ccc45;
    }
    
    input:checked + .slider:before {
        transform: translateX(20px);
    }
    
    .btn-settings {
        width: 100%;
        padding: 12px 24px;
        background: #5ccc45;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-settings:hover {
        background: #4CAF50;
        transform: translateY(-1px);
    }
    
    .btn-settings.btn-danger {
        background: #dc3545;
    }
    
    .btn-settings.btn-danger:hover {
        background: #c82333;
    }
    
    .btn-settings.btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-settings.btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    .countdown-text {
        color: #dc3545;
        font-size: 14px;
        font-weight: 500;
        margin-top: 8px;
        text-align: center;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
    }
    
    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 24px;
        width: 90%;
        max-width: 400px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-header h3 {
        color: white;
        margin: 0;
        font-size: 18px;
    }
    
    .close-btn {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .close-btn:hover {
        color: white;
    }
    
    .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }
    
    .modal-buttons button {
        flex: 1;
    }
    
    /* Toast Messages */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #5ccc45;
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        z-index: 2000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        max-width: 300px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .toast.show {
        transform: translateX(0);
    }
    
    .toast.error {
        background: #dc3545;
    }
    
    .toast.warning {
        background: #ffc107;
        color: #000;
    }
    
    .toast.info {
        background: #17a2b8;
    }
    
    /* Loading states */
    .btn-settings.loading {
        opacity: 0.7;
        cursor: not-allowed;
        position: relative;
    }
    
    .btn-settings.loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        margin: auto;
        border: 2px solid transparent;
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Confirmation Modal */
    .confirmation-modal {
        display: none;
        position: fixed;
        z-index: 1500;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
    }
    
    .confirmation-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .confirmation-content {
        background: #1a1a1a;
        border-radius: 12px;
        padding: 24px;
        width: 90%;
        max-width: 400px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
    }
    
    .confirmation-icon {
        font-size: 48px;
        margin-bottom: 16px;
    }
    
    .confirmation-title {
        color: white;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 12px;
    }
    
    .confirmation-message {
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        margin-bottom: 24px;
        line-height: 1.5;
    }
    
    .confirmation-buttons {
        display: flex;
        gap: 12px;
    }
    
    .confirmation-buttons button {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-confirm {
        background: #dc3545;
        color: white;
    }
    
    .btn-confirm:hover {
        background: #c82333;
    }
    
    .btn-cancel {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-cancel:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .limey-glow-outline {
    display: inline-block;
    font-size: 48px; /* Adjust size as needed */
    color: #fff; /* White text */
    padding: 10px 20px;
    border: 3px solid #28a745; /* Green border */
    border-radius: 10px; /* Rounded corners if desired */
    box-shadow: 0 0 10px #28a745, 0 0 20px #28a745; /* initial glow */
    animation: pulse-glow 2s infinite;
    font-family: Arial, sans-serif; /* or your preferred font */
    text-align: center;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 10px #28a745, 0 0 20px #28a745;
    }
    50% {
        box-shadow: 0 0 20px #28a745, 0 0 40px #28a745;
    }
}
    </style>
    
    <div class="settings-container">
        <div class="settings-header">
            <h1>Settings</h1>
        </div>
        
        <!-- Account Settings Card -->
        <div class="settings-card">
            <div class="card-header">
                <span class="card-icon">👤</span>
                <h2 class="card-title">Account</h2>
            </div>
            <div class="card-description">Manage your account information and security</div>
            
            <div class="settings-field">
                <label>Email Address</label>
                <input type="email" class="settings-input" value="<?php echo esc_attr($current_user->user_email); ?>" disabled>
            </div>
            
            <div class="settings-field">
                <label>Phone Number (Optional)</label>
                <input type="tel" class="settings-input" id="phoneNumber" placeholder="Enter your phone number" 
                       value="<?php echo esc_attr($profile->phone_number ?? ''); ?>"
                       onchange="savePhoneNumber()">
            </div>
            
            <button class="btn-settings btn-secondary" onclick="openPasswordModal()">
                Change Password
            </button>
        </div>
        
        <!-- Privacy Settings Card -->
        <div class="settings-card">
            <div class="card-header">
                <span class="card-icon">🔒</span>
                <h2 class="card-title">Privacy</h2>
            </div>
            <div class="card-description">Control your privacy and notification preferences</div>
            
            <div class="toggle-switch">
                <label>Push Notifications</label>
                <label class="switch">
                    <input type="checkbox" id="notificationsToggle" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        
        <!-- Delete Account Card -->
        <div class="settings-card">
            <div class="card-header">
                <span class="card-icon">⚠️</span>
                <h2 class="card-title">Delete Account</h2>
            </div>
            <div class="card-description">Permanently delete your account and all associated data</div>
            
            <button class="btn-settings btn-danger" id="deleteAccountBtn" onclick="toggleDeleteAccount()">
                Delete Account
            </button>
            <div id="deleteCountdown" class="countdown-text" style="display: none;">
                Account deletion scheduled in <span id="countdownDays">7</span> days
            </div>
        </div>
        
    </div>
    
    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-icon" id="confirmIcon">⚠️</div>
            <div class="confirmation-title" id="confirmTitle">Confirm Action</div>
            <div class="confirmation-message" id="confirmMessage">Are you sure you want to proceed?</div>
            <div class="confirmation-buttons">
                <button class="btn-cancel" onclick="closeConfirmation()">Cancel</button>
                <button class="btn-confirm" id="confirmBtn" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toastContainer"></div>
    
    <!-- Password Change Modal -->
    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Password</h3>
                <button class="close-btn" onclick="closePasswordModal()">×</button>
            </div>
            
            <form id="passwordForm">
                <div class="settings-field">
                    <label>Current Password</label>
                    <div class="password-field">
                        <input type="password" class="settings-input" id="currentPassword" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="settings-field">
                    <label>New Password</label>
                    <div class="password-field">
                        <input type="password" class="settings-input" id="newPassword" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="settings-field">
                    <label>Confirm New Password</label>
                    <div class="password-field">
                        <input type="password" class="settings-input" id="confirmPassword" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-settings btn-secondary" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn-settings">Update Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    // Toast message system
    function showToast(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Remove toast
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => container.removeChild(toast), 300);
        }, duration);
    }
    
    // Confirmation modal system
    let confirmCallback = null;
    
    function showConfirmation(title, message, icon = '⚠️', confirmText = 'Confirm') {
        return new Promise((resolve) => {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmIcon').textContent = icon;
            document.getElementById('confirmBtn').textContent = confirmText;
            document.getElementById('confirmationModal').classList.add('active');
            
            confirmCallback = resolve;
        });
    }
    
    function closeConfirmation() {
        document.getElementById('confirmationModal').classList.remove('active');
        if (confirmCallback) {
            confirmCallback(false);
            confirmCallback = null;
        }
    }
    
    function confirmAction() {
        document.getElementById('confirmationModal').classList.remove('active');
        if (confirmCallback) {
            confirmCallback(true);
            confirmCallback = null;
        }
    }
    
    // Loading state helper
    function setButtonLoading(button, loading = true) {
        if (loading) {
            button.classList.add('loading');
            button.disabled = true;
        } else {
            button.classList.remove('loading');
            button.disabled = false;
        }
    }
    
    // Password visibility toggle
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const toggle = field.nextElementSibling;
        
        if (field.type === 'password') {
            field.type = 'text';
            toggle.textContent = '🙈';
        } else {
            field.type = 'password';
            toggle.textContent = '👁️';
        }
    }
    
    // Password modal functions
    function openPasswordModal() {
        document.getElementById('passwordModal').classList.add('active');
    }
    
    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('active');
        document.getElementById('passwordForm').reset();
    }
    
    // Password form submission
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const submitBtn = this.querySelector('button[type="submit"]');
        
        // Enhanced validation
        if (newPassword !== confirmPassword) {
            showToast('New passwords do not match!', 'error');
            return;
        }
        
        if (newPassword.length < 8) {
            showToast('Password must be at least 8 characters long!', 'error');
            return;
        }
        
        if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
            showToast('Password must contain uppercase, lowercase, and number!', 'error');
            return;
        }
        
        // Show loading state
        setButtonLoading(submitBtn, true);
        
        // Implement password change AJAX call
        const formData = new FormData();
        formData.append('action', 'limey_change_password');
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
        formData.append('nonce', '<?php echo wp_create_nonce('limey_change_password_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Password updated successfully!', 'success');
                closePasswordModal();
            } else {
                showToast(data.data || 'Failed to update password', 'error');
            }
        })
        .catch(error => {
            showToast('Failed to update password. Please try again.', 'error');
        })
        .finally(() => {
            setButtonLoading(submitBtn, false);
        });
    });
    
    // Delete account functionality
    let deleteScheduled = <?php 
        global $wpdb;
        $current_user_uuid = get_user_meta(get_current_user_id(), 'limey_user_uuid', true);
        $deletion = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}limey_account_deletions WHERE user_id = %s",
            $current_user_uuid
        ));
        echo $deletion ? 'true' : 'false';
    ?>;
    
    function toggleDeleteAccount() {
        const btn = document.getElementById('deleteAccountBtn');
        const countdown = document.getElementById('deleteCountdown');
        const daysSpan = document.getElementById('countdownDays');
        
        if (!deleteScheduled) {
            showConfirmModal(
                'Delete Account',
                'Are you sure you want to delete your account? This action cannot be undone and will be processed in 7 days.',
                () => {
                    // Schedule deletion
                    const formData = new FormData();
                    formData.append('action', 'limey_schedule_deletion');
                    formData.append('nonce', '<?php echo wp_create_nonce('limey_schedule_deletion_nonce'); ?>');
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            deleteScheduled = true;
                            btn.textContent = 'Cancel Deletion';
                            btn.classList.remove('btn-danger');
                            btn.classList.add('btn-secondary');
                            countdown.style.display = 'block';
                            updateCountdown();
                            showToast('Account deletion scheduled for 7 days', false);
                        } else {
                            showToast(data.data || 'Failed to schedule deletion', true);
                        }
                    })
                    .catch(error => {
                        showToast('Failed to schedule deletion', true);
                    });
                }
            );
        } else {
            showConfirmModal(
                'Cancel Deletion',
                'Cancel account deletion?',
                () => {
                    // Cancel deletion
                    const formData = new FormData();
                    formData.append('action', 'limey_cancel_deletion');
                    formData.append('nonce', '<?php echo wp_create_nonce('limey_cancel_deletion_nonce'); ?>');
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            deleteScheduled = false;
                            btn.textContent = 'Delete Account';
                            btn.classList.add('btn-danger');
                            btn.classList.remove('btn-secondary');
                            countdown.style.display = 'none';
                            showToast('Account deletion cancelled', false);
                        } else {
                            showToast(data.data || 'Failed to cancel deletion', true);
                        }
                    })
                    .catch(error => {
                        showToast('Failed to cancel deletion', true);
                    });
                }
            );
        }
    }
    
    function updateCountdown() {
        if (!deleteScheduled) return;
        
        const formData = new FormData();
        formData.append('action', 'limey_get_deletion_countdown');
        formData.append('nonce', '<?php echo wp_create_nonce('limey_get_deletion_countdown_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('countdownDays').textContent = data.data.days;
                if (data.data.days <= 0) {
                    showToast('Account deletion is being processed', false);
                }
            }
        });
    }
    
    // Update countdown on page load if deletion is scheduled
    if (deleteScheduled) {
        document.getElementById('deleteAccountBtn').textContent = 'Cancel Deletion';
        document.getElementById('deleteAccountBtn').classList.remove('btn-danger');
        document.getElementById('deleteAccountBtn').classList.add('btn-secondary');
        document.getElementById('deleteCountdown').style.display = 'block';
        updateCountdown();
        setInterval(updateCountdown, 60000); // Update every minute
    }
    
    // Sign out function
    function signOut() {
        showConfirmModal(
            'Sign Out',
            'Are you sure you want to sign out?',
            () => {
                // Use WordPress logout URL instead of custom handling
                window.location.href = '<?php echo wp_logout_url( home_url( '/index' ) ); ?>';
            }
        );
    }
    
    // Custom confirm modal
    function showConfirmModal(title, message, onConfirm) {
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                </div>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 20px;">${message}</p>
                <div class="modal-buttons">
                    <button class="btn-settings btn-secondary" onclick="closeConfirmModal()">No</button>
                    <button class="btn-settings btn-danger" onclick="confirmAction()">Yes</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        window.closeConfirmModal = () => {
            document.body.removeChild(modal);
            delete window.closeConfirmModal;
            delete window.confirmAction;
        };
        
        window.confirmAction = () => {
            onConfirm();
            closeConfirmModal();
        };
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeConfirmModal();
            }
        });
    }
    
    // Toast function (matching the rest of the app)
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${isError ? '#dc3545' : '#5ccc45'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    // Phone number save function
    let phoneTimeout = null;
    function savePhoneNumber() {
        clearTimeout(phoneTimeout);
        phoneTimeout = setTimeout(() => {
            const phoneNumber = document.getElementById('phoneNumber').value;
            
            const formData = new FormData();
            formData.append('action', 'limey_update_phone');
            formData.append('phone_number', phoneNumber);
            formData.append('nonce', '<?php echo wp_create_nonce('limey_update_phone_nonce'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Phone number updated successfully!', 'success');
                } else {
                    showToast(data.data || 'Failed to update phone number', 'error');
                }
            })
            .catch(error => {
                showToast('Failed to update phone number', 'error');
            });
        }, 1000); // Debounce for 1 second
    }
    
    // Notifications toggle
    document.getElementById('notificationsToggle').addEventListener('change', function() {
        const enabled = this.checked;
        
        const formData = new FormData();
        formData.append('action', 'limey_update_notifications');
        formData.append('notifications_enabled', enabled ? '1' : '0');
        formData.append('nonce', '<?php echo wp_create_nonce('limey_update_notifications_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(enabled ? 'Notifications enabled' : 'Notifications disabled', 'success');
            } else {
                showToast(data.data || 'Failed to update notifications', 'error');
                // Revert toggle on failure
                this.checked = !enabled;
            }
        })
        .catch(error => {
            showToast('Failed to update notifications', 'error');
            // Revert toggle on failure
            this.checked = !enabled;
        });
    });
    
    // Close modal when clicking outside
    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePasswordModal();
        }
    });
    
    // Close confirmation modal when clicking outside
    document.getElementById('confirmationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmation();
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
}


// Enqueue the video feed JavaScript
function limey_enqueue_video_scripts() {
    if (is_page('feed-2')) {
        ?>
        <script>
        // Global Toast Function
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            if (!toast) {
                // Create toast if it doesn't exist
                const newToast = document.createElement('div');
                newToast.id = 'toast';
                newToast.className = 'toast';
                document.body.appendChild(newToast);
            }
            
            const toastElement = document.getElementById('toast');
            toastElement.innerHTML = isError ? 
                `<span style="margin-right: 8px;">❌</span>${message}` : 
                `<span style="margin-right: 8px;">🎉</span>${message}`;
            toastElement.className = 'toast' + (isError ? ' error' : ' success');
            toastElement.style.display = 'block';
            toastElement.style.opacity = '1';
            
            setTimeout(() => { 
                toastElement.style.opacity = '0';
                setTimeout(() => {
                    toastElement.style.display = 'none';
                }, 300);
            }, 3000);
        }
        
        // Global variables for video management
        let currentPlayingVideo = null;
        let globalMuted = true;
        let likedVideos = new Set();
        let savedVideos = new Set();
        let userHasInteracted = false; // Track if user has interacted with any video
        let pendingSaveRequests = new Set(); // Track pending save requests
        
        // Load user's saved videos
        async function loadUserSavedVideos() {
            try {
                const formData = new FormData();
                formData.append('action', 'limey_get_user_saved_videos');
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    // Clear existing saved videos
                    savedVideos.clear();
                    
                    result.data.forEach(videoId => {
                        savedVideos.add(videoId);
                        // Update UI for saved videos
                        const saveBtn = document.querySelector(`[onclick*="toggleSave('${videoId}')"]`);
                        if (saveBtn) {
                            saveBtn.style.color = '#ffd700';
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading saved videos:', error);
            }
        }
        
        // Initialize video functionality
        window.initVideo = function(video) {
            video.muted = globalMuted;
            
            video.addEventListener('loadedmetadata', function() {
                // Initialize duration display
                const videoId = video.closest('.video-item')?.dataset.videoId;
                if (videoId && video.duration) {
                    const timeDisplay = document.querySelector(`.duration-time[data-video-id="${videoId}"]`);
                    if (timeDisplay) {
                        const minutes = Math.floor(video.duration / 60);
                        const seconds = Math.floor(video.duration % 60);
                        timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    }
                }
            });
            
            video.addEventListener('error', function(e) {
                console.error('Video error:', e);
            });
            
            // Add event listeners for user interaction tracking
            video.addEventListener('play', function() {
                userHasInteracted = true;
                currentPlayingVideo = video;
            });
            
            video.addEventListener('pause', function() {
                if (currentPlayingVideo === video) {
                    currentPlayingVideo = null;
                }
            });
        }
        
        // Update video progress and duration slider
        window.updateVideoProgress = function(video) {
            const videoId = video.dataset.videoUrl ? video.closest('.video-item').dataset.videoId : null;
            if (!videoId || !video.duration) return;
            
            const progressBar = document.querySelector(`.duration-progress-bar[data-video-id="${videoId}"]`);
            const timeDisplay = document.querySelector(`.duration-time[data-video-id="${videoId}"]`);
            
            if (progressBar && timeDisplay) {
                // Update progress bar
                const progress = (video.currentTime / video.duration) * 100;
                progressBar.style.width = progress + '%';
                
                // Update time display (countdown)
                const remainingTime = video.duration - video.currentTime;
                const minutes = Math.floor(remainingTime / 60);
                const seconds = Math.floor(remainingTime % 60);
                timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }
        
        // Toggle video play/pause
        window.toggleVideoPlay = function(videoItem) {
            const video = videoItem.querySelector('.video-player');
            
            if (!video) return;
            
            // Stop all other videos first
            document.querySelectorAll('.video-player').forEach(v => {
                if (v !== video && !v.paused) {
                    v.pause();
                    const vItem = v.closest('.video-item');
                    if (vItem) {
                        vItem.classList.add('paused');
                    }
                }
            });
            
            // Toggle current video
            if (video.paused) {
                video.play().then(() => {
                    userHasInteracted = true; // Mark that user has interacted
                    currentPlayingVideo = video;
                    videoItem.classList.remove('paused'); // This hides the play button
                }).catch(error => {
                    // Show play button if autoplay fails
                    videoItem.classList.add('paused');
                });
            } else {
                video.pause();
                videoItem.classList.add('paused'); // This shows the play button
                if (currentPlayingVideo === video) {
                    currentPlayingVideo = null;
                }
            }
        }
        
        // Global mute toggle
        window.toggleGlobalMute = function() {
            globalMuted = !globalMuted;
            const muteBtn = document.getElementById('globalMuteBtn');
            
            // Update all videos immediately
            document.querySelectorAll('.video-player').forEach(video => {
                video.muted = globalMuted;
            });
            
            // Update button icon with smooth transition
            if (muteBtn) {
                muteBtn.style.transform = 'scale(1.1)';
                muteBtn.textContent = globalMuted ? '🔇' : '🔊';
                setTimeout(() => {
                    muteBtn.style.transform = 'scale(1)';
                }, 150);
            }
            
            // Show feedback to user
            showToast(globalMuted ? 'Videos muted' : 'Videos unmuted');
        }
        
        // Toggle like functionality
        window.toggleLike = async function(videoId) {
            const likeBtn = document.querySelector(`[onclick*="toggleLike('${videoId}')"]`);
            const countSpan = likeBtn.parentElement.querySelector('.action-count');
            
            if (!likeBtn || !countSpan) return;
            
            // Prevent multiple clicks
            if (likeBtn.disabled) return;
            likeBtn.disabled = true;
            
            const isLiked = likedVideos.has(videoId);
            
            try {
                // Add animation
                likeBtn.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    likeBtn.style.transform = 'scale(1)';
                }, 200);
                
                // Make AJAX call to server
                const formData = new FormData();
                formData.append('action', 'limey_toggle_like');
                formData.append('video_id', videoId);
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update with server response only
                    countSpan.textContent = formatCount(result.data.count);
                    if (result.data.liked) {
                        likedVideos.add(videoId);
                        likeBtn.style.color = '#ff3040';
                        showToast('Video liked!', false);
                    } else {
                        likedVideos.delete(videoId);
                        likeBtn.style.color = 'white';
                        showToast('Video unliked', false);
                    }
                } else {
                    showToast('Error liking video', true);
                }
                
            } catch (error) {
                console.error('Error toggling like:', error);
                showToast('Error liking video', true);
            } finally {
                likeBtn.disabled = false;
            }
        }
        
        // Toggle save functionality
        window.toggleSave = async function(videoId) {
            // Prevent multiple simultaneous requests for the same video
            if (pendingSaveRequests.has(videoId)) {
                return;
            }
            pendingSaveRequests.add(videoId);
            
            const saveBtn = document.querySelector(`[onclick*="toggleSave('${videoId}')"]`);
            const countSpan = saveBtn.parentElement.querySelector('.action-count');
            
            if (!saveBtn || !countSpan) {
                pendingSaveRequests.delete(videoId);
                return;
            }
            
            // Prevent multiple clicks
            if (saveBtn.disabled) {
                pendingSaveRequests.delete(videoId);
                return;
            }
            saveBtn.disabled = true;
            
            try {
                // Add animation
                saveBtn.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    saveBtn.style.transform = 'scale(1)';
                }, 200);
                
                // Make AJAX call to server
                const formData = new FormData();
                formData.append('action', 'limey_toggle_save');
                formData.append('video_id', videoId);
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update with server response only
                    countSpan.textContent = formatCount(result.data.count);
                    if (result.data.saved) {
                        savedVideos.add(videoId);
                        saveBtn.style.color = '#ffd700';
                        showToast('Video saved!', false);
                    } else {
                        savedVideos.delete(videoId);
                        saveBtn.style.color = 'white';
                        showToast('Video removed from saved', false);
                    }
                } else {
                    showToast('Error saving video', true);
                }
                
            } catch (error) {
                console.error('Error toggling save:', error);
                showToast('Error saving video', true);
            } finally {
                saveBtn.disabled = false;
                pendingSaveRequests.delete(videoId);
            }
        }
        
        // Share video functionality
        window.shareVideo = function(videoId) {
            const videoItem = document.querySelector(`[data-video-id="${videoId}"]`);
            const videoTitle = videoItem.querySelector('.video-title')?.textContent || 'Check out this video';
            const videoUrl = window.location.origin + window.location.pathname + '?video=' + videoId;
            
            // Create share modal
            const modal = document.createElement('div');
            modal.className = 'share-modal';
            modal.innerHTML = `
                <div class="share-modal-overlay" onclick="closeShareModal()"></div>
                <div class="share-modal-content">
                    <div class="share-header">
                        <h3>Share Video</h3>
                        <button onclick="closeShareModal()" class="close-btn">×</button>
                    </div>
                    <div class="share-options">
                        <button class="share-option" onclick="shareToWhatsApp('${videoUrl}', '${videoTitle}')">
                            <span style="font-size: 24px;">📱</span>
                            <span>WhatsApp</span>
                        </button>
                        <button class="share-option" onclick="shareToFacebook('${videoUrl}')">
                            <span style="font-size: 24px;">📘</span>
                            <span>Facebook</span>
                        </button>
                        <button class="share-option" onclick="shareToTwitter('${videoUrl}', '${videoTitle}')">
                            <span style="font-size: 24px;">🐦</span>
                            <span>Twitter</span>
                        </button>
                        <button class="share-option" onclick="copyLink('${videoUrl}')">
                            <span style="font-size: 24px;">🔗</span>
                            <span>Copy Link</span>
                        </button>
                        <button class="share-option" onclick="downloadVideo('${videoId}')">
                            <span style="font-size: 24px;">💾</span>
                            <span>Download</span>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Track share opening
            trackShare(videoId, 'modal_open');
        }
        
        // Share modal functions
        window.closeShareModal = function() {
            const modal = document.querySelector('.share-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        window.shareToWhatsApp = function(url, title) {
            const videoId = url.split('video=')[1];
            trackShare(videoId, 'whatsapp');
            window.open(`https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`, '_blank');
            closeShareModal();
        }
        
        window.shareToFacebook = function(url) {
            const videoId = url.split('video=')[1];
            trackShare(videoId, 'facebook');
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
            closeShareModal();
        }
        
        window.shareToTwitter = function(url, title) {
            const videoId = url.split('video=')[1];
            trackShare(videoId, 'twitter');
            window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`, '_blank');
            closeShareModal();
        }
        
        window.copyLink = function(url) {
            const videoId = url.split('video=')[1];
            trackShare(videoId, 'copy_link');
            navigator.clipboard.writeText(url).then(() => {
                showToast('Link copied to clipboard!');
                closeShareModal();
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Link copied to clipboard!');
                closeShareModal();
            });
        }
        
        window.downloadVideo = function(videoId) {
            trackShare(videoId, 'download');
            const videoItem = document.querySelector(`[data-video-id="${videoId}"]`);
            const video = videoItem.querySelector('.video-player source');
            if (video) {
                const link = document.createElement('a');
                link.href = video.src;
                link.download = `limey-video-${videoId}.mp4`;
                link.click();
                showToast('Download started!');
            }
            closeShareModal();
        }
        
        // Toggle follow functionality
        window.toggleFollow = function(userId, buttonElement) {
            const followBtn = buttonElement || document.querySelector(`[onclick*="toggleFollow('${userId}')"]`);
            if (!followBtn) return;
            
            const isFollowing = followBtn.classList.contains('following');
            
            if (isFollowing) {
                followBtn.classList.remove('following');
                followBtn.textContent = '+';
            } else {
                followBtn.classList.add('following');
                followBtn.textContent = '✓';
            }
            
            // Add animation
            followBtn.style.transform = 'translateX(-50%) scale(1.2)';
            setTimeout(() => {
                followBtn.style.transform = 'translateX(-50%) scale(1)';
            }, 200);
            
            showToast(isFollowing ? 'Unfollowed' : 'Following!');
        }
        
        // Seek video to specific time
        window.seekVideo = function(event, videoId) {
            event.stopPropagation();
            
            const progressBar = event.currentTarget;
            const rect = progressBar.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            const percentage = clickX / rect.width;
            
            const videoItem = document.querySelector(`[data-video-id="${videoId}"]`);
            const video = videoItem?.querySelector('.video-player');
            
            if (video && video.duration) {
                const newTime = percentage * video.duration;
                video.currentTime = newTime;
                
                // Update progress bar immediately
                const progressBarFill = progressBar.querySelector('.duration-progress-bar');
                if (progressBarFill) {
                    progressBarFill.style.width = (percentage * 100) + '%';
                }
                
                // Update time display
                const timeDisplay = document.querySelector(`.duration-time[data-video-id="${videoId}"]`);
                if (timeDisplay) {
                    const remainingTime = video.duration - newTime;
                    const minutes = Math.floor(remainingTime / 60);
                    const seconds = Math.floor(remainingTime % 60);
                    timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
            }
        }
        
        // Navigate to profile (own or other user's)
        window.navigateToProfile = function(videoUserId, username) {
            // Get current user's UUID from PHP (we'll add this)
            const currentUserUuid = '<?php echo esc_js(get_user_meta(get_current_user_id(), "limey_user_uuid", true)); ?>';
            
            if (videoUserId === currentUserUuid) {
                // It's the current user's video, go to own profile
                window.location.href = '/profile';
            } else {
                // It's another user's video, go to their profile
                window.location.href = '/profile/' + username;
            }
        }
  //-------------------------------------------------------------------------------COMMENTS SECTION TODO --------------------//

        // Open comments (placeholder for now)
        window.openComments = function(videoId) {
            showToast('Comments feature coming soon!');
        }
        
        // Track share function
        async function trackShare(videoId, shareType) {
            try {
                const formData = new FormData();
                formData.append('action', 'limey_track_share');
                formData.append('video_id', videoId);
                formData.append('share_type', shareType);
                
                const response = await fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update share count in UI
                    const shareBtn = document.querySelector(`[onclick*="shareVideo('${videoId}')"]`);
                    const countSpan = shareBtn?.parentElement.querySelector('.action-count');
                    if (countSpan) {
                        countSpan.textContent = formatCount(result.data.count);
                    }
                }
            } catch (error) {
                console.error('Error tracking share:', error);
            }
        }
        
        // Utility functions
        function formatCount(count) {
            if (count >= 1000000) {
                return (count / 1000000).toFixed(1) + 'M';
            } else if (count >= 1000) {
                return (count / 1000).toFixed(1) + 'K';
            }
            return count.toString();
        }
        
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast-message';
            toast.innerHTML = isError ? 
                `<span style="margin-right: 8px;">❌</span>${message}` : 
                `<span style="margin-right: 8px;">🎉</span>${message}`;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${isError ? '#dc3545' : '#5ccc45'};
                color: white;
                padding: 12px 24px;
                border-radius: 8px;
                z-index: 10000;
                font-size: 14px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                text-align: center;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Auto-play video when in viewport (only after user interaction)
        function handleVideoIntersection() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const video = entry.target.querySelector('.video-player');
                    const videoItem = entry.target;
                    const playPauseOverlay = videoItem.querySelector('.play-pause-overlay');
                    const playIcon = playPauseOverlay?.querySelector('.play-icon');
                    const pauseIcon = playPauseOverlay?.querySelector('.pause-icon');
                    
                    if (entry.isIntersecting && entry.intersectionRatio > 0.5) {
                        // Stop all other videos first
                        document.querySelectorAll('.video-player').forEach(v => {
                            if (v !== video && !v.paused) {
                                v.pause();
                                const vItem = v.closest('.video-item');
                                if (vItem) {
                                    vItem.classList.add('paused');
                                }
                            }
                        });
                        
                        // Auto-play videos after first interaction or if it's the first video
                        if (video && video.paused && userHasInteracted) {
                            video.play().then(() => {
                                currentPlayingVideo = video;
                                videoItem.classList.remove('paused'); // Hide play button when playing
                            }).catch(error => {
                                videoItem.classList.add('paused'); // Show play button if failed
                            });
                        } else if (!userHasInteracted) {
                            // Show play button for first video
                            videoItem.classList.add('paused');
                        }
                    } else if (video && !video.paused) {
                        // Pause videos that are out of view
                        video.pause();
                        videoItem.classList.add('paused'); // Show play button when paused
                        if (currentPlayingVideo === video) {
                            currentPlayingVideo = null;
                        }
                    }
                });
            }, { 
                threshold: 0.5,
                rootMargin: '0px 0px -10% 0px' // Trigger slightly before video is fully in view
            });
            
            document.querySelectorAll('.video-item').forEach(item => {
                observer.observe(item);
            });
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Load user's saved videos
            loadUserSavedVideos();
            
            const videoElements = document.querySelectorAll('.video-player');
            
            // Set initial mute state for all videos
            videoElements.forEach((video) => {
                video.muted = globalMuted;
            });
            
            // Fix mute button icon
            const muteBtn = document.getElementById('globalMuteBtn');
            if (muteBtn) {
                muteBtn.textContent = globalMuted ? '🔇' : '🔊';
            }
            
            // Auto-play first video (muted)
            const firstVideo = document.querySelector('.video-item');
            const firstVideoPlayer = firstVideo?.querySelector('.video-player');
            if (firstVideo && firstVideoPlayer) {
                firstVideoPlayer.play().then(() => {
                    userHasInteracted = true; // Mark as interacted so scrolling works
                    currentPlayingVideo = firstVideoPlayer;
                    firstVideo.classList.remove('paused');
                }).catch(() => {
                    // If autoplay fails, show play button
                    firstVideo.classList.add('paused');
                });
            }
            
            // Initialize video intersection observer
            handleVideoIntersection();
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.code === 'Space') {
                    e.preventDefault();
                    const visibleVideo = document.querySelector('.video-item:not(.paused)') || document.querySelector('.video-item');
                    if (visibleVideo) {
                        toggleVideoPlay(visibleVideo);
                    }
                } else if (e.code === 'KeyM') {
                    e.preventDefault();
                    toggleGlobalMute();
                }
            });
        });
        </script>
        <?php
    }
}
add_action('wp_head', 'limey_enqueue_video_scripts');

// Execute page callbacks
add_filter('the_content', 'limey_execute_page_callback');

function limey_execute_page_callback($content) {
    if (is_page()) {
        $page_id = get_the_ID();
        $callback = get_post_meta($page_id, '_page_callback', true);
        
        if ($callback && function_exists($callback)) {
            ob_start();
            $callback();
            return ob_get_clean();
        }
    }
    return $content;
}

function limey_site_title_shortcode() {
    return '<div class="limey-glow-outline">Limey</div>';
}
add_shortcode('limey_title', 'limey_site_title_shortcode');
?>
