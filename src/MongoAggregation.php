<?php
namespace ConnectHolland\Windmill\Addon\MongoAggregations;

use ConnectHolland\MongoAggregations\Aggregation\Group;
use ConnectHolland\MongoAggregations\Aggregation\Match;
use ConnectHolland\MongoAggregations\Aggregation\RangeProjection;
use ConnectHolland\MongoAggregations\Builder\PipelineBuilder;
use ConnectHolland\MongoAggregations\Builder\UnwindBuilder;
use ConnectHolland\MongoAggregations\Operation\Sum;
use MongoCollection;
use MongoCriteria;
use MongoDB;
use MongoPeer;
use WMCollection;
use WMPropelFilterCount;

/**
 * Class that interfaces with the MongoAggregations library to perform windmil actions (filter counts)
 *
 * @author Ron Rademaker
 */
class MongoAggregation
{
    /**
     * Supported range types
     */
    const UNKNOWN = -1;
    const MINMAX = 1;
    const VALUELABEL = 2;

    /**
     * Performs a doFilterCount using aggregations
     *
     * @param MongoCriteria $mc
     * @param MongoDB $db
     * @param string $collection
     * @param string $category
     * @param array $ranges
     * @param boolean $orderByAmount
     * @return WMCollection
     */
    public function doFilterCount(
        MongoCriteria $mc,
        MongoDB $db,
        MongoCollection $collection,
        $category,
        array $ranges = null,
        $orderByAmount = true
    ) {
        $query = $this->getAggregationMatchQuery(clone $mc, $db, $collection);

        $aggregationPipeline = $this->getAggregationPipeline($query, $category, $ranges);
        $counts = $collection->aggregate($aggregationPipeline);

        $result = new WMCollection();

        if (array_key_exists("result", $counts) && is_array($counts["result"]) && count($counts["result"]) > 0) {
            if (is_array($ranges) && $this->getRangeType($ranges) === static::VALUELABEL) {
                $this->addRangedFilterCountResult($result, $counts, $category, $ranges);
            } else {
                $this->addFilterCountResult($result, $counts, $category);
            }
        }

        if ($orderByAmount) {
            $result->usort(
                function ($a, $b) {
                    if ($a->getAmount() == $b->getAmount()) {
                        return 0;
                    }

                    return ($a->getAmount() < $b->getAmount()) ? 1 : -1;
                }
            );
        }

        return $result;
    }

    /**
     * Gets the aggregation pipeline
     *
     * @param array $query
     * @param string $category
     * @param mixed $ranges
     * @return array
     */
    private function getAggregationPipeline(array $query, $category, $ranges)
    {
        $pipelineBuilder = new PipelineBuilder();

        $categoryQuery = array();
        $categoryQuery[] = array($category => array('$exists' => true));
        if (isset($ranges)
            && is_array($ranges)
            && $this->getRangeType($ranges) === static::MINMAX) {
            $categoryQuery[] = array('$or' => array(
                array($category => array('$type' => 1)),
                array($category => array('$type' => 16)),
                array($category => array('$type' => 18))
            ));
        }

        unset($query[$category]);
        $query['$and'] = $categoryQuery;

        $match = new Match();
        $match->setQuery($query);
        $pipelineBuilder->add($match);

        if (is_array($ranges) && static::getRangeType($ranges) === static::MINMAX) {
            $rangeProjection = new RangeProjection();
            $rangeProjection->addRange($category, $ranges);
            $pipelineBuilder->add($rangeProjection);
        } else {
            $pipelineBuilder->addBuilder(new UnwindBuilder($category));
        }

        $group = new Group();
        $group->setGroupBy($category);
        $sum = new Sum();
        $sum->setSum(1);
        $group->setResultField('count', $sum);
        $pipelineBuilder->add($group);

        return $pipelineBuilder->build();
    }

     /**
     * Gets the mongo query to search from, ported from the old (pre 6.3) mongo code
     * Rewrites geo queries to ids can be improved using $geoNear
     *
     * @param MongoCriteria $mc
     * @param MongoDB $db
     * @param MongoCollection $collection
     * @return array
     */
    private function getAggregationMatchQuery(MongoCriteria $mc, MongoDB $db, MongoCollection $collection)
    {
        $clonedMongoCriteria = clone $mc;

        $condition = $clonedMongoCriteria->getSelectQuery();

        foreach ($condition as $conditionkey => $conditionpart) {
            if (is_array($conditionpart)
                && (array_key_exists("\$near", $conditionpart) || array_key_exists("\$within", $conditionpart))) {
                $idsMongoCriteria = clone $clonedMongoCriteria;
                $idsMongoCriteria->setLimit(0);
                $idsMongoCriteria->addSelectColumn("_id");
                $resultCursor = MongoPeer::doSelectStmt($idsMongoCriteria, $db, $collection->getName());
                $ids = array();
                while ($resultCursor->hasNext()) {
                    $item = $resultCursor->getNext();
                    if (array_key_exists("_id", $item)) {
                        $ids[] = $item["_id"];
                    }
                }
                unset($condition[$conditionkey]); // $near not supported in aggregations
                $condition["_id"] = array("\$in" => $ids);
            }
        }

        return $condition;
    }

    /**
     * Checks what kind of ranges are passed
     *
     * @param array $ranges
     * @return int
     */
    private function getRangeType(array $ranges)
    {
        if (count($ranges) > 0) {
            $testRange = $ranges[0];
            if (is_array($testRange) && array_key_exists('min', $testRange)) {
                return static::MINMAX;
            } elseif (is_array($testRange) && array_key_exists('max', $testRange)) {
                return static::MINMAX;
            } elseif (is_array($testRange) && array_key_exists('value', $testRange)) {
                return static::VALUELABEL;
            }
        }

        return static::UNKNOWN;
    }

     /**
     * Adds the count results with rewriting of labels as defined in the ranges array
     *
     * @param WMCollection $result
     * @param array $counts
     * @param type $category
     * @param array $ranges
     */
    private function addRangedFilterCountResult(
        WMCollection $result,
        array $counts,
        $category,
        array $ranges
    ) {
        foreach ($ranges as $range) {
            foreach ($counts["result"] as $resultPart) {
                if ($resultPart['_id'] === $range['value']) {
                    $counter = new WMPropelFilterCount($category);
                    $counter->identifier = $range['label'];
                    $counter->amount = $resultPart['count'];
                    $result->add($counter);
                }
            }
        }
    }

    /**
     * Adds the count results
     *
     * @param WMCollection $result
     * @param array $counts
     * @param type $category
     */
    private function addFilterCountResult(
        WMCollection $result,
        array $counts,
        $category
    ) {
        foreach ($counts["result"] as $resultPart) {
            if ($resultPart['count'] > 0) {
                $counter = new WMPropelFilterCount($category);
                $counter->identifier = $resultPart['_id'];
                $counter->amount = $resultPart['count'];
                $result->add($counter);
            }
        }
    }
}
