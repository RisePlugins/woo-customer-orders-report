<?php
/**
 * Plugin Updater Class
 * Handles automatic updates from GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCorPluginUpdater {
    
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $github_api_result;
    
    public function __construct($plugin_file, $github_username, $github_repo, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->plugin_path = plugin_dir_path($plugin_file);
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'set_transient'));
        add_filter('plugins_api', array($this, 'set_plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }
    
    /**
     * Get information from GitHub API
     */
    private function get_repository_info() {
        if (is_null($this->github_api_result)) {
            $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
            
            $request = wp_remote_get($url);
            
            if (!is_wp_error($request)) {
                $body = wp_remote_retrieve_body($request);
                $this->github_api_result = json_decode($body, true);
            }
        }
        
        return $this->github_api_result;
    }
    
    /**
     * Check if update is available
     */
    public function set_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_new_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url()
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for the update screen
     */
    public function set_plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return false;
        }
        
        if ($response->slug !== $this->get_plugin_slug()) {
            return false;
        }
        
        $remote_version = $this->get_new_version();
        
        $response = (object) array(
            'name' => 'WooCommerce Customer Orders Report',
            'slug' => $this->get_plugin_slug(),
            'version' => $remote_version,
            'author' => 'Ryan Moreno',
            'homepage' => $this->get_github_repo_url(),
            'requires' => '5.0',
            'tested' => '6.4',
            'downloaded' => 0,
            'last_updated' => $this->get_date(),
            'sections' => array(
                'description' => 'Comprehensive customer orders reporting tool for WooCommerce with advanced filtering, analytics, and export capabilities.',
                'changelog' => $this->get_changelog()
            ),
            'download_link' => $this->get_download_url()
        );
        
        return $response;
    }
    
    /**
     * Perform additional actions after plugin update
     */
    public function post_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        
        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->plugin_slug);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;
        
        if ($this->is_plugin_active()) {
            activate_plugin($this->plugin_slug);
        }
        
        return $result;
    }
    
    /**
     * Get new version from GitHub
     */
    private function get_new_version() {
        $version = $this->get_repository_info();
        return isset($version['tag_name']) ? ltrim($version['tag_name'], 'v') : $this->version;
    }
    
    /**
     * Get GitHub repository URL
     */
    private function get_github_repo_url() {
        return "https://github.com/{$this->github_username}/{$this->github_repo}";
    }
    
    /**
     * Get download URL for the latest release
     */
    private function get_download_url() {
        $version = $this->get_repository_info();
        return isset($version['zipball_url']) ? $version['zipball_url'] : false;
    }
    
    /**
     * Get plugin slug
     */
    private function get_plugin_slug() {
        return dirname($this->plugin_slug);
    }
    
    /**
     * Get release date
     */
    private function get_date() {
        $version = $this->get_repository_info();
        return isset($version['published_at']) ? date('Y-m-d', strtotime($version['published_at'])) : date('Y-m-d');
    }
    
    /**
     * Get changelog from GitHub releases
     */
    private function get_changelog() {
        $version = $this->get_repository_info();
        return isset($version['body']) ? $version['body'] : 'No changelog available.';
    }
    
    /**
     * Check if plugin is active
     */
    private function is_plugin_active() {
        return is_plugin_active($this->plugin_slug);
    }
} 