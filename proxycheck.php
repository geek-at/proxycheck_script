<?php

// Things you can edit:
$type = $argv[2]?$argv[2]:'http';
$proxylist = $argv[1]?$argv[1]:'proxylist_'.$type.'.txt';
$timeout = $argv[3]?$argv[3]:5;
$basedir = $argv[4]?$argv[4]:'tmp/'; // the directory where session folders will be stored





//urls that will be checked
$js = 'http://orf.at/oon/media/3.0/jquery.video-min.js';
$https = 'https://ip.haschek.at';
$url = 'http://s.haschek.at/hascheksolutions/index.html';
$ipcheck_url = 'http://ip.haschek.at';


// from here on just things you can edit if you know what you're doing
//
//

$start = time();
$session = 'scan_'.$type.'_'.substr(md5(rand(0,1000).time()),-8); //session used for storing data
$socks = ($type=='socks'?true:false);

if(!file_exists($proxylist)) exit("[X] File $proxylist not found.\n");
if(!is_dir($basedir))
	mkdir($basedir);
mkdir($basedir.$session);

//	reference data
$refdata = @scanProxy(false,$js,$socks,$timeout);
$refdata_html = @scanProxy(false,$url,$socks,$timeout);
$ip_ref = @scanProxy(false,$ipcheck_url,$socks,$timeout);

//saving the original files so you can do diffs on them
saveData($basedir.$session."/original_".basename($js),$refdata);
saveData($basedir.$session."/original_".basename($url),$refdata_html);

$lines = file($proxylist);
echo "[i] Got ".count($lines)." proxies to test\n";
foreach($lines as $line)
{
	$proxy = trim($line);
	if(!$proxy) continue;
	$out['tested']++;
	$bad = false;
	echo "[i] Trying $proxy";

	$data = @scanProxy($proxy,$js,$socks,$timeout); 				//get the requested file from the proxy
	$data_html = @scanProxy($proxy,$url,$socks,$timeout); 			//get the requested file from the proxy
	$https_data = @scanProxy($proxy,$https,$socks,$timeout);		//is https allowed?
	$ip_viaproxy = @scanProxy($proxy,$ipcheck_url,$socks,$timeout);	//what IP does the proxy return


	$online = ($data?'online':'offline');
	$altered_js = (strlen($data)==strlen($refdata))?'no':'yes';
	$altered_html = (strlen($data_html)==strlen($refdata_html))?'no':'yes';
	$http_allowed = ($data||$data_html)?'yes':'no';
	$https_allowed = ($https_data)?'yes':'no';
	$ip_altered = ($ip_viaproxy!=$ip_ref)?'yes':'no';

	if($data || $data_html || $https_data || $ip_viaproxy) //online
	{
		addData($basedir.$session."/up.txt",$proxy);
		$out['up']++;

		if($altered_js=='yes' && $data!='')
		{
			echo "\t [JS]";
			addData($basedir.$session."/bad_js.txt",$proxy);
			addData($basedir.$session."/bad.txt",$proxy);
			saveData($basedir.$session."/".str_replace(':', '-', $proxy)."_".basename($js),$data);
			$out['altered_js']++;
			$bad = true;
		}

		if($altered_html=='yes' && $data_html!='')
		{
			echo "\t [HTML]";
			addData($basedir.$session."/bad_html.txt",$proxy);
			addData($basedir.$session."/bad.txt",$proxy);
			saveData($basedir.$session."/".str_replace(':', '-', $proxy)."_".basename($url),$data_html);
			$out['altered_html']++;
			$bad = true;
		}

		if($ip_altered=='no' && $ip_viaproxy!='')
		{
			echo "\t [NOIP]";
			addData($basedir.$session."/transparent.txt",$proxy);
			$out['nonaltered_ip']++;
			$bad = true;
		}

		if($http_allowed=='yes')
		{
			addData($basedir.$session."/http_allowed.txt",$proxy);
			$out['http_allowed']++;
		}

		if($https_allowed=='no')
		{
			echo "\t [HTTPS]";
			addData($basedir.$session."/https_forbidden.txt",$proxy);
			$out['https_forbidden']++;
			$bad = true;
		}
		else {$out['https_allowed']++;}

		if(!$bad)
		{
			echo " [GOOD]";
			addData($basedir.$session."/good.txt",$proxy);
		}
			

		echo "\n";
	}
	else
	{
		echo "                                                    \r";
		$out['down']++;
	}

}

echo "\n============  STATS  ==============\n";
echo "Proxies tested:\t".$out['tested']."\n";
echo "Online:\t".$out['up']."\n";
echo "Offline:\t".$out['down']."\n";
echo "HTTP only\t".$out['https_forbidden']."\n";
echo "Altered JS\t".$out['altered_js']."\n";
echo "Altered HTML\t".$out['altered_html']."\n";
echo "IP not hidden\t".$out['nonaltered_ip']."\n";

echo "Runtime\t".round((time()-$start)/60)." Minutes\n";

if(is_array($p))
	saveData($basedir.$session."/bad_proxies_$type.txt",implode("\n", $p));


/**************************************************************************/
/* scanProxy function by Christian Haschek christian@haschek.at           */
/* It's intended to be used with php5-cli .. don't put it on a web server */
/*                                                                        */
/* Requests a specific file ($url) via a proxy ($proxy)                   */
/* if first parameter is set to false it will retrieve                    */
/* $url without a proxy. CURL extension for PHP is required.              */
/*                                                                        */
/* @param $proxy (string) is the proxy server used (eg 127.0.0.1:8123)    */
/* @param $url (string) is the URL of the requested file or site          */
/* @param $socks (bool) true: SOCKS proxy, false: HTTP proxy              */
/* @param $timeout (int) timeout for the request in seconds               */
/* @return (string) the content of requested url                          */
/**************************************************************************/
function scanProxy($proxy,$url,$socks=true,$timeout=10)
{
    $ch = curl_init($url); 
    $headers["User-Agent"] = "Proxyscanner/1.0";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, 0); //we don't need headers in our output
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0); 
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,$timeout); 
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return output as string
    $proxytype = ($socks?CURLPROXY_SOCKS5:CURLPROXY_HTTP); //socks or http proxy?
    if($proxy)
    {
        curl_setopt($ch, CURLOPT_PROXY, $proxy); 
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxytype);
    }

    $out = curl_exec($ch); 
    curl_close($ch);

    return trim($out);
}

function saveData($filename,$data)
{
	$fp = fopen($filename,'w');
	fwrite($fp, $data);
	fclose($fp);
}

function addData($filename,$data)
{
	$fp = fopen($filename,'a');
	fwrite($fp, $data."\n");
	fclose($fp);
}