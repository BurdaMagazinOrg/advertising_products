<?php

namespace Drupal\advertising_products;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines interface for advertising product providers.
 */
interface AdvertisingProductsProviderInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Extracts the product ID from given url.
   *
   * @param string $url
   */
  public function getProductIdFromUrl($url);

  /**
   * Creates advertising product.
   *
   * @param string $product_id
   */
  public function fetchProductOnTheFly($product_id);

  /**
   * Retrieves product data through provider API.
   *
   * @param type $product_id
   */
  public function queryProduct($product_id);

  /**
   * Creates advertising product entity.
   *
   * @param mixed $product_data
   */
  public function saveProduct($product_data, $entity_id = NULL);

  /**
   * Updates advertising product entity.
   *
   * @param string $product_id
   * @param string $entity_id
   */
  public function updateProduct($product_id, $entity_id);

  /**
   * Changes product status to "0"
   *
   * @param string $entity_id
   */
  public function setProductInactive($entity_id);

}
