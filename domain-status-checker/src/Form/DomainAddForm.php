<?php

namespace Drupal\domain_status_checker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DomainAddForm extends FormBase {

  protected $messenger;

  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  public function getFormId() {
    return 'domain_status_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['domain_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain Name'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Domain'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domain_name = $form_state->getValue('domain_name');

    // Aggiungi un messaggio di debug
    \Drupal::logger('domain_status_checker')->notice('Attempting to insert domain: %domain', ['%domain' => $domain_name]);

    // Logica per aggiungere il dominio al database
    try {
      \Drupal::database()->insert('domain_status_checker_domains')
        ->fields(['domain_name' => $domain_name])
        ->execute();
      // Se l'inserimento ha successo, mostra il messaggio
      $this->messenger->addMessage($this->t('Domain %domain added successfully.', ['%domain' => $domain_name]));
    }
    catch (\Exception $e) {
      // Se c'è un errore, loggalo e mostralo
      \Drupal::logger('domain_status_checker')->error('Error inserting domain: %error', ['%error' => $e->getMessage()]);
      $this->messenger->addMessage($this->t('Error adding domain: %error', ['%error' => $e->getMessage()]), 'error');
    }
  }
}
