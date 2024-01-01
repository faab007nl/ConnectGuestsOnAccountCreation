<?php declare(strict_types=1);

namespace ConnectGuestsOnAccountCreation\Service;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;

class ConnectGuestsOrderMover
{
    private EntityRepository $orderCustomerRepository;

    public function __construct(EntityRepository $orderCustomerRepository)
    {
        $this->orderCustomerRepository = $orderCustomerRepository;
    }

    public function moveOrders(CustomerEntity $sourceCustomer, CustomerEntity $targetCustomer, Context $context): void
    {
        // Find orders associated with the source customer
        $orderCustomers = $this->getOrderCustomersByCustomerEmail($sourceCustomer, $context);

        /** @var OrderCustomerEntity $orderCustomer */
        // Update the customer ID for each order
        foreach ($orderCustomers->getElements() as $orderCustomer){
            $this->updateOrderCustomer($orderCustomer, $targetCustomer, $context);
        }
    }

    private function getOrderCustomersByCustomerEmail(CustomerEntity $customer, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $customer->getEmail()),
        );
        return $this->orderCustomerRepository->search($criteria, $context)->getEntities();
    }

    private function updateOrderCustomer(OrderCustomerEntity $orderCustomer, CustomerEntity $newCustomer, $context): void
    {
        $this->orderCustomerRepository->update([
            [
                'id' => $orderCustomer->getId(),
                'customerId' => $newCustomer->getId(),
                'title' => "Transferred from guest account"
            ]
        ], $context);
    }
}