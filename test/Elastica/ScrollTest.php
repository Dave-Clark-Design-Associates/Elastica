<?php
namespace Elastica\Test;

use Elastica\Client;
use Elastica\Document;
use Elastica\Query;
use Elastica\ResultSet;
use Elastica\Scroll;
use Elastica\Search;

class ScrollTest extends Base
{
    /**
     * Full foreach test.
     *
     * @group functional
     */
    public function testForeach()
    {
        $search = $this->_prepareSearch();
        $scroll = new Scroll($search);
        $count = 1;

        $this->_assertOpenSearchContexts($search->getClient(), 0);

        /** @var ResultSet $resultSet */
        foreach ($scroll as $scrollId => $resultSet) {
            $this->assertNotEmpty($scrollId);
            $this->_assertOpenSearchContexts($search->getClient(), 1);

            $results = $resultSet->getResults();
            switch (true) {
                case $count === 1:
                    // hits: 1 - 5
                    $this->assertEquals(5, $resultSet->count());
                    $this->assertEquals('1', $results[0]->getId());
                    $this->assertEquals('5', $results[4]->getId());
                    break;
                case $count === 2:
                    // hits: 6 - 10
                    $this->assertEquals(5, $resultSet->count());
                    $this->assertEquals('6', $results[0]->getId());
                    $this->assertEquals('10', $results[4]->getId());
                    break;
                case $count === 3:
                    // hit: 11
                    $this->assertEquals(1, $resultSet->count());
                    $this->assertEquals('11', $results[0]->getId());
                    break;
                case $count === 4:
                    $this->assertEquals(0, $resultSet->count());
                    break;
                default:
                    $this->fail('too many iterations');
            }

            ++$count;
        }

        $this->_assertOpenSearchContexts($search->getClient(), 0);
    }

    /**
     * Scroll must not overwrite options.
     *
     * @group functional
     */
    public function testSearchRevert()
    {
        $search = $this->_prepareSearch();

        $search->setOption(Search::OPTION_SCROLL, 'must');
        $search->setOption(Search::OPTION_SCROLL_ID, 'not');
        $old = $search->getOptions();

        $scroll = new Scroll($search);

        $scroll->rewind();
        $this->assertEquals($old, $search->getOptions());

        $scroll->next();
        $this->assertEquals($old, $search->getOptions());
    }

    /**
     * index: 11 docs
     * query size: 5.
     *
     * @return Search
     */
    private function _prepareSearch()
    {
        $index = $this->_createIndex();
        $index->refresh();

        $docs = [];
        for ($x = 1; $x <= 11; ++$x) {
            $docs[] = new Document($x, ['id' => $x, 'key' => 'value']);
        }

        $type = $index->getType('scrollTest');
        $type->addDocuments($docs);
        $index->refresh();

        $search = new Search($this->_getClient());
        $search->addIndex($index)->addType($type);
        $search->getQuery()->setSize(5);

        return $search;
    }

    /**
     * Tests the number of open search contexts on ES.
     *
     * @param Client $client
     * @param int    $count
     */
    private function _assertOpenSearchContexts(Client $client, $count)
    {
        $stats = $client->getStatus()->getData();
        $this->assertSame($count, $stats['_all']['total']['search']['open_contexts'], 'Open search contexts should match');
    }
}
