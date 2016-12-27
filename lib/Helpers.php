<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function bybassUrl($url) {
    $url = base64_encode($url);
    return "http://mssit.6te.net/?url=$url";
}

function smartDownload($url, $savePath) {
    if (!file_exists($savePath)) {
        $buffer = file_get_contents($url);
        if (!empty($buffer)) {
            file_put_contents($savePath, $buffer);
        }
    }
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function paragraphTrim($text, $maxLength) {
    if (strlen($text)>$maxLength) {
        $text = substr($text, 0, $maxLength - 1);
        $words = explode(' ', $text);
        array_pop($words);
        return implode(' ', $words) . "…";
    } else {
        return $text;
    }
}

//&macr;\_(ツ)_/&macr;
function decodeHtml($text){
    return html_entity_decode($text, ENT_XHTML  , 'UTF-8');
}

function ip_is_private ($ip) {
    $pri_addrs = array (
                      '10.0.0.0|10.255.255.255', // single class A network
                      '172.16.0.0|172.31.255.255', // 16 contiguous class B network
                      '192.168.0.0|192.168.255.255', // 256 contiguous class C network
                      '169.254.0.0|169.254.255.255', // Link-local address also refered to as Automatic Private IP Addressing
                      '127.0.0.0|127.255.255.255' // localhost
                     );

    $long_ip = ip2long ($ip);
    if ($long_ip != -1) {

        foreach ($pri_addrs AS $pri_addr) {
            list ($start, $end) = explode('|', $pri_addr);

             // IF IS PRIVATE
             if ($long_ip >= ip2long ($start) && $long_ip <= ip2long ($end)) {
                 return true;
             }
        }
    }

    return false;
}

function isCommandLineInterface()
{
    return (php_sapi_name() === 'cli');
}

function swapVars(&$var1,&$var2){
    $temp=$var1;
    $var1=$var2;
    $var2=$temp;
}