<?php

namespace Drupal\advertising_products_amazon;

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service class Amazon
 */
class Amazon {

  /**
   * @var string
   */
  protected $accessKey;

  /**
   * @var string
   */
  protected $accessSecret;

  /**
   * @var string
   */
  protected $associatesId;

  /**
   * @var string
   */
  protected $locale;

  /**
   * @var \ApaiIO\ApaiIO
   */
  protected $apaiIO;

  /**
   * Create instance of Amazon class.
   *
   * @param ConfigFactoryInterface $configFactory
   * @throws \Exception
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $configuration = $configFactory->get('amazon.settings');
    $this->accessKey = $configuration->get('access_key');
    $this->accessSecret = $configuration->get('access_secret');
    $this->associatesId = $configuration->get('associates_id');
    $this->locale = $configuration->get('locale');
    // Amazon access configuuration is required
    if (!isset($this->accessKey) || !isset($this->accessSecret) || !isset($this->associatesId)) {
      throw new \Exception('Missing Amazon access configuration.');
    }
  }

  /**
   * Extract ASIN from given url.
   *
   * @param string $url
   * @return mixed
   */
  public function getAsinFromUri($url) {

    // regex from http://littlegreenfootballs.com/article/44256_Tech_Note-_A_Regular_Expression_to_Extract_the_ASIN_Product_Code_From_Any_Amazon_URL
    $regex = '~
    (?:www\.)?              # optionally starts with www.
    ama?zo?n\.              # also allow shortened amzn.com URLs
    (?:
        com                 # match all Amazon domains
        |
        ca
        |
        co\.uk
        |
        co\.jp
        |
        de
        |
        fr
    )
    /
    (?:                     # here comes the stuff before the ASIN
        exec/obidos/ASIN/   # the possible components of a URL
        |
        o/
        |
        gp/product/
        |
        (?:                 # the dp/ format may contain a title
            (?:[^"\'/]*)/   # anything but a slash or quote
        )?                  # optional
        dp/
        |                   # if short format, nothing before ASIN
    )
    ([A-Z0-9]{10})          # capture group $1 contains the ASIN
    (?:                     # everything after the ASIN
        (?:/|\?|\#)         # beginning with /, ? or #
        (?:[^"\'\s]*)       # everything up to quote or white space
    )?                      # optional
~isx';

    if (preg_match($regex, $url, $matches)) {
      return $matches[1];
    }
    return FALSE;
  }

  /**
   * Fetch product data from API.
   *
   * @param string $product_id
   * @return array
   */
  public function itemLookup($product_id) {
    // Prepare apaiIO object
    $conf = new GenericConfiguration();
    $conf
      ->setCountry($this->locale)
      ->setAccessKey($this->accessKey)
      ->setSecretKey($this->accessSecret)
      ->setAssociateTag($this->associatesId)
      ->setResponseTransformer('\Drupal\amazon\LookupXmlToItemsArray');
    $this->apaiIO = new ApaiIO($conf);
    // Prepare Lookup object
    $lookup = new Lookup();
    $lookup->setItemId($product_id);
    $lookup->setResponseGroup(['ItemAttributes','Images','Offers']);
    // Do the API call
    $results = $this->apaiIO->runOperation($lookup);
    // Return product response
    return $results;
  }

  /**
   * Fetch product image from api.
   *
   * @param \SimpleXMLElement $product
   *   A product XML fetched from the api
   * @return \Psr\Http\Message\ResponseInterface
   *   Response from server
   * @throws \Exception
   */
  public function retrieveImage($product) {
    $image_path = FALSE;
    // Find the primary image set and fetch the large image url
    foreach ($product->ImageSets->children() as $imageSet) {
      if ($imageSet['Category'] == 'primary') {
        $image_path = (string)$imageSet->LargeImage->URL;
        break;
      }
    }

    if ($image_path) {
      $tries = 0;
      do {
        $tries++;
        $image = \Drupal::httpClient()->request('GET', $image_path);
      } while (($image->getStatusCode() != 200 || !$image->getBody()) && $tries < 3);

      if (!$image->getBody()) {
        $error_msg = 'Error Message: ' . $image->getStatusCode() ? $image->getStatusCode() : "Couldn't retrieve image";
        throw new \Exception($error_msg, $product->ASIN, 'original');
      }

      if (!in_array($image->getHeader('content-type')[0], array('image/png', 'image/jpeg'))) {
        $error_msg = 'Error Message: Unexpected content type "' . $image->getHeader('content-type') . '"';
        throw new \Exception($error_msg, $product->ASIN, 'original');
      }
    }

    return $image;
  }
}

