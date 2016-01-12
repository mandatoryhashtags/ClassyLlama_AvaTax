<?php

namespace ClassyLlama\AvaTax\Framework\Interaction\Cacheable;

use AvaTax\GetTaxRequest;
use AvaTax\GetTaxResult;
use ClassyLlama\AvaTax\Framework\Interaction\MetaData\MetaDataObject;
use ClassyLlama\AvaTax\Framework\Interaction\MetaData\MetaDataObjectFactory;
use ClassyLlama\AvaTax\Framework\Interaction\Tax;
use ClassyLlama\AvaTax\Model\Config;
use ClassyLlama\AvaTax\Model\Logger\AvaTaxLogger;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class TaxService
{
    /**
     * @var CacheInterface
     */
    protected $cache = null;

    /**
     * @var AvaTaxLogger
     */
    protected $avaTaxLogger = null;

    /**
     * @var Tax
     */
    protected $taxInteraction = null;

    /**
     * @var MetaDataObject
     */
    protected $metaDataObject = null;

    /**
     * @var null
     */
    protected $type = null;

    /**
     * @param CacheInterface $cache
     * @param AvaTaxLogger $avaTaxLogger
     */
    public function __construct(
        CacheInterface $cache,
        AvaTaxLogger $avaTaxLogger,
        Tax $taxInteraction,
        MetaDataObjectFactory $metaDataObjectFactory,
        $type = null
    ) {
        $this->cache = $cache;
        $this->avaTaxLogger = $avaTaxLogger;
        $this->taxInteraction = $taxInteraction;
        $this->metaDataObject = $metaDataObjectFactory->create(['metaDataProperties' => \ClassyLlama\AvaTax\Framework\Interaction\Tax::$validFields]);
    }

    /**
     * Cache validated response
     *
     * @author Jonathan Hodges <jonathan@classyllama.com>
     * @param GetTaxRequest $getTaxRequest
     * @return GetTaxResult
     * @throws LocalizedException
     */
    public function getTax(GetTaxRequest $getTaxRequest)
    {
        $cacheKey = $this->getCacheKey($getTaxRequest);
        $getTaxResult = @unserialize($this->cache->load($cacheKey));

        if ($getTaxResult instanceof GetTaxResult) {
            $this->avaTaxLogger->addDebug('Loaded \AvaTax\GetTaxResult from cache.', [
                'result' => var_export($getTaxResult, true),
                'cache_key' => $cacheKey
            ]);
            return $getTaxResult;
        }

        $getTaxResult = $this->taxInteraction->getTaxService($this->type)->getTax($getTaxRequest);
        $this->avaTaxLogger->addDebug('Loaded \AvaTax\GetTaxResult from SOAP.', [
            'request' => var_export($getTaxRequest, true),
            'result' => var_export($getTaxResult, true),
        ]);

        $serializedGetTaxResult = serialize($getTaxResult);
        $this->cache->save($serializedGetTaxResult, $cacheKey, [Config::AVATAX_CACHE_TAG]);
        return $getTaxResult;
    }

    /**
     * Create cache key by calling specified methods and concatenating and hashing
     *
     * @author Jonathan Hodges <jonathan@classyllama.com>
     * @param $object
     * @return string
     * @throws LocalizedException
     */
    protected function getCacheKey($object)
    {
        return $this->metaDataObject->getCacheKeyFromObject($object);
    }

    /**
     * Pass all undefined method calls through to Tax Service
     *
     * @author Jonathan Hodges <jonathan@classyllama.com>
     * @param $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name , array $arguments)
    {
        return call_user_func_array([$this->taxInteraction->getTaxService($this->type), $name], $arguments);
    }

}