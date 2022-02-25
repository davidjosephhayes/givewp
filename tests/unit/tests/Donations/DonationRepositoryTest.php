<?php

namespace unit\tests\Donations;

use Exception;
use Give\Donations\Models\Donation;
use Give\Donations\Repositories\DonationRepository;
use Give\Donations\ValueObjects\DonationMetaKeys;
use Give\Donations\ValueObjects\DonationMode;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Donors\Models\Donor;
use Give\Framework\Database\DB;
use Give\Framework\Exceptions\Primitives\InvalidArgumentException;
use Give\Framework\Models\Traits\InteractsWithTime;
use Give\PaymentGateways\Gateways\TestGateway\TestGateway;

/**
 * @coversDefaultClass DonationRepository
 */
final class DonationRepositoryTest extends \Give_Unit_Test_Case
{
    use InteractsWithTime;

    /**
     * @unreleased - truncate donationMetaTable to avoid duplicate records
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        $donationMetaTable = DB::prefix('give_donationmeta');
        $donationsTable = DB::prefix('posts');
        $donorsTable = DB::prefix('give_donors');
        $donorMetaTable = DB::prefix('give_donormeta');

        DB::query("TRUNCATE TABLE $donationMetaTable");
        DB::query("TRUNCATE TABLE $donationsTable");
        DB::query("TRUNCATE TABLE $donorsTable");
        DB::query("TRUNCATE TABLE $donorMetaTable");
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGetByIdShouldReturnDonation()
    {
        $donor = $this->createDonor();
        $donationFactory = $this->createDonationInstance();
        $repository = new DonationRepository();

        $insertedDonation = $repository->insert($donationFactory);

        $donation = $repository->getById($insertedDonation->id);

        $this->assertInstanceOf(Donation::class, $donation);
        $this->assertEquals($insertedDonation->id, $donation->id);
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testInsertShouldAddDonationToDatabase()
    {
        $donor = $this->createDonor();
        $donation = $this->createDonationInstance();
        $repository = new DonationRepository();

        $newDonation = $repository->insert($donation);

        $query = $repository->prepareQuery()
            ->where('ID', $newDonation->id)
            ->get();


        // simulate asserting database has values
        $this->assertInstanceOf(Donation::class, $newDonation);
        $this->assertEquals($query->id, $newDonation->id);
        $this->assertEquals($query->status, $newDonation->status->getValue());
        $this->assertEquals($query->{DonationMetaKeys::AMOUNT()->getKeyAsCamelCase()}, $newDonation->amount);
        $this->assertEquals($query->{DonationMetaKeys::CURRENCY()->getKeyAsCamelCase()}, $newDonation->currency);
        $this->assertEquals($query->{DonationMetaKeys::GATEWAY()->getKeyAsCamelCase()}, $newDonation->gateway);
        $this->assertEquals($query->{DonationMetaKeys::DONOR_ID()->getKeyAsCamelCase()}, $newDonation->donorId);
        $this->assertEquals($query->{DonationMetaKeys::FIRST_NAME()->getKeyAsCamelCase()}, $newDonation->firstName);
        $this->assertEquals($query->{DonationMetaKeys::LAST_NAME()->getKeyAsCamelCase()}, $newDonation->lastName);
        $this->assertEquals($query->{DonationMetaKeys::DONOR_EMAIL()->getKeyAsCamelCase()}, $newDonation->email);
        $this->assertEquals($this->toDateTime($query->createdAt), $newDonation->createdAt);
        $this->assertEquals($query->parentId, $newDonation->parentId);
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testInsertShouldFailValidationWhenMissingKeyAndThrowException()
    {
        $this->expectException(InvalidArgumentException::class);

        $donationMissingAmount = new Donation([
            'createdAt' => $this->getCurrentDateTime(),
            'status' => DonationStatus::PENDING(),
            'gateway' => TestGateway::id(),
            'currency' => 'USD',
            'donorId' => 1,
            'firstName' => 'Bill',
            'lastName' => 'Murray',
            'email' => 'billMurray@givewp.com',
        ]);

        $repository = new DonationRepository();

        $repository->insert($donationMissingAmount);
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testInsertShouldFailValidationWhenDonorDoesNotExistAndThrowException()
    {
        $this->expectException(InvalidArgumentException::class);

        $donationWithInvalidDonor = new Donation([
            'createdAt' => $this->getCurrentDateTime(),
            'status' => DonationStatus::PENDING(),
            'gateway' => TestGateway::id(),
            'currency' => 'USD',
            'amount' => 50,
            'formId' => 1,
            'donorId' => 2,
            'firstName' => 'Bill',
            'lastName' => 'Murray',
            'email' => 'billMurray@givewp.com',
        ]);

        $repository = new DonationRepository();

        $repository->insert($donationWithInvalidDonor);
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testUpdateShouldFailValidationAndThrowException()
    {
        $this->expectException(InvalidArgumentException::class);

        $donationMissingAmount = new Donation([
            'createdAt' => $this->getCurrentDateTime(),
            'status' => DonationStatus::PENDING(),
            'gateway' => TestGateway::id(),
            'currency' => 'USD',
            'donorId' => 1,
            'firstName' => 'Bill',
            'lastName' => 'Murray',
            'email' => 'billMurray@givewp.com',
        ]);

        $repository = new DonationRepository();

        $repository->update($donationMissingAmount);
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testUpdateShouldUpdateDonationValuesInTheDatabase()
    {
        $donor = $this->createDonor();
        $donation = $this->createDonation();
        $repository = new DonationRepository();

        // update donation values
        $donation->amount = 100;
        $donation->firstName = "Ron";
        $donation->lastName = "Swanson";
        $donation->email = "ron@swanson.com";

        // call update method
        $repository->update($donation);

        $query = DB::table('posts')
            ->select('ID')
            ->attachMeta(
                'give_donationmeta',
                'ID',
                'donation_id',
                DonationMetaKeys::AMOUNT,
                DonationMetaKeys::FIRST_NAME,
                DonationMetaKeys::LAST_NAME,
                DonationMetaKeys::DONOR_EMAIL
            )
            ->where('ID', $donation->id)
            ->get();

        // assert updated values from the database
        $this->assertNotEquals(50, $query->{DonationMetaKeys::AMOUNT});
        $this->assertEquals(100, $query->{DonationMetaKeys::AMOUNT});
        $this->assertEquals("Ron", $query->{DonationMetaKeys::FIRST_NAME});
        $this->assertEquals("Swanson", $query->{DonationMetaKeys::LAST_NAME});
        $this->assertEquals("ron@swanson.com", $query->{DonationMetaKeys::DONOR_EMAIL});
    }

    /**
     * @unreleased
     *
     * @return void
     *
     * @throws Exception
     */
    public function testDeleteShouldRemoveDonationFromTheDatabase()
    {
        $donor = $this->createDonor();
        $donation = $this->createDonation();
        $repository = new DonationRepository();

        $repository->delete($donation);

        $donationQuery = DB::table('posts')
            ->where('ID', $donation->id)
            ->get();

        $donationCoreMetaQuery =
            DB::table('give_donationmeta')
                ->where('donation_id', $donation->id)
                ->getAll();

        $this->assertNull($donationQuery);
        $this->assertEmpty($donationCoreMetaQuery);
    }

    /**
     * Local donation factory
     *
     * @unreleased
     *
     * @return Donation
     */
    private function createDonationInstance()
    {
        return new Donation([
            'createdAt' => $this->getCurrentDateTime(),
            'status' => DonationStatus::PENDING(),
            'gateway' => TestGateway::id(),
            'amount' => 50,
            'currency' => 'USD',
            'donorId' => 1,
            'firstName' => 'Bill',
            'lastName' => 'Murray',
            'email' => 'billMurray@givewp.com',
            'parentId' => 0,
            'formId' => 1,
            'formTitle' => 'Form Title'
        ]);
    }

    /**
     * Local donation factory
     *
     * @unreleased
     *
     * @return Donation
     * @throws Exception
     */
    private function createDonation()
    {
        return Donation::create([
            'createdAt' => $this->getCurrentDateTime(),
            'status' => DonationStatus::PENDING(),
            'gateway' => TestGateway::id(),
            'amount' => 50,
            'currency' => 'USD',
            'donorId' => 1,
            'firstName' => 'Bill',
            'lastName' => 'Murray',
            'email' => 'billMurray@givewp.com',
            'parentId' => 0,
            'formId' => 1,
            'formTitle' => 'Form Title',
            'mode' => DonationMode::TEST()
        ]);
    }

    /**
     * @unreleased
     *
     * @return Donor
     *
     * @throws Exception
     */
    private function createDonor()
    {
        return Donor::create([
            'createdAt' => $this->getCurrentDateTime(),
            'name' => 'Bill Murray',
            'firstName' => 'Bill',
            'lastName' => 'Bill Murray',
            'email' => 'billMurray@givewp.com'
        ]);
    }
}
