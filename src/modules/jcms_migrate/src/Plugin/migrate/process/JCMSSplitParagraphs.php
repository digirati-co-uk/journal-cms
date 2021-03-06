<?php

namespace Drupal\jcms_migrate\Plugin\migrate\process;

use DOMDocument;
use DOMXPath;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Process the content values into paragraphs.
 *
 * @MigrateProcessPlugin(
 *   id = "jcms_split_paragraphs"
 * )
 */
class JCMSSplitParagraphs extends ProcessPluginBase {

  use JMCSCheckMarkupTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      $dom = new DomDocument();
      libxml_use_internal_errors(TRUE);
      $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $value . '</body></html>');
      libxml_clear_errors();
      $xpath = new DomXPath($dom);
      $children = $xpath->query('//body/*');
      $paragraphs = [];
      foreach ($children as $child) {
        $node_children = $child->childNodes;
        $innerHTML = '';
        foreach ($node_children as $node_child) {
          $innerHTML .= $dom->saveXML($node_child);
        }
        $innerHTML = trim($this->checkMarkup($innerHTML, 'basic_html'));
        if (!empty($innerHTML)) {
          if (!empty($this->configuration['strip_regex'])) {
            if (preg_match('/' . $this->configuration['strip_regex'] . '/i', $innerHTML)) {
              if (isset($this->configuration['break_regex']) && $this->configuration['break_regex'] === TRUE) {
                break;
              }
              else {
                continue;
              }
            }
          }
          $paragraphs[] = [
            'type' => 'paragraph',
            'text' => $innerHTML,
          ];
        }
      }
      return $paragraphs;
    }

    return NULL;
  }

}
