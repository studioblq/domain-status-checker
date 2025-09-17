<?php

namespace Drupal\domain_status_checker\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Psr\Log\LoggerInterface;

class DomainChecker {
  protected $database;
  protected $mailManager;
  protected $logger;

  public function __construct(Connection $database, MailManagerInterface $mail_manager, LoggerInterface $logger) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
    $this->logger = $logger;
  }

  public function checkDomainStatus(string $domain): string {
    $domain = strtolower(trim($domain));
    $parts = explode('.', $domain);
    $tld = end($parts);

    $server = $this->getWhoisServer($tld);
    if (!$server) {
      return 'unknown';
    }

    $fp = @fsockopen($server, 43, $errno, $errstr, 10);
    if (!$fp && $tld === 'it') {
      $server = 'whois.registro.it';
      $fp = @fsockopen($server, 43, $errno, $errstr, 10);
    }
    if (!$fp) {
      return 'error';
    }

    fputs($fp, $domain."\r\n");
    $resp = '';
    while (!feof($fp)) {
      $resp .= fgets($fp, 128);
    }
    fclose($fp);

    switch ($tld) {
      case 'it':
        if (stripos($resp, 'Status: AVAILABLE') !== false) return 'available';
        if (stripos($resp, 'Status: ok') !== false || stripos($resp, 'Status: active') !== false) return 'registered';
        if (stripos($resp, 'Status: pendingDelete') !== false) return 'pending delete';
        if (stripos($resp, 'Status: redemptionPeriod') !== false) return 'redemption';
        if (stripos($resp, 'Status: inactive') !== false) return 'inactive';
        break;
      case 'com':
        if (stripos($resp, 'No match for') !== false) return 'available';
        if (stripos($resp, 'Domain Name:') !== false && (stripos($resp, 'Name Server:') || stripos($resp, 'Registrar:') !== false)) return 'registered';
        if (stripos($resp, 'pendingDelete') !== false) return 'pending delete';
        if (stripos($resp, 'redemptionPeriod') !== false) return 'redemption';
        break;
      default:
        if (stripos($resp, 'no match') !== false || stripos($resp, 'not found') !== false || stripos($resp, 'status: free') !== false) return 'available';
        if (stripos($resp, 'registered') !== false || stripos($resp, 'status: active') !== false) return 'registered';
        if (stripos($resp, 'pending delete') !== false) return 'pending delete';
        if (stripos($resp, 'redemption') !== false) return 'redemption';
        if (stripos($resp, 'reserved') !== false) return 'reserved';
        if (stripos($resp, 'error') !== false) return 'error';
    }

    return 'unknown';
  }

  protected function getWhoisServer(string $tld): ?string {
    $map = [
      'com' => 'whois.verisign-grs.com',
      'net' => 'whois.verisign-grs.com',
      'org' => 'whois.pir.org',
      'info' => 'whois.afilias.net',
      'biz' => 'whois.neulevel.biz',
      'it' => 'whois.nic.it',
    ];
    return $map[$tld] ?? null;
  }

  public function sendAlert(string $domain, string $old, string $new): void {
    $to = \Drupal::config('domain_status_checker.settings')->get('alert_email');
    $this->mailManager->mail('domain_status_checker', 'domain_status_alert', $to, 'it', [
      'domain' => $domain,
      'old' => $old,
      'new' => $new,
      'time' => date('Y-m-d H:i:s'),
    ]);
    $this->logger->info("Alert for {$domain}: {$old} → {$new}");
  }
}
