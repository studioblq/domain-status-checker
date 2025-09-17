<?php

namespace Drupal\domain_status_checker\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\domain_status_checker\Service\DomainChecker;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Ajax endpoints for Domain Status Checker.
 */
class AjaxController extends ControllerBase {

  protected $checker;
  protected $database;
  protected $currentUser;

  public function __construct(DomainChecker $checker, Connection $database, AccountInterface $current_user) {
    $this->checker = $checker;
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('domain_status_checker.domain_checker'),
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * Check a single domain now.
   */
  public function checkNow($domain) {
    if (! $this->currentUser->hasPermission('administer site configuration')) {
      return new JsonResponse(['error' => 'Access denied'], 403);
    }
    // Load current status from DB.
    $record = $this->database->select('domain_status', 'd')
      ->fields('d', ['id', 'current_status'])
      ->condition('domain_name', $domain)
      ->execute()
      ->fetchAssoc();
    if (! $record) {
      return new JsonResponse(['error' => 'Domain not found'], 404);
    }
    $new = $this->checker->checkDomainStatus($domain);
    // Update DB.
    $this->database->update('domain_status')
      ->fields(['current_status' => $new, 'last_check' => \Drupal::time()->getRequestTime()])
      ->condition('id', $record['id'])
      ->execute();
    // Send alert if needed.
    if ($record['current_status'] !== $new) {
      $this->checker->sendAlert($domain, $record['current_status'], $new);
    }
    return new JsonResponse(['status' => $new]);
  }

  /**
   * Clear all logs.
   */
  public function clearLogs() {
    if (! $this->currentUser->hasPermission('administer site configuration')) {
      return new JsonResponse(['error' => 'Access denied'], 403);
    }
    // Delete all records from log table.
    $this->database->truncate('domain_status_log')->execute();
    \Drupal::logger('domain_status_checker')->notice('All logs cleared by @user', ['@user' => $this->currentUser->getAccountName()]);
    return new JsonResponse(['cleared' => TRUE]);
  }
}
