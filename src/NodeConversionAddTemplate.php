<?php
namespace Drupal\node_conversion;

class NodeConversionAddTemplate extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_conversion_add_template';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $template = NULL) {
    $form = [];

    // @TODO Figure out how to let a user edit templates that are stored in code via features.
    $is_editing_mode = FALSE;
    if (!empty($template)) {
      $is_editing_mode = TRUE;
      $form_state->setStorage($is_editing_mode);
    }

    /* Setting the steps */
    if ($form_state->getValue(['step'])) {
      $op = 'choose_destination_type';
    }
    elseif ($form_state->getValue(['step']) == 'choose_destination_type') {
      $op = 'choose_destination_fields';
    }
    $form['step'] = [
      '#type' => 'value',
      '#value' => $op,
    ];

    if ($op == 'choose_destination_type') {
      // Get available content types
      $to_types = node_conversion_return_access_node_types('to');
      $from_types = node_conversion_return_access_node_types('from');
      if ($to_types != FALSE && $from_types != FALSE) {
        $form['template_name'] = [
          '#type' => 'textfield',
          '#title' => t("Template name"),
          '#required' => TRUE,
        ];
        $form['machine_name'] = [
          '#type' => 'machine_name',
          '#title' => t('Machine name'),
          '#machine_name' => [
            'exists' => 'node_conversion_machine_name_check',
            'source' => [
              'template_name'
              ],
          ],
          '#required' => TRUE,
        ];
        $form['source_type'] = [
          '#type' => 'select',
          '#title' => t("Source type"),
          '#options' => $from_types,
        ];
        $form['dest_type'] = [
          '#type' => 'select',
          '#title' => t("Destination type"),
          '#options' => $to_types,
        ];
        $form['create_action'] = [
          '#type' => 'checkbox',
          '#title' => t("Create action?"),
          '#description' => t("If the option is checked, an action named Convert *Template name* will be created."),
        ];

        if ($is_editing_mode) {
          $form['template_name']['#default_value'] = $template['name'];
          $form['machine_name']['#default_value'] = $template['machine_name'];
          $form['machine_name']['#disabled'] = TRUE;
          $form['source_type']['#default_value'] = $template['source_type'];
          $form['dest_type']['#default_value'] = $template['destination_type'];

          // @TODO Fix action when editing.
          $form['create_action']['#access'] = FALSE;
        }

      }
      else {
        $form['no_types'] = [
          '#type' => 'markup',
          '#value' => t("You don't have access to any node types."),
        ];
      }
    }
    elseif ($op == 'choose_destination_fields') {
      // Get the fields of the source type
      $source_fields = field_info_instances('node', $form_state->getStorage());
      $fields_info = field_info_fields();
      // In case there are no fields, just convert the node type
      if (count($source_fields) == 0) {
        $no_fields = TRUE;
      }
      else {
        $no_fields = FALSE;
        // Get the destination type fields
        $dest_fields = field_info_instances('node', $form_state->getStorage());
        $i = 0;
        foreach ($source_fields as $source_field_name => $source_field) {
          ++$i;
          $options = [];
          $options['discard'] = 'discard';
          $options[APPEND_TO_BODY] = t('Append to body');
          $options[REPLACE_BODY] = t('Replace body');

          // Insert destination type fields into $options that are of the same type as the source.
          foreach ($dest_fields as $dest_field_name => $dest_field) {
            if ($fields_info[$source_field_name]['type'] == $fields_info[$dest_field_name]['type'] || ($fields_info[$source_field_name]['type'] == 'text_with_summary' && $fields_info[$dest_field_name]['type'] == 'text_long') || ($fields_info[$source_field_name]['type'] == 'text_long' && $fields_info[$dest_field_name]['type'] == 'text_with_summary')) {
              $options[$dest_field['field_name']] = $dest_field['field_name'];
            }
          }
          // Remember the source fields to be converted
          $form['source_field_' . $i] = [
            '#type' => 'value',
            '#value' => $source_field['field_name'],
          ];
          // The select populated with possible destination fields for each source field
          $form['dest_field_' . $i] = [
            '#type' => 'select',
            '#options' => $options,
            '#title' => (isset($source_field['label'])
            ? $source_field['label'] . ' (' . $source_field['field_name'] . ')'
            : $source_field['field_name']) . ' ' . t("should be inserted into"),
          ];

          if ($is_editing_mode) {
            // Populate the previous fields, only if the selected node types haven't changed from the original ones.
            $source_type = $form_state->getValue([
              'source_type'
              ]);
            $destination_type = $form_state->getValue(['dest_type']);
            if ($source_type == $template['source_type'] && $destination_type == $template['destination_type']) {
              $form['dest_field_' . $i]['#default_value'] = $template['data']['fields']['destination'][$i - 1];
            }
          }

        }
        $form['number_of_fields'] = [
          '#type' => 'value',
          '#value' => $i,
        ];
      }
      $form['no_fields'] = [
        '#type' => 'value',
        '#value' => $no_fields,
      ];

      // All node specific form options needed for types like book, forum, etc. are done here
      $hook_options = node_conversion_invoke_all('node_conversion_change', [
        'dest_node_type' => $form_state->getStorage()
        ], 'options');
      if (!empty($hook_options)) {
        $form['hook_options'] = $hook_options;
        array_unshift($form['hook_options'], [
          '#value' => '<strong>' . t("Also the following parameters are available:") . '</strong>'
          ]);
        $form['hook_options']['#tree'] = TRUE;
      }
    }

    if ($op == 'choose_destination_type' && $to_types != FALSE && $from_types != FALSE) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t("Next"),
      ];
    }
    elseif ($op == "choose_destination_fields") {
      $submit_label = $is_editing_mode ? t('Update') : t('Create');
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $submit_label,
        '#weight' => 100,
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['step']) == 'choose_destination_type') {
      if ($form_state->getValue(['source_type']) == $form_state->getValue([
        'dest_type'
        ])) {
        $form_state->setErrorByName('source_type', t('Please select different node types.'));
        $form_state->setErrorByName('dest_type', t('Please select different node types.'));
      }
    }
      // All node specific form validations needed for types like book, forum, etc. are done here
    elseif ($form_state->getValue([
      'step'
      ]) == 'choose_destination_fields') {
      node_conversion_invoke_all('node_conversion_change', [
        'dest_node_type' => $form_state->getStorage(),
        'form_state' => $form_state,
      ], 'options validate');
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['step']) == 'choose_destination_type') {
      $form_state->setRebuild(TRUE);
      $form_state->setStorage($form_state->getValue(['template_name']));
      $form_state->setStorage($form_state->getValue(['machine_name']));
      $form_state->setStorage($form_state->getValue(['source_type']));
      $form_state->setStorage($form_state->getValue(['dest_type']));
      $form_state->setStorage($form_state->getValue(['create_action']));
    }
    elseif ($form_state->getValue(['step']) == 'choose_destination_fields') {
      $no_fields = $form_state->getValue(['no_fields']);
      // If there are fields that can to be converted
      if ($no_fields == FALSE) {
        for ($i = 1; $i <= $form_state->getValue(['number_of_fields']); $i++) {
          $source_fields[] = $form_state->getValue(['source_field_' . $i]); //  Source fields
          $dest_fields[] = $form_state->getValue(['dest_field_' . $i]); // Destination fields
        }
      }

      if (!empty($form['hook_options'])) {
        $hook_options = $form_state->getValue(['hook_options']);
      }
      else {
        $hook_options = NULL;
      }

      $fields = [
        'source' => $source_fields,
        'destination' => $dest_fields,
      ];
      $data = [
        'fields' => $fields,
        'hook_options' => $hook_options,
        'no_fields' => $no_fields,
      ];
      $data = serialize($data);

      $is_editing_mode = !$form_state->getStorage();
      $id = node_conversion_save_template($form_state->getStorage(), $form_state->getStorage(), $form_state->getStorage(), $form_state->getStorage(), $data, $is_editing_mode);

      ctools_include('export');
      ctools_export_load_object_reset(node_conversion_TEMPLATE_TABLE);
      if ($is_editing_mode) {
        drupal_set_message(t("Template updated successfully."));
      }
      else {
        drupal_set_message(t("Template created successfully."));
      }

      // @TODO Fix being able to create action when editing. Need to find template_id.
      if ($form_state->getStorage() == 1 && !$is_editing_mode) {
        $template_id = $id;
        actions_save('node_conversion_convert_action', 'node', [
          'template' => $template_id
          ], 'Convert ' . $form_state->getStorage(), NULL);
      }
      // We clear the storage so redirect works
      $form_state->setStorage([]);
      $form_state->set(['redirect'], 'admin/structure/node_conversion_templates');
    }
  }

}
