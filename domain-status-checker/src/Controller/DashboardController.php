<?php
namespace Drupal\domain_status_checker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Link;
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

    // Add Domain Link
    $build['add_domain_link'] = [
      '#type' => 'container',
      '#attributes' => ['style' => 'margin: 20px 0;'],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Add New Domain'),
        '#url' => Url::fromRoute('domain_status_checker.add_domain'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
    ];

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
    // CORREZIONE: Leggiamo dalla tabella del database invece che dalla configurazione
    $query = $this->database->select('domain_status_checker_domains', 'd')
      ->fields('d', ['id', 'domain_name'])
      ->orderBy('id', 'DESC');
    $results = $query->execute()->fetchAll();

    $header = [
      $this->t('ID'),
      $this->t('Domain'),
      $this->t('Status'),
      $this->t('Last Check'),
      $this->t('Actions'),
    ];

    $rows = [];
    foreach ($results as $record) {
      // Get domain status from database or service
      $status = $this->getDomainStatus($record->domain_name);
      $last_check = $this->getDomainLastCheck($record->domain_name);
      
      $rows[] = [
        $record->id,
        $record->domain_name,
        $status ?: $this->t('Unknown'),
        $last_check ?: $this->t('Never'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'check' => [
                'title' => $this->t('Check Now'),
                'url' => Url::fromRoute('domain_status_checker.check_now', ['domain' => $record->domain_name]),
                'attributes' => [
                  'class' => ['button', 'button--small', 'use-ajax'],
                  'data-dialog-type' => 'modal',
                ],
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('domain_status_checker.delete_domain', ['id' => $record->id]),
                'attributes' => [
                  'class' => ['button', 'button--danger', 'button--small'],
                  'onclick' => 'return confirm("' . $this->t('Are you sure you want to delete this domain?') . '")',
                ],
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('📋 Monitored Domains (' . count($results) . ')'),
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No domains found. <a href="@url">Add your first domain</a>.', [
          '@url' => Url::fromRoute('domain_status_checker.add_domain')->toString(),
        ]),
        '#attributes' => ['class' => ['domain-status-table']],
      ],
    ];
  }

  protected function buildLogsSection() {
    // Get recent logs from watchdog
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'timestamp', 'message', 'variables', 'severity'])
      ->condition('type', 'domain_status_checker')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 20);
    $logs = $query->execute()->fetchAll();

    $header = [
      $this->t('Timestamp'),
      $this->t('Message'),
      $this->t('Severity'),
    ];

    $rows = [];
    foreach ($logs as $log) {
      $variables = $log->variables ? unserialize($log->variables) : [];
      $message = $this->t($log->message, $variables);
      
      $severity_levels = [
        0 => $this->t('Emergency'),
        1 => $this->t('Alert'),
        2 => $this->t('Critical'),
        3 => $this->t('Error'),
        4 => $this->t('Warning'),
        5 => $this->t('Notice'),
        6 => $this->t('Info'),
        7 => $this->t('Debug'),
      ];
      
      $rows[] = [
        date('Y-m-d H:i:s', $log->timestamp),
        $message,
        $severity_levels[$log->severity] ?? $this->t('Unknown'),
      ];
    }

    return [
      '#type' => 'details',
      '#title' => $this->t('📝 Recent Logs'),
      '#open' => FALSE,
      'clear_logs' => [
        '#type' => 'link',
        '#title' => $this->t('Clear Logs'),
        '#url' => Url::fromRoute('domain_status_checker.clear_logs'),
        '#attributes' => [
          'class' => ['button', 'button--small', 'button--danger'],
          'style' => 'margin-bottom: 10px;',
          'onclick' => 'return confirm("' . $this->t('Are you sure you want to clear all logs?') . '")',
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No logs found.'),
      ],
    ];
  }

  protected function getDomainStatus($domain) {
    // Usa il servizio DomainChecker per ottenere lo status reale
    $domain_checker = \Drupal::service('domain_status_checker.checker');
    try {
      return $domain_checker->checkDomainStatus($domain);
    } catch (\Exception $e) {
      \Drupal::logger('domain_status_checker')->error('Error checking domain %domain: %error', [
        '%domain' => $domain,
        '%error' => $e->getMessage(),
      ]);
      return 'error';
    }
  }

  protected function getDomainLastCheck($domain) {
    // Cerca l'ultimo check nei log
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['timestamp'])
      ->condition('type', 'domain_status_checker')
      ->condition('message', '%' . $domain . '%', 'LIKE')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 1);
    $result = $query->execute()->fetchField();
    
    return $result ? date('Y-m-d H:i:s', $result) : $this->t('Never');
  }
}