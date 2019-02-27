<?php
/**
 * Created by PhpStorm.
 * User: fengkai8375
 * Date: 2019/2/27
 * Time: 下午2:33
 */

class Ethereum
{
    private $wallet_url;

    public function __construct($wallet_ip, $wallet_port){
        $this->wallet_url = "{$wallet_ip}:{$wallet_port}";
    }

    /**
     * ETH钱包交互
     */
    public function send_request($api , $param, $timeout = 5){
        $request_id = rand(1, 99);
        $data = "{\"jsonrpc\":\"2.0\",\"method\":\"{$api}\",\"params\":{$param},\"id\":{$request_id}}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->wallet_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $content = curl_exec($ch);
        $error_no = curl_errno($ch);
        curl_close($ch);
        if($error_no){
            return false;
        }else{
            return json_decode($content, true);
        }
    }

    /**
     * 发送Ether
     */
    public function send_ether($from, $to, $gas_price, $num){
        $num = $this->fix_my_num($num, 5);
        $custom_gas_price_hex = dechex($gas_price);
        $amount = bcdechex(bcmul($num, 10 ^ 18));

        $param = array(
            'from' => $from,
            'to' => $to,
            'gas' => "0x5208",  //25200个Gas limit，实际使用21000
            'gasPrice' => "0x{$custom_gas_price_hex}",
            'value' => "0x{$amount}"
        );
        $param = json_encode($param);

        $sendrs = $this->send_request('eth_sendTransaction', "[{$param}]");

        return $sendrs;
    }

    /**
     * 发送Ether Token
     */
    public function send_token($from, $to, $token_address, $token_decimals, $gas_price, $num){
        $gas = "0x1d4c0";

        $num = $this->fix_my_num($num, 5);
        $custom_gas_price_hex = dechex($gas_price);

        $hex_data = "0xa9059cbb";

        $hex_data .= str_repeat(0, 24);

        $hex_data .= substr($to, 2); //去掉0x

        $amount = bcdechex(bcmul($num, $token_decimals));

        $hex_data .= str_repeat(0, 64 - strlen($amount)) . $amount;

        $param = array(
            'from' => $from,
            'to' => $token_address,
            'gas' => $gas,  //120000个Gas limit，实际使用多少不确定，多在30000~60000
            'gasPrice' => "0x{$custom_gas_price_hex}",
            'data' => "{$hex_data}"
        );
        $param = json_encode($param);

        $sendrs = $this->send_request('eth_sendTransaction', "[{$param}]");

        return $sendrs;
    }

    /**
     * 查询Ether余额
     */
    public function get_eth_balance($address){
        $decimals = 10 ^ 18;
        $response = $this->send_request("eth_getBalance", "[\"{$address}\",\"latest\"]");
        $eth_balance =  hexdec($response['result']) / $decimals;
        return $eth_balance;
    }

    /**
     * 查询Ether Token余额
     */
    public function get_token_balance($address, $token_address, $token_decimals){
        $query_data = "0x70a08231000000000000000000000000" . substr($address, 2);
        $response = $this->send_request("eth_call", "[{\"to\":\"{$token_address}\",\"data\":\"{$query_data}\"},\"latest\"]");
        $token_balance =  hexdec($response['result']) / $token_decimals;
        return $token_balance;
    }

    /**
     * 保留特定位数的小数
     */
    private function fix_my_num($num, $len){
        $arr = explode(".", $num);

        if(count($arr) == 1){
            return $num;
        }

        if(strlen($arr[1]) <= $len){
            return $num;
        }

        $fixed = $arr[0] . '.' . substr($arr[1], 0, $len);

        return floatval($fixed);
    }

}