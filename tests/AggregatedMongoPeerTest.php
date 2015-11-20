<?php
namespace ConnectHolland\Windmill\Addon\MongoAggregations\Test;

use ConnectHolland\Windmill\Addon\MongoAggregations\MongoAggregation;
use MongoCode;
use MongoCriteria;
use MongoPeerTest;
use WMCommonRegistry;
/**
 * Perform unit test on MongoPeer using aggregations
 *
 * @author Ron Rademaker
 */
class AggregatedMongoPeerTest extends MongoPeerTest
{
    /**
     * setUp
     */
    public function setUp()
    {
        WMCommonRegistry::get('serviceregistry')->set('mongo.aggregations', new MongoAggregation());
        parent::setUp();
    }

    /**
     * Add test case for a criteria with $where
     *
     * @return array
     */
    public function provideFilterCountData()
    {
        $testCases = parent::provideFilterCountData();

        $mongoCriteria = new MongoCriteria();
        $query = new MongoCode("function () { return true; }");
        $mongoCriteria->add('$or', [['$where' => $query]], MongoCriteria::CUSTOM);

        $new = $testCases[0];
        $new[0] = $mongoCriteria;
        $testCases[] = $new;

        return $testCases;
    }

}
