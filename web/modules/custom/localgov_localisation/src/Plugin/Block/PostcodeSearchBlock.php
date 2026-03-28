<?php

namespace Drupal\localgov_localisation\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an "In my area" postcode search block.
 *
 * Place this block on any page to allow users to search by postcode
 * and see localised information (bin days, councillors, planning, etc).
 *
 * @Block(
 *   id = "localgov_localisation_postcode_search",
 *   admin_label = @Translation("In My Area - Postcode Search"),
 *   category = @Translation("LocalGov Localisation"),
 * )
 */
class PostcodeSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'localgov_localisation_postcode_block',
      '#attached' => [
        'library' => ['localgov_localisation/localisation'],
        'drupalSettings' => [
          'localgov_localisation' => [
            'ajax_url' => '/api/localisation/search/',
          ],
        ],
      ],
    ];
  }

}
