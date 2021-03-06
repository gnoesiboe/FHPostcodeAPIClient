<?php

namespace FH\PostcodeAPI\Test;

use FH\PostcodeAPI\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client AS HTTPClient;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\Mock;

/**
 * @author Gijs Nieuwenhuis <gijs.nieuwenhuis@freshheads.com>
 */
final class ClientTest extends \PHPUnit_Framework_TestCase
{
    /** @var string */
    const POSTCODE_PATTERN = '/^[\d]{4}[\w]{2}$/i';

    /** @var string */
    const FRESHHEADS_POSTCODE = '5041EB';

    /** @var int */
    const FRESHHEADS_NUMBER = 21;

    /** @var string */
    const FRESHHEADS_CITY = 'Tilburg';

    /** @var float */
    const FRESHHEADS_LONGITUDE = 5.07717893166;

    /** @var float */
    const FRESHHEADS_LATITUDE = 51.566414786;

    /** @var string */
    const FRESHHEADS_ADDRESS_ID = '0855200000061001';

    public function testRequestExceptionIsThrownWhenUsingAnInvalidApiKey()
    {
        $client = $this->createClient(
            $this->loadMockResponse('failed_list_with_invalid_api_key')
        );

        try {
            $client->getAddresses();

            $this->fail('Should not get to this point as an exception should have been thrown because the api client was supplied with an invalid API key');
        } catch (RequestException $exception) {
            if ($exception->getResponse() instanceof Response) {
                $this->assertTrue($exception->getResponse()->getStatusCode() === 401);
            } else {
                $this->fail($exception->getMessage());
            }
        }
    }

    public function testListResourceReturnsAllAddressesWhenNoParamsAreSupplied()
    {
        $client = $this->createClient(
            $this->loadMockResponse('successful_list_without_filtering')
        );

        $response = $client->getAddresses();

        $this->applyAssertsToMakeSureAddressesArrayIsAvailableInResponse($response);

        $addresses = $response->_embedded->addresses;

        $this->assertTrue(count($addresses) > 0, 'Expecting that there are always addresses available');

        $this->applyAddressFieldAreSetAndOfTheCorrectTypeAssertions($addresses[0]);
    }

    public function testListResourceReturnsExpectedAddressWhenPostcodeAndNumberAreSupplied()
    {
        $client = $this->createClient(
            $this->loadMockResponse('successful_list_freshheads_postcode_and_number')
        );

        $response = $client->getAddresses(self::FRESHHEADS_POSTCODE, self::FRESHHEADS_NUMBER);

        $this->applyAssertsToMakeSureAddressesArrayIsAvailableInResponse($response);

        $addresses = $response->_embedded->addresses;

        $this->assertTrue(count($addresses) > 0, 'Expecting that there are always addresses available when no filters are applied');

        $firstAddress = $addresses[0];

        $this->applyAddressFieldAreSetAndOfTheCorrectTypeAssertions($firstAddress);
        $this->applyIsFreshheadsAddressAssertions($firstAddress);
    }

    public function testExpectedAddressInformationIsReturnedFromDetailResource()
    {
        $client = $this->createClient(
            $this->loadMockResponse('successful_detail_freshheads')
        );

        $address = $client->getAddress(self::FRESHHEADS_ADDRESS_ID);

        $this->applyAddressFieldAreSetAndOfTheCorrectTypeAssertions($address);

        $this->applyIsFreshheadsAddressAssertions($address);
    }

    /**
     * @expectedException \GuzzleHttp\Exception\RequestException
     */
    public function testClientThrowsExceptionWhenInvalidInputIsSupplied()
    {
        $client = $this->createClient(
            $this->loadMockResponse('failed_list_with_invalid_postalcode_and_number')
        );

        $client->getAddresses('invalid_postcode', 'invalid_number');
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function loadMockResponse($name)
    {
        return file_get_contents(__DIR__ . "/../../Mock/{$name}");
    }

    /**
     * @param \StdClass $address
     */
    private function applyIsFreshheadsAddressAssertions(\StdClass $address)
    {
        $this->assertSame(strtoupper($address->postcode), self::FRESHHEADS_POSTCODE, 'Incoming postcode did not match the expected postcode');
        $this->assertSame((string)$address->number, (string)self::FRESHHEADS_NUMBER, 'Incoming number did not match the expected number');
        $this->assertSame($address->city->label, self::FRESHHEADS_CITY, 'Incoming city did not match the expected city');

        // use number_format number rounding to allow for minor changes between expected and actual value
        $this->assertSame(
            number_format($address->geo->center->wgs84->coordinates[0], 5),
            number_format(self::FRESHHEADS_LONGITUDE, 5),
            'Incoming longitude did not match the expected value'
        );
        $this->assertSame(
            number_format($address->geo->center->wgs84->coordinates[1], 5),
            number_format(self::FRESHHEADS_LATITUDE, 5),
            'Incoming latitude did not match the expected value'
        );
    }

    /**
     * @param \StdClass $response
     */
    private function applyAssertsToMakeSureAddressesArrayIsAvailableInResponse(\StdClass $response)
    {
        $this->assertTrue(isset($response->_embedded->addresses));
        $this->assertTrue(is_array($response->_embedded->addresses));
    }

    /**
     * @param \stdClass $address
     */
    private function applyAddressFieldAreSetAndOfTheCorrectTypeAssertions(\StdClass $address)
    {
        // only test the availability of the most import fields and their values

        $this->assertTrue(isset($address->street), 'Incoming address did not have a street field');
        $this->assertTrue(is_string($address->street), 'Incoming address did not have a street value of type string');

        $this->assertTrue(isset($address->city->label), 'Incoming address did not have a city.label field');
        $this->assertTrue(is_string($address->city->label), 'Incoming address did not have a city.label value of type string');

        $this->assertTrue(isset($address->postcode), 'Incoming address did not have a postcode field');
        $this->assertTrue(preg_match(self::POSTCODE_PATTERN, $address->postcode) === 1, 'Incoming address did not have a postcode value that matches the pattern: ' . self::POSTCODE_PATTERN);

        $this->assertTrue(isset($address->number), 'Incoming address did not have a number field');
        $this->assertTrue(is_string($address->number) || is_numeric($address->number), 'Incoming address did not have a number field with type string');

        $this->assertTrue(isset($address->geo->center->wgs84->coordinates[0]), 'Incoming address did not have a longitude field');
        $this->assertTrue(is_float($address->geo->center->wgs84->coordinates[0]), 'Incoming address did not have a longitude value of type float');

        $this->assertTrue(isset($address->geo->center->wgs84->coordinates[1]), 'Incoming address did not have a latitude field');
        $this->assertTrue(is_float($address->geo->center->wgs84->coordinates[1]), 'Incoming address did not have a latitude value of type float');
    }

    /**
     * @param string $mockedResponses
     *
     * @return Client
     */
    private function createClient($mockedResponses)
    {
        $someKey = 'SomeApiKey';

        $httpClient = new HTTPClient();

        $httpClient->getEmitter()->attach(
            new Mock([
                $mockedResponses
            ])
        );

        return new Client($httpClient, $someKey);
    }
}
