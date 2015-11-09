# Windmill-Mongo-Aggregations-Addon
Addon to use aggregations from Windmill

# Usage

```
composer require connectholland/windmill-mongo-aggregations-addon
```

Define a mongo.aggregation service through the dependency injection container:

```
 <service id='mongo.aggregation' class='ConnectHolland\Windmill\Addon\MongoAggregations\MongoAggregation'/>
```

**This addon is not compatible with mongo 2.2**
