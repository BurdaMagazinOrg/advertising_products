<?php

namespace Drupal\advertising_products_amazon\Plugin\AdvertisingProducts\Provider;

use Drupal\advertising_products\AdvertisingProductsProviderBase;
use Drupal\advertising_products_amazon\Amazon;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides advertising products provider plugin for Amazon.
 *
 * @AdvertisingProductsProvider(
 *   id = "amazon_provider",
 *   name = @Translation("Amazon product provider")
 * )
 */
class AmazonProvider extends AdvertisingProductsProviderBase {

  /**
   * @var string
   */
  public static $providerDomain = 'amazon';

  /**
   * @var string
   */
  public static $productBundle = 'advertising_product_amazon';

  /**
   * @var Drupal\advertising_products_amazon\Amazon
   */
  protected $amazonService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entityManager, Amazon $amazonService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityManager);
    $this->amazonService = $amazonService;
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
      $container->get('advertising_products_amazon.amazon')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProductIdFromUrl($url) {
    // Extract product ID
    if ($product_id = $this->amazonService->getAsinFromUri($url)) {
      return $product_id;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchProductOnTheFly($product_id) {
    // Retrieve product
    $product_request = $this->queryProduct($product_id);
    if (!empty($product_request['items'])) {
      $product = reset($product_request['items']);
      // Save product
      $fetchedProduct = $this->saveProduct($product);
      return $fetchedProduct;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function queryProduct($product_id) {
    // Retrieve product information from API
    $response = $this->amazonService->itemLookup($product_id);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function saveProduct($product_data, $entity_id = NULL) {
    // Retrieve product image
    $image = $this->amazonService->retrieveImage($product_data);
    if ($image) {
      $suffix = '.jpg';
      if ($image->getHeader('content-type')[0] == 'image/png')  {
        $suffix = '.png';
      }
      $file = file_save_data($image->getBody(), 'public://' . implode('-', [$this::$productBundle, (string)$product_data->ASIN]) . $suffix, FILE_EXISTS_REPLACE);
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
      $item['product_id'] = (string)$product_data->ASIN;
      $product = $this->entityManager->getStorage('advertising_product')->create($item);
    }

    $product->product_name->value = Unicode::substr((string)$product_data->ItemAttributes->Title, 0, 255);
    $product->product_description->value = '';
    if ($file) {
      $product->product_image->target_id = $file->id();
      $product->product_image->alt = (string)$product_data->ItemAttributes->Title;
    }
    $product->product_brand->value = Unicode::substr((string)$product_data->ItemAttributes->Brand, 0, 50);
    $product->product_url->uri = (string)$product_data->DetailPageURL;
    $product->product_url->options = array();
    $product->product_shop->value = 'Amazon';
    // Unpublished by default
    $product->status->value = 0;
    // Fill price if we have an offer and set as published
    if ((int)$product_data->Offers->TotalOffers > 0) {
      $product->status->value = 1;
      $product->product_price->value = (int)$product_data->Offers->Offer->OfferListing->Price->Amount / 100;
      $product->product_currency->value = (string)$product_data->Offers->Offer->OfferListing->Price->CurrencyCode;
    }

    // Save product entity
    $product->save();
    return $product;
  }

  /**
   * {@inheritdoc}
   */
  public function updateProduct($product_id, $entity_id) {
    // Retrieve product data
    $product_request = $this->queryProduct($product_id);
    if (!empty($product_request['items'])) {
      // Update product entity
      $product = reset($product_request['items']);
      $this->saveProduct($product, $entity_id);
    }
    // No items returned and no lookup error
    // We assume that this means that product doesn't exist
    elseif (empty($product_request['items'] && empty($product_request['errors']['lookup_error']))) {
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
