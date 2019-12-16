# Environment Setup
## Create db inside mariadb container
```
# Login to the db server
docker exec -it "$(docker ps | grep laradock_mariadb | awk '{print $1}')" /bin/bash

# Create database, note root password is root 
mysql -uroot -p -e "CREATE DATABASE laravel"

# Give default user access to this db
mysql -uroot -p -e "GRANT ALL PRIVILEGES ON laravel.* TO 'default'@'%' IDENTIFIED BY 'secret';"

```

## Setup cron
```
# Insert the following to workspace container's cron
* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1

```

## Test
```
# Login to the db server
docker exec -it "$(docker ps | grep laradock_mariadb | awk '{print $1}')" /bin/bash

# Create database, note root password is root 
mysql -uroot -p -e "CREATE DATABASE laraveltest"

# Give default user access to this db
mysql -uroot -p -e "GRANT ALL PRIVILEGES ON laraveltest.* TO 'default'@'%' IDENTIFIED BY 'secret';"

# Migrate db
php artisan --env=testing migrate:fresh --seed

# Run test
phpunit
```

# Generate Instruction
## Feed Workflow
1. Newly created feeds are pushed out to each logged in user's redis cache keyed by `user:x:feed` as well as the feed 
owner's profile feed keyed by `user:x:profile` via an asyc event `FeedPosted`, the feed itself will be cached inside 
`feed:[feed uuid]`. these are basically the mechanisms to ensure the users can receive the feed right away when it is 
being posted. Deletes works in the same way. 
1. during the feed fanout process, two separate sorted set `buffer:insert` and `buffer:delete` are also being maintained
to buffer the persistence of the insertion and deletion. A cron `PersistBuffer` that runs at 1 minute interval will 
be in charge of executing the actual dequeue and persist jobs.
1. When an user loads the feed page, depends on if the user's feed cache exists or not, an async event `FeedCachePreloaded` 
will be fired when it sees there are reads from db, so that it will then preload data and warm up the cache based on the 
data in storage buffer + cache + db 
1. When an user loads its profile page, similar steps will happen than user's feed page, besides the event fired is
`ProfileCachePreloaded`, and also it will ignore data from storage buffer because as the owner of the profile feeds,
this user's profile feed will be updated synchronously when the feed is being created

## Code Structure
- **Events**
    - `FeedCachePreloaded` - preload feed cache
    - `ProfileCachePreloaded` - preload profile feed cache
    - `FeedPosted` - handles the feed fanout
- **Commands** 
    - `PersistBuffer` - persists data from the storage buffer to database
- **FeedStrategy**
    - `FeedContract` - interface to define general behavior of feed fanout strategy, here strategy means that feed can 
    have different ways to be delivered to clients based on different user types/popularity/activity. 
    ie. push(fanout), pull(get on read) or hybrid
    - `PushStrategy` - the basic push strategy is implemented here for simplicity where all feeds will be fanout to each 
    user's feed list upon creation
    - `StrategyBase` - general utilities
- **FeedSubscriber**
    - `FeedSubscriberContract` - interface to define user's behavior to maintain the list of who should receive feeds
    - `SubscribeToAll` - the assumption maid here is all users will subscribe to each other, hence all feed will be 
    visible across the whole user base
- **FeedManager**
    - `ManagerBase` - logic to load feed from database/cache/buffer 
    - `ProfileFeedManager` -  logic to load feed for profile feed
    - `UserFeedManager` - logic to load feed for user feeds
- **StorageBuffer**
    Storage buffer logic to add or delete a feed, as well as selecting feeds eligible to be persisted
- **TimeSeriesCollection** 
    Collection intent to hold the time series data read from redis or eloquent models, also some extra methods are added
- **TimeSeriesPaginator**
    Custom Paginator extending laravel paginator to change the behavior so that it supports time series based data, ie. 
    length is not aware, offset is timestamp instead of page, can only paginate forward, etc
