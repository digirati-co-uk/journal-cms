<?php

namespace Drupal\jcms_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\Migration;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process the episode chapter values into paragraphs.
 *
 * @MigrateProcessPlugin(
 *   id = "jcms_episode_chapter"
 * )
 */
class JCMSEpisodeChapter extends AbstractJCMSContainerFactoryPlugin {

  use JMCSCheckMarkupTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value)) {
      $values = [
        'type' => 'episode_chapter',
        'field_block_title' => [
          'value' => $this->checkMarkup($value['title']),
        ],
        'field_chapter_time' => [
          'value' => $value['time'],
        ],
      ];

      if (!empty($value['impact_statement'])) {
        $values['field_block_html'] = [
          'value' => $this->checkMarkup($value['impact_statement']),
          'format' => 'basic_html',
        ];
      }

      if (!empty($value['content'])) {
        $values['field_chapter_content'] = [];
        foreach ($value['content'] as $content) {
          $value = $content['source'];
          if ($content['type'] == 'collection') {
            $value = 'collections/' . $this->migrationDestionationIDs('jcms_collections_db', $value, $migrate_executable, $row, $destination_property);
          }
          $values['field_chapter_content'][] = [
            'value' => $value,
          ];
        }
      }

      $paragraph = Paragraph::create($values);
      $paragraph->save();
      return [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    return NULL;
  }

}
