<?php
namespace Drupal\domain_status_checker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DomainAddForm extends FormBase {

  protected $messenger;
  protected $database;

  public function __construct(MessengerInterface $messenger, Connection $database) {
    $this->messenger = $messenger;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'domain_status_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'domain-add-form';
    
    $form['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Add New Domain to Monitor'),
    ];

    $form['domain_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain Name'),
      '#description' => $this->t('Enter the domain name without http:// or www (e.g., example.com)'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#placeholder' => 'example.com',
      '#attributes' => [
        'pattern' => '^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$',
      ],
    ];

    // Mostra domini esistenti
    $existing_domains = $this->getExistingDomains();
    if (!empty($existing_domains)) {
      $form['existing_domains'] = [
        '#type' => 'details',
        '#title' => $this->t('Currently Monitored Domains'),
        '#open' => FALSE,
        'list' => [
          '#theme' => 'item_list',
          '#items' => $existing_domains,
          '#empty' => $this->t('No domains currently monitored.'),
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Domain'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('domain_status_checker.dashboard'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $domain_name = trim($form_state->getValue('domain_name'));
    
    // Validazione formato domain
    if (!$this->isValidDomain($domain_name)) {
      $form_state->setErrorByName('domain_name', $this->t('Please enter a valid domain name (e.g., example.com)'));
      return;
    }

    // Controlla se il dominio esiste già
    $exists = $this->database->select('domain_status_checker_domains', 'd')
      ->fields('d', ['id'])
      ->condition('domain_name', $domain_name)
      ->execute()
      ->fetchField();
    
    if ($exists) {
      $form_state->setErrorByName('domain_name', $this->t('This domain is already being monitored.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domain_name = trim($form_state->getValue('domain_name'));

    // Log tentativo di inserimento
    \Drupal::logger('domain_status_checker')->notice('Attempting to insert domain: %domain', ['%domain' => $domain_name]);

    try {
      // Inserimento nel database
      $id = $this->database->insert('domain_status_checker_domains')
        ->fields(['domain_name' => $domain_name])
        ->execute();

      if ($id) {
        // Successo
        $this->messenger->addMessage($this->t('Domain %domain has been successfully added to monitoring.', ['%domain' => $domain_name]));
        
        // Log successo
        \Drupal::logger('domain_status_checker')->info('Domain %domain added successfully with ID %id', [
          '%domain' => $domain_name,
          '%id' => $id,
        ]);

        // Effettua un controllo immediato del dominio
        $this->performImmediateCheck($domain_name);

        // Redirect alla dashboard
        $form_state->setRedirectUrl(Url::fromRoute('domain_status_checker.dashboard'));
      } else {
        throw new \Exception('Failed to insert domain into database');
      }
    }
    catch (\Exception $e) {
      // Errore
      \Drupal::logger('domain_status_checker')->error('Error inserting domain %domain: %error', [
        '%domain' => $domain_name,
        '%error' => $e->getMessage(),
      ]);
      
      $this->messenger->addMessage(
        $this->t('Error adding domain %domain: %error', [
          '%domain' => $domain_name,
          '%error' => $e->getMessage()
        ]), 
        'error'
      );
    }
  }

  /**
   * Valida il formato del dominio
   */
  private function isValidDomain($domain) {
    // Rimuovi spazi e converti in lowercase
    $domain = strtolower(trim($domain));
    
    // Controlla formato base
    if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
      return FALSE;
    }
    
    // Controlla che abbia almeno un punto
    if (strpos($domain, '.') === FALSE) {
      return FALSE;
    }
    
    // Controlla lunghezza
    if (strlen($domain) > 255 || strlen($domain) < 4) {
      return FALSE;
    }
    
    return TRUE;
  }

  /**
   * Ottiene la lista dei domini esistenti
   */
  private function getExistingDomains() {
    $query = $this->database->select('domain_status_checker_domains', 'd')
      ->fields('d', ['domain_name'])
      ->orderBy('domain_name', 'ASC');
    $results = $query->execute()->fetchAll();
    
    $domains = [];
    foreach ($results as $record) {
      $domains[] = $record->domain_name;
    }
    
    return $domains;
  }

  /**
   * Effettua un controllo immediato del dominio appena aggiunto
   */
  private function performImmediateCheck($domain) {
    try {
      $domain_checker = \Drupal::service('domain_status_checker.checker');
      $status = $domain_checker->checkDomainStatus($domain);
      
      \Drupal::logger('domain_status_checker')->info('Initial check for domain %domain: status is %status', [
        '%domain' => $domain,
        '%status' => $status,
      ]);
      
      $this->messenger->addMessage($this->t('Initial check completed: %domain status is "%status"', [
        '%domain' => $domain,
        '%status' => $status,
      ]));
      
    } catch (\Exception $e) {
      \Drupal::logger('domain_status_checker')->warning('Failed initial check for domain %domain: %error', [
        '%domain' => $domain,
        '%error' => $e->getMessage(),
      ]);
    }
  }
}