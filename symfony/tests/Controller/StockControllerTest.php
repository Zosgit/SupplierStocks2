<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use App\Factory\StockItemFactory;

final class StockControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private $client;

    /**
     *  This method runs before each test, creating a new thest HTTP client
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        self::bootKernel();
    }

    /**
     *  Testing search by ean only
     */
    public function testGetStocksByEan(): void
    {
        // Create one fake stock item with a known EAN
        $knownEan = '0007561489';
        StockItemFactory::createOne(['ean' => $knownEan]);

        // Send GET request with ean parameter
        $this->client->request('GET', '/get-stocks?ean=' . $knownEan);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Should return exactly one result
        $this->assertEquals(1, $data['total_returned']);
        $this->assertEquals($knownEan, $data['items'][0]['ean']);
    }

    /**
     *  Testing search by mpn only
     */
    public function testGetStocksByMpn(): void
    {
        // Create one fake stock item with a known MPN
        $knownMpn = '91103000';
        StockItemFactory::createOne(['mpn' => $knownMpn]);

        // Send GET request with mpn parameter
        $this->client->request('GET', '/get-stocks?mpn=' . $knownMpn);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Should return exactly one result
        $this->assertEquals(1, $data['total_returned']);
        $this->assertEquals($knownMpn, $data['items'][0]['mpn']);
    }

    /**
     *  Testing search by both ean and mpn
     */
    public function testGetStocksByBothEanAndMpn(): void
    {
        // Create one fake stock item with a known ean and mpn
        $knownEan = '0007561489';
        $knownMpn = '91103000';

        // Create two stock items - one with known ean, one with known mpn
        StockItemFactory::createOne(['ean' => $knownEan]);
        StockItemFactory::createOne(['mpn' => $knownMpn]);
        StockItemFactory::createMany(3); 

        // Send request with both parameters
        $this->client->request('GET', '/get-stocks?ean=' . $knownEan . '&mpn=' . $knownMpn);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Expect two matching records
        $this->assertEquals(2, $data['total_returned']);

        $allEans = array_column($data['items'], 'ean');
        $allMpns = array_column($data['items'], 'mpn');

        // One record should match ean and another mpn
        $this->assertContains($knownEan, $allEans);
        $this->assertContains($knownMpn, $allMpns);
    }

    /**
     *  Testing search without parameters
     */
    public function testGetStocksWithoutParams(): void
    {
        // Send get request without parameters
        $this->client->request('GET', '/get-stocks');

        // Expect http 400 Bad Request
        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        // Check for error message
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Please provide at least one query parameter: ean or mpn', $responseData['error']);
    }
}
