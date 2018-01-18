<?php /**
 * @file
 * Contains \Drupal\node_conversion\Controller\DefaultController.
 */

namespace Drupal\node_conversion\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the node_conversion module.
 */
class DefaultController extends ControllerBase {

  public function node_conversion_templates() {
    $rows = [];
    $headers = [
      t("Name"),
      t('Machine name'),
      t("Source type"),
      t("Dest type"),
      t("Operations"),
    ];
    $templates = node_conversion_load_all_templates();
    foreach ($templates as $row) {
      $can_delete = isset($row->nctid);
      $name = l($row->name, 'admin/structure/node_conversion_templates/' . $row->machine_name);
      $operations = [];
      if ($can_delete) {
        $operations[] = l(t("Edit"), 'admin/structure/node_conversion_templates/edit/' . $row->nctid);
        $operations[] = l(t("Delete"), 'admin/structure/node_conversion_templates/delete/' . $row->nctid);
      }
      $rows[] = [
        $name,
        $row->machine_name,
        $row->source_type,
        $row->destination_type,
        implode(' ', $operations),
      ];
    }
    $output = theme('table', ['header' => $headers, 'rows' => $rows]);

    return $output;
  }

  public function node_conversion_template_info($machine_name) {
    $output = '';
    $rows = [];
    $headers = [t("Property"), t("Value")];
    $row = node_conversion_load_template($machine_name);
    $template_id = isset($row['nctid']) ? $row['nctid'] : t('In Code');
    $rows[] = [t("Template id"), $template_id];
    $rows[] = [t("Name"), $row['name']];
    $rows[] = [t("Machine name"), $row['machine_name']];
    $rows[] = [t("Source type"), $row['source_type']];
    $rows[] = [t("Destination type"), $row['destination_type']];
    $data = $row['data'];
    if ($data['no_fields'] == FALSE) {
      $source_fields_string = implode(', ', $data['fields']['source']);
      $dest_fields_string = implode(', ', $data['fields']['destination']);
      $rows[] = [t("Source fields"), $source_fields_string];
      $rows[] = [t("Destination fields"), $dest_fields_string];
    }
    if (!empty($data['hook_options'])) {
      $rows[] = [t("Hook options"), print_r($data['hook_options'], TRUE)];
    }
    $output .= theme('table', ['header' => $headers, 'rows' => $rows]);

    return $output;
  }

}
