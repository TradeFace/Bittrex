<?php

namespace TradeFace\Bittrex;

/*
   See https://bittrex.com/Home/Api
*/

const BUY_ORDERBOOK = 'buy';
const SELL_ORDERBOOK = 'sell';
const BOTH_ORDERBOOK = 'both';

const TICKINTERVAL_ONEMIN = 'oneMin';
const TICKINTERVAL_FIVEMIN = 'fiveMin';
const TICKINTERVAL_HOUR = 'hour';
const TICKINTERVAL_THIRTYMIN = 'thirtyMin';
const TICKINTERVAL_DAY = 'Day';

const ORDERTYPE_LIMIT = 'LIMIT';
const ORDERTYPE_MARKET = 'MARKET';

const TIMEINEFFECT_GOOD_TIL_CANCELLED = 'GOOD_TIL_CANCELLED';
const TIMEINEFFECT_IMMEDIATE_OR_CANCEL = 'IMMEDIATE_OR_CANCEL';
const TIMEINEFFECT_FILL_OR_KILL = 'FILL_OR_KILL';

const CONDITIONTYPE_null = 'null';
const CONDITIONTYPE_GREATER_THAN = 'GREATER_THAN';
const CONDITIONTYPE_LESS_THAN = 'LESS_THAN';
const CONDITIONTYPE_STOP_LOSS_FIXED = 'STOP_LOSS_FIXED';
const CONDITIONTYPE_STOP_LOSS_PERCENTAGE = 'STOP_LOSS_PERCENTAGE';

const API_V1_1 = 'v1.1';
const API_V2_0 = 'v2.0';

const BASE_URL_V1_1 = 'https://bittrex.com/api/v1.1%s?';
const BASE_URL_V2_0 = 'https://bittrex.com/api/v2.0%s?';

const PROTECTION_PUB = 'pub';  # public methods
const PROTECTION_PRV = 'prv';  # authenticated methods


class Bittrex{
    /*
    Used for requesting Bittrex with API key and API secret
    */

    public function __construct(string $api_key, string $api_secret, int $calls_per_second=1, $api_version=API_V1_1){
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->call_rate = 1.0 / $calls_per_second;
        $this->last_call = null;
        $this->api_version = $api_version;
    }

    private function wait(){

        if ($this->last_call == null){
            $this->last_call = time();
        } else {
            $now = time();
            $passed = $now - $this->last_call;
            if ($passed < $this->call_rate){
                sleep($this->call_rate - $passed);
            }
            $this->last_call = time();
        }
    }

    private function _api_query(array $path_dict, string $protection=PROTECTION_PUB, $params = null){
        /*
        Queries Bittrex
        :param $request_url: fully-formed URL to request
        :type options: dict
        :return: JSON response from Bittrex
        :rtype : object
        */

        if (!array_key_exists($this->api_version, $path_dict)){
            echo sprintf('method call not available under API version %s',($this->api_version));
            return;
        }

        $request_url = ($this->api_version == API_V2_0) ? BASE_URL_V2_0 : BASE_URL_V1_1;
        $request_url = sprintf($request_url, $path_dict[$this->api_version]);       

        $uri  = $request_url;

		if ($protection != PROTECTION_PUB){
			$params['apikey'] = $this->api_key;
			$params['nonce']  = time();
		}

		if (!empty($params)) {
			$uri .= http_build_query($params);
        }
        d($uri);

		$sign = hash_hmac ('sha512', $uri, $this->api_secret);

        $this->wait();

		$ch = curl_init ($uri);
		curl_setopt ($ch, CURLOPT_HTTPHEADER, array('apisign: '.$sign));
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		
		if (curl_errno($ch)) { 
			throw new \Exception(curl_error($ch));
		}

		$answer = json_decode($result);
        
		if (isset($answer->success) == false) {
            d('Some Ting Wong');
            d($result);
			return null;
		}

		return $answer->result;
    }

    public function get_markets(){
        /*
        Used to get the open and available trading markets
        at Bittrex along with other meta data.
        1.1 Endpoint: /public/getmarkets
        2.0 NO Equivalent
        Example ::
            {'success': True,
             'message': '',
             'result': [ {'MarketCurrency': 'LTC',
                          'BaseCurrency': 'BTC',
                          'MarketCurrencyLong': 'Litecoin',
                          'BaseCurrencyLong': 'Bitcoin',
                          'MinTradeSize': 1e-08,
                          'MarketName': 'BTC-LTC',
                          'IsActive': True,
                          'Created': '2014-02-13T00:00:00',
                          'Notice': null,
                          'IsSponsored': null,
                          'LogoUrl': 'https://i.imgur.com/R29q3dD.png'},
                          ...
                        ]
            }
        :return: Available market info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getmarkets'
        ], PROTECTION_PUB);
    }

    public function get_currencies(){
        /*
        Used to get all supported currencies at Bittrex
        along with other meta data.
        Endpoint:
        1.1 /public/getcurrencies
        2.0 /pub/Currencies/GetCurrencies
        :return: Supported currencies info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getcurrencies',
            API_V2_0 => '/pub/Currencies/GetCurrencies'
        ], PROTECTION_PUB);
    }

    public function get_ticker(string $market){
        /*
        Used to get the current tick values for a market.
        Endpoints:
        1.1 /public/getticker
        2.0 NO EQUIVALENT -- but get_candlesticks gives comparable data
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :return: Current values for given market in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getticker'
        ], PROTECTION_PUB, [
            'market' => $market
        ]);
    }

    public function get_market_summaries(){
        /*
        Used to get the last 24 hour summary of all active exchanges
        Endpoint:
        1.1 /public/getmarketsummaries
        2.0 /pub/Markets/GetMarketSummaries
        :return: Summaries of active exchanges in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getmarketsummaries',
            API_V2_0 => '/pub/Markets/GetMarketSummaries'
        ], PROTECTION_PUB);
    }

    public function get_marketsummary(string $market){//not reliable
        /*
        Used to get the last 24 hour summary of all active
        exchanges in specific coin
        Endpoint:
        1.1 /public/getmarketsummary
        2.0 /pub/Market/GetMarketSummary
        :param market: String literal for the market(ex: BTC-XRP)
        :type market: str
        :return: Summaries of active exchanges of a coin in JSON 
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getmarketsummary',
            API_V2_0 => '/pub/Market/GetMarketSummary'
        ], PROTECTION_PUB, [
            'market' => $market, 
            'marketName' => $market
        ]);
    }

    public function get_orderbook(string $market, string $depth_type=BOTH_ORDERBOOK){
        /*
        Used to get retrieve the orderbook for a given market.
        The depth_type parameter is IGNORED under v2.0 and both orderbooks are aleways returned
        Endpoint:
        1.1 /public/getorderbook
        2.0 /pub/Market/GetMarketOrderBook
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :param depth_type: buy, sell or both to identify the type of
            orderbook to return.
            Use constants BUY_ORDERBOOK, SELL_ORDERBOOK, BOTH_ORDERBOOK
        :type depth_type: str
        :return: Orderbook of market in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getorderbook',
            API_V2_0 => '/pub/Market/GetMarketOrderBook'
        ], PROTECTION_PUB, [
            'market' => $market, 
            'marketname' => $market, 
            'type' => $depth_type
        ]);
    }

    public function get_market_history(string $market){
        /*
        Used to retrieve the latest trades that have occurred for a
        specific market.
        Endpoint:
        1.1 /market/getmarkethistory
        2.0 NO Equivalent
        Example ::
            {'success': True,
            'message': '',
            'result': [ {'Id': 5625015,
                         'TimeStamp': '2017-08-31T01:29:50.427',
                         'Quantity': 7.31008193,
                         'Price': 0.00177639,
                         'Total': 0.01298555,
                         'FillType': 'FILL',
                         'OrderType': 'BUY'},
                         ...
                       ]
            }
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :return: Market history in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/public/getmarkethistory',
        ], PROTECTION_PUB, [
            'market' => $market, 
            'marketname' => $market
        ]);
    }

    public function buy_limit(string $market, float $quantity, float $rate){
        /*
        Used to place a buy order in a specific market. Use buylimit to place
        limit orders Make sure you have the proper permissions set on your
        API keys for this call to work
        Endpoint:
        1.1 /market/buylimit
        2.0 NO Direct equivalent.  Use trade_buy for LIMIT and MARKET buys
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :param quantity: The amount to purchase
        :type quantity: float
        :param rate: The rate at which to place the order.
            This is not needed for market orders
        :type rate: float
        :return:
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/market/buylimit',
        ], PROTECTION_PRV, [
            'market' => $market,
            'quantity' => $quantity,
            'rate' => $rate
        ]);
    }

    public function sell_limit(string $market, float $quantity, float $rate){
        /*
        Used to place a sell order in a specific market. Use selllimit to place
        limit orders Make sure you have the proper permissions set on your
        API keys for this call to work
        Endpoint:
        1.1 /market/selllimit
        2.0 NO Direct equivalent.  Use trade_sell for LIMIT and MARKET sells
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :param quantity: The amount to purchase
        :type quantity: float
        :param rate: The rate at which to place the order.
            This is not needed for market orders
        :type rate: float
        :return:
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/market/selllimit'
        ], PROTECTION_PRV, [
            'market' => $market,
            'quantity' => $quantity,
            'rate' => $rate
        ]);
    }

    public function cancel(string $uuid){
        /*
        Used to cancel a buy or sell order
        Endpoint:
        1.1 /market/cancel
        2.0 /key/market/tradecancel
        :param uuid: uuid of buy or sell order
        :type uuid: str
        :return:
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/market/cancel',
            API_V2_0 => '/key/market/tradecancel'
        ], PROTECTION_PRV, [
            'uuid' => $uuid, 
            'orderid' => $uuid
        ]);
    }

    public function get_open_orders(string $market = null){
        /*
        Get all orders that you currently have opened.
        A specific market can be requested.
        Endpoint:
        1.1 /market/getopenorders
        2.0 /key/market/getopenorders
        :param market: String literal for the market (ie. BTC-LTC)
        :type market: str
        :return: Open orders info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/market/getopenorders',
            API_V2_0 => '/key/market/getopenorders'
        ], PROTECTION_PRV, ($market)?[
            'market' => $market, 
            'marketname' => $market
        ] : null);
    }

    public function get_balances(){
        /*
        Used to retrieve all balances from your account.
        Endpoint:
        1.1 /account/getbalances
        2.0 /key/balance/getbalances
        Example ::
            {'success': True,
             'message': '',
             'result': [ {'Currency': '1ST',
                          'Balance': 10.0,
                          'Available': 10.0,
                          'Pending': 0.0,
                          'CryptoAddress': null},
                          ...
                        ]
            }
        :return: Balances info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/getbalances',
            API_V2_0 => '/key/balance/getbalances'
        ], PROTECTION_PRV);
    }

    public function get_balance(string $currency){
        /*
        Used to retrieve the balance from your account for a specific currency
        Endpoint:
        1.1 /account/getbalance
        2.0 /key/balance/getbalance
        Example ::
            {'success': True,
             'message': '',
             'result': {'Currency': '1ST',
                        'Balance': 10.0,
                        'Available': 10.0,
                        'Pending': 0.0,
                        'CryptoAddress': null}
            }
        :param currency: String literal for the currency (ex: LTC)
        :type currency: str
        :return: Balance info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/getbalance',
            API_V2_0 => '/key/balance/getbalance'
        ], PROTECTION_PRV, [
            'currency' => $currency, 
            'currencyname' => $currency
        ]);
    }

    public function get_deposit_address(string $currency){
        /*
        Used to generate or retrieve an address for a specific currency
        Endpoint:
        1.1 /account/getdepositaddress
        2.0 /key/balance/getdepositaddress
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: Address info in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/getdepositaddress',
            API_V2_0 => '/key/balance/getdepositaddress'
        ], PROTECTION_PRV, [
            'currency' => $currency, 
            'currencyname' => $currency
        ]);
    }

    public function withdraw(string $currency, float $quantity, string $address){
        /*
        Used to withdraw funds from your account
        Endpoint:
        1.1 /account/withdraw
        2.0 /key/balance/withdrawcurrency
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :param quantity: The quantity of coins to withdraw
        :type quantity: float
        :param address: The address where to send the funds.
        :type address: str
        :return:
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/withdraw',
            API_V2_0 => '/key/balance/withdrawcurrency'
        ], PROTECTION_PRV, [
            'currency' => $currency, 
            'quantity' => $quantity, 
            'address' => $address
        ]);
    }

    public function get_order_history(string $market=null){
        /*
        Used to retrieve order trade history of account
        Endpoint:
        1.1 /account/getorderhistory
        2.0 /key/orders/getorderhistory or /key/market/GetOrderHistory
        :param market: optional a string literal for the market (ie. BTC-LTC).
            If omitted, will return for all markets
        :type market: str
        :return: order history in JSON
        :rtype : object
        */
        if ($market){
            return $this->_api_query([
                API_V1_1 => '/account/getorderhistory',
                API_V2_0 => '/key/market/GetOrderHistory'
            ], PROTECTION_PRV, [
                'market' => $market, 
                'marketname' => $market
            ]);
        } else {
            return $this->_api_query([
                API_V1_1 => '/account/getorderhistory',
                API_V2_0 => '/key/orders/getorderhistory'
            ], PROTECTION_PRV);
        }
    }

    public function get_order($uuid){
        /*
        Used to get details of buy or sell order
        Endpoint:
        1.1 /account/getorder
        2.0 /key/orders/getorder
        :param uuid: uuid of buy or sell order
        :type uuid: str
        :return:
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/getorder',
            API_V2_0 => '/key/orders/getorder'
        ], PROTECTION_PRV, [
            'uuid' => $uuid, 
            'orderid' => $uuid
        ]);
    }

    public function get_withdrawal_history(string $currency=null){
        /*
        Used to view your history of withdrawals
        Endpoint:
        1.1 /account/getwithdrawalhistory
        2.0 /key/balance/getwithdrawalhistory
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: withdrawal history in JSON
        :rtype : object
        */

        return $this->_api_query([
            API_V1_1 => '/account/getwithdrawalhistory',
            API_V2_0 => '/key/balance/getwithdrawalhistory'
        ], PROTECTION_PRV, ($currency)?[
            'currency' => $currency, 
            'currencyname' => $currency
        ] : null);
    }

    public function get_deposit_history(string $currency=null){
        /*
        Used to view your history of deposits
        Endpoint:
        1.1 /account/getdeposithistory
        2.0 /key/balance/getdeposithistory
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: deposit history in JSON
        :rtype : object
        */
        return $this->_api_query([
            API_V1_1 => '/account/getdeposithistory',
            API_V2_0 => '/key/balance/getdeposithistory'
        ], PROTECTION_PRV, ($currency)?[
            'currency' => $currency, 
            'currencyname' => $currency
        ] : null);
    }

    public function list_markets_by_currency(string $currency){
        /*
        Helper function to see which markets exist for a currency.
        Endpoint: /public/getmarkets
        Example ::
            >>> Bittrex(null, null).list_markets_by_currency('LTC')
            ['BTC-LTC', 'ETH-LTC', 'USDT-LTC']
        :param currency: String literal for the currency (ex: LTC)
        :type currency: str
        :return: List of markets that the currency appears in
        :rtype: list
        */
        /*
        //TODO:
        return [$market['MarketName'] for $market in $this->get_markets()['result']
                if $market['MarketName'].lower().endswith($currency.lower())]
        */
    }

    public function get_wallet_health(){
        /*
        Used to view wallet health
        Endpoints:
        1.1 NO Equivalent
        2.0 /pub/Currencies/GetWalletHealth
        :return:
        */
        return $this->_api_query([
            API_V2_0 => '/pub/Currencies/GetWalletHealth'
        ], PROTECTION_PUB);
    }

    public function get_balance_distribution(){
        /*
        Used to view balance distibution
        Endpoints:
        1.1 NO Equivalent
        2.0 /pub/Currency/GetBalanceDistribution
        :return:
        */
        return $this->_api_query([
            API_V2_0 => '/pub/Currency/GetBalanceDistribution'
        ], PROTECTION_PUB);
    }

    public function get_pending_withdrawals(string $currency){
        /*
        Used to view your pending withdrawls
        Endpoint:
        1.1 NO EQUIVALENT
        2.0 /key/balance/getpendingwithdrawals
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: pending widthdrawls in JSON
        :rtype : list
        */
        return $this->_api_query([
            API_V2_0 => '/key/balance/getpendingwithdrawals'
        ], PROTECTION_PRV, ($currency)?[
            'currencyname' => $currency
        ] : null);
    }

    public function get_pending_deposits(string $currency){
        /*
        Used to view your pending deposits
        Endpoint:
        1.1 NO EQUIVALENT
        2.0 /key/balance/getpendingdeposits
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: pending deposits in JSON
        :rtype : list
        */
        return $this->_api_query([
            API_V2_0 => '/key/balance/getpendingdeposits'
        ], PROTECTION_PRV, ($currency)?[
            'currencyname' => $currency
        ] : null);
    }

    public function generate_deposit_address(string $currency){
        /*
        Generate a deposit address for the specified currency
        Endpoint:
        1.1 NO EQUIVALENT
        2.0 /key/balance/generatedepositaddress
        :param currency: String literal for the currency (ie. BTC)
        :type currency: str
        :return: result of creation operation
        :rtype : object
        */
        return $this->_api_query([
            API_V2_0 => '/key/balance/getpendingdeposits'
        ], PROTECTION_PRV, [
            'currencyname' => $currency
        ]);
    }

    public function trade_sell(string $market, string $order_type, float $quantity, float $rate=null, $time_in_effect=null,
    $condition_type=CONDITIONTYPE_null, float $target=0.0){
        /*
        Enter a sell order into the book
        Endpoint
        1.1 NO EQUIVALENT -- see sell_market or sell_limit
        2.0 /key/market/tradesell
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :param order_type: ORDERTYPE_LIMIT = 'LIMIT' or ORDERTYPE_MARKET = 'MARKET';
        :type order_type: str
        :param quantity: The amount to purchase
        :type quantity: float
        :param rate: The rate at which to place the order.
            This is not needed for market orders
        :type rate: float
        :param time_in_effect: TIMEINEFFECT_GOOD_TIL_CANCELLED = 'GOOD_TIL_CANCELLED',
                TIMEINEFFECT_IMMEDIATE_OR_CANCEL = 'IMMEDIATE_OR_CANCEL', or TIMEINEFFECT_FILL_OR_KILL = 'FILL_OR_KILL';
        :type time_in_effect: str
        :param condition_type: CONDITIONTYPE_null = 'null', CONDITIONTYPE_GREATER_THAN = 'GREATER_THAN',
                CONDITIONTYPE_LESS_THAN = 'LESS_THAN', CONDITIONTYPE_STOP_LOSS_FIXED = 'STOP_LOSS_FIXED',
                CONDITIONTYPE_STOP_LOSS_PERCENTAGE = 'STOP_LOSS_PERCENTAGE';
        :type condition_type: str
        :param target: used in conjunction with condition_type
        :type target: float
        :return:
        */
        return $this->_api_query([
            API_V2_0 => '/key/market/tradesell'
        ], PROTECTION_PRV, [
            'marketname' => $market,
            'ordertype' => $order_type,
            'quantity' => $quantity,
            'rate' => $rate,
            'timeInEffect' => $time_in_effect,
            'conditiontype' => $condition_type,
            'target' => $target
        ]);
    }

    public function trade_buy(string $market, string $order_type, float $quantity, float $rate=null, $time_in_effect=null,
                 $condition_type=CONDITIONTYPE_null, float $target=0.0){
        /*
        Enter a buy order into the book
        Endpoint 
        1.1 NO EQUIVALENT -- see buy_market or buy_limit
        2.0 /key/market/tradebuy
        :param market: String literal for the market (ex: BTC-LTC)
        :type market: str
        :param order_type: ORDERTYPE_LIMIT = 'LIMIT' or ORDERTYPE_MARKET = 'MARKET';
        :type order_type: str
        :param quantity: The amount to purchase
        :type quantity: float
        :param rate: The rate at which to place the order.
            This is not needed for market orders
        :type rate: float
        :param time_in_effect: TIMEINEFFECT_GOOD_TIL_CANCELLED = 'GOOD_TIL_CANCELLED',
                TIMEINEFFECT_IMMEDIATE_OR_CANCEL = 'IMMEDIATE_OR_CANCEL', or TIMEINEFFECT_FILL_OR_KILL = 'FILL_OR_KILL';
        :type time_in_effect: str
        :param condition_type: CONDITIONTYPE_null = 'null', CONDITIONTYPE_GREATER_THAN = 'GREATER_THAN',
                CONDITIONTYPE_LESS_THAN = 'LESS_THAN', CONDITIONTYPE_STOP_LOSS_FIXED = 'STOP_LOSS_FIXED',
                CONDITIONTYPE_STOP_LOSS_PERCENTAGE = 'STOP_LOSS_PERCENTAGE';
        :type condition_type: str
        :param target: used in conjunction with condition_type
        :type target: float
        :return:
        */
        return $this->_api_query([
            API_V2_0 => '/key/market/tradebuy'
        ], PROTECTION_PRV, [
            'marketname' => $market,
            'ordertype' => $order_type,
            'quantity' => $quantity,
            'rate' => $rate,
            'timeInEffect' => $time_in_effect,
            'conditiontype' => $condition_type,
            'target' => $target
        ]);
    }

    public function get_candles(string $market, $tick_interval){
        /*
        Used to get all tick candle for a market.
        Endpoint:
        1.1 NO EQUIVALENT
        2.0 /pub/market/GetTicks
        Example  ::
            { success: true,
              message: '',
              result:
               [ { O: 421.20630125,
                   H: 424.03951276,
                   L: 421.20630125,
                   C: 421.20630125,
                   V: 0.05187504,
                   T: '2016-04-08T00:00:00',
                   BV: 21.87921187 },
                 { O: 420.206,
                   H: 420.206,
                   L: 416.78743422,
                   C: 416.78743422,
                   V: 2.42281573,
                   T: '2016-04-09T00:00:00',
                   BV: 1012.63286332 }]
            }
        :return: Available tick candle in JSON
        :rtype : object
        */

        return $this->_api_query([
            API_V2_0 => '/pub/market/GetTicks'
        ], PROTECTION_PUB, [
            'marketName' => $market, 
            'tickInterval' => $tick_interval
        ]);
    }

    public function get_latest_candle(string $market, $tick_interval){
        /*
        Used to get the latest candle for the market.
        Endpoint:
        1.1 NO EQUIVALENT
        2.0 /pub/market/GetLatestTick
        Example ::
            { success: true,
              message: '',
              result:
              [ {   O : 0.00350397,
                    H : 0.00351000,
                    L : 0.00350000,
                    C : 0.00350350,
                    V : 1326.42643480,
                    T : 2017-11-03T03:18:00,
                    BV: 4.64416189 } ]
            }
        :return: Available latest tick candle in JSON
        :rtype : object
        */

        return $this->_api_query([
            API_V2_0 => '/pub/market/GetLatestTick'
        ], PROTECTION_PUB, [
            'marketName' => $market, 
            'tickInterval' => $tick_interval
        ]);
    }
}