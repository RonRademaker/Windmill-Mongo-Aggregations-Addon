<?php
namespace ConnectHolland\Windmill\Addon\MongoAggregations\Test;

use ConnectHolland\Windmill\Addon\MongoAggregations\MongoAggregation;
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
}
