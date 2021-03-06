<?php

namespace Drupal\jcms_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Process the content values into a the field_content structure.
 *
 * @MigrateProcessPlugin(
 *   id = "jcms_press_package_content"
 * )
 */
class JCMSPressPackageContent extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $section = (isset($this->configuration['section']) && in_array($this->configuration['section'], ['summary', 'relatedContent', 'mediaContacts', 'about'])) ? $this->configuration['section'] : 'content';
    $breakup = $this->breakupContent($value);

    return $breakup[$section];
  }

  function breakupContent($content) {
    $content = preg_replace("~&(nbsp|#xA0);~", ' ', trim($content));
    $content = preg_replace("~( ){2,}~", ' ', $content);

    $breakup = [
      'summary' => NULL,
      'content' => preg_replace("~^(.*)<[^>]+>\\s*Reference.*~s", '$1', $content),
      'relatedContent' => NULL,
      'mediaContacts' => NULL,
      'about' => NULL,
    ];

    if (strpos($breakup['content'], '<strong>') !== FALSE && strpos($breakup['content'], '<strong>') < 50) {
      $summary_regex = "~^(.*<[^>]+>)(\s*<strong>\s*)(?P<summary>.*)(\s*</strong>\s*)(<\/[^>]+>\s*.*)~s";
      if (preg_match($summary_regex, $breakup['content'], $match)) {
        $breakup['summary'] = $match['summary'];
        $breakup['content'] = preg_replace($summary_regex, '$1$5', $breakup['content']);
      }
    }

    if (preg_match_all("~10\\.7554/elife\\.(?P<article_id>[0-9]{5})~i", $content, $matches)) {
      $breakup['relatedContent'] = [];
      foreach ($matches['article_id'] as $article_id) {
        if (!in_array($article_id, $breakup['relatedContent'])) {
          $breakup['relatedContent'][] = $article_id;
        }
      }

      foreach ($breakup['relatedContent'] as $k => $article_id) {
        $breakup['relatedContent'][$k] = [
          'type' => 'article',
          'source' => $article_id,
        ];
      }
    }

    if (preg_match("~(?P<media_contacts>Media contacts.*>\\s*About)~s", $content, $match)) {
      $split = preg_split("~\\s*\\n+\\s*~i", trim(strip_tags($match['media_contacts'])));
      $split = array_slice($split, 1, count($split) - 2);
      if ($contacts = $this->breakupMediaContacts($split)) {
        $breakup['mediaContacts'] = $contacts;
      }
    }

    if (preg_match("~(?P<about>>\\s*about( elife)?\\s*<.*<p>.*</p>.*<p>.*)~si", $content, $match)) {
      $split = preg_split("~\\s*\\n+\\s*~i", trim($match['about']));
      $split = array_slice($split, 1, count($split) - 1);
      $breakup['about'] = implode("\n\n", $split);
    }

    return $breakup;
  }

  function breakupMediaContacts($contacts_combined) {
    $contacts = [];
    $default_contact = [
      'name' =>  [],
      'affiliations' => [],
      'emailAddresses' => [],
      'phoneNumbers' => [],
    ];
    $contact = $default_contact;

    $clean_up = function ($contact, $strip_elife = TRUE) {
      if (empty($contact['name']) || ($strip_elife && !empty($contact['elife']))) {
        return [];
      }
      foreach ($contact as $k => $contact_item) {
        if (empty($contact_item)) {
          unset($contact[$k]);
        }
      }
      return $contact;
    };

    foreach ($contacts_combined as $contacts_item) {
      if (preg_match("~^(?P<name>[^,]+),\\s+(?P<aff>.*)$~", $contacts_item, $match)) {
        if (!empty($contact) && $clean_contact = $clean_up($contact)) {
          $contacts[] = $clean_contact;
        }
        $contact = $default_contact;
        $contact['name'] = [
          'preferred' => $match['name'],
          'index' => preg_replace("~^(.*)\\s+([^\\s]+)$~", '$2, $1', trim($match['name'])),
        ];
        $contact['affiliations'] = [['name' => [trim($match['aff'])]]];
        if (preg_match("~eLife~i", $match['aff'])) {
          $contact['elife'] = TRUE;
        }
      }
      elseif (preg_match("~@~", $contacts_item)) {
        $contact['emailAddresses'][] = trim($contacts_item);
      }
      else {
        $contacts_item = preg_replace("~\\s+~", '', $contacts_item);
        $phone_numbers = preg_split('(or|,|;|\|)', $contacts_item);
        foreach ($phone_numbers as $phone_number) {
          $phone_number = preg_replace("~ext[^0-9]*~", '#', $phone_number);
          $phone_number = preg_replace("~[^0-9\\-\\+#]~", '', $phone_number);
          $phone_number = preg_replace("~\\+0~", '0', $phone_number);
          $phone_number = preg_replace("~#~", ';ext=', $phone_number);
          if (!empty($phone_number)) {
            $phone_util = PhoneNumberUtil::getInstance();
            $phone_proto = $phone_util->parse($phone_number, 'GB');
            $contact['phoneNumbers'][] = $phone_util->format($phone_proto, PhoneNumberFormat::E164);
          }
        }
      }
    }
    if (!empty($contact) && $clean_contact = $clean_up($contact)) {
      $contacts[] = $clean_contact;
    }

    return $contacts;
  }

}
