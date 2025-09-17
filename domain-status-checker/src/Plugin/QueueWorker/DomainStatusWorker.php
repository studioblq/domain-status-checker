<?php

namespace Drupal\domain_status_checker\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\domain_status_checker\Service\DomainChecker;

/**
 * @QueueWorker(
 *   id = "domain_status_check",
 *   title = "Domain Status Checker",
 *   cron = {"time" = 1800}
 * )
 */
class DomainStatusWorker extends QueueWorkerBase {
  protected $checker;

  public function __construct(DomainChecker $checker) {
    $this->checker = $checker;
  }

  public function processItem($data) {
    $new = $this->checker->checkDomainStatus($data['domain']);
    if ($new !== $data['current_status']) {
      $this->checker->sendAlert($data['domain'], $data['current_status'], $new);
      // Salva nel DB lo stato nuovo e timestamp.
    }
  }
}
