<?php

namespace Drupal\advertising_products\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'advertising_products_autocomplete_widget' widget.
 *
 * @FieldWidget(
 *   id = "advertising_products_autocomplete_widget",
 *   label = @Translation("Advertising products autocomplete"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class AdvertisingProductsAutocompleteWidget extends EntityReferenceAutocompleteWidget {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['target_id']['#selection_handler'] = 'advertising_products:product';

    return $element;
  }

}
