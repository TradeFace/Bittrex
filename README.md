php-bittrex  
==============


Php bindings for bittrex.  I am Not associated -- use at your own risk, etc.


Installation
-------------

### for most recent release
add the following to composer.json
```json
    "require": {
	"tradeface/bittrex":"master"
    }

```
```json
	"repositories": [
        {
            "type": "git",
            "url": "https://github.com/tradeface/bittrex"
        }
    ],
```


Example Usage for Bittrex API

```php
use TradeFace\Bittrex\Bittrex;

my_bittrex = Bittrex(null, null, 1, API_V2_0)  # or defaulting to v1.1 as Bittrex()
my_bittrex.get_markets()
{'success': True, 'message': '', 'result': [{'MarketCurrency': 'LTC', ...
```

API_V2_0 and API_V1_1 are constants that can be imported from Bittrex.

To access account methods, an API key for your account is required and can be 
generated on the `Settings` then `API Keys` page. 
Make sure you save the secret, as it will not be visible 
after navigating away from the page. 

```php
use TradeFace\Bittrex\Bittrex;

my_bittrex = Bittrex("<my_api_key>", "<my_api_secret>", ["<calls_per_second>", ["<API_V1_1> or <API_V2_0>"]])

my_bittrex.get_balance('ETH')
{'success': True, 
 'message': '',
 'result': {'Currency': 'ETH', 'Balance': 0.0, 'Available': 0.0, 
            'Pending': 0.0, 'CryptoAddress': None}
}
```

v1.1 constants of interest:
---
```
BUY_ORDERBOOK = 'buy'
SELL_ORDERBOOK = 'sell'
BOTH_ORDERBOOK = 'both'
```

v2.0 constants of interest
---
These are used by get_candles()
```
TICKINTERVAL_ONEMIN = 'oneMin'
TICKINTERVAL_FIVEMIN = 'fiveMin'
TICKINTERVAL_HOUR = 'hour'
TICKINTERVAL_THIRTYMIN = 'thirtyMin'
TICKINTERVAL_DAY = 'Day'
```
these are used by trade_sell() and trade_buy()
```
ORDERTYPE_LIMIT = 'LIMIT'
ORDERTYPE_MARKET = 'MARKET'

TIMEINEFFECT_GOOD_TIL_CANCELLED = 'GOOD_TIL_CANCELLED'
TIMEINEFFECT_IMMEDIATE_OR_CANCEL = 'IMMEDIATE_OR_CANCEL'
TIMEINEFFECT_FILL_OR_KILL = 'FILL_OR_KILL'

CONDITIONTYPE_NONE = 'NONE'
CONDITIONTYPE_GREATER_THAN = 'GREATER_THAN'
CONDITIONTYPE_LESS_THAN = 'LESS_THAN'
CONDITIONTYPE_STOP_LOSS_FIXED = 'STOP_LOSS_FIXED'
CONDITIONTYPE_STOP_LOSS_PERCENTAGE = 'STOP_LOSS_PERCENTAGE'
```

Credits
---
https://github.com/ericsomdahl/python-bittrex 
