<?php

/**
 * @file
 * Contains Drupal\tracdelight_client\Form\TracdelightClientAdminForm.
 */

namespace Drupal\tracdelight_client\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TracdelightClientAdminForm.
 *
 * @package Drupal\tracdelight_client\Form
 */
class TracdelightClientAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'tracdelight_client.config'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tracdelight_client_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tracdelight_client.config');
    $form['access_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Access Key'),
      '#description' => $this->t('Access Key to use the tracdelight API'),
      '#maxlength' => 128,
      '#default_value' => $config->get('access_key'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('tracdelight_client.config')
      ->set('access_key', $form_state->getValue('access_key'))
      ->save();
  }

}
