<?php

namespace Drupal\advertising_products_tracdelight\Plugin\AdvertisingProducts\Provider;

use Drupal\advertising_products\AdvertisingProductsProviderBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\tracdelight\Tracdelight;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides advertising products provider plugin for Tracdelight.
 *
 * @AdvertisingProductsProvider(
 *   id = "tracdelight_provider",
 *   name = @Translation("Tracdelight product provider")
 * )
 */
class TracdelightProvider extends AdvertisingProductsProviderBase {

  /**
   * @var string
   */
  public static $providerDomain = 'td.oo34.net';

  /**
   * @var string
   */
  public static $productBundle = 'advertising_product_tracdelight';

  /**
   * @var \Drupal\tracdelight\Tracdelight
   */
  protected $tracdelightService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, Tracdelight $tracdelight) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityManager);
    $this->tracdelightService = $tracdelight;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('tracdelight')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProductIdFromUrl($url) {
    // Extract product ID
    if ($product_id = $this->tracdelightService->getEinFromUri($url)) {
      return $product_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchProductOnTheFly($product_id) {
    // Retrieve product
    if ($product = $this->queryProduct($product_id)) {
      // Save product
      $fetchedProduct = $this->saveProduct($product);
      return $fetchedProduct;
    }
    return $product;
  }

  /**
   * {@inheritdoc}
   */
  public function queryProduct($product_id) {
    // Retrieve product information from API
    $query = [
      'EIN' => $product_id
    ];
    // The request will throw an exception in case the product is not available
    try {
      $products = $this->tracdelightService->queryProducts($query);
    } catch (ClientException $ex) {}

    return reset($products);
  }

  /**
   * {@inheritdoc}
   */
  public function saveProduct($product_data, $entity_id = NULL) {
    // Retrieve product image
    $image = $this->tracdelightService->retrieveImage($product_data);
    if ($image) {
      $suffix = '.jpg';
      if ($image->getHeader('content-type')[0] == 'image/png')  {
        $suffix = '.png';
      }
      $file = file_save_data($image->getBody(), 'public://' . implode('-', [$this::$productBundle, $product_data['ein']]) . $suffix, FILE_EXISTS_REPLACE);
      image_path_flush($file->getFileUri());
    }

    if ($entity_id) {
      // Update existing product entity
      $product = $this->entityManager->getStorage('advertising_product')->load($entity_id);
    }
    else {
      // Create new product entity
      $item['type'] = $this::$productBundle;
      $item['product_provider'] = $this->getPluginId();
      $item['product_id'] = $product_data['ein'];
      $product = $this->entityManager->getStorage('advertising_product')->create($item);
    }

    $product->product_name->value = $product_data['title'];
    $product->product_description->value = $product_data['description'];
    if ($file) {
      $product->product_image->target_id = $file->id();
      $product->product_image->alt = $product_data['title'];
    }
    $product->product_price->value = $product_data['list_price']['current'];
    $product->product_currency->value = $product_data['list_price']['currency'];
    $product->product_brand->value = $product_data['brand'];
    $product->product_url->uri = $product_data['tracking'];
    $product->product_url->options = array();
    $product->product_shop->value = $product_data['shop'];
    // Published by default
    $product->status->value = 1;

    // Save product entity
    $product->save();
    return $product;
  }

  /**
   * {@inheritdoc}
   */
  public function updateProduct($product_id, $entity_id) {
    // Retrieve product data
    if ($product = $this->queryProduct($product_id)) {
      // Update product entity
      $this->saveProduct($product, $entity_id);
    }
    else {
      // Set product as inactive.
      $this->setProductInactive($entity_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setProductInactive($entity_id) {
    $product = $this->entityManager->getStorage('advertising_product')->load($entity_id);
    $product->status->value = 0;
    $product->save();
  }
}
