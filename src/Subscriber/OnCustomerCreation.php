<?php declare(strict_types=1);

namespace ConnectGuestsOnAccountCreation\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use ConnectGuestsOnAccountCreation\Service\ConnectGuestsOrderMover;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OnCustomerCreation implements EventSubscriberInterface
{
    private EntityRepository $customerRepository;
    private ConnectGuestsOrderMover $orderMover;

    public function __construct(EntityRepository $customerRepository, ConnectGuestsOrderMover $orderMover){
        $this->customerRepository = $customerRepository;
        $this->orderMover = $orderMover;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => 'onRegister'
        ];
    }

    public function onRegister(CustomerRegisterEvent $event): void
    {
        $current_customer = $event->getCustomer();
        $context = $event->getContext();

        // Find other guest accounts with the same email
        $otherGuests = $this->findOtherGuests($current_customer, $context);

        // Delete other guest accounts and move over their orders
        $this->processOtherGuests($otherGuests, $current_customer, $context);
    }

    private function findOtherGuests(CustomerEntity $customer, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $customer->getEmail()),
            new EqualsFilter('guest', true),
            new NotFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('id', $customer->getId()),
            ])
        );

        return $this->customerRepository->search($criteria, $context)->getEntities();
    }

    private function processOtherGuests(EntityCollection $otherGuests, CustomerEntity $customer, Context $context): void
    {
        /** @var CustomerEntity $guest */
        foreach ($otherGuests->getElements() as $guest){
            // Move over orders from guest account to the current customer
            $this->orderMover->moveOrders($guest, $customer, $context);

            // Delete the guest account
            $this->deleteGuestAccount($guest);
        }
    }

    private function deleteGuestAccount(CustomerEntity $customer): void
    {
        // Delete the old guest customer
        $this->customerRepository->delete([['id' => $customer->getId()]], Context::createDefaultContext());
    }

}
