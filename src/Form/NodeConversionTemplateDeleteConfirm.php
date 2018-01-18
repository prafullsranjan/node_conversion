<?php

/**
 * @file
 * Contains \Drupal\node_conversion\Form\NodeConversionTemplateDeleteConfirm.
 */

namespace Drupal\node_conversion\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class NodeConversionTemplateDeleteConfirm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_conversion_template_delete_confirm';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $template_id = NULL) {
    $form['template_id'] = [
      '#type' => 'value',
      '#value' => $template_id,
    ];

    $form['delete_action'] = [
      '#type' => 'checkbox',
      '#title' => t("Delete action?"),
      '#default_value' => 1,
      '#description' => t("If the option is checked, all actions that contain this template will be erased. Otheriwise, the actions' template will be set to none."),
    ];

    return confirm_form($form, t('Are you sure you want to delete this template?'), isset($_GET['destination']) ? $_GET['destination'] : 'admin/structure/node_conversion_templates', t('This action cannot be undone.'), t('Delete'), t('Cancel'));
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['confirm'])) {
      ctools_include('export');
      $template = ctools_export_load_object(node_conversion_TEMPLATE_TABLE, 'conditions', [
        'nctid' => $form_state->getValue(['template_id'])
        ]);
      $template = array_shift($template);
      node_conversion_delete_template($template);

      if ($form_state->getValue(['delete_action']) == 1) {
        db_delete('actions')
          ->condition('callback', 'node_conversion_convert_action')
          ->condition('parameters', '%template";s:%:"' . $form_state->getValue(['template_id']) . '%', 'LIKE')
          ->execute();
      }
      else {
        $none = serialize(['template' => '0']);
        db_update('actions')
          ->fields(['parameters' => $none])
          ->condition('callback', 'node_conversion_convert_action')
          ->condition('parameters', '%template";s:%:"' . $form_state->getValue(['template_id']) . '%', 'LIKE')
          ->execute();
      }
    }
    $form_state->set(['redirect'], 'admin/structure/node_conversion_templates');
  }

}
?>
