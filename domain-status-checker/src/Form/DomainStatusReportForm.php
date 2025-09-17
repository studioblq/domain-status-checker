<?php
namespace Drupal\domain_status_checker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DomainStatusReportForm extends FormBase {

  protected $mailManager;

  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mail.manager')
    );
  }

  public function getFormId() {
    return 'domain_status_report_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Domain Status Report'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Recupera i domini monitorati (ad esempio, dal database o dalla configurazione)
    $domains = $this->getMonitoredDomains();
    
    // Crea il contenuto del report
    $report_content = "Domain Status Report:\n\n";
    foreach ($domains as $domain) {
      $report_content .= "Domain: " . $domain['name'] . " - Status: " . $domain['status'] . "\n";
    }

    // Invia il report via email
    $this->sendReportEmail($report_content);
  }

  private function getMonitoredDomains() {
    // Recupera i domini monitorati (esempio statico)
    return [
      ['name' => 'example.com', 'status' => 'Active'],
      ['name' => 'example.net', 'status' => 'Expired'],
    ];
  }

  private function sendReportEmail($content) {
    $to = 'admin@example.com'; // Destinatario
    $params = [
      'subject' => 'Domain Status Report',
      'message' => $content,
    ];

    // Invia l'email
    $this->mailManager->mail('domain_status_checker', 'domain_status_report', $to, 'en', $params);
  }
}
