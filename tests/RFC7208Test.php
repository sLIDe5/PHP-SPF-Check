<?php

declare(strict_types=1);

namespace Mika56\SPFCheck\Test;

use Mika56\SPFCheck\DNS\DNSRecordGetterInterface;
use Mika56\SPFCheck\Exception\DNSLookupException;
use Mika56\SPFCheck\SPFCheck;

class RFC7208Test extends OpenSPFTest
{
    /**
     * @dataProvider RFC7208DataProvider
     */
    public function testRFC7208(string $ipAddress, string $domain, DNSRecordGetterInterface $dnsData, array $expectedResult)
    {
        $spfCheck = new SPFCheck($dnsData);
        $result   = $spfCheck->getIPStringResult($ipAddress, $domain);

        try {
            $spfRecords = $dnsData->getSPFRecordsForDomain($domain);
            $spfRecord = $spfRecords[0] ?? '(none)';
        } catch (DNSLookupException $e) {
            $spfRecord = '(lookup exception)';
        }

        $this->assertTrue(
            in_array($result, $expectedResult),
            'Failed asserting that (expected) '.(
            (count($expectedResult) == 1)
                ? ($expectedResult[0].' equals ')
                : ('('.implode(', ', $expectedResult).') contains '))
            .'(result) '.$result.PHP_EOL
            .'IP address: '.$ipAddress.PHP_EOL
            .'SPF record: '.$spfRecord
        );
    }

    public function RFC7208DataProvider(): array
    {
        $scenarios = file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'rfc7208-tests.yml');
        // Apparently there is a YML error in that file
        $scenarios = str_replace('Result is none if checking SPF records only', '>-'."\n".'      Result is none if checking SPF records only', $scenarios);

        return $this->loadTestCases($scenarios);
    }

    protected function isScenarioAllowed(string $scenarioName): bool
    {
        return $scenarioName != 'Macro expansion rules';
    }

    protected function isTestAllowed(string $testName): bool
    {
        $ignored_tests = array(
            // @formatter:off
            'spfonly', 'spftimeout', 'spfoverride', // These tests fails because DNSRecordGetterOpenSPF returns SPF records. However, DnsRecordGetter does not, so we just ignore those tests

            'a-cidr6-0-ip4', 'a-cidr6-0-ip4mapped', 'a-cidr6-0-ip6', 'a-cidr6-0-nxdomain',     // Dual CIDR is not (yet) supported
            'mx-cidr6-0-ip4', 'mx-cidr6-0-ip4mapped', 'mx-cidr6-0-ip6', 'mx-cidr6-0-nxdomain', // Dual CIDR
            'a-dual-cidr-ip4-match', 'a-dual-cidr-ip6-match', 'a-dual-cidr-ip6-default', 'a-cidr4-0-ip6', 'mx-cidr4-0-ip6', // Dual CIDR
            'cidr6-0-ip4', // Dual CIDR
            'cidr6-ip4', // Needs implementation
            // @formatter:on
        );

        return !in_array($testName, $ignored_tests);
    }

    protected function fixZoneData(string $scenarioName, array $zoneData): array
    {
        if ($scenarioName == 'IP6 mechanism syntax') {
            // This syntax is deprecated and not supported by this library
            $zoneData['e2.example.com'][0]['SPF'] = 'v=spf1 ip6:::FFFF:1.1.1.1/0';
            $zoneData['e3.example.com'][0]['SPF'] = 'v=spf1 ip6:::FFFF:1.1.1.1/129';
            $zoneData['e4.example.com'][0]['SPF'] = 'v=spf1 ip6:::FFFF:1.1.1.1//33';
        }

        return $zoneData;
    }
}
