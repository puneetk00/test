<?php 
namespace Synapse\Offer\Model\Overwrite;
use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddress;
use Magento\Customer\Api\Data\CustomerInterface as CustomerDataObject;
use Magento\Customer\Api\Data\RegionInterface as AddressRegion;
use Magento\Customer\Api\GroupManagementInterface as CustomerGroupManagement;
use Magento\Customer\Api\GroupRepositoryInterface as CustomerGroupRepository;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\Store;
use Magento\Tax\Api\TaxClassRepositoryInterface;
class Calculation extends \Magento\Tax\Model\Calculation
{
	 public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory $classesFactory,
        \Magento\Tax\Model\ResourceModel\Calculation $resource,
        CustomerAccountManagement $customerAccountManagement,
        CustomerGroupManagement $customerGroupManagement,
        CustomerGroupRepository $customerGroupRepository,
        CustomerRepository $customerRepository,
        PriceCurrencyInterface $priceCurrency,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        TaxClassRepositoryInterface $taxClassRepository,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
		$context, 
		$registry, 
		$scopeConfig,
		$taxConfig,
		$storeManager,
		$customerSession,
		$customerFactory,
		$classesFactory,
		$resource, 
		$customerAccountManagement,
		$customerGroupManagement,
		$customerGroupRepository,
		$customerRepository,
		$priceCurrency,
		$searchCriteriaBuilder,
		$filterBuilder,
		$taxClassRepository,
		$resourceCollection, 
		$data);
    }
	 public function getRateRequest(
        $shippingAddress = null,
        $billingAddress = null,
        $customerTaxClass = null,
        $store = null,
        $customerId = null
    ) {
        if ($shippingAddress === false && $billingAddress === false && $customerTaxClass === false) {
            return $this->getRateOriginRequest($store);
        }
        $address = new \Magento\Framework\DataObject();
        $basedOn = $this->_scopeConfig->getValue(
            \Magento\Tax\Model\Config::CONFIG_XML_PATH_BASED_ON,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        if ($shippingAddress === false && $basedOn == 'shipping' || $billingAddress === false && $basedOn == 'billing'
        ) {
            $basedOn = 'default';
        } else {
            if (($billingAddress === null || !$billingAddress->getCountryId())
                && $basedOn == 'billing'
                || ($shippingAddress === null || !$shippingAddress->getCountryId())
                && $basedOn == 'shipping'
            ) {
                if ($customerId) {
                    //fallback to default address for registered customer
                    try {
                        $defaultBilling = $this->customerAccountManagement->getDefaultBillingAddress($customerId);
                    } catch (NoSuchEntityException $e) {
                    }

                    try {
                        $defaultShipping = $this->customerAccountManagement->getDefaultShippingAddress($customerId);
                    } catch (NoSuchEntityException $e) {
                    }

                    if ($basedOn == 'billing' && isset($defaultBilling) && $defaultBilling->getCountryId()) {
                        $billingAddress = $defaultBilling;
                    } elseif ($basedOn == 'shipping' && isset($defaultShipping) && $defaultShipping->getCountryId()) {
                        $shippingAddress = $defaultShipping;
                    } else {
                        $basedOn = 'default';
                    }
                } else {
                    //fallback for guest
                    if ($basedOn == 'billing' && is_object($shippingAddress) && $shippingAddress->getCountryId()) {
                        $billingAddress = $shippingAddress;
                    } elseif ($basedOn == 'shipping' && is_object($billingAddress) && $billingAddress->getCountryId()) {
                        $shippingAddress = $billingAddress;
                    } else {
                        $basedOn = 'default';
                    }
                }
            }
        }

        switch ($basedOn) {
            case 'billing':
                $address = $billingAddress;
                break;
            case 'shipping':
                $address = $shippingAddress;
                break;
            case 'origin':
                $address = $this->getRateOriginRequest($store);
                break;
            case 'default':
				$country_code = $this->getCountryCode();
                $address->setCountryId($country_code)->setRegionId(
                    $this->_scopeConfig->getValue(
                        \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_REGION,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $store
                    )
                )->setPostcode(
                    $this->_scopeConfig->getValue(
                        \Magento\Tax\Model\Config::CONFIG_XML_PATH_DEFAULT_POSTCODE,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $store
                    )
                );
                break;
            default:
                break;
        }

        if ($customerTaxClass === null || $customerTaxClass === false) {
            if ($customerId) {
                $customerData = $this->customerRepository->getById($customerId);
                $customerTaxClass = $this->customerGroupRepository
                    ->getById($customerData->getGroupId())
                    ->getTaxClassId();
            } else {
                $customerTaxClass = $this->customerGroupManagement->getNotLoggedInGroup()->getTaxClassId();
            }
        }

        $request = new \Magento\Framework\DataObject();
        //TODO: Address is not completely refactored to use Data objects
        if ($address->getRegion() instanceof AddressRegion) {
            $regionId = $address->getRegion()->getRegionId();
        } else {
            $regionId = $address->getRegionId();
        }
        $request->setCountryId($address->getCountryId())
            ->setRegionId($regionId)
            ->setPostcode($address->getPostcode())
            ->setStore($store)
            ->setCustomerClassId($customerTaxClass);
        return $request;
    }
	public function getVisIpAddr() { 
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) { 
			return $_SERVER['HTTP_CLIENT_IP']; 
		} 
		else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { 
			return $_SERVER['HTTP_X_FORWARDED_FOR']; 
		} 
		else { 
			return $_SERVER['REMOTE_ADDR']; 
		} 
	} 
	public function getCountryCode(){
		$ip = $this->getVisIpAddr();
 
		$ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip)); 
		return $ipdat->geoplugin_countryCode;
	}
}