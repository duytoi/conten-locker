<?php
namespace WP_Content_Locker\Core;

class Loader {
    protected $actions;
    protected $filters;

    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
        return $hooks;
    }

    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
	
	/**
 * Add download route handler
 */
public function init_download_route() {
    add_action('init', array($this, 'add_download_rewrite_rule'));
    add_filter('query_vars', array($this, 'add_download_query_var'));
    add_action('template_redirect', array($this, 'handle_download_request'));
}

/**
 * Add rewrite rule for downloads
 */
public function add_download_rewrite_rule() {
    add_rewrite_rule(
        'download/([0-9]+)/?$',
        'index.php?wcl_download_id=$matches[1]',
        'top'
    );
}

/**
 * Add query var for download
 */
public function add_download_query_var($vars) {
    $vars[] = 'wcl_download_id';
    return $vars;
}

/**
 * Handle download request
 */
public function handle_download_request() {
    $download_id = get_query_var('wcl_download_id');
    
    if ($download_id) {
        $download_service = new Download_Service();
        $download_service->process_download_request($download_id);
    }
}
}