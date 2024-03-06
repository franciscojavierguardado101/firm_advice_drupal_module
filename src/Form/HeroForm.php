<?php

namespace Drupal\fa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the HeroForm form.
 */
class HeroForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fa_hero_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#theme'] = 'fa_hero_section';
    $service_vid = 'services';
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($service_vid);

    $options = [];
    $child_options = [];
    foreach ($terms as $term) {
      if ($term->parents[0] === "0") {
        $options[$term->tid] = $term->name;
      } else {
        $child_options[$term->parents[0]][$term->tid] = $term->name;
      }
    }

    $form['root_services'] = [
      '#type' => 'value',
      '#value' => $options,
    ];

    $form['root_child_services'] = [
      '#type' => 'value',
      '#value' => $child_options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}
