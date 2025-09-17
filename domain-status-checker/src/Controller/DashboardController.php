<?php

namespace Drupal\domain_status_checker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DashboardController extends ControllerBase {

  protected $configFactory;
  protected $database;

  public function __construct(ConfigFactoryInterface $config_factory, Connection $database) {
    $this->configFactory = $config_factory;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  public function content() {
    $build = [];

    // Dashboard Header
    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Domain Status Checker Dashboard'),
    ];

    // Cron Status Section
    $build['cron_status'] = $this->buildCronStatusSection();

    // Domains Table Section
    $build['domains_table'] = $this->buildDomainsTableSection();

    // Log Section
    $build['logs'] = $this->buildLogsSection();

    return $build;
  }

  protected function buildCronStatusSection() {
    $config = $this->configFactory->get('domain_status_checker.settings');
    $last_run = $config->get('last_cron_run') ?: $this->t('Never');
    
    // Get Drupal cron info
    $next_cron = \Drupal::state()->get('system.cron_last') ?: 0;
    $next_cron_formatted = $next_cron ? date('Y-m-d H:i:s', $next_cron) : $this->t('Unknown');

    return [
      '#type' => 'details',
      '#title' => $this->t('📊 Cron Status'),
      '#open' => TRUE,
      'content' => [
        '#type' => 'container',
        '#attributes' => ['style' => 'display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'],
        'drupal_cron' => [
          '#type' => 'container',
          '#attributes' => ['style' => 'background: #f9f9f9; padding: 15px; border-radius: 5px;'],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('🕐 Drupal Cron General'),
          ],
          'last_run' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => '<strong>' . $this->t('Last run:') . '</strong> ' . $next_cron_formatted,
          ],
        ],
        'plugin_cron' => [
          '#type' => 'container',
          '#attributes' => ['style' => 'background: #f9f9f9; padding: 15px; border-radius: 5px;'],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('🔍 Domain Checker Cron'),
          ],
          'last_run' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => '<strong>' . $this->t('Last execution:') . '</strong> ' . $last_run,
          ],
        ],
      ],
    ];
  }

  protected function buildDomainsTableSection() {
    $config = $this->configFactory->get('domain_status_checker.settings');
    $domains = $config->get('domains') ?: '';
    $domain_list = array_filter(explode("\n", $domains));

    $header = [
      $this->t('Domain'),
      $this->t('Status'),
      $this->t('Last Check'),
      $this->t('Actions'),
    ];

    $rows = [];
    foreach ($domain_list as $domain) {
      $domain = trim($domain);
      if (!empty($domain)) {
        // Get domain status from database or config
        $status = $this->getDomainStatus($domain);
        $last_check = $this->getDomainLastCheck($domain);
        
        $rows[] = [
          $domain,
          $status ?: $this->t('Unknown'),
          $last_check ?: $this->t('Never'),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Check Now'),
              '#url' => \Drupal\Core\Url::fromRoute('domain_status_checker.check_domain', ['domain' => $domain]),
              '#attributes' => ['class' => ['button', 'button--small']],
            ],
          ],
        ];
      }
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('📋 Monitored Domains'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No domains configured.'),
      ],
    ];
  }

  protected function buildLogsSection() {
    // Get recent logs from database
    $logs = $this->getRecentLogs(50);

    $header = [
      $this->t('Timestamp'),
      $this->t('Domain'),
      $this->t('Message'),
      $this->t('Type'),
    ];

    $rows = [];
    foreach ($logs as $log) {
      $rows[] = [
        $log['timestamp'],
        $log['domain'],
        $log['message'],
        $log['type'],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('📝 Recent Logs'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No logs found.'),
      ],
    ];
  }

  protected function getDomainStatus($domain) {
    // Query database or config for domain status
    // This would need to be implemented based on your storage method
    return 'registered'; // placeholder
  }

  protected function getDomainLastCheck($domain) {
    // Query database or config for last check time
    // This would need to be implemented based on your storage method
    return date('Y-m-d H:i:s'); // placeholder
  }

  protected function getRecentLogs($limit = 50) {
    // Query database for recent logs
    // This would need to be implemented based on your logging storage
    return []; // placeholder
  }

}