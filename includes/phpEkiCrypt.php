<?php

/*
 * phpEkiCrypt demo utility
 *
 * CIB Bank Zrt. 2018 ecommerce@cib.hu
 *
 * This utility is part of EKI protocol documentation and provided AS IS.
 *
 * USAGE: See page info
 */

define("SAKI_ENCRYPT", 0);
define("SAKI_DECRYPT", 1);

/**
 * Function saki_binary2binstr
 * Converts a mixed input (string) into it binary representation
 *
 * @param mixed $input arbitrary binary input
 * @param int $len the accumulated bit length the output should be padded to
 * @return String
 *
 * Example: saki_binary2binstr("asdf",32) returns "01100001011100110110010001100110"
 */
function saki_binary2binstr($input, $len)
{
	$retarr = unpack("H*", $input);
	$rets = "";
	for ($i = 0; $i < strlen($retarr[1]); $i++)
	{
		$bints = base_convert(substr($retarr[1], $i, 1), 16, 2);
		$rets .= str_repeat("0", 4 - strlen($bints)) . $bints;
	}
	$rets = str_repeat("0", $len - strlen($rets)) . $rets;
	return $rets;
}

/**
 * Function saki_binstr2binary
 * Converts a binary representation into its string equivalent
 *
 * @param String $input, must be mod8 long
 * @return String
 *
 * Example: saki_binstr2binary("01100001011100110110010001100110") returns "asdf"
 */
function saki_binstr2binary($input)
{
	$ret = "";
	for ($i = 0; $i < strlen($input) / 8; $i++)
	{
		$ascval = base_convert(substr($input, $i * 8, 8), 2, 10);
		$ret .= chr($ascval);
	}
	return $ret;
}

/**
 * Function saki_binstr2int
 * Converts a a binary representation into decimal value
 *
 * @param String $input
 * @return int
 *
 * Example: saki_binstr2int('10010100') returns 148
 */
function saki_binstr2int($input)
{
	return intval(base_convert($input, 2, 10));
}

/**
 * Function saki_int2binstr
 * Converts a base10 integer to a specified length binary representation
 *
 * @param int $input
 * @param int $len
 * @return string
 *
 * Example: saki_int2binstr(10, 8) returns "00001010"
 */
function saki_int2binstr($input, $len)
{
	$ret = base_convert($input, 10, 2);
	$ret = str_repeat("0", $len - strlen($ret)) . $ret;
	return $ret;
}

/**
 * function saki_xor
 * Applies binary XOR to two equal-lengthbinary representative strings.
 *
 * @param String $input1
 * @param String $input2
 * @return String
 *
 * Example: saki_xor("0101", "1110") returns "1011"
 */
function saki_xor($input1, $input2)
{
	$bininput1 = saki_binstr2binary($input1);
	$bininput2 = saki_binstr2binary($input2);
	$binret = $bininput1 ^ $bininput2;
	$ret = saki_binary2binstr($binret, strlen($bininput1) * 8);
	return $ret;
}

/**
 * function saki_lsh
 * Shifts binary string values in $input exactly $cnt positiions to the left.
 *
 * @param String $input
 * @param int $cnt
 * @param bool $rotate
 * @return String
 *
 * EXample: saki_lsh("0100", 2, true) returns "0001"
 */
function saki_lsh($input, $cnt, $rotate=false)
{
	$ret = substr($input, $cnt, strlen($input)-$cnt);
	if ($rotate)
		$ret .= substr($input, 0, $cnt);
	else
		$ret .= str_repeat("0", $cnt);
	return $ret;
}

/**
 * function des_remap
 * Processes remappings and S-boxes needed by DES
 *
 * @param String $input, the input of the algorithm in binary string format
 * @param String $mapname, the name of the box
 * @return String
 */
function des_remap($input, $mapname)
{
	$maps = array(
			"PC1" => array(57, 49, 41, 33, 25, 17, 9, 1, 58, 50, 42, 34, 26, 18, 10, 2, 59, 51, 43, 35, 27, 19, 11, 3, 60, 52, 44, 36, 63, 55, 47, 39, 31, 23, 15, 7, 62, 54, 46, 38, 30, 22, 14, 6, 61, 53, 45, 37, 29, 21, 13, 5, 28, 20, 12, 4),
			"PC2" => array(14, 17, 11, 24, 1, 5, 3, 28, 15, 6, 21, 10, 23, 19, 12, 4, 26, 8, 16, 7, 27, 20, 13, 2, 41, 52, 31, 37, 47, 55, 30, 40, 51, 45, 33, 48, 44, 49, 39, 56, 34, 53, 46, 42, 50, 36, 29, 32),
			"IP" => array(58, 50, 42, 34, 26, 18, 10, 2, 60, 52, 44, 36, 28, 20, 12, 4, 62, 54, 46, 38, 30, 22, 14, 6, 64, 56, 48, 40, 32, 24, 16, 8, 57, 49, 41, 33, 25, 17, 9, 1, 59, 51, 43, 35, 27, 19, 11, 3, 61, 53, 45, 37, 29, 21, 13, 5, 63, 55, 47, 39, 31, 23, 15, 7, ),
			"P" => array(16, 7, 20, 21, 29, 12, 28, 17, 1, 15, 23, 26, 5, 18, 31, 10, 2, 8, 24, 14, 32, 27, 3, 9, 19, 13, 30, 6, 22, 11, 4, 25),
			"IPINV" => array(40, 8, 48, 16, 56, 24, 64, 32, 39, 7, 47, 15, 55, 23, 63, 31, 38, 6, 46, 14, 54, 22, 62, 30, 37, 5, 45, 13, 53, 21, 61, 29, 36, 4, 44, 12, 52, 20, 60, 28, 35, 3, 43, 11, 51, 19, 59, 27, 34, 2, 42, 10, 50, 18, 58, 26, 33, 1, 41, 9, 49, 17, 57, 25),
			"S" => array(
					array(14, 4, 13, 1, 2, 15, 11, 8, 3, 10, 6, 12, 5, 9, 0, 7, 0, 15, 7, 4, 14, 2, 13, 1, 10, 6, 12, 11, 9, 5, 3, 8, 4, 1, 14, 8, 13, 6, 2, 11, 15, 12, 9, 7, 3, 10, 5, 0, 15, 12, 8, 2, 4, 9, 1, 7, 5, 11, 3, 14, 10, 0, 6, 13),
					array(15, 1, 8, 14, 6, 11, 3, 4, 9, 7, 2, 13, 12, 0, 5, 10, 3, 13, 4, 7, 15, 2, 8, 14, 12, 0, 1, 10, 6, 9, 11, 5, 0, 14, 7, 11, 10, 4, 13, 1, 5, 8, 12, 6, 9, 3, 2, 15, 13, 8, 10, 1, 3, 15, 4, 2, 11, 6, 7, 12, 0, 5, 14, 9),
					array(10, 0, 9, 14, 6, 3, 15, 5, 1, 13, 12, 7, 11, 4, 2, 8, 13, 7, 0, 9, 3, 4, 6, 10, 2, 8, 5, 14, 12, 11, 15, 1, 13, 6, 4, 9, 8, 15, 3, 0, 11, 1, 2, 12, 5, 10, 14, 7, 1, 10, 13, 0, 6, 9, 8, 7, 4, 15, 14, 3, 11, 5, 2, 12),
					array(7, 13, 14, 3, 0, 6, 9, 10, 1, 2, 8, 5, 11, 12, 4, 15, 13, 8, 11, 5, 6, 15, 0, 3, 4, 7, 2, 12, 1, 10, 14, 9, 10, 6, 9, 0, 12, 11, 7, 13, 15, 1, 3, 14, 5, 2, 8, 4, 3, 15, 0, 6, 10, 1, 13, 8, 9, 4, 5, 11, 12, 7, 2, 14),
					array(2, 12, 4, 1, 7, 10, 11, 6, 8, 5, 3, 15, 13, 0, 14, 9, 14, 11, 2, 12, 4, 7, 13, 1, 5, 0, 15, 10, 3, 9, 8, 6, 4, 2, 1, 11, 10, 13, 7, 8, 15, 9, 12, 5, 6, 3, 0, 14, 11, 8, 12, 7, 1, 14, 2, 13, 6, 15, 0, 9, 10, 4, 5, 3),
					array(12, 1, 10, 15, 9, 2, 6, 8, 0, 13, 3, 4, 14, 7, 5, 11, 10, 15, 4, 2, 7, 12, 9, 5, 6, 1, 13, 14, 0, 11, 3, 8, 9, 14, 15, 5, 2, 8, 12, 3, 7, 0, 4, 10, 1, 13, 11, 6, 4, 3, 2, 12, 9, 5, 15, 10, 11, 14, 1, 7, 6, 0, 8, 13),
					array(4, 11, 2, 14, 15, 0, 8, 13, 3, 12, 9, 7, 5, 10, 6, 1, 13, 0, 11, 7, 4, 9, 1, 10, 14, 3, 5, 12, 2, 15, 8, 6, 1, 4, 11, 13, 12, 3, 7, 14, 10, 15, 6, 8, 0, 5, 9, 2, 6, 11, 13, 8, 1, 4, 10, 7, 9, 5, 0, 15, 14, 2, 3, 12),
					array(13, 2, 8, 4, 6, 15, 11, 1, 10, 9, 3, 14, 5, 0, 12, 7, 1, 15, 13, 8, 10, 3, 7, 4, 12, 5, 6, 11, 0, 14, 9, 2, 7, 11, 4, 1, 9, 12, 14, 2, 0, 6, 10, 13, 15, 3, 5, 8, 2, 1, 14, 7, 4, 10, 8, 13, 15, 12, 9, 0, 3, 5, 6, 11)
			),
			"E" => array(32, 1, 2, 3, 4, 5, 4, 5, 6, 7, 8, 9, 8, 9, 10, 11, 12, 13, 12, 13, 14, 15, 16, 17, 16, 17, 18, 19, 20, 21, 20, 21, 22, 23, 24, 25, 24, 25, 26, 27, 28, 29, 28, 29, 30, 31, 32, 1)
	);
	$ret = "";
	if (substr($mapname, 0, 1) == "S")
	{
		for ($i = 0; $i < 8; $i++)
		{
			$sinput = substr($input, $i * 6, 6);
			$row = saki_binstr2int(substr($sinput, 0, 1) . substr($sinput, 5, 1));
			$col = saki_binstr2int(substr($sinput, 1, 1) . substr($sinput, 2, 1) . substr($sinput, 3, 1) . substr($sinput, 4, 1));
			$rval = saki_int2binstr($maps["S"][$i][($row * 16) + $col], 4);
			$ret .= $rval;
		}
	}
	else
	{
		for ($i = 0; $i < count($maps[$mapname]); $i++)
			 $ret .= substr($input, $maps[$mapname][$i] - 1, 1);
	}
	return $ret;
}

/**
 * Function des_getsubkeys
 * Calculates DES subkeys for given binary-rep key
 *
 * @param String $key
 * @throws Exception upon key length error
 *
 * @return array
 */
function des_getsubkeys($key)
{
	$key = saki_binary2binstr($key, strlen($key) * 8);
	if (strlen($key) != 64)
		throw new Exception ("Key is not 64 bits long!");
	$key = des_remap($key, "PC1");
	$lshift = array(1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1);
	$keyn = array();
	for ($i = 1; $i <= 16; $i++)
	{
		$keyc = saki_lsh(substr($key, 0, 28), array_sum(array_slice($lshift, 0, $i)), true);
		$keyd = saki_lsh(substr($key, 28, 28), array_sum(array_slice($lshift, 0, $i)), true);
		$keycd_remapped = des_remap($keyc . $keyd, "PC2");
		if (strlen($keycd_remapped) != 48)
			throw new Exception("CnDn key is not 48 bits long!");
			array_push($keyn, $keycd_remapped);
	}
	return $keyn;
}

/**
 * function des_crypt
 *
 * Encrypts or decrypts arbitrary length data using 3DES CBC mode
 *
 * @param String $msg
 * @param int $direction, one of SAKI_ENCRYPT, SAKI_DECRYPT
 * @param String $key12, the 16 bytes long key (key1 and key2 concatenated)
 * @param String $iv, the 8 bytes log init vector
 * @return string
 */
function des_crypt($msg, $direction, $key12, $iv)
{
	$ret = "";
	try
	{
		if (strlen($msg) % 8 != 0)
			throw new Exception("Input message is not mod 8 long.");
		$msg = saki_binary2binstr($msg, strlen($msg) * 8);
		$iv = saki_binary2binstr($iv, strlen($iv) * 8);
		if (strlen($iv) != 64)
			throw new Exception ("IV is not 64 bits long!");
		$key1n = des_getsubkeys(substr($key12, 0, 8));
		$key1rn = array_reverse($key1n);
		$key2n = des_getsubkeys(substr($key12, 8, 8));
		$key2rn = array_reverse($key2n);
		$lastblockinput = "";
		$lastblockoutput = "";
		for ($i = 0; $i < strlen($msg) / 64; $i++)
		{
			$blockinput = substr($msg, $i * 64, 64);
			$curblockinput = $blockinput;
			if ($direction == SAKI_ENCRYPT)
			{
				if ($i > 0)
					$iv = $lastblockoutput;
				$blockinput = saki_xor($iv, $blockinput);
			}
			for ($d = 0; $d < 3; $d++)
			{
				if ($d > 0)
					$blockinput = $lastblockoutput;
				$blockinput = des_remap($blockinput, "IP");
				$blockl = substr($blockinput, 0, 32);
				$blockr = substr($blockinput, 32, 32);
				$keyn = array();
				if ($direction == SAKI_ENCRYPT)
					($d == 1) ? $keyn = $key2rn : $keyn = $key1n;
				else
					($d == 1) ? $keyn = $key2n  : $keyn = $key1rn;
				for ($j = 0; $j < 16; $j++)
				{
					$blockrlast = $blockr;
					$blockr = saki_xor($blockl, des_remap(des_remap(saki_xor($keyn[$j], des_remap($blockrlast, "E")), "S"), "P"));
					$blockl = $blockrlast;
				}
				$lastblockoutput = des_remap($blockr . $blockl, "IPINV");
				if (strlen($lastblockoutput) != 64)
					throw new Exception("Block output is not 64 bits long");
			}
			if ($direction == SAKI_DECRYPT)
			{
				if ($i > 0)
					$iv = $lastblockinput;
				$lastblockoutput = saki_xor($iv, $lastblockoutput);
				$lastblockinput = $curblockinput;
			}
			$ret .= $lastblockoutput;
		}
		$ret = saki_binstr2binary($ret);
	}
	catch (Exception $e)
	{
		error_log($e->getMessage());
		$ret = "";
	}
	return $ret;
}

/**
 * Function des3_encrypt
 * Performs DES3-EDE-CBC encryption on a given string. Pads input as needed.
 *
 * @param String $cleartext the string to be encrypted
 * @param String $key the keys (key1 and key2) concatenated
 * @param String $iv the init vector
 * @return String
 */
function des3_encrypt($cleartext, $key, $iv)
{
	$ret = $cleartext;
	$pad = 8 - (strlen($ret) % 8);
	for ($i = 0; $i < $pad ; $i++)
		$ret .= chr($pad);
	$ret = des_crypt($ret, SAKI_ENCRYPT, $key, $iv);
	return $ret;
}

/**
 * Function des3_decrypt
 * Performs DES3-EDE-CBC decryption on a given string. Unpads plaintext as needed.
 *
 * @param String $ciphertext the string to be decrypted
 * @param String $key he keys (key1 and key2) concatenated
 * @param String $iv the init vector
 * @return String
 */
function des3_decrypt($ciphertext, $key, $iv)
{
	$ret = des_crypt($ciphertext, SAKI_DECRYPT, $key, $iv);
	$pad = ord(substr($ret, strlen($ret) - 1, 1));
	$ret = substr($ret, 0, strlen($ret) - $pad);
	return $ret;
}

/**
 * Function ekiEncode
 * Performs all mandated steps to encrypt a cleartext to its CIB-specifiec 3DES form, ready to be submitted.
 * The input must be an already assembled query string including parameters PID and CRYPTO
 * For further info please consult SAKI documentation.
 *
 * @param String $cleartext
 * @return string
 */
function ekiEncode($cleartext, $des) //origin no $des
{
	$arr = explode ( "&", $cleartext );
	$ciphertext = "";
	$pid = "";
	// Strip CRYPTO and get PID
	for($i = 0; $i < count ( $arr ); $i ++)
	{
		if (strtoupper ( $arr [$i] ) != "CRYPTO=1")
			$ciphertext .= "&" . $arr [$i];
		if (substr ( strtoupper ( $arr [$i] ), 0, 4 ) == "PID=")
			$pid = substr ( strtoupper ( $arr [$i] ), 4, 7 );
	}
	$ciphertext = substr ( $ciphertext, 1 );
	// URL encode
	$ciphertext = rawurlencode ( $ciphertext );
	$ciphertext = str_replace ( "%3D", "=", $ciphertext );
	$ciphertext = str_replace ( "%26", "&", $ciphertext );
	$crc = str_pad ( dechex ( crc32 ( $ciphertext ) ), 8, "0", STR_PAD_LEFT );
	for($i = 0; $i < 4; $i ++)
		$ciphertext .= chr ( base_convert ( substr ( $crc, $i * 2, 2 ), 16, 10 ) );
	$f = fopen ( $des, "r" );
	$keyinfo = fread ( $f, 38 );
	fclose ( $f );
	$key1 = substr ( $keyinfo, 14, 8 );
	$key2 = substr ( $keyinfo, 22, 8 );
	$iv = substr ( $keyinfo, 30, 8 );
	$key = $key1 . $key2 . $key1;
	$ciphertext = des3_encrypt ( $ciphertext, $key, $iv );
	$pad = 3 - (strlen ( $ciphertext ) % 3);
	for($i = 0; $i < $pad; $i ++)
		$ciphertext .= chr ( $pad );
	$ciphertext = base64_encode ( $ciphertext );
	$ciphertext = rawurlencode ( $ciphertext );
	$ciphertext = "PID=" . $pid . "&CRYPTO=1&DATA=" . $ciphertext;
	return $ciphertext;
}

/**
 * Function ekiDecode
 * Performs all mandated steps to decrypt a ciphertext to its CIB-specifiec plaintext form.
 * For further info please consult SAKI documentation.
 *
 * @param String $ciphertext
 * @return string
 */
function ekiDecode($ciphertext, $des) //origin no $des
{
	$arr = explode ( "&", $ciphertext );
	$cleartext = "";
	$pid = "";
	for($i = 0; $i < count ( $arr ); $i ++)
	{
		if (substr ( strtoupper ( $arr [$i] ), 0, 5 ) == "DATA=")
			$cleartext = substr ( $arr [$i], 5 );
		if (substr ( strtoupper ( $arr [$i] ), 0, 4 ) == "PID=")
			$pid = substr ( strtoupper ( $arr [$i] ), 4, 7 );
	}
	// Url decoding
	$cleartext = rawurldecode ( $cleartext );
	$cleartext = base64_decode ( $cleartext );
	$lastc = ord ( $cleartext [strlen ( $cleartext ) - 1] );
	$validpad = 1;
	for($i = 0; $i < $lastc; $i ++)
		if (ord ( substr ( $cleartext, strlen ( $cleartext ) - 1 - $i, 1 ) ) != $lastc)
			$validpad = 0;
	if ($validpad == 1)
		$cleartext = substr ( $cleartext, 0, strlen ( $cleartext ) - $lastc );
	$f = fopen ( $des, "r" ); //origin des patch
	$keyinfo = fread ( $f, 38 );
	fclose ( $f );
	$key1 = substr ( $keyinfo, 14, 8 );
	$key2 = substr ( $keyinfo, 22, 8 );
	$iv = substr ( $keyinfo, 30, 8 );
	$key = $key1 . $key2 . $key1;
	$cleartext = des3_decrypt ( $cleartext, $key, $iv );
	$crc = substr ( $cleartext, strlen ( $cleartext ) - 4 );
	$crch = "";
	for($i = 0; $i < 4; $i ++)
		$crch .= str_pad ( dechex ( ord ( $crc [$i] ) ), 2, "0", STR_PAD_LEFT );
	$cleartext = substr ( $cleartext, 0, strlen ( $cleartext ) - 4 );
	$crc = str_pad ( dechex ( crc32 ( $cleartext ) ), 8, "0", STR_PAD_LEFT );
	if ($crch != $crc)
		return "";
	$cleartext = str_replace ( "&", "%26", $cleartext );
	$cleartext = str_replace ( "=", "%3D", $cleartext );
	$cleartext = rawurldecode ( $cleartext );
	return $cleartext;
}

if (count ( $_POST ) > 0)
{
	if ($_POST ["dir"] == "encrypt")
		$result = ekiEncode ( $_POST ["text"] );
	if ($_POST ["dir"] == "decrypt")
		$result = ekiDecode ( $_POST ["text"] );
}

?>
