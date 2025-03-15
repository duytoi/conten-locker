<?php
class WCL_Statistics_Service {
    private $stats_model;
    private $download_service;
    private $protection_service;

    public function __construct() {
        $this->stats_model = new WCL_Statistics_Model();
        $this->download_service = new Download_Service();
        $this->protection_service = new Protection_Service();
    }

    public function get_dashboard_stats() {
        return array(
            'downloads' => $this->get_download_overview(),
            'protections' => $this->get_protection_overview(),
            'top_downloads' => $this->get_top_downloads(),
            'recent_activity' => $this->get_recent_activity()
        );
    }

    public function get_download_overview($period = '30days') {
        $stats = $this->stats_model->get_download_stats(0, $period);
        
        $total_downloads = 0;
        $unique_downloads = 0;
        $daily_stats = array();

        foreach ($stats as $stat) {
            $total_downloads += $stat->total;
            $unique_downloads += $stat->unique_downloads;
            $daily_stats[$stat->date] = array(
                'total' => $stat->total,
                'unique' => $stat->unique_downloads
            );
        }

        return array(
            'total_downloads' => $total_downloads,
            'unique_downloads' => $unique_downloads,
            'daily_stats' => $daily_stats
        );
    }

    public function get_protection_overview($period = '30days') {
        $stats = $this->stats_model->get_protection_stats(0, $period);
        
        $total_attempts = 0;
        $successful_attempts = 0;
        $failed_attempts = 0;
        $daily_stats = array();

        foreach ($stats as $stat) {
            $total_attempts += $stat->total;
            if ($stat->status === 'success') {
                $successful_attempts += $stat->total;
            } else {
                $failed_attempts += $stat->total;
            }

            if (!isset($daily_stats[$stat->date])) {
                $daily_stats[$stat->date] = array(
                    'success' => 0,
                    'failed' => 0
                );
            }
            $daily_stats[$stat->date][$stat->status] = $stat->total;
        }

        return array(
            'total_attempts' => $total_attempts,
            'successful_attempts' => $successful_attempts,
            'failed_attempts' => $failed_attempts,
            'success_rate' => $total_attempts > 0 ? 
                            ($successful_attempts / $total_attempts) * 100 : 0,
            'daily_stats' => $daily_stats
        );
    }

    public function get_top_downloads($limit = 10, $period = '30days') {
        $top_downloads = $this->stats_model->get_top_downloads($limit, $period);
        $results = array();

        foreach ($top_downloads as $stat) {
            $download = $this->download_service->get_download($stat->entity_id);
            if ($download) {
                $results[] = array(
                    'id' => $download->id,
                    'title' => $download->title,
                    'total_downloads' => $stat->total_downloads,
                    'unique_downloads' => $stat->unique_downloads,
                    'conversion_rate' => ($stat->unique_downloads / $stat->total_downloads) * 100
                );
            }
        }

        return $results;
    }

    public function get_recent_activity($limit = 20) {
        $activities = $this->stats_model->get_recent_activities($limit);
        $results = array();

        foreach ($activities as $activity) {
            $result = array(
                'id' => $activity->id,
                'type' => $activity->type,
                'action' => $activity->action,
                'status' => $activity->status,
                'timestamp' => $activity->created_at,
                'ip_address' => $activity->ip_address
            );

            // Add entity details
            if ($activity->type === 'download') {
                $download = $this->download_service->get_download($activity->entity_id);
                if ($download) {
                    $result['entity'] = array(
                        'id' => $download->id,
                        'title' => $download->title
                    );
                }
            } elseif ($activity->type === 'protection') {
                $protection = $this->protection_service->get_protection($activity->entity_id);
                if ($protection) {
                    $result['entity'] = array(
                        'id' => $protection->id,
                        'type' => $protection->type
                    );
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    public function get_download_report($download_id, $period = '30days') {
        $download = $this->download_service->get_download($download_id);
        if (!$download) {
            return false;
        }

        $stats = $this->stats_model->get_download_stats($download_id, $period);
        
        return array(
            'download' => array(
                'id' => $download->id,
                'title' => $download->title,
                'status' => $download->status,
                'created_at' => $download->created_at
            ),
            'stats' => $stats,
            'summary' => $this->calculate_download_summary($stats)
        );
    }

    private function calculate_download_summary($stats) {
        $summary = array(
            'total_downloads' => 0,
            'unique_downloads' => 0,
            'average_daily' => 0,
            'peak_day' => array(
                'date' => null,
                'total' => 0
            )
        );

        if (empty($stats)) {
            return $summary;
        }

        $days = 0;
        foreach ($stats as $stat) {
            $days++;
            $summary['total_downloads'] += $stat->total;
            $summary['unique_downloads'] += $stat->unique_downloads;

            if ($stat->total > $summary['peak_day']['total']) {
                $summary['peak_day'] = array(
                    'date' => $stat->date,
                    'total' => $stat->total
                );
            }
        }

        $summary['average_daily'] = $summary['total_downloads'] / $days;

        return $summary;
    }
}