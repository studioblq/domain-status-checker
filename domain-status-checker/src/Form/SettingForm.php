<?php

namespace Drupal\domain_status_checker\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  public function getFormId() {
    return 'domain_status_checker_settings';
  }

  protected function getEditableConfigNames() {
    return ['domain_status_checker.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_status_checker.settings');
    $form['alert_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Alert Email'),
      '#default_value' => $config->get('alert_email'),
    ];
    $form['domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Elenco domini'),
      '#description' => $this->t('Un dominio per riga.'),
      '#default_value' => $config->get('domains'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('domain_status_checker.settings')
      ->set('alert_email', $form_state->getValue('alert_email'))
      ->set('domains', $form_state->getValue('domains'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
