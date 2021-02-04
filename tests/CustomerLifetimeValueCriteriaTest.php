<?php

namespace Crm\ClvModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\ClvModule\Models\Scenarios\CustomerLifetimeValueCriteria;
use Crm\ClvModule\Repositories\CustomerLifetimeValuesRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;

class CustomerLifetimeValueCriteriaTest extends DatabaseTestCase
{
    /** @var UserManager */
    private $userManager;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var CustomerLifetimeValuesRepository */
    private $customerLifetimeValuesRepository;

    /** @var CustomerLifetimeValueCriteria */
    private $criteria;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->criteria = $this->inject(CustomerLifetimeValueCriteria::class);
        $this->customerLifetimeValuesRepository = $this->getRepository(CustomerLifetimeValuesRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            CustomerLifetimeValuesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    public function testSingleOptionWithRecordExists()
    {
        $user = $this->userManager->addNewUser('foo@example.com');
        $this->customerLifetimeValuesRepository->add(
            $user->id,
            DateTime::from('1984-04-24 01:23:45'),
            DateTime::from('1989-11-17 20:00:00'),
            2,
            1,
            3,
            5,
            7,
            9
        );

        $this->assertTrue(
            $this->evaluate($user, ['bucket_25'])
        );
    }

    public function testMultipleOptionRecordExistsNotMatching()
    {
        $user = $this->userManager->addNewUser('foo@example.com');
        $this->customerLifetimeValuesRepository->add(
            $user->id,
            DateTime::from('1984-04-24 01:23:45'),
            DateTime::from('1989-11-17 20:00:00'),
            6,
            1,
            3,
            5,
            7,
            9
        );
        $this->assertFalse(
            $this->evaluate($user, ['bucket_25', 'bucket_50', 'bucket_100'])
        );
    }

    public function testSingleOptionWithMissingRecord()
    {
        $user = $this->userManager->addNewUser('foo@example.com');
        $this->assertFalse(
            $this->evaluate($user, ['bucket_25'])
        );
    }

    private function evaluate($user, array $values)
    {
        $conditionValues = (object) [
            'selection' => $values
        ];

        $query = $this->usersRepository->getTable();
        $conditionAdded = $this->criteria->addCondition($query, $conditionValues, $user);
        return $conditionAdded && (bool) $query->fetch();
    }
}
