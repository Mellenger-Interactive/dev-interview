<?php

namespace Drupal\mellon\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Configure MellON settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mellon_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mellon.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $uid = $this->config('mellon.settings')->get('default_user') ?? 1;
    $user = User::load($uid);
    $form['default_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Default user'),
      '#description' => $this->t('*************'),
      '#target_type' => 'user',
      '#default_value' => $user
    ];

    $form['user_mapping'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('User mapping'),
      '#description' => $this->t('**********'),
      '#default_value' => $this->config('mellon.settings')->get('user_mapping')
    ];

    $form['enforce_user_mapping'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce mapping'),
      '#description' => $this->t('**********'),
      '#default_value' => $this->config('mellon.settings')->get('enforce_user_mapping'),
      '#states' => [
        'visible' => [
          ':input[name="user_mapping"]' => ['checked' => TRUE],
        ]
      ]
    ];
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
    $this->config('mellon.settings')
      ->set('default_user', $form_state->getValue('default_user'))
      ->set('user_mapping', $form_state->getValue('user_mapping'))
      ->set('enforce_user_mapping', $form_state->getValue('enforce_user_mapping'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
